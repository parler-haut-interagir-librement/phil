<?php

declare(strict_types=1);

namespace Phil\LocaleDimensions\Tests\Unit;

use Phil\LocaleDimensions\Exception\InvalidLocaleDimension;
use Phil\LocaleDimensions\FallbackChain;
use Phil\LocaleDimensions\Locale;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FallbackChain::class)]
#[CoversClass(InvalidLocaleDimension::class)]
final class FallbackChainTest extends TestCase
{
    public function testTheRequestedLocaleIsTheFirst(): void
    {
        self::assertSame('fr', $this->chain('fr', 'fr_CA', 'en_CA')->requested()->value);
    }

    /**
     * The chain is a list whatever keys it was given: the requested locale is the first one given.
     */
    public function testTheLocalesAreAList(): void
    {
        $chain = FallbackChain::of(['a' => Locale::fromString('fr'), 'b' => Locale::fromString('en')]);

        self::assertSame('fr', $chain->requested()->value);
        self::assertSame([0, 1], array_keys($chain->locales));
    }

    public function testAnEmptyChainIsRefused(): void
    {
        $this->expectException(InvalidLocaleDimension::class);

        FallbackChain::of([]);
    }

    /**
     * A repeated locale is a chain written wrong: its second occurrence can never be reached.
     */
    public function testARepeatedLocaleIsRefused(): void
    {
        $this->expectException(InvalidLocaleDimension::class);
        $this->expectExceptionMessageIsOrContains('The locale "fr" appears twice in the fallback chain.');

        $this->chain('fr', 'fr_CA', 'fr');
    }

    public function testTheRequestedLocaleWinsWhenItHasContent(): void
    {
        self::assertSame('fr', $this->chain('fr', 'fr_CA', 'en_CA')->resolve($this->locales('en_CA', 'fr_CA', 'fr'))?->value);
    }

    /**
     * The chain is walked in its own order, not in the order the available locales arrive in.
     */
    public function testTheChainOrderDecidesNotTheAvailableOrder(): void
    {
        self::assertSame('fr_CA', $this->chain('fr', 'fr_CA', 'en_CA')->resolve($this->locales('en_CA', 'fr_CA'))?->value);
    }

    public function testTheLastLocaleIsReachable(): void
    {
        self::assertSame('en_CA', $this->chain('fr', 'fr_CA', 'en_CA')->resolve($this->locales('en_CA'))?->value);
    }

    /**
     * Nothing catches the last step: a locale outside the chain is never served, even when it has content.
     */
    public function testALocaleOutsideTheChainIsNeverServed(): void
    {
        self::assertNull($this->chain('fr', 'fr_CA')->resolve($this->locales('en_CA', 'en')));
    }

    public function testNothingAvailableResolvesToNothing(): void
    {
        self::assertNull($this->chain('fr')->resolve([]));
    }

    public function testStayingInTheLanguageDoesNotLeaveIt(): void
    {
        $chain = $this->chain('fr', 'fr_CA', 'en_CA');

        self::assertFalse($chain->leavesRequestedLanguage(Locale::fromString('fr')));
        self::assertFalse($chain->leavesRequestedLanguage(Locale::fromString('fr_CA')));
    }

    public function testChangingLanguageLeavesIt(): void
    {
        self::assertTrue($this->chain('fr', 'fr_CA', 'en_CA')->leavesRequestedLanguage(Locale::fromString('en_CA')));
    }

    public function testItsStringFormListsTheChainInOrder(): void
    {
        self::assertSame('fr_CA → fr → en_CA', $this->chain('fr_CA', 'fr', 'en_CA')->toString());
    }

    private function chain(string ...$locales): FallbackChain
    {
        return FallbackChain::of($this->locales(...$locales));
    }

    /**
     * @return list<Locale>
     */
    private function locales(string ...$locales): array
    {
        return array_values(array_map(Locale::fromString(...), $locales));
    }
}
