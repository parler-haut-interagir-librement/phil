<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Tests\Unit\Application;

use Phil\EmailTemplatesBundle\Application\CatalogEntry;
use Phil\EmailTemplatesBundle\Application\CatalogRow;
use Phil\EmailTemplatesBundle\Application\Contract\TemplateStore;
use Phil\EmailTemplatesBundle\Application\EmailTemplates;
use Phil\EmailTemplatesBundle\Application\TemplateValidator;
use Phil\EmailTemplatesBundle\Domain\Event\TemplateDraftDiscarded;
use Phil\EmailTemplatesBundle\Domain\Event\TemplatePublished;
use Phil\EmailTemplatesBundle\Domain\Event\TemplateReset;
use Phil\EmailTemplatesBundle\Domain\Event\TemplateSaved;
use Phil\EmailTemplatesBundle\Domain\Exception\InvalidTemplate;
use Phil\EmailTemplatesBundle\Domain\Exception\UnknownTemplate;
use Phil\EmailTemplatesBundle\Domain\Exception\VersionConflict;
use Phil\EmailTemplatesBundle\Domain\Status\PublicationState;
use Phil\EmailTemplatesBundle\Domain\Status\TemplateOrigin;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Locale;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Site;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateDefinition;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateKey;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateVersion;
use Phil\EmailTemplatesBundle\Tests\Fixtures\Stage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EmailTemplates::class)]
#[CoversClass(TemplateValidator::class)]
#[CoversClass(CatalogRow::class)]
#[CoversClass(CatalogEntry::class)]
final class EmailTemplatesTest extends TestCase
{
    private Stage $stage;

    protected function setUp(): void
    {
        $this->stage = new Stage();
    }

    public function testASavedDraftIsCheckedDatedAndAnnouncedThenSentOncePublished(): void
    {
        $saved = $this->stage->templates->save('plain', 'en', $this->plain('Saved {{ code }}'), 0, author: 'editor-1');

        self::assertSame(1, $saved->version);
        self::assertSame(1, $saved->revision);
        self::assertSame(PublicationState::Unpublished, $saved->state);
        self::assertSame('main', $saved->site->value);
        self::assertSame('editor-1', $saved->author);
        self::assertSame('2026-11-18 10:00:00', $saved->savedAt->format('Y-m-d H:i:s'));
        self::assertEquals([new TemplateSaved('plain', 'main', 'en', 1, 1, 'editor-1')], $this->stage->events->events);
        self::assertSame(TemplateOrigin::Default, $this->stage->templates->current('plain', 'en')->origin);
        self::assertSame('Saved {{ code }}', $this->stage->templates->draft('plain', 'en')?->definition->subject);

        $published = $this->stage->templates->publish('plain', 'en', author: 'editor-2');

        self::assertSame(1, $published->version);
        self::assertEquals(new TemplatePublished('plain', 'main', 'en', 1, 'editor-2'), $this->stage->events->events[1]);
        self::assertSame('Saved 7', $this->stage->renderer->render('plain')->subject);
        $current = $this->stage->templates->current('plain', 'en');
        self::assertSame(TemplateOrigin::Site, $current->origin);
        self::assertSame(1, $current->version);
        self::assertSame(PublicationState::Published, $this->stage->templates->draft('plain', 'en')?->state);
        self::assertNull($this->stage->templates->draft('plain', 'fr'));
    }

    public function testPublishingNeedsADraftThatPassesTheChecks(): void
    {
        try {
            $this->stage->templates->publish('plain', 'en');
            self::fail('Nothing is published without a draft.');
        } catch (UnknownTemplate $unknownTemplate) {
            self::assertSame('The email template "plain" has no draft for the locale "en": save one first.', $unknownTemplate->getMessage());
        }

        $this->stage->publish('plain', 'en', $this->plain('One'));

        try {
            $this->stage->templates->publish('plain', 'en');
            self::fail('A draft published already, without changes, is not published again.');
        } catch (InvalidTemplate $invalidTemplate) {
            self::assertStringContainsString('is published already, without changes', $invalidTemplate->getMessage());
        }

        self::assertCount(2, $this->stage->events->events);
    }

    public function testAbandoningTheChangesIsAnnouncedOnlyWhenThereWereSome(): void
    {
        self::assertFalse($this->stage->templates->discard('plain', 'en'));
        $this->stage->publish('plain', 'en', $this->plain('One'));
        self::assertFalse($this->stage->templates->discard('plain', 'en'));
        $this->stage->templates->save('plain', 'en', $this->plain('Two'), 1);

        self::assertTrue($this->stage->templates->discard('plain', 'en', author: 'ada@example.com'));
        self::assertEquals(new TemplateDraftDiscarded('plain', 'main', 'en', 2), $this->stage->events->events[3]);
        self::assertSame('One', $this->stage->templates->draft('plain', 'en')?->definition->subject);
        self::assertCount(4, $this->stage->events->events);
    }

    public function testAnEmailAddressIsNeverKeptAsTheAuthor(): void
    {
        self::assertNull($this->stage->templates->save('plain', 'en', $this->plain('a'), 0, author: 'ada@example.com')->author);
        self::assertNull($this->stage->templates->save('plain', 'en', $this->plain('b'), 1)->author);
        self::assertSame('Ada:9.x_y-z', $this->stage->templates->save('plain', 'en', $this->plain('c'), 2, author: 'Ada:9.x_y-z')->author);
        self::assertNull($this->stage->templates->save('plain', 'en', $this->plain('d'), 3, author: str_repeat('a', 129))->author);
    }

    public function testATemplateThatDoesNotRenderIsRefusedWithEveryReason(): void
    {
        try {
            $this->stage->templates->save('plain', 'en', TemplateDefinition::fromArray(['subject' => 'Hi {{ who }}', 'body' => '{% include "x" %}']), 0);
            self::fail('The template is refused.');
        } catch (InvalidTemplate $invalidTemplate) {
            self::assertSame(['The body: Tag "include" is not allowed.', 'The template reads "who", which its schema does not declare.'], $invalidTemplate->errors);
        }

        self::assertSame([], $this->stage->events->events);
        self::assertSame([], $this->stage->templates->history('plain', 'en'));
    }

    public function testEachSegmentIsRenderedBeforeASave(): void
    {
        $definition = TemplateDefinition::fromArray(['subject' => 'Hi', 'body' => '{% if segment("vip") %}{{ button("x", "/relative") }}{% endif %}', 'segments' => ['vip', 'new']]);

        try {
            $this->stage->templates->save('plain', 'en', $definition, 0);
            self::fail('The template is refused.');
        } catch (InvalidTemplate $invalidTemplate) {
            self::assertSame(['In the segment "vip": The body (line 1): An address is an absolute http or https address.'], $invalidTemplate->errors);
        }
    }

    public function testAFailureWithoutSegmentIsReportedAlone(): void
    {
        $this->expectException(InvalidTemplate::class);
        $this->expectExceptionMessageIsOrContains('The body (line 1): An address is an absolute http or https address.');

        $this->stage->templates->save('plain', 'en', TemplateDefinition::fromArray(['subject' => 'Hi', 'body' => '{{ button("x", "/relative") }}']), 0);
    }

    public function testOnlyADeclaredKeyMayBeSaved(): void
    {
        $this->expectException(InvalidTemplate::class);
        $this->expectExceptionMessageIsOrContains('No default file declares the template "invented": add one before the sites write their own.');

        $this->stage->templates->save('invented', 'en', $this->plain('a'), 0);
    }

    public function testARevisionSavedMeanwhileIsAConflict(): void
    {
        $this->stage->templates->save('plain', 'en', $this->plain('a'), 0);

        $this->expectException(VersionConflict::class);
        $this->stage->templates->save('plain', 'en', $this->plain('b'), 0);
    }

    public function testRevertingCopiesAPublishedVersionIntoTheDraft(): void
    {
        $this->stage->publish('plain', 'en', $this->plain('First'), author: 'a');
        $this->stage->publish('plain', 'en', $this->plain('Second'), author: 'a');

        $reverted = $this->stage->templates->revert('plain', 'en', 1, 2, author: 'b');

        self::assertSame(3, $reverted->version);
        self::assertSame(3, $reverted->revision);
        self::assertSame('First', $reverted->definition->subject);
        self::assertSame(PublicationState::Pending, $reverted->state);
        self::assertEquals(new TemplateSaved('plain', 'main', 'en', 3, 3, 'b', 1), $this->stage->events->events[4]);
        self::assertSame('Second', $this->stage->templates->current('plain', 'en')->definition->subject);
        self::assertSame([2, 1], array_map(static fn (TemplateVersion $version): int => $version->version, $this->stage->templates->history('plain', 'en')));
        self::assertSame([2], array_map(static fn (TemplateVersion $version): int => $version->version, $this->stage->templates->history('plain', 'en', limit: 1)));

        foreach ([9, 3, 0] as $missing) {
            try {
                $this->stage->templates->revert('plain', 'en', $missing, 3);
                self::fail('Only a published version is copied.');
            } catch (UnknownTemplate $unknownTemplate) {
                self::assertSame(\sprintf('The email template "plain" has no published version %d for the locale "en".', $missing), $unknownTemplate->getMessage());
            }
        }

        self::assertCount(5, $this->stage->events->events);
    }

    public function testResettingTakesTheTemplateOfTheSiteOfflineOnly(): void
    {
        $shared = Site::shared();
        $this->stage->publish('plain', 'en', $this->plain('Shared'), $shared);
        $this->stage->publish('plain', 'en', $this->plain('Own'));

        self::assertTrue($this->stage->templates->reset('plain', 'en', author: 'boss'));
        self::assertEquals(new TemplateReset('plain', 'main', 'en', 'boss'), $this->stage->events->events[4]);
        self::assertSame('Shared', $this->stage->templates->current('plain', 'en')->definition->subject);
        self::assertSame(TemplateOrigin::Shared, $this->stage->templates->current('plain', 'en')->origin);
        self::assertSame('Own', $this->stage->templates->draft('plain', 'en')?->definition->subject, 'The draft is kept.');
        self::assertFalse($this->stage->templates->reset('plain', 'en'));
        self::assertCount(5, $this->stage->events->events, 'Nothing taken offline is not announced.');
        self::assertTrue($this->stage->templates->reset('plain', 'en', $shared, 'ada@example.com'));
        $events = $this->stage->events->events;
        self::assertEquals(new TemplateReset('plain', '', 'en'), end($events));
    }

    public function testTheCatalogOfASite(): void
    {
        $this->stage->publish('plain', 'en', $this->plain('Shared'), Site::shared());
        $this->stage->publish('plain', 'fr', $this->plain('Own fr'));
        $this->stage->publish('welcome', 'en', $this->stage->defaults->find(TemplateKey::fromString('welcome'), Locale::fromString('en')) ?? $this->plain('x'), Site::shared());
        $this->stage->publish('welcome', 'en', $this->stage->templates->current('welcome', 'en')->definition);
        $this->stage->templates->save('welcome', 'en', $this->plain('Pending'), 1);
        $this->stage->templates->save('ticket', 'de', $this->plain('Draft de'), 0);
        $this->stage->templates->save('ticket', 'it', $this->plain('Shared draft it'), 0, Site::shared());

        $rows = array_map(static fn (CatalogRow $row): string => \sprintf('%s %s %s %s %s', $row->key, $row->locale, $row->origin->value ?? 'fallback', $row->version ?? '-', $row->state->value ?? '-'), $this->stage->templates->catalog());

        self::assertSame([
            'cancellation en default - -',
            'cancellation fr default - -',
            'confirmation en default - -',
            'confirmation fr default - -',
            'plain en shared 1 -',
            'plain fr site 1 published',
            'ticket de fallback - unpublished',
            'ticket en default - -',
            'ticket fr default - -',
            'ticket it fallback - -',
            'welcome en site 1 draft',
            'welcome fr default - -',
        ], $rows);
        $shared = array_map(static fn (CatalogRow $row): string => $row->key.' '.$row->locale.' '.($row->origin->value ?? 'fallback').' '.($row->state->value ?? '-'), $this->stage->templates->catalog(Site::shared()));
        self::assertContains('plain en shared published', $shared);
        self::assertContains('welcome en shared published', $shared);
        self::assertContains('ticket it fallback unpublished', $shared);
        self::assertNull($this->stage->templates->catalog()[4]->savedAt, "The shared draft is not the site's.");
        self::assertNotNull($this->stage->templates->catalog()[5]->savedAt);
    }

    public function testAnAbandonedDraftThatTheStoreNoLongerHoldsIsAnnouncedAsTheFirstVersion(): void
    {
        $store = self::createStub(TemplateStore::class);
        $store->method('discardDraft')->willReturn(true);
        $store->method('draft')->willReturn(null);
        $stage = new Stage($store);

        self::assertTrue($stage->templates->discard('plain', 'en'));
        self::assertEquals([new TemplateDraftDiscarded('plain', 'main', 'en', 1)], $stage->events->events);
    }

    public function testADefaultTemplateKeepsItsOriginWhenOnlyADraftExists(): void
    {
        $this->stage->templates->save('ticket', 'en', $this->plain('Draft'), 0);

        $rows = array_filter($this->stage->templates->catalog(), static fn (CatalogRow $row): bool => 'ticket' === $row->key && 'en' === $row->locale);

        self::assertCount(1, $rows);
        $row = array_first($rows);
        self::assertSame(TemplateOrigin::Default, $row->origin);
        self::assertNull($row->version);
        self::assertSame(PublicationState::Unpublished, $row->state);
    }

    private function plain(string $subject): TemplateDefinition
    {
        return TemplateDefinition::fromArray(['subject' => $subject, 'body' => '<p>{{ code }}</p>', 'variables' => ['code' => ['type' => 'integer', 'default' => 7]]]);
    }
}
