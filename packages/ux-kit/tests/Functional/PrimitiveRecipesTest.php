<?php

declare(strict_types=1);

namespace Phil\UxKit\Tests\Functional;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Button, Badge, ButtonGroup, Separator, Spinner, Skeleton, QrCode.
 */
#[CoversNothing]
final class PrimitiveRecipesTest extends KitTestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function buttonVariantsAndSizes(): iterable
    {
        yield 'default' => ['', 'isPrimary'];
        yield 'secondary' => ['variant="secondary"', 'isSecondary'];
        yield 'destructive' => ['variant="destructive"', 'isDanger'];
        yield 'outline' => ['variant="outline"', 'isOutline'];
        yield 'ghost' => ['variant="ghost"', 'isGhost'];
        yield 'link' => ['variant="link"', 'isLink'];
        yield 'xs' => ['size="xs"', 'isPrimary isSmall'];
        yield 'sm' => ['size="sm"', 'isPrimary isSmall'];
        yield 'lg' => ['size="lg"', 'isPrimary isLarge'];
        yield 'icon' => ['size="icon"', 'isPrimary hasIcon isIconOnly'];
        yield 'icon-xs' => ['size="icon-xs"', 'isPrimary hasIcon isIconOnly isSmall'];
        yield 'icon-sm' => ['size="icon-sm"', 'isPrimary hasIcon isIconOnly isSmall'];
        yield 'icon-lg' => ['size="icon-lg"', 'isPrimary hasIcon isIconOnly isLarge'];
        yield 'unknown' => ['variant="nope" size="nope"', 'isPrimary'];
    }

    #[DataProvider('buttonVariantsAndSizes')]
    public function testEachVariantAndSizeOfAButtonIsAClassOfTheStylesheet(string $props, string $classes): void
    {
        self::assertStringContainsString(' class="'.$classes.'"', $this->render('<twig:Button '.$props.'>Go</twig:Button>'));
    }

    public function testAButton(): void
    {
        self::assertSame(
            '<button data-slot="button" data-variant="default" data-size="default" class="isPrimary" type="button">Go</button>',
            $this->render('<twig:Button>Go</twig:Button>'),
        );
    }

    public function testAButtonIsALinkWithAs(): void
    {
        self::assertSame(
            '<a data-slot="button" data-variant="ghost" data-size="default" class="isButton isGhost" href="/e" aria-current="page">All</a>',
            $this->render('<twig:Button as="a" variant="ghost" href="/e" aria-current="page">All</twig:Button>'),
        );
    }

    public function testATagOtherThanALinkIsAButton(): void
    {
        self::assertStringStartsWith('<button ', $this->render('<twig:Button as="script">x</twig:Button>'));
    }

    public function testAButtonBusyToggleAndSubmit(): void
    {
        $html = $this->render('<twig:Button :loading="true" type="submit" aria-pressed="true">Sending</twig:Button>');

        self::assertStringContainsString('aria-busy="true" class="isPrimary isLoading" type="submit" aria-pressed="true"', $html);
    }

    public function testADataSlotOverridesTheOneOfAButton(): void
    {
        self::assertStringStartsWith('<button data-slot="dialog-close" ', $this->render('<twig:Button data-slot="dialog-close">x</twig:Button>'));
    }

    public function testABadge(): void
    {
        self::assertSame('<span data-slot="badge" data-variant="default" class="isBadge">New</span>', $this->render('<twig:Badge>New</twig:Badge>'));
        self::assertSame(
            '<a data-slot="badge" data-variant="destructive" class="isBadge isDanger x" href="/t">Late</a>',
            $this->render('<twig:Badge as="a" variant="destructive" href="/t" class="x">Late</twig:Badge>'),
        );
    }

    public function testTheColourOfABadgeIsWrittenOnlyWhenItIsOne(): void
    {
        self::assertSame(
            '<span data-slot="badge" data-variant="default" data-color="#7c3aed" class="isBadge">W</span>',
            $this->render('<twig:Badge color="#7c3aed">W</twig:Badge>'),
        );
        self::assertStringContainsString('data-color="oklch(0.5 0.2 300)"', $this->render('<twig:Badge color="oklch(0.5 0.2 300)">W</twig:Badge>'));
        self::assertSame(
            '<span data-slot="badge" data-variant="default" class="isBadge">W</span>',
            $this->render('<twig:Badge color="red; background: url(x)">W</twig:Badge>'),
        );
        self::assertStringNotContainsString('style=', $this->render('<twig:Badge color="#7c3aed">W</twig:Badge>'));
    }

    public function testAButtonGroupAndItsParts(): void
    {
        self::assertSame(
            '<div data-slot="button-group" role="group" data-orientation="horizontal" class="isButtonGroup" aria-label="View">a<hr data-slot="button-group-separator" data-orientation="vertical" role="none" class="isSeparator isVertical"><div data-slot="button-group-text" class="isButtonGroupText">b</div></div>',
            $this->render('<twig:ButtonGroup aria-label="View">a<twig:ButtonGroup:Separator /><twig:ButtonGroup:Text>b</twig:ButtonGroup:Text></twig:ButtonGroup>'),
        );
        self::assertStringStartsWith('<span data-slot="button-group-text"', $this->render('<twig:ButtonGroup:Text as="span">b</twig:ButtonGroup:Text>'));
    }

    public function testASeparatorDecorativeOrAnnounced(): void
    {
        self::assertSame('<hr data-slot="separator" data-orientation="horizontal" role="none" class="isSeparator">', $this->render('<twig:Separator />'));
        self::assertSame(
            '<hr data-slot="separator" data-orientation="vertical" aria-orientation="vertical" class="isSeparator isVertical">',
            $this->render('<twig:Separator orientation="vertical" :decorative="false" />'),
        );
    }

    public function testASpinnerAnnouncesItsLabel(): void
    {
        self::assertSame(
            '<span role="status" data-slot="spinner" class="isSpinner"><span class="isVisuallyHidden">Chargement</span></span>',
            $this->render('<twig:Spinner label="Chargement" />'),
        );
        self::assertStringContainsString('>Loading<', $this->render('<twig:Spinner />'));
    }

    public function testASkeletonIsHiddenFromScreenReaders(): void
    {
        self::assertSame('<div data-slot="skeleton" aria-hidden="true" class="isSkeleton"></div>', $this->render('<twig:Skeleton />'));
    }

    public function testAQrCodeThroughItsBundle(): void
    {
        $html = $this->render('<twig:QrCode content="https://example.com/t" class="ticket" />');

        self::assertStringStartsWith('<figure class="phil-qr-code isQrCode ticket"><svg aria-hidden="true"', $html);
        self::assertStringContainsString('<a href="https://example.com/t" rel="noopener">https://example.com/t</a>', $html);
        self::assertStringContainsString('role="img"', $this->render('<twig:QrCode content="HI" alt="Hi" :caption="false" size="{{ 96 }}" level="H" />'));
    }
}
