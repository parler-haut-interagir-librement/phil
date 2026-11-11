<?php

declare(strict_types=1);

namespace Phil\CssSanitizer\Tests\Unit;

use Phil\CssSanitizer\AtRuleKind;
use Phil\CssSanitizer\Exception\InvalidPolicy;
use Phil\CssSanitizer\Policy;
use Phil\CssSanitizer\Url\AllowedFiles;
use Phil\CssSanitizer\Url\RefuseUrls;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Policy::class)]
#[CoversClass(InvalidPolicy::class)]
#[CoversClass(AtRuleKind::class)]
#[UsesClass(RefuseUrls::class)]
#[UsesClass(AllowedFiles::class)]
final class PolicyTest extends TestCase
{
    public function testItHasDefaults(): void
    {
        $policy = new Policy();

        self::assertInstanceOf(RefuseUrls::class, $policy->urlRewriter);
        self::assertNull($policy->scope);
        self::assertTrue($policy->important);
        self::assertFalse($policy->customProperties);
        self::assertSame(1_048_576, $policy->maxLength);
        self::assertSame(32, $policy->maxDepth);
        self::assertSame(3, $policy->maxNesting);
        self::assertSame(array_fill_keys(Policy::DEFAULT_PROPERTIES, true), $policy->properties);
        self::assertSame(array_fill_keys(Policy::DEFAULT_FUNCTIONS, true), $policy->functions);
        self::assertSame(array_fill_keys(Policy::DEFAULT_PSEUDO_CLASSES, true), $policy->pseudoClasses);
        self::assertSame(array_fill_keys(Policy::DEFAULT_PSEUDO_ELEMENTS, true), $policy->pseudoElements);
        self::assertSame(['lang' => true, 'dir' => true], $policy->attributes);
        self::assertSame(['all' => true, 'print' => true, 'screen' => true], $policy->mediaTypes);
        self::assertSame(['media' => true, 'supports' => true, 'page' => true, 'font-face' => true], $policy->atRules);
        self::assertArrayNotHasKey('position', $policy->properties);
        self::assertArrayNotHasKey('url', $policy->functions);
    }

    public function testItAddsAndRemovesNamesInLowerCase(): void
    {
        $policy = new Policy();
        $changed = $policy
            ->withProperties('Position', 'z-index')->withoutProperties('COLOR')
            ->withFunctions('ENV')->withoutFunctions('Calc')
            ->withPseudoClasses('Checked')->withoutPseudoClasses('HOVER')
            ->withPseudoElements('Placeholder')->withoutPseudoElements('BEFORE')
            ->withAttributes('Data-*')->withoutAttributes('LANG')
            ->withMediaTypes('Print');

        self::assertArrayHasKey('position', $changed->properties);
        self::assertArrayHasKey('z-index', $changed->properties);
        self::assertArrayNotHasKey('color', $changed->properties);
        self::assertArrayHasKey('margin', $changed->properties);
        self::assertArrayHasKey('env', $changed->functions);
        self::assertArrayNotHasKey('calc', $changed->functions);
        self::assertArrayHasKey('rgb', $changed->functions);
        self::assertArrayHasKey('checked', $changed->pseudoClasses);
        self::assertArrayNotHasKey('hover', $changed->pseudoClasses);
        self::assertArrayHasKey('root', $changed->pseudoClasses);
        self::assertArrayHasKey('placeholder', $changed->pseudoElements);
        self::assertArrayNotHasKey('before', $changed->pseudoElements);
        self::assertArrayHasKey('after', $changed->pseudoElements);
        self::assertSame(['dir' => true, 'data-*' => true], $changed->attributes);
        self::assertSame(['print' => true], $changed->mediaTypes);
        self::assertArrayHasKey('color', $policy->properties);
        self::assertArrayNotHasKey('position', $policy->properties);
    }

    public function testItChangesAtRules(): void
    {
        $policy = new Policy()->withoutAtRules(AtRuleKind::Page, AtRuleKind::FontFace);

        self::assertTrue($policy->allowsAtRule(AtRuleKind::Media));
        self::assertTrue($policy->allowsAtRule(AtRuleKind::Supports));
        self::assertFalse($policy->allowsAtRule(AtRuleKind::Page));
        self::assertFalse($policy->allowsAtRule(AtRuleKind::FontFace));
        self::assertTrue($policy->withAtRules(AtRuleKind::Page)->allowsAtRule(AtRuleKind::Page));
        self::assertFalse($policy->withAtRules(AtRuleKind::Page)->allowsAtRule(AtRuleKind::FontFace));
        self::assertFalse(new Policy()->allowsAtRule(AtRuleKind::Import));
        self::assertTrue(new Policy()->withAtRules(AtRuleKind::Import)->allowsAtRule(AtRuleKind::Import));
        self::assertSame(['media', 'supports', 'page', 'font-face', 'import'], array_map(static fn (AtRuleKind $kind): string => $kind->value, AtRuleKind::cases()));
    }

    public function testItChangesSettings(): void
    {
        $rewriter = new AllowedFiles();
        $policy = new Policy()
            ->withUrlRewriter($rewriter)
            ->withScope('.x')
            ->withImportant(false)
            ->withCustomProperties(true)
            ->withMaxLength(10)
            ->withMaxDepth(2);

        self::assertSame($rewriter, $policy->urlRewriter);
        self::assertSame('.x', $policy->scope);
        self::assertFalse($policy->important);
        self::assertTrue($policy->customProperties);
        self::assertSame(10, $policy->maxLength);
        self::assertSame(2, $policy->maxDepth);
        self::assertSame(1, $policy->withMaxLength(1)->maxLength);
        self::assertSame(1, $policy->withMaxDepth(1)->maxDepth);
        self::assertSame(1, new Policy(maxLength: 1, maxDepth: 1)->maxDepth);
        self::assertSame(0, $policy->withMaxNesting(0)->maxNesting);
        self::assertSame(5, new Policy(maxNesting: 5)->withMaxNesting(1)->withMaxNesting(5)->maxNesting);
        self::assertSame(0, new Policy(maxNesting: 0)->maxNesting);
    }

    public function testItMatchesAttributesByNameOrPrefix(): void
    {
        $policy = new Policy()->withAttributes('data-*', 'title');

        self::assertTrue($policy->allowsAttribute('LANG'));
        self::assertTrue($policy->allowsAttribute('title'));
        self::assertTrue($policy->allowsAttribute('data-role'));
        self::assertTrue($policy->allowsAttribute('DATA-'));
        self::assertFalse($policy->allowsAttribute('data'));
        self::assertFalse($policy->allowsAttribute('value'));
        self::assertFalse($policy->allowsAttribute('x-data-role'));
        self::assertFalse(new Policy()->withAttributes('data')->allowsAttribute('data-x'));
        self::assertFalse(new Policy()->allowsAttribute('*'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function deniedProperties(): iterable
    {
        foreach (['behavior', '-ms-behavior', '-moz-binding', 'BEHAVIOR'] as $property) {
            yield $property => [$property];
        }
    }

    #[DataProvider('deniedProperties')]
    public function testItNeverAllowsPropertiesThatRunCode(string $property): void
    {
        $this->expectException(InvalidPolicy::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The property "%s" can run code or load a binding; it is never allowed.', $property));

        new Policy()->withProperties('color', $property);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function deniedFunctions(): iterable
    {
        foreach (Policy::DENIED_FUNCTIONS as $function) {
            yield $function => [$function];
        }

        yield 'URL' => ['URL'];
    }

    #[DataProvider('deniedFunctions')]
    public function testItNeverAllowsFunctionsThatLoadOrRun(string $function): void
    {
        $this->expectException(InvalidPolicy::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The function "%s()" can run code or load a resource; it is never allowed.', $function));

        new Policy()->withFunctions('calc', $function);
    }

    /**
     * @return iterable<string, array{callable(): Policy, string}>
     */
    public static function limits(): iterable
    {
        yield 'length in the constructor' => [static fn (): Policy => new Policy(maxLength: 0), 'The maximum length must be at least 1; got 0.'];
        yield 'depth in the constructor' => [static fn (): Policy => new Policy(maxDepth: 0), 'The maximum depth must be at least 1; got 0.'];
        yield 'length' => [static fn (): Policy => new Policy()->withMaxLength(0), 'The maximum length must be at least 1; got 0.'];
        yield 'depth' => [static fn (): Policy => new Policy()->withMaxDepth(-1), 'The maximum depth must be at least 1; got -1.'];
        yield 'nesting in the constructor' => [static fn (): Policy => new Policy(maxNesting: -1), 'The maximum nesting cannot be negative; got -1.'];
        yield 'nesting' => [static fn (): Policy => new Policy()->withMaxNesting(-1), 'The maximum nesting cannot be negative; got -1.'];
    }

    /**
     * @param callable(): Policy $build
     */
    #[DataProvider('limits')]
    public function testItRefusesLimitsThatCannotHold(callable $build, string $message): void
    {
        $this->expectException(InvalidPolicy::class);
        $this->expectExceptionMessageIsOrContains($message);

        $build();
    }
}
