<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Domain\Exception;

use Phil\ContentBundle\Domain\Dimension\Stage;
use Phil\ContentBundle\Domain\Exception\ContentNotFound;
use Phil\ContentBundle\Domain\Exception\SlugTaken;
use Phil\ContentBundle\Domain\Exception\TransitionRefused;
use Phil\ContentBundle\Domain\ValueObject\ContentReference;
use Phil\DddBundle\Domain\Exception\DomainError;
use Phil\LocaleDimensions\FallbackChain;
use Phil\LocaleDimensions\Locale;
use Phil\Slug\Slug;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The messages a console command or a migration shows when the bundle refuses: each names what it refused.
 */
#[CoversClass(ContentNotFound::class)]
#[CoversClass(SlugTaken::class)]
#[CoversClass(TransitionRefused::class)]
final class ExceptionMessagesTest extends TestCase
{
    public function testNothingLiveAlongAChain(): void
    {
        $error = ContentNotFound::liveAlongChain(FallbackChain::of([Locale::fromString('fr_CA'), Locale::fromString('fr')]));

        self::assertInstanceOf(DomainError::class, $error);
        self::assertSame('No content is published in any locale of the chain "fr_CA → fr".', $error->getMessage());
    }

    public function testNothingInAStage(): void
    {
        self::assertSame('No content exists in the stage "live" for the locale "fr".', ContentNotFound::inStage(Stage::Live, Locale::fromString('fr'))->getMessage());
        self::assertSame('No content exists in the stage "draft" for the locale "*".', ContentNotFound::inStage(Stage::Draft, null)->getMessage());
    }

    public function testNoRootForAReference(): void
    {
        self::assertSame('No content root "news:42" exists.', ContentNotFound::forReference(ContentReference::of('news', '42'))->getMessage());
    }

    public function testASlugTakenNamesItsOwner(): void
    {
        $error = SlugTaken::by(ContentReference::of('news', '42'), Locale::fromString('fr'), Slug::fromString('ouverture'));

        self::assertInstanceOf(DomainError::class, $error);
        self::assertSame('The slug "ouverture" is already the address of "news:42" in the locale "fr".', $error->getMessage());
    }

    public function testARefusedTransitionNamesItsPlace(): void
    {
        $error = TransitionRefused::from('publish', 'published', 'fr/draft/1');

        self::assertInstanceOf(DomainError::class, $error);
        self::assertSame('The transition "publish" is not allowed from the place "published" of "fr/draft/1".', $error->getMessage());
        self::assertSame('The transition "publish" is not allowed from the place "none" of "fr/draft/1".', TransitionRefused::from('publish', null, 'fr/draft/1')->getMessage());
    }

    public function testADraftWhoseSharedValuesOtherLocalesReadIsKept(): void
    {
        self::assertSame(
            'The draft of "fr/draft/2" cannot be removed: its shared values changed since it was published, and the locales "en", "fr_CA" read them too.',
            TransitionRefused::sharedValuesPending('fr/draft/2', ['en', 'fr_CA'])->getMessage(),
        );
    }

    public function testARowWithoutWorkflowCannotBePublished(): void
    {
        self::assertSame('The row class "App\Row" is not WorkflowCapable, so it cannot be published.', TransitionRefused::withoutWorkflow('App\Row')->getMessage());
    }
}
