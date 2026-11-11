<?php

declare(strict_types=1);

namespace Phil\UxCss\Tests\Unit\Stylesheet;

use Phil\UxCss\Token\TokenSheet;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * The stylesheets as files: the entry imports each one into its layer, every variable read is declared or has a
 * fallback, and every class follows the isX/hasX naming.
 */
#[CoversNothing]
final class StylesheetTest extends TestCase
{
    private const string CSS = __DIR__.'/../../../css/';

    private const string PACKAGE = __DIR__.'/../../../';

    /**
     * What Symfony's form theme writes, which the base dresses as it comes.
     */
    private const array FOREIGN_CLASSES = ['required', 'help-text'];

    /**
     * The names the recipes of the kit rely on.
     */
    private const array COMPONENTS = [
        'isPrimary', 'isSecondary', 'isGhost', 'isDanger', 'isOutline', 'isSmall', 'isLarge', 'isLoading', 'hasIcon',
        'isBadge', 'isButtonGroup', 'isCard', 'isEmptyState', 'isTabs', 'isAlert', 'isSheet', 'isSwitch', 'isTooltip',
        'isPagination', 'isCarousel', 'isSpinner', 'isSkeleton', 'isAvatar', 'isSeparator', 'isBreadcrumb',
        'isAccordion', 'isDropdown', 'isStepper', 'isActivityCalendar', 'isLink', 'isCardHeader', 'isCardFooter',
        'isDialog', 'isDialogClose', 'isSheetClose', 'isTabList', 'isTab', 'isTabPanel', 'isSelectTrigger', 'isSelectItem',
        'isDropdownItem', 'isAccordionItem', 'isAlertTitle', 'isAvatarImage', 'isBreadcrumbItem', 'isPaginationLink',
        'isTable', 'isTableRow', 'isCarouselFrame', 'isCarouselViewport', 'isTooltipWrapper',
        'isCopyButton', 'isAnimatedButton', 'hasShine', 'hasLift', 'hasArrow', 'isStepperItem', 'isStepperLabel', 'isActivityCalendarDay',
        'isTree', 'isTreeItem', 'isRating', 'isRatingStar', 'isNumberInput', 'isFileInput', 'isFileInputZone', 'isTag', 'isTagRemove',
        'isRelativeTime', 'isProgressRing', 'isSparkline', 'isBeforeAfter', 'isColorPicker', 'isDatePicker', 'isDatePickerDay',
    ];

    public function testTheEntryImportsEachFileIntoItsLayerInOrder(): void
    {
        $entry = $this->read('phil-ux.css');

        self::assertStringContainsString('@layer phil.palette, phil.colors, phil.dimensions, phil.base, phil.layout, phil.components, phil.utilities;', $entry);
        preg_match_all('/@import url\("\.\/([^"]+)"\) layer\(([a-z.]+)\);/', $entry, $imports, \PREG_SET_ORDER);

        self::assertSame(
            ['phil.palette', 'phil.colors', 'phil.dimensions', 'phil.base', 'phil.layout', 'phil.components', 'phil.utilities'],
            array_column($imports, 2),
        );

        foreach ($imports as $import) {
            self::assertFileExists(self::CSS.$import[1]);
        }

        self::assertCount(\count($this->files()) - 1, $imports, 'every file is imported');
    }

    public function testEveryVariableReadIsDeclaredOrHasAFallback(): void
    {
        $sheet = TokenSheet::fromCss(...array_map($this->read(...), $this->files()));

        foreach ($this->files() as $file) {
            preg_match_all('/var\((--[a-z0-9-]+)\s*(,)?/', $this->read($file), $reads, \PREG_SET_ORDER);

            foreach ($reads as $read) {
                self::assertTrue($sheet->has($read[1]) || '' !== ($read[2] ?? ''), \sprintf('%s reads %s, declared nowhere and without a fallback', $file, $read[1]));
            }
        }
    }

    public function testEveryClassSaysWhatTheElementIsOrHas(): void
    {
        $classes = [];

        foreach ($this->files() as $file) {
            $css = (string) preg_replace('#/\*.*?\*/#s', '', $this->read($file));
            // The class selectors only: a dot after a digit is a number, inside a declaration it is a value.
            preg_match_all('/(?<![\w.-])\.([A-Za-z_][\w-]*)(?=[^{};]*\{)/', $css, $matches);
            array_push($classes, ...$matches[1]);
        }

        self::assertNotSame([], $classes);

        foreach (array_unique($classes) as $class) {
            if (!\in_array($class, self::FOREIGN_CLASSES, true) && !str_starts_with($class, 'hl-') && !str_starts_with($class, 'ujo-')) {
                self::assertMatchesRegularExpression('/^(is|has)[A-Z][A-Za-z]*$/', $class);
            }
        }

        foreach (['isStack', 'isCluster', 'isCenter', 'isSplit', 'isCover', 'isBleed', 'isEyebrow', 'isHeadline', 'isReveal', 'isProse', 'isVisuallyHidden'] as $class) {
            self::assertContains($class, $classes);
        }
    }

    public function testEachAnimationStopsUnderReducedMotion(): void
    {
        foreach ($this->files() as $file) {
            $css = $this->read($file);

            if (str_contains($css, 'animation:') || str_contains($css, 'transition:')) {
                self::assertStringContainsString('prefers-reduced-motion', $css, $file);
            }
        }
    }

    public function testTheBaseDressesTheElementsWithoutAClass(): void
    {
        $css = $this->read('layers/base.css');
        $selectors = (string) preg_replace('/\{[^{}]*\}/', '{}', (string) preg_replace('#/\*.*?\*/#s', '', $css));

        foreach (['fieldset', 'legend', 'label', 'select', 'textarea', 'button', 'table', 'caption', 'dialog', '::backdrop', 'details', 'summary', 'nav', 'progress', 'meter', '[popover]', 'kbd', 'abbr', '[type="checkbox"]', '[type="radio"]', '[type="range"]', '[type="file"]', '[type="color"]', ':disabled', ':user-valid', ':user-invalid', '[aria-invalid="true"]', '[aria-current]'] as $selector) {
            self::assertStringContainsString($selector, $selectors, $selector);
        }
    }

    public function testTheBaseDressesTheMarkupOfSymfonyForms(): void
    {
        $css = $this->read('layers/base.css');

        foreach (['label.required::after', '.help-text', 'form div > label + ul', 'form div:has(> input:is([type="checkbox"], [type="radio"]))'] as $selector) {
            self::assertStringContainsString($selector, $css, $selector);
        }
    }

    public function testEveryComponentTheKitReliesOnExists(): void
    {
        $css = $this->read('layers/components.css');

        foreach (self::COMPONENTS as $class) {
            self::assertStringContainsString('.'.$class, $css, $class);
        }
    }

    public function testTheSheetStandsAloneAndNeverReadsTheMarkersOfTheRecipes(): void
    {
        foreach ($this->files() as $file) {
            $css = (string) preg_replace('#/\*.*?\*/#s', '', $this->read($file));

            self::assertStringNotContainsString('data-slot', $css, $file);
            self::assertStringNotContainsString('data-side', $css, $file);
        }
    }

    public function testEveryClassOfTheComponentsIsDocumented(): void
    {
        $css = (string) preg_replace('#/\*.*?\*/#s', '', $this->read('layers/components.css').$this->read('layers/layout.css').$this->read('layers/utilities.css'));
        $doc = file_get_contents(self::PACKAGE.'doc/components.md').file_get_contents(self::PACKAGE.'doc/layout.md');
        preg_match_all('/\.((?:is|has)[A-Z][A-Za-z]*)(?=[^{};]*\{)/', $css, $matches);

        foreach (array_unique($matches[1]) as $class) {
            self::assertStringContainsString('`'.$class.'`', $doc, $class);
        }
    }

    public function testTheLayersHoldNoColourOfTheirOwnAndNeverReadThePalette(): void
    {
        foreach ($this->layers() as $path) {
            $css = (string) preg_replace('#/\*.*?\*/#s', '', $this->read($path));

            self::assertDoesNotMatchRegularExpression('/#[0-9a-fA-F]{3,8}\b|\b(?:rgba?|hsla?|oklch|oklab|lab|lch)\(/', $css, $path);
            self::assertDoesNotMatchRegularExpression('/var\(--phil-(?:neutral|brand|red|green|amber|blue|violet)-\d/', $css, $path);
            self::assertDoesNotMatchRegularExpression('/font-size:\s*[\d.]+px/', $css, $path);
        }
    }

    public function testDirectionsAreLogical(): void
    {
        foreach ($this->layers() as $path) {
            $css = $this->read($path);

            self::assertDoesNotMatchRegularExpression('/^\s*(?:(?:margin|padding|border)-(?:left|right|top|bottom)|left|right|top|bottom|width|height|(?:min|max)-(?:width|height))\s*:/m', $css, $path);
            self::assertDoesNotMatchRegularExpression('/text-align:\s*(?:left|right)/', $css, $path);
        }
    }

    public function testThePrintFallbackOfUjoShowsOnlyOnPaper(): void
    {
        $css = $this->read('layers/components.css');

        self::assertMatchesRegularExpression('/@media not print \{\s*\.ujo-print-fallback \{\s*display: none;/', $css);
        self::assertMatchesRegularExpression('/@media print \{\s*:is\(iframe, video, audio\):has\(\+ \.ujo-print-fallback\) \{\s*display: none;/', $css);
    }

    public function testTheTonesAndTheSizesComeLastAmongTheComponents(): void
    {
        $css = $this->read('layers/components.css');
        $default = strpos($css, '.isBadge,');

        self::assertNotFalse($default);
        self::assertGreaterThan($default, strpos($css, "\n.isPrimary {"));
        self::assertGreaterThan(strrpos($css, '.isAlert'), strpos($css, "\n.isDanger {"));
        self::assertGreaterThan(strrpos($css, '.isAvatar'), strpos($css, "\n.isSmall {"));
    }

    /**
     * @return list<string>
     */
    private function files(): array
    {
        $files = glob(self::CSS.'{*.css,*/*.css}', \GLOB_BRACE);
        self::assertIsArray($files);
        sort($files);

        return array_map(static fn (string $path): string => substr($path, \strlen(self::CSS)), $files);
    }

    /**
     * @return list<string>
     */
    private function layers(): array
    {
        return array_values(array_filter($this->files(), static fn (string $file): bool => str_starts_with($file, 'layers/')));
    }

    private function read(string $file): string
    {
        $contents = file_get_contents(self::CSS.$file);
        self::assertIsString($contents);

        return $contents;
    }
}
