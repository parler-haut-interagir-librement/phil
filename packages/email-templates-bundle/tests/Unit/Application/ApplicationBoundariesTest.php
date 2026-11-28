<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\EmailTemplatesBundle\Application\Contract\EmailLayout;
use Phil\EmailTemplatesBundle\Application\Contract\StyleInliner;
use Phil\EmailTemplatesBundle\Application\EmailComposer;
use Phil\EmailTemplatesBundle\Application\EmailTemplates;
use Phil\EmailTemplatesBundle\Application\FixedSiteScope;
use Phil\EmailTemplatesBundle\Application\Rendering\EmailRenderer;
use Phil\EmailTemplatesBundle\Application\Rendering\RenderContext;
use Phil\EmailTemplatesBundle\Application\Rendering\RenderedParts;
use Phil\EmailTemplatesBundle\Application\Rendering\RenderOptions;
use Phil\EmailTemplatesBundle\Application\ResolvedTemplate;
use Phil\EmailTemplatesBundle\Application\TemplateResolver;
use Phil\EmailTemplatesBundle\Application\TemplateValidator;
use Phil\EmailTemplatesBundle\Domain\Exception\InvalidTemplate;
use Phil\EmailTemplatesBundle\Domain\Exception\RenderingFailed;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Locale;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Site;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateDefinition;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateKey;
use Phil\EmailTemplatesBundle\Tests\Fixtures\Stage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Mime\Email;

/**
 * The limits of a render, what a step skipped would leave undone, and the defaults the container never changes.
 */
#[CoversClass(EmailRenderer::class)]
#[CoversClass(EmailComposer::class)]
#[CoversClass(EmailTemplates::class)]
#[CoversClass(TemplateResolver::class)]
#[CoversClass(TemplateValidator::class)]
#[CoversClass(ResolvedTemplate::class)]
#[CoversClass(FixedSiteScope::class)]
#[CoversClass(RenderContext::class)]
#[CoversClass(RenderOptions::class)]
final class ApplicationBoundariesTest extends TestCase
{
    public function testTheEmailIsPublicAsEmailComposerWritesItOntoAnEmail(): void
    {
        $stage = new Stage();
        $email = EmailComposer::toEmail($stage->renderer->render('plain'), new Email());

        self::assertSame('Plain 7', $email->getSubject());
        self::assertSame("Code: 7\n", $email->getTextBody());
    }

    public function testATemplateAlreadyFoundIsRenderedWithTheSegmentsItDeclares(): void
    {
        $stage = new Stage();
        $resolved = $stage->resolver->resolve(TemplateKey::fromString('welcome'), Site::fromString('main'), Locale::fromString('en'));

        $email = $stage->renderer->renderResolved($resolved, ['name' => 'Ada', 'link' => null, 'items' => []], Site::fromString('main'), ['new', 'gold']);

        self::assertSame(['new'], $email->segments);
        self::assertSame('Welcome, Ada', $email->subject);
    }

    public function testTheSegmentsOfTheEmailAreAListWhateverIsDeclared(): void
    {
        $stage = new Stage();
        $email = $stage->renderer->render('welcome', ['name' => 'Ada'], new RenderOptions(segments: ['new']));

        self::assertSame(['new'], $email->segments);
        self::assertSame([0], array_keys($email->segments));
    }

    public function testTheHtmlMayBeExactlyAsLargeAsTheCeiling(): void
    {
        $renderer = $this->renderer(str_repeat('a', 1_048_576));

        self::assertSame(1_048_576, \strlen($renderer->render('plain')->html));
    }

    public function testTheHtmlOneByteOverTheCeilingIsRefusedWithItsSizeInKibibytes(): void
    {
        $renderer = $this->renderer(str_repeat('a', 1_048_577));

        $this->expectException(RenderingFailed::class);
        $this->expectExceptionMessageIsOrContains('The email renders more than 1024 KiB of HTML.');
        $renderer->render('plain');
    }

    public function testTheCeilingIsGivenInBytesAndTheMessageInKibibytes(): void
    {
        $limit = 1023 * 1024;
        $renderer = $this->renderer(str_repeat('a', $limit + 1), $limit);

        $this->expectException(RenderingFailed::class);
        $this->expectExceptionMessageIsOrContains('The email renders more than 1023 KiB of HTML.');
        $renderer->render('plain');
    }

    public function testASavedVersionIsSavedWithoutADispatcher(): void
    {
        $stage = new Stage();
        $templates = new EmailTemplates($stage->store, $stage->resolver, $stage->validator, $stage->defaults, new FixedSiteScope(Site::fromString('main')), new MockClock('2026-11-18 10:00:00 UTC'));

        self::assertSame(1, $templates->save('plain', 'en', $this->plain('a'), 0)->version);
        self::assertSame(1, $templates->publish('plain', 'en')->version);
        self::assertSame(2, $templates->revert('plain', 'en', 1, 1)->version);
        self::assertTrue($templates->discard('plain', 'en'));
        self::assertTrue($templates->reset('plain', 'en'));
    }

    public function testARevertedVersionIsCheckedAgain(): void
    {
        $stage = new Stage();
        $stage->store->saveDraft(TemplateKey::fromString('plain'), Site::fromString('main'), Locale::fromString('en'), TemplateDefinition::fromArray(['subject' => 'Hi {{ who }}', 'body' => '<p>x</p>']), new DateTimeImmutable('@0'), null, 0);
        $stage->store->publish(TemplateKey::fromString('plain'), Site::fromString('main'), Locale::fromString('en'));

        $this->expectException(InvalidTemplate::class);
        $this->expectExceptionMessageIsOrContains('The template reads "who", which its schema does not declare.');
        $stage->templates->revert('plain', 'en', 1, 1);
    }

    public function testAPublishedDraftIsCheckedAgain(): void
    {
        $stage = new Stage();
        $stage->store->saveDraft(TemplateKey::fromString('plain'), Site::fromString('main'), Locale::fromString('en'), TemplateDefinition::fromArray(['subject' => 'Hi {{ who }}', 'body' => '<p>x</p>']), new DateTimeImmutable('@0'), null, 0);

        try {
            $stage->templates->publish('plain', 'en');
            self::fail('A draft that does not pass the checks is not published.');
        } catch (InvalidTemplate $invalidTemplate) {
            self::assertStringContainsString('The template reads "who", which its schema does not declare.', $invalidTemplate->getMessage());
        }

        self::assertNull($stage->store->published(TemplateKey::fromString('plain'), Site::fromString('main'), Locale::fromString('en')));
        self::assertSame([], $stage->events->events);
    }

    public function testTheHistoryHoldsFiftyVersionsUnlessAskedForMore(): void
    {
        $stage = new Stage();

        for ($version = 0; $version < 52; ++$version) {
            $stage->publish('plain', 'en', $this->plain('v'.$version));
        }

        self::assertCount(50, $stage->templates->history('plain', 'en'));
        self::assertSame(52, $stage->templates->history('plain', 'en')[0]->version);
        self::assertCount(51, $stage->templates->history('plain', 'en', limit: 51));
    }

    public function testTheLocalesTriedAreEachOnceAndTheFirstInstanceIsKept(): void
    {
        $stage = new Stage();
        $locale = Locale::fromString('en');
        $chain = $stage->resolver->chain($locale);

        self::assertCount(1, $chain);
        self::assertSame($locale, $chain[0]);
        self::assertSame(['fr_CA', 'fr', 'en'], array_map(static fn (Locale $candidate): string => $candidate->value, $stage->resolver->chain(Locale::fromString('fr_CA'))));
    }

    public function testAnErrorOfASegmentIsPrefixedByIt(): void
    {
        $stage = new Stage();
        $definition = TemplateDefinition::fromArray(['subject' => 'Hi', 'body' => '{% if segment("vip") %}{{ button("x", "/relative") }}{% endif %}', 'segments' => ['vip']]);

        try {
            $stage->validator->validate(TemplateKey::fromString('plain'), Site::fromString('main'), Locale::fromString('en'), $definition);
            self::fail('The template is refused.');
        } catch (InvalidTemplate $invalidTemplate) {
            self::assertSame(['In the segment "vip": The body (line 1): An address is an absolute http or https address.'], $invalidTemplate->errors);
        }
    }

    private function plain(string $subject): TemplateDefinition
    {
        return TemplateDefinition::fromArray(['subject' => $subject, 'body' => '<p>{{ code }}</p>', 'variables' => ['code' => ['type' => 'integer', 'default' => 7]]]);
    }

    /**
     * @param int<1, max>|null $limit
     */
    private function renderer(string $html, ?int $limit = null): EmailRenderer
    {
        $stage = new Stage();
        $layout = new readonly class($html) implements EmailLayout {
            public function __construct(private string $html)
            {
            }

            public function wrap(RenderedParts $parts, RenderContext $context): string
            {
                return $this->html;
            }
        };
        $inliner = new class implements StyleInliner {
            public function inline(string $html): string
            {
                return $html;
            }
        };

        return null === $limit
            ? new EmailRenderer($stage->resolver, $stage->engine, $layout, $inliner, new FixedSiteScope(Site::fromString('main')))
            : new EmailRenderer($stage->resolver, $stage->engine, $layout, $inliner, new FixedSiteScope(Site::fromString('main')), null, $limit);
    }
}
