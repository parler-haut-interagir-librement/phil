<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck\Tests\Unit;

use Phil\AccessibilityCheck\ContrastLevel;
use Phil\AccessibilityCheck\Exception\InvalidPolicy;
use Phil\AccessibilityCheck\HeadingPolicy;
use Phil\AccessibilityCheck\Policy;
use Phil\AccessibilityCheck\Rule\GenericLinkText;
use Phil\AccessibilityCheck\Severity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Policy::class)]
#[CoversClass(InvalidPolicy::class)]
final class PolicyTest extends TestCase
{
    public function testTheDefaultsSuitAnArticle(): void
    {
        $policy = new Policy();

        self::assertSame([5_242_880, 256, 100_000], [$policy->maxBytes, $policy->maxDepth, $policy->maxElements]);
        self::assertSame(HeadingPolicy::Single, $policy->headings);
        self::assertSame(ContrastLevel::AA, $policy->contrast);
        self::assertSame('#000000', $policy->foreground->toHex());
        self::assertSame('#ffffff', $policy->background->toHex());
        self::assertNull($policy->language);
        self::assertSame('en', $policy->locale);
        self::assertSame([], $policy->colourTokens);
        self::assertSame(GenericLinkText::DEFAULTS, $policy->genericLinkTexts);
        self::assertSame([], $policy->disabledRules);
        self::assertSame([], $policy->severities);
    }

    public function testALimitIsAtLeastOne(): void
    {
        self::assertSame(1, new Policy(maxBytes: 1, maxDepth: 1, maxElements: 1)->maxBytes);

        $this->expectException(InvalidPolicy::class);
        $this->expectExceptionMessageIsOrContains('The maximum size must be at least 1; got 0.');

        new Policy(maxBytes: 0);
    }

    /**
     * @return iterable<array{array<string, int>, string}>
     */
    public static function limits(): iterable
    {
        yield [['maxDepth' => 0], 'The maximum depth must be at least 1; got 0.'];
        yield [['maxElements' => -1], 'The maximum number of elements must be at least 1; got -1.'];
    }

    /**
     * @param array<string, int> $arguments
     */
    #[DataProvider('limits')]
    public function testEachLimitIsChecked(array $arguments, string $message): void
    {
        $this->expectExceptionObject(new InvalidPolicy($message));

        new Policy(...$arguments);
    }

    /**
     * @return iterable<array{string, string, string}>
     */
    public static function colours(): iterable
    {
        yield ['var(--text)', '#fff', 'The foreground must be an opaque CSS colour; got "var(--text)".'];
        yield ['#000', 'rgb(0 0 0 / 50%)', 'The background must be an opaque CSS colour; got "rgb(0 0 0 / 50%)".'];
    }

    #[DataProvider('colours')]
    public function testTheDefaultColoursAreOpaque(string $foreground, string $background, string $message): void
    {
        $this->expectExceptionObject(new InvalidPolicy($message));

        new Policy(foreground: $foreground, background: $background);
    }

    public function testTheColoursOfTheLookAreAddedOrReplaced(): void
    {
        $policy = new Policy(foreground: '#333', background: 'navy')->withColourTokens(['accent' => '#b00020', 'muted' => 'grey'])->withColourTokens(['muted' => 'rgb(0 0 0 / 50%)']);

        self::assertSame('#333333', $policy->foreground->toHex());
        self::assertSame('#000080', $policy->background->toHex());
        self::assertSame(['muted', 'accent'], array_keys($policy->colourTokens));
        self::assertSame('#b00020', $policy->colourTokens['accent']->toHex());
        self::assertSame(0.5, $policy->colourTokens['muted']->alpha);
    }

    public function testATokenColourIsReadable(): void
    {
        $this->expectExceptionObject(new InvalidPolicy('The colour of the token "accent" must be an opaque CSS colour; got "var(--a)".'));

        new Policy()->withColourTokens(['accent' => 'var(--a)']);
    }

    public function testTheGenericLinkTextsOfALanguageAreReplacedNormalized(): void
    {
        $policy = new Policy()->withGenericLinkTexts('EN', 'Click Here!', 'click here', '  Tap  ');

        self::assertSame(['click here', 'tap'], $policy->genericLinkTexts['en']);
        self::assertSame(GenericLinkText::DEFAULTS['fr'], $policy->genericLinkTexts['fr']);
        self::assertSame([], new Policy()->withGenericLinkTexts('fr')->genericLinkTexts['fr']);
        self::assertSame(['hier'], new Policy()->withGenericLinkTexts('nl', 'Hier')->genericLinkTexts['nl']);
    }

    public function testRulesAreTurnedOff(): void
    {
        $policy = new Policy()->withoutRules('image-alt', 'link-text')->withoutRules('image-alt', 'svg-name');

        self::assertSame(['image-alt', 'link-text', 'svg-name'], $policy->disabledRules);
        self::assertFalse($policy->isEnabled('image-alt'));
        self::assertTrue($policy->isEnabled('link-name'));
        self::assertFalse(new Policy(disabledRules: ['svg-name'])->isEnabled('svg-name'));
    }

    public function testARuleIsGivenAnotherSeverity(): void
    {
        $policy = new Policy()->withSeverity('svg-name', Severity::Error)->withSeverity('link-text', Severity::Notice)->withSeverity('svg-name', Severity::Notice);

        self::assertSame(['svg-name' => Severity::Notice, 'link-text' => Severity::Notice], $policy->severities);
    }

    public function testADuplicateRuleIsNamed(): void
    {
        self::assertSame('Two rules have the identifier "x"; an identifier names one rule.', InvalidPolicy::duplicateRule('x')->getMessage());
    }
}
