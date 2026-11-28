<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\EmailTemplatesBundle\Application\CatalogEntry;
use Phil\EmailTemplatesBundle\Application\Contract\TemplateStore;
use Phil\EmailTemplatesBundle\Domain\Exception\InvalidTemplate;
use Phil\EmailTemplatesBundle\Domain\Exception\UnknownTemplate;
use Phil\EmailTemplatesBundle\Domain\Exception\VersionConflict;
use Phil\EmailTemplatesBundle\Domain\Schema\VariableSchema;
use Phil\EmailTemplatesBundle\Domain\Status\BodyFormat;
use Phil\EmailTemplatesBundle\Domain\Status\PublicationState;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Locale;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Site;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateDefinition;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateDraft;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateKey;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateVersion;
use PHPUnit\Framework\TestCase;

/**
 * What every store promises: a draft sent only once published, a published version frozen and the next save starting
 * the next version, a save over a stale revision refused, the changes abandoned back to the published version, a locale
 * taken offline, the definition read back whole, and the sites and the keys compared byte for byte.
 */
abstract class StoreTestCase extends TestCase
{
    abstract protected function store(): TemplateStore;

    public function testADraftIsNotSentUntilItIsPublished(): void
    {
        $store = $this->store();
        $definition = self::definition('One');

        self::assertNull($store->draft(self::key(), self::site(), self::locale()));
        $draft = $store->saveDraft(self::key(), self::site(), self::locale(), $definition, new DateTimeImmutable('@1700000000'), 'ada', 0);

        self::assertSame(1, $draft->version);
        self::assertSame(1, $draft->revision);
        self::assertSame(PublicationState::Unpublished, $draft->state);
        self::assertTrue($draft->isPending());
        self::assertNull($draft->liveVersion);
        self::assertEquals($definition, $draft->definition);
        self::assertSame('ada', $draft->author);
        self::assertSame(1_700_000_000, $draft->savedAt->getTimestamp());
        self::assertSame(['main', 'ticket', 'fr_CA'], [$draft->site->value, $draft->key->value, $draft->locale->value]);
        self::assertNull($store->published(self::key(), self::site(), self::locale()));
        self::assertNull($store->version(self::key(), self::site(), self::locale(), 1));
        self::assertSame([], $store->history(self::key(), self::site(), self::locale()));

        $published = $store->publish(self::key(), self::site(), self::locale());

        self::assertSame(1, $published->version);
        self::assertEquals($definition, $published->definition);
        self::assertSame('ada', $published->author);
        self::assertSame(1_700_000_000, $published->savedAt->getTimestamp());
        self::assertInstanceOf(DateTimeImmutable::class, $published->publishedAt);
        self::assertSame(['main', 'ticket', 'fr_CA'], [$published->site->value, $published->key->value, $published->locale->value]);
        self::assertEquals($definition, $store->published(self::key(), self::site(), self::locale())?->definition);
        $after = $store->draft(self::key(), self::site(), self::locale());
        self::assertInstanceOf(TemplateDraft::class, $after);
        self::assertSame(PublicationState::Published, $after->state);
        self::assertFalse($after->isPending());
        self::assertSame(1, $after->liveVersion);
        self::assertSame([1], self::versions($store->history(self::key(), self::site(), self::locale())));
        self::assertNull($store->published(self::key(), Site::shared(), self::locale()));
    }

    public function testSavingAPublishedDraftStartsTheNextVersion(): void
    {
        $store = $this->store();
        $store->saveDraft(self::key(), self::site(), self::locale(), self::definition('One'), new DateTimeImmutable('@10'), 'ada', 0);
        $store->publish(self::key(), self::site(), self::locale());

        $draft = $store->saveDraft(self::key(), self::site(), self::locale(), self::definition('Two'), new DateTimeImmutable('@20'), null, 1);

        self::assertSame(2, $draft->version);
        self::assertSame(2, $draft->revision);
        self::assertSame(PublicationState::Pending, $draft->state);
        self::assertSame(1, $draft->liveVersion);
        self::assertSame('One', $store->published(self::key(), self::site(), self::locale())?->definition->subject);
        self::assertNull($store->version(self::key(), self::site(), self::locale(), 2));

        self::assertSame(2, $store->publish(self::key(), self::site(), self::locale())->version);
        self::assertSame('Two', $store->published(self::key(), self::site(), self::locale())?->definition->subject);
        self::assertSame([2, 1], self::versions($store->history(self::key(), self::site(), self::locale())));
        self::assertSame([2], self::versions($store->history(self::key(), self::site(), self::locale(), 1)));
        $first = $store->version(self::key(), self::site(), self::locale(), 1);
        self::assertInstanceOf(TemplateVersion::class, $first);
        self::assertSame('One', $first->definition->subject);
        self::assertSame('ada', $first->author);
        self::assertSame(10, $first->savedAt->getTimestamp());
        self::assertNull($store->version(self::key(), self::site(), self::locale(), 3));
    }

    public function testSavingADraftNotYetPublishedKeepsItsVersion(): void
    {
        $store = $this->store();
        $store->saveDraft(self::key(), self::site(), self::locale(), self::definition('One'), new DateTimeImmutable('@0'), null, 0);

        $draft = $store->saveDraft(self::key(), self::site(), self::locale(), self::definition('Two'), new DateTimeImmutable('@5'), 'bob', 1);

        self::assertSame(1, $draft->version);
        self::assertSame(2, $draft->revision);
        self::assertSame('Two', $draft->definition->subject);
        self::assertSame('bob', $draft->author);
        self::assertSame(PublicationState::Unpublished, $draft->state);
    }

    public function testADraftSavedOverAStaleRevisionIsRefused(): void
    {
        $store = $this->store();
        $store->saveDraft(self::key(), self::site(), self::locale(), self::definition('One'), new DateTimeImmutable('@0'), null, 0);

        foreach ([0, 2] as $expected) {
            try {
                $store->saveDraft(self::key(), self::site(), self::locale(), self::definition('Late'), new DateTimeImmutable('@0'), null, $expected);
                self::fail('A draft saved over a stale revision is refused.');
            } catch (VersionConflict $versionConflict) {
                self::assertSame(\sprintf('The email template "ticket" (fr_CA) was saved over revision %d, but revision 1 is the latest: reload it and save again.', $expected), $versionConflict->getMessage());
            }
        }

        self::assertSame('One', $store->draft(self::key(), self::site(), self::locale())?->definition->subject);

        try {
            $store->saveDraft(self::key(), Site::shared(), self::locale(), self::definition('New'), new DateTimeImmutable('@0'), null, 1);
            self::fail('A first draft is saved over revision 0.');
        } catch (VersionConflict $versionConflict) {
            self::assertStringContainsString('saved over revision 1, but revision 0 is the latest', $versionConflict->getMessage());
        }
    }

    public function testPublishingNeedsADraftWithChanges(): void
    {
        $store = $this->store();

        try {
            $store->publish(self::key(), self::site(), self::locale());
            self::fail('Nothing is published without a draft.');
        } catch (UnknownTemplate $unknownTemplate) {
            self::assertSame('The email template "ticket" has no draft for the locale "fr_CA": save one first.', $unknownTemplate->getMessage());
        }

        $store->saveDraft(self::key(), self::site(), Locale::fromString('en'), self::definition('En'), new DateTimeImmutable('@0'), null, 0);

        try {
            $store->publish(self::key(), self::site(), self::locale());
            self::fail('Nothing is published without a draft in the locale.');
        } catch (UnknownTemplate) {
        }

        $store->saveDraft(self::key(), self::site(), self::locale(), self::definition('One'), new DateTimeImmutable('@0'), null, 0);
        $store->publish(self::key(), self::site(), self::locale());

        $this->expectException(InvalidTemplate::class);
        $this->expectExceptionMessageIsOrContains('The email template "ticket" (fr_CA)');
        $store->publish(self::key(), self::site(), self::locale());
    }

    public function testTakingALocaleOfflineKeepsItsDraftAndItsVersions(): void
    {
        $store = $this->store();

        self::assertFalse($store->unpublish(self::key(), self::site(), self::locale()));
        $store->saveDraft(self::key(), self::site(), self::locale(), self::definition('One'), new DateTimeImmutable('@0'), null, 0);
        self::assertFalse($store->unpublish(self::key(), self::site(), self::locale()));
        $store->publish(self::key(), self::site(), self::locale());

        self::assertTrue($store->unpublish(self::key(), self::site(), self::locale()));
        self::assertFalse($store->unpublish(self::key(), self::site(), self::locale()));
        self::assertNull($store->published(self::key(), self::site(), self::locale()));
        $draft = $store->draft(self::key(), self::site(), self::locale());
        self::assertSame(PublicationState::Unpublished, $draft?->state);
        self::assertNull($draft->liveVersion);
        self::assertSame([1], self::versions($store->history(self::key(), self::site(), self::locale())));

        $store->publish(self::key(), self::site(), self::locale());
        self::assertSame('One', $store->published(self::key(), self::site(), self::locale())?->definition->subject);
    }

    public function testAbandoningTheChangesPutsThePublishedVersionBack(): void
    {
        $store = $this->store();

        self::assertFalse($store->discardDraft(self::key(), self::site(), self::locale()));
        $store->saveDraft(self::key(), self::site(), self::locale(), self::definition('One'), new DateTimeImmutable('@0'), null, 0);
        self::assertFalse($store->discardDraft(self::key(), self::site(), self::locale()));
        $store->publish(self::key(), self::site(), self::locale());
        self::assertFalse($store->discardDraft(self::key(), self::site(), self::locale()));
        $store->saveDraft(self::key(), self::site(), self::locale(), self::definition('Two'), new DateTimeImmutable('@0'), null, 1);

        self::assertTrue($store->discardDraft(self::key(), self::site(), self::locale()));
        $draft = $store->draft(self::key(), self::site(), self::locale());
        self::assertSame('One', $draft?->definition->subject);
        self::assertSame(PublicationState::Published, $draft->state);
        self::assertSame(1, $draft->liveVersion);
        self::assertSame(1, $draft->revision);
        self::assertSame('One', $store->published(self::key(), self::site(), self::locale())?->definition->subject);
        self::assertFalse($store->discardDraft(self::key(), self::site(), self::locale()));

        $next = $store->saveDraft(self::key(), self::site(), self::locale(), self::definition('Three'), new DateTimeImmutable('@0'), null, 1);
        self::assertSame(PublicationState::Pending, $next->state);
        self::assertSame('One', $store->published(self::key(), self::site(), self::locale())?->definition->subject);
    }

    public function testTheCatalogHoldsTheDraftOfEachTemplateOfASite(): void
    {
        $store = $this->store();
        $store->saveDraft(self::key(), self::site(), self::locale(), self::definition('One'), new DateTimeImmutable('@10'), 'ada', 0);
        $store->publish(self::key(), self::site(), self::locale());
        $store->saveDraft(self::key(), self::site(), self::locale(), self::definition('Two'), new DateTimeImmutable('@20'), 'bob', 1);
        $store->saveDraft(self::key(), self::site(), Locale::fromString('en'), self::definition('En'), new DateTimeImmutable('@30'), null, 0);
        $store->saveDraft(TemplateKey::fromString('alpha'), self::site(), self::locale(), self::definition('Alpha'), new DateTimeImmutable('@40'), null, 0);
        $store->saveDraft(self::key(), Site::shared(), self::locale(), self::definition('Shared'), new DateTimeImmutable('@50'), null, 0);

        $entries = array_map(static fn (CatalogEntry $entry): array => [$entry->key->value, $entry->locale->value, $entry->state->value, $entry->version, $entry->liveVersion, $entry->savedAt->getTimestamp(), $entry->author, $entry->site->value], $store->catalog(self::site()));

        self::assertSame([
            ['alpha', 'fr_CA', 'unpublished', 1, null, 40, null, 'main'],
            ['ticket', 'en', 'unpublished', 1, null, 30, null, 'main'],
            ['ticket', 'fr_CA', 'draft', 2, 1, 20, 'bob', 'main'],
        ], $entries);
        self::assertCount(1, $store->catalog(Site::shared()));
        self::assertSame([], $store->catalog(Site::fromString('none')));
    }

    public function testSitesAndKeysCompareByteForByte(): void
    {
        $store = $this->store();
        $store->saveDraft(self::key(), Site::fromString('Main'), self::locale(), self::definition('Upper'), new DateTimeImmutable('@0'), null, 0);
        $store->saveDraft(self::key(), self::site(), self::locale(), self::definition('Lower'), new DateTimeImmutable('@0'), null, 0);
        $store->saveDraft(TemplateKey::fromString('ticket.b'), self::site(), self::locale(), self::definition('Dotted'), new DateTimeImmutable('@0'), null, 0);

        self::assertSame('Upper', $store->draft(self::key(), Site::fromString('Main'), self::locale())?->definition->subject);
        self::assertSame('Lower', $store->draft(self::key(), self::site(), self::locale())?->definition->subject);
        self::assertSame('Dotted', $store->draft(TemplateKey::fromString('ticket.b'), self::site(), self::locale())?->definition->subject);
        self::assertNull($store->draft(self::key(), Site::fromString('MAIN'), self::locale()));
        self::assertCount(1, $store->catalog(Site::fromString('Main')));
    }

    public function testTheHistoryHoldsFiftyVersionsUnlessAskedForMore(): void
    {
        $store = $this->store();

        for ($revision = 0; $revision < 52; ++$revision) {
            $store->saveDraft(self::key(), self::site(), self::locale(), self::definition('v'.$revision), new DateTimeImmutable('@0'), null, $revision);
            $store->publish(self::key(), self::site(), self::locale());
        }

        $history = $store->history(self::key(), self::site(), self::locale());
        self::assertCount(50, $history);
        self::assertSame(52, $history[0]->version);
        self::assertSame(3, $history[49]->version);
        self::assertCount(51, $store->history(self::key(), self::site(), self::locale(), 51));
    }

    public function testNothingShiftsTheBoundaryBetweenTheSiteAndTheKey(): void
    {
        $store = $this->store();
        $at = new DateTimeImmutable('@0');
        $en = Locale::fromString('en');
        $store->saveDraft(TemplateKey::fromString('c'), Site::fromString('ab'), $en, self::definition('Site ab, key c'), $at, null, 0);
        $store->saveDraft(TemplateKey::fromString('bc'), Site::fromString('a'), $en, self::definition('Site a, key bc'), $at, null, 0);

        self::assertSame('Site ab, key c', $store->draft(TemplateKey::fromString('c'), Site::fromString('ab'), $en)?->definition->subject);
        self::assertSame('Site a, key bc', $store->draft(TemplateKey::fromString('bc'), Site::fromString('a'), $en)?->definition->subject);
        self::assertNull($store->draft(TemplateKey::fromString('bc'), Site::fromString('ab'), $en));
        self::assertNull($store->draft(TemplateKey::fromString('c'), Site::fromString('a'), $en));
    }

    public function testTheCatalogIsSortedByKeyThenLocaleWhateverOneKeyStartsWith(): void
    {
        $store = $this->store();
        $at = new DateTimeImmutable('@0');
        $en = Locale::fromString('en');
        $store->saveDraft(TemplateKey::fromString('a.b'), self::site(), $en, self::definition('Dotted'), $at, null, 0);
        $store->saveDraft(TemplateKey::fromString('a'), self::site(), Locale::fromString('fr'), self::definition('Plain fr'), $at, null, 0);
        $store->saveDraft(TemplateKey::fromString('a'), self::site(), $en, self::definition('Plain en'), $at, null, 0);

        self::assertSame(['a en', 'a fr', 'a.b en'], array_map(static fn (CatalogEntry $entry): string => $entry->key->value.' '.$entry->locale->value, $store->catalog(self::site())));
    }

    /**
     * @param list<TemplateVersion> $history
     *
     * @return list<int>
     */
    protected static function versions(array $history): array
    {
        return array_map(static fn (TemplateVersion $version): int => $version->version, $history);
    }

    protected static function definition(string $subject): TemplateDefinition
    {
        return TemplateDefinition::create($subject, "<p>{{ name }} — é</p>\n", BodyFormat::Markdown, 'Pré', 'Texte', VariableSchema::fromArray(['name' => ['type' => 'string', 'required' => true, 'sample' => 'Ada'], 'price' => ['type' => 'number', 'default' => 1.0]]), ['vip'], ['logo' => 'logo.png']);
    }

    protected static function key(): TemplateKey
    {
        return TemplateKey::fromString('ticket');
    }

    protected static function site(): Site
    {
        return Site::fromString('main');
    }

    protected static function locale(): Locale
    {
        return Locale::fromString('fr_CA');
    }
}
