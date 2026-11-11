<?php

declare(strict_types=1);

namespace Phil\UxCss\Tests\Unit\Token;

use Phil\UxCss\Token\CssValue;
use Phil\UxCss\Token\DesignTokens;
use Phil\UxCss\Token\TokenSheet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(DesignTokens::class)]
#[CoversClass(CssValue::class)]
final class DesignTokensTest extends TestCase
{
    private const string CSS = __DIR__.'/../../../css';

    /**
     * @return iterable<string, array{string, string, string, mixed}>
     */
    public static function tokens(): iterable
    {
        yield 'a colour' => ['--phil-brand-40: oklch(0.40 0.058 195)', 'brand.40', 'color', ['colorSpace' => 'oklch', 'components' => [0.4, 0.058, 195]]];
        yield 'a colour with alpha' => ['--phil-a-b: oklch(40% 0.1 20deg / 50%)', 'a.b', 'color', ['colorSpace' => 'oklch', 'components' => [0.4, 0.1, 20], 'alpha' => 0.5]];
        yield 'a length in rem' => ['--phil-space-sm: 0.75rem', 'space.sm', 'dimension', ['value' => 0.75, 'unit' => 'rem']];
        yield 'a length in px' => ['--phil-border-sm: 1px', 'border.sm', 'dimension', ['value' => 1, 'unit' => 'px']];
        yield 'a computed length' => ['--phil-space-xs: calc(1rem * 0.5)', 'space.xs', 'dimension', ['value' => 0.5, 'unit' => 'rem']];
        yield 'a duration in ms' => ['--phil-duration-sm: 150ms', 'duration.sm', 'duration', ['value' => 150, 'unit' => 'ms']];
        yield 'a duration in s' => ['--phil-duration-loop: 1s', 'duration.loop', 'duration', ['value' => 1, 'unit' => 's']];
        yield 'a number' => ['--phil-leading-md: 1.6', 'leading.md', 'number', 1.6];
        yield 'a number of one word' => ['--phil-scale: 1', 'scale.$root', 'number', 1];
        yield 'a font weight' => ['--phil-weight-bold: 700', 'weight.bold', 'fontWeight', 700];
        yield 'the lightest font weight' => ['--phil-weight-thin: 1', 'weight.thin', 'fontWeight', 1];
        yield 'the heaviest font weight' => ['--phil-weight-black: 1000', 'weight.black', 'fontWeight', 1000];
        yield 'a colour in capitals' => ['--phil-brand-50: OKLCH(0.5 0.1 20)', 'brand.50', 'color', ['colorSpace' => 'oklch', 'components' => [0.5, 0.1, 20]]];
        yield 'a cubic Bézier in capitals' => ['--phil-ease-in: CUBIC-BEZIER(0.4, 0, 1, 1)', 'ease.in', 'cubicBezier', [0.4, 0, 1, 1]];
        yield 'font families' => ['--phil-font-sans: system-ui, "Segoe UI", \'Noto Sans\', sans-serif', 'font.sans', 'fontFamily', ['system-ui', 'Segoe UI', 'Noto Sans', 'sans-serif']];
        yield 'a family holding a comma' => ['--phil-font-x: "A, B", serif', 'font.x', 'fontFamily', ['A, B', 'serif']];
        yield 'a cubic Bézier' => ['--phil-ease-out: cubic-bezier(0.22, 1, 0.36, 1)', 'ease.out', 'cubicBezier', [0.22, 1, 0.36, 1]];
        yield 'a cubic Bézier going back' => ['--phil-ease-back: cubic-bezier(0, -0.5, 1, 1.5)', 'ease.back', 'cubicBezier', [0, -0.5, 1, 1.5]];
        yield 'a shadow' => ['--phil-shadow-sm: 0 1px 2px oklch(0 0 0 / 0.1)', 'shadow.sm', 'shadow', [
            'color' => ['colorSpace' => 'oklch', 'components' => [0, 0, 0], 'alpha' => 0.1],
            'offsetX' => ['value' => 0, 'unit' => 'px'], 'offsetY' => ['value' => 1, 'unit' => 'px'],
            'blur' => ['value' => 2, 'unit' => 'px'], 'spread' => ['value' => 0, 'unit' => 'px'],
        ]];
        yield 'an inset shadow with its spread' => ['--phil-shadow-in: inset 1px 2px 3px 4rem oklch(0 0 0)', 'shadow.in', 'shadow', [
            'color' => ['colorSpace' => 'oklch', 'components' => [0, 0, 0]],
            'offsetX' => ['value' => 1, 'unit' => 'px'], 'offsetY' => ['value' => 2, 'unit' => 'px'],
            'blur' => ['value' => 3, 'unit' => 'px'], 'spread' => ['value' => 4, 'unit' => 'rem'], 'inset' => true,
        ]];
        yield 'an inset shadow in capitals' => ['--phil-shadow-up: INSET 1px 2px oklch(0 0 0)', 'shadow.up', 'shadow', [
            'color' => ['colorSpace' => 'oklch', 'components' => [0, 0, 0]],
            'offsetX' => ['value' => 1, 'unit' => 'px'], 'offsetY' => ['value' => 2, 'unit' => 'px'],
            'blur' => ['value' => 0, 'unit' => 'px'], 'spread' => ['value' => 0, 'unit' => 'px'], 'inset' => true,
        ]];
        yield 'shadows' => ['--phil-shadow-md: 0 1px oklch(0 0 0), 2px 3px oklch(1 0 0)', 'shadow.md', 'shadow', [
            [
                'color' => ['colorSpace' => 'oklch', 'components' => [0, 0, 0]],
                'offsetX' => ['value' => 0, 'unit' => 'px'], 'offsetY' => ['value' => 1, 'unit' => 'px'],
                'blur' => ['value' => 0, 'unit' => 'px'], 'spread' => ['value' => 0, 'unit' => 'px'],
            ],
            [
                'color' => ['colorSpace' => 'oklch', 'components' => [1, 0, 0]],
                'offsetX' => ['value' => 2, 'unit' => 'px'], 'offsetY' => ['value' => 3, 'unit' => 'px'],
                'blur' => ['value' => 0, 'unit' => 'px'], 'spread' => ['value' => 0, 'unit' => 'px'],
            ],
        ]];
    }

    #[DataProvider('tokens')]
    public function testEachValueHasItsType(string $declaration, string $path, string $type, mixed $value): void
    {
        $token = $this->token($this->export(':root { '.$declaration.'; }'), $path);

        self::assertSame($type, $token['$type']);
        self::assertSame($value, $token['$value']);
        self::assertSame(
            [DesignTokens::EXTENSION => ['property' => explode(':', $declaration, 2)[0], 'css' => trim(explode(':', $declaration, 2)[1])]],
            $token['$extensions'],
        );
    }

    public function testAPropertyThatIsAnotherIsAnAliasOfItsType(): void
    {
        $tokens = $this->export(':root { --phil-brand-40: oklch(0.4 0.05 195); --phil-color-link: var(--phil-brand-40); --phil-space-unit: 1rem; --phil-space-md: var(--phil-space-unit); --phil-scale: 1; --phil-scale-space: var(--phil-scale); }');

        self::assertSame(['color', '{brand.40}'], $this->typed($tokens, 'color.link'));
        self::assertSame(['dimension', '{space.unit}'], $this->typed($tokens, 'space.md'));
        self::assertSame(['number', '{scale.$root}'], $this->typed($tokens, 'scale.space'));
    }

    public function testLightAndDarkGiveTheValueAndTheDarkOne(): void
    {
        $tokens = $this->export(':root { --phil-n-10: oklch(0.1 0 0); --phil-n-99: oklch(0.99 0 0); --phil-color-surface: light-dark(var(--phil-n-99), var(--phil-n-10)); --phil-color-link: var(--phil-color-surface); --phil-color-shadow: light-dark(oklch(0 0 0 / 0.1), oklch(0 0 0 / 0.5)); }');

        self::assertSame(['$type' => 'color', '$value' => '{n.99}', '$extensions' => [DesignTokens::EXTENSION => [
            'property' => '--phil-color-surface',
            'css' => 'light-dark(var(--phil-n-99), var(--phil-n-10))',
            'dark' => '{n.10}',
        ]]], $this->token($tokens, 'color.surface'));
        self::assertSame(['color', '{color.surface}'], $this->typed($tokens, 'color.link'));
        self::assertSame(['colorSpace' => 'oklch', 'components' => [0, 0, 0], 'alpha' => 0.5], $this->at($this->token($tokens, 'color.shadow'), '$extensions', DesignTokens::EXTENSION, 'dark'));
    }

    public function testAnAliasOfAFontWeightIsAFontWeight(): void
    {
        $tokens = $this->export(':root { --phil-weight-bold: 700; --phil-weight-strong: var(--phil-weight-bold); }');

        self::assertSame(['fontWeight', '{weight.bold}'], $this->typed($tokens, 'weight.strong'));
    }

    public function testLightAndDarkAreReadWhateverTheirCaseAndLines(): void
    {
        $tokens = $this->export(":root { --phil-space-x: LIGHT-DARK(1px,\n2px); }");

        self::assertSame(['value' => 2, 'unit' => 'px'], $this->at($this->token($tokens, 'space.x'), '$extensions', DesignTokens::EXTENSION, 'dark'));
    }

    public function testTheLongestChainOfAliasesIsFollowed(): void
    {
        $chain = '--phil-c-0: oklch(0 0 0);';

        foreach (range(1, 17) as $index) {
            $chain .= ' --phil-c-'.$index.': var(--phil-c-'.($index - 1).');';
        }

        $tokens = DesignTokens::fromSheet(TokenSheet::fromCss(':root { '.$chain.'
            --phil-l-last: light-dark(var(--phil-c-15), var(--phil-c-0));
            --phil-l-light: light-dark(var(--phil-c-16), var(--phil-c-0));
            --phil-d-last: light-dark(var(--phil-c-0), var(--phil-c-15));
            --phil-d-dark: light-dark(var(--phil-c-0), var(--phil-c-16));
            --phil-shadow-last: 1px 1px var(--phil-c-14);
            --phil-shadow-far: 1px 1px var(--phil-c-15);
        }'));

        self::assertSame(['--phil-c-17', '--phil-l-light', '--phil-d-dark', '--phil-shadow-far'], $tokens->skipped());
    }

    public function testAPropertyOutsideThePrefixIsNotRead(): void
    {
        $tokens = DesignTokens::fromSheet(TokenSheet::fromCss(':root { --other-x: oklch(nonsense); --phil-a-b: 1px; }'));

        self::assertSame(['--other-x'], $tokens->skipped());
    }

    public function testTheJsonKeepsItsCharacters(): void
    {
        $json = DesignTokens::fromSheet(TokenSheet::fromCss(':root { --phil-font-x: "Équipe"; }'))->toJson();

        self::assertStringContainsString('"Équipe"', $json);
    }

    public function testAZeroAmongLengthsIsALength(): void
    {
        $tokens = $this->export(':root { --phil-radius-none: 0; --phil-radius-sm: 4px; --phil-z-low: 0; --phil-z-high: 10; --phil-gap-none: 0; --phil-gap-word: 0; }');

        self::assertSame(['dimension', ['value' => 0, 'unit' => 'px']], $this->typed($tokens, 'radius.none'));
        self::assertSame(['number', 0], $this->typed($tokens, 'z.low'));
        self::assertSame(['number', 0], $this->typed($tokens, 'gap.none'));
    }

    public function testAZeroAmongOtherTypesStaysANumber(): void
    {
        $tokens = $this->export(':root { --phil-mixed-none: 0; --phil-mixed-sm: 4px; --phil-mixed-duration: 1s; --phil-mixed-one: 1; }');

        self::assertSame(['number', 0], $this->typed($tokens, 'mixed.none'));
        self::assertSame(['number', 1], $this->typed($tokens, 'mixed.one'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function leftOut(): iterable
    {
        yield 'a length in ch' => ['--phil-measure: 65ch'];
        yield 'a percentage' => ['--phil-mix-hover: 12%'];
        yield 'a word' => ['--phil-display-x: block'];
        yield 'an alias of nothing' => ['--phil-color-x: var(--phil-nowhere)'];
        yield 'an alias outside the prefix' => ['--phil-color-x: var(--other)'];
        yield 'an alias of a word' => ['--phil-display-x: block; --phil-display-y: var(--phil-display-x)'];
        yield 'an alias in a cycle' => ['--phil-loop-a: var(--phil-loop-b); --phil-loop-b: var(--phil-loop-a)'];
        yield 'light and dark of two types' => ['--phil-x-y: light-dark(1px, oklch(0 0 0))'];
        yield 'light and dark of one value' => ['--phil-x-y: light-dark(1px)'];
        yield 'light and dark of a word' => ['--phil-x-y: light-dark(auto, 1px)'];
        yield 'a font weight out of bounds' => ['--phil-weight-x: 1001'];
        yield 'a font weight of zero' => ['--phil-weight-x: 0'];
        yield 'a font weight with a fraction' => ['--phil-weight-x: 450.5'];
        yield 'an empty family' => ['--phil-font-x: serif, ""'];
        yield 'a cubic Bézier out of bounds' => ['--phil-ease-x: cubic-bezier(1.5, 0, 0, 1)'];
        yield 'a cubic Bézier out of bounds on its second point' => ['--phil-ease-x: cubic-bezier(0, 0, -0.1, 1)'];
        yield 'a cubic Bézier of three numbers' => ['--phil-ease-x: cubic-bezier(0, 0, 1)'];
        yield 'a shadow of one length' => ['--phil-shadow-x: 1px oklch(0 0 0)'];
        yield 'a shadow of five lengths' => ['--phil-shadow-x: 1px 1px 1px 1px 1px oklch(0 0 0)'];
        yield 'a shadow without colour' => ['--phil-shadow-x: 1px 1px'];
        yield 'a shadow of two colours' => ['--phil-shadow-x: 1px 1px oklch(0 0 0) oklch(1 0 0)'];
        yield 'a shadow of a word' => ['--phil-shadow-x: 1px 1px auto'];
        yield 'a shadow of a length in ch' => ['--phil-shadow-x: 1ch 1px oklch(0 0 0)'];
        yield 'a shadow whose colour is a length' => ['--phil-space-x: 1px; --phil-shadow-x: 1px 1px var(--phil-space-x)'];
        yield 'a shadow of a number other than zero' => ['--phil-shadow-x: 1 1px oklch(0 0 0)'];
        yield 'an empty group' => ['--phil--x: 1px'];
        yield 'an empty name' => ['--phil-a-: 1px'];
        yield 'an alias followed by more' => ['--phil-space-y: 1px; --phil-space-x: var(--phil-space-y) 1px'];
        yield 'light and dark after something' => ['--phil-x-y: 1px light-dark(1px, 2px)'];
        yield 'light and dark before something' => ['--phil-x-y: light-dark(1px, 2px) 1px'];
        yield 'an alias of light and dark of two types' => ['--phil-x-y: light-dark(1px, oklch(0 0 0)); --phil-x-z: var(--phil-x-y)'];
    }

    #[DataProvider('leftOut')]
    public function testAValueWithoutTypeIsLeftOutAndNamed(string $declarations): void
    {
        $tokens = DesignTokens::fromSheet(TokenSheet::fromCss(':root { '.$declarations.'; }'));
        $last = array_last(explode(';', $declarations));
        $property = trim(explode(':', $last, 2)[0]);

        self::assertContains($property, $tokens->skipped());
        self::assertSame($tokens->skipped(), $this->at($tokens->toArray(), '$extensions', DesignTokens::EXTENSION, 'skipped'));
    }

    public function testOnlyThePrefixIsRead(): void
    {
        $tokens = DesignTokens::fromSheet(TokenSheet::fromCss(':root { --phil-a-b: 1px; --other-c-d: 2px; --x-e-f: 3px; }'), '--x-');

        self::assertSame(['--phil-a-b', '--other-c-d'], $tokens->skipped());
        self::assertSame(['value' => 3, 'unit' => 'px'], $this->token($tokens->toArray(), 'e.f')['$value']);
    }

    public function testTheFileDescribesItselfAndKeepsTheOrderOfTheSheet(): void
    {
        $array = $this->export(':root { --phil-b-x: 1px; --phil-a-y: 2px; --phil-b-z: 3px; }');

        self::assertSame(['$description', 'b', 'a', '$extensions'], array_keys($array));
        self::assertSame('The tokens of phil-ux, generated from its CSS, which stays the source.', $array['$description']);
        self::assertIsArray($array['b']);
        self::assertSame(['x', 'z'], array_keys($array['b']));
    }

    public function testNamesOfDigitsAreObjectsInTheJson(): void
    {
        $json = DesignTokens::fromSheet(TokenSheet::fromCss(':root { --phil-n-0: 1px; --phil-n-1: 2px; --phil-m-05: 3px; }'))->toJson();

        self::assertStringEndsWith("}\n", $json);
        self::assertStringContainsString('"n": {'."\n".'        "0": {', $json);
        self::assertStringContainsString('"m": {'."\n".'        "05": {', $json);
        self::assertStringContainsString('"$description": "The tokens of phil-ux', $json);
        self::assertStringNotContainsString('\/', $json);
        self::assertStringContainsString('"$extensions": {'."\n".'        "ca.ph-il.phil-ux": {'."\n".'            "skipped": []', $json);
    }

    public function testNumbersAreRoundedAndWholeOnesWrittenWithoutFraction(): void
    {
        $tokens = $this->export(':root { --phil-text-xl: calc(1rem / 3); --phil-text-lg: calc(1rem * 1.25 * 1.25 * 1.25 * 1.25 * 1.25); --phil-text-md: 2.0rem; }');

        self::assertSame(['value' => 0.33333333, 'unit' => 'rem'], $this->token($tokens, 'text.xl')['$value']);
        self::assertSame(['value' => 3.05175781, 'unit' => 'rem'], $this->token($tokens, 'text.lg')['$value']);
        self::assertSame(['value' => 2, 'unit' => 'rem'], $this->token($tokens, 'text.md')['$value']);
    }

    public function testTheSourcesAreTheTokenFilesInTheOrderOfTheirLayers(): void
    {
        self::assertSame(['/c/tokens/palette.css', '/c/tokens/colors.css', '/c/tokens/dimensions.css'], DesignTokens::sources('/c'));
    }

    public function testThePhilUxTokensAreAllExportedButFour(): void
    {
        $tokens = DesignTokens::fromSheet(TokenSheet::fromFiles(...DesignTokens::sources(self::CSS)));

        self::assertSame(['--phil-measure', '--phil-measure-narrow', '--phil-mix-hover', '--phil-mix-active'], $tokens->skipped());
        self::assertSame(['color', '{neutral.99}'], $this->typed($tokens->toArray(), 'color.surface'));
        self::assertSame(['dimension', ['value' => 0.8, 'unit' => 'rem']], $this->typed($tokens->toArray(), 'text.sm'));
    }

    public function testTheDistributedFileIsTheExportOfTheSources(): void
    {
        self::assertStringEqualsFile(
            __DIR__.'/../../../dist/tokens.json',
            DesignTokens::fromSheet(TokenSheet::fromFiles(...DesignTokens::sources(self::CSS)))->toJson(),
            'dist/tokens.json is stale: run php bin/build.php in the package',
        );
    }

    /**
     * @return array<array-key, mixed>
     */
    private function export(string $css): array
    {
        $tokens = DesignTokens::fromSheet(TokenSheet::fromCss($css));
        self::assertSame([], $tokens->skipped());

        return $tokens->toArray();
    }

    /**
     * @param array<array-key, mixed> $tokens
     *
     * @return array<array-key, mixed>
     */
    private function token(array $tokens, string $path): array
    {
        [$group, $name] = explode('.', $path, 2);
        $token = $this->at($tokens, $group, $name);
        self::assertIsArray($token, $path);

        return $token;
    }

    /**
     * What lies under the keys, each level checked to be an array.
     *
     * @param array<array-key, mixed> $array
     */
    private function at(array $array, string ...$keys): mixed
    {
        $value = $array;

        foreach ($keys as $key) {
            self::assertIsArray($value, $key);
            self::assertArrayHasKey($key, $value);
            $value = $value[$key];
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $tokens
     *
     * @return array{mixed, mixed}
     */
    private function typed(array $tokens, string $path): array
    {
        $token = $this->token($tokens, $path);

        return [$token['$type'], $token['$value']];
    }
}
