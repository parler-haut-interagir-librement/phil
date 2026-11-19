<?php

declare(strict_types=1);

namespace Phil\ShortLinkBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Phil\RedirectsBundle\Domain\Enum\QueryPolicy;
use Phil\RedirectsBundle\Domain\Enum\RedirectStatus;
use Phil\ShortLinkBundle\Domain\Enum\LinkState;
use Phil\ShortLinkBundle\Domain\Exception\InvalidShortLink;
use Phil\ShortLinkBundle\Domain\Model\ShortLink;
use Phil\ShortLinkBundle\Domain\ValueObject\LinkTerms;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

#[CoversClass(ShortLink::class)]
#[CoversClass(LinkTerms::class)]
#[CoversClass(LinkState::class)]
#[CoversClass(InvalidShortLink::class)]
final class ShortLinkTest extends TestCase
{
    public function testALinkKeepsWhatItWasGiven(): void
    {
        $now = $this->at('12:00');
        $terms = new LinkTerms(RedirectStatus::TemporaryRedirect, QueryPolicy::Keep, '  Affiche  ', 'fr', false, $this->at('13:00'), $this->at('14:00'), 3);
        $link = new ShortLink('abc123', 'https://example.com/', $now, $terms);

        self::assertNull($link->id());
        self::assertSame(['abc123', 'https://example.com/', 'Affiche', 'fr', RedirectStatus::TemporaryRedirect, QueryPolicy::Keep, false, 3, 0, null], [$link->code(), $link->target(), $link->label(), $link->locale(), $link->status(), $link->query(), $link->isEnabled(), $link->maxVisits(), $link->visits(), $link->lastVisitAt()]);
        self::assertSame($now, $link->createdAt());
        self::assertSame($now, $link->updatedAt());
        self::assertEquals($this->at('13:00'), $link->validFrom());
        self::assertEquals($this->at('14:00'), $link->validUntil());
        self::assertEquals(new LinkTerms(RedirectStatus::TemporaryRedirect, QueryPolicy::Keep, 'Affiche', 'fr', false, $this->at('13:00'), $this->at('14:00'), 3), $link->terms());
    }

    public function testTheDefaultsRedirectWithA302WithoutTheQueryForEveryLocale(): void
    {
        $link = new ShortLink('abc', 'https://example.com/', $this->at('12:00'));

        self::assertSame([RedirectStatus::Found, QueryPolicy::Drop, null, null, true, null, null, null, null], [$link->status(), $link->query(), $link->label(), $link->locale(), $link->isEnabled(), $link->validFrom(), $link->validUntil(), $link->maxVisits(), $link->remainingVisits()]);
        self::assertSame(LinkState::Active, $link->stateAt($this->at('12:00')));
    }

    /**
     * @return iterable<string, array{string, string, LinkTerms, string}>
     */
    public static function invalidLinks(): iterable
    {
        yield 'an empty code' => ['', 'https://example.com/', new LinkTerms(), 'code_invalid'];
        yield 'a code too long' => [str_repeat('a', 65), 'https://example.com/', new LinkTerms(), 'code_invalid'];
        yield 'an empty target' => ['abc', '', new LinkTerms(), 'target'];
        yield 'a target too long' => ['abc', 'https://example.com/'.str_repeat('a', 2029), new LinkTerms(), 'target'];
        yield 'a 410' => ['abc', 'https://example.com/', new LinkTerms(RedirectStatus::Gone), 'status'];
        yield 'an end at the start' => ['abc', 'https://example.com/', new LinkTerms(validFrom: new DateTimeImmutable('2026-11-12 12:00 UTC'), validUntil: new DateTimeImmutable('2026-11-12 07:00 America/Toronto')), 'dates'];
        yield 'an end before the start' => ['abc', 'https://example.com/', new LinkTerms(validFrom: new DateTimeImmutable('2026-11-12 12:00 UTC'), validUntil: new DateTimeImmutable('2026-11-12 11:59 UTC')), 'dates'];
        yield 'no visit allowed' => ['abc', 'https://example.com/', new LinkTerms(maxVisits: 0), 'max_visits'];
        yield 'a label too long' => ['abc', 'https://example.com/', new LinkTerms(label: str_repeat('é', 201)), 'label'];
        yield 'a locale that is none' => ['abc', 'https://example.com/', new LinkTerms(locale: 'French'), 'locale'];
    }

    #[DataProvider('invalidLinks')]
    public function testALinkThatCannotExistIsRefused(string $code, string $target, LinkTerms $terms, string $reason): void
    {
        try {
            new ShortLink($code, $target, $this->at('12:00'), $terms);
            self::fail('The link was accepted.');
        } catch (InvalidShortLink $invalidShortLink) {
            self::assertSame($reason, $invalidShortLink->reason);
            self::assertSame(strtr($invalidShortLink->template, $invalidShortLink->parameters), $invalidShortLink->getMessage());
        }
    }

    public function testTheBoundsAreAccepted(): void
    {
        $link = new ShortLink(str_repeat('a', 64), 'https://example.com/'.str_repeat('a', 2028), $this->at('12:00'), new LinkTerms(label: str_repeat('é', 200), locale: 'pt-BR', validFrom: $this->at('12:00'), validUntil: $this->at('12:01'), maxVisits: 1));

        self::assertSame(2048, \strlen($link->target()));
        self::assertSame(1, $link->remainingVisits());
        self::assertSame('A code has between 1 and 64 characters.', $this->refusal(static fn (): ShortLink => new ShortLink('', 'https://example.com/', new DateTimeImmutable())));
        self::assertSame('A label has 200 characters at most.', $this->refusal(static fn (): ShortLink => new ShortLink('a', 'https://example.com/', new DateTimeImmutable(), new LinkTerms(label: str_repeat('a', 201)))));
        self::assertSame('The locale "en US" is not a locale.', $this->refusal(static fn (): ShortLink => new ShortLink('a', 'https://example.com/', new DateTimeImmutable(), new LinkTerms(locale: 'en US'))));
    }

    public function testTheParametersOfARefusalAreTheTextsOfTheBounds(): void
    {
        $parameters = static function (callable $make): array {
            try {
                $make();
            } catch (InvalidShortLink $invalidShortLink) {
                return $invalidShortLink->parameters;
            }

            return [];
        };

        self::assertSame(['%max%' => '64'], $parameters(static fn (): ShortLink => new ShortLink('', 'https://example.com/', new DateTimeImmutable())));
        self::assertSame(['%max%' => '2048'], $parameters(static fn (): ShortLink => new ShortLink('a', '', new DateTimeImmutable())));
        self::assertSame(['%max%' => '200'], $parameters(static fn (): ShortLink => new ShortLink('a', 'https://example.com/', new DateTimeImmutable(), new LinkTerms(label: str_repeat('a', 201)))));
        self::assertSame('A target has between 1 and 2048 characters.', $this->refusal(static fn (): ShortLink => new ShortLink('a', '', new DateTimeImmutable())));
    }

    public function testAnEmptyLabelIsNone(): void
    {
        self::assertNull(new ShortLink('a', 'https://example.com/', $this->at('12:00'), new LinkTerms(label: '   '))->label());
    }

    /**
     * @return iterable<string, array{LinkTerms, int, string, LinkState}>
     */
    public static function states(): iterable
    {
        $from = new DateTimeImmutable('2026-11-12 13:00 UTC');
        $until = new DateTimeImmutable('2026-11-12 15:00 UTC');

        yield 'in force' => [new LinkTerms(validFrom: $from, validUntil: $until, maxVisits: 2), 1, '14:00', LinkState::Active];
        yield 'at its start' => [new LinkTerms(validFrom: $from), 0, '13:00', LinkState::Active];
        yield 'before its start' => [new LinkTerms(validFrom: $from), 0, '12:59', LinkState::Scheduled];
        yield 'at its end' => [new LinkTerms(validUntil: $until), 0, '15:00', LinkState::Expired];
        yield 'just before its end' => [new LinkTerms(validUntil: $until), 0, '14:59', LinkState::Active];
        yield 'out of visits' => [new LinkTerms(maxVisits: 2), 2, '12:00', LinkState::Exhausted];
        yield 'one visit left' => [new LinkTerms(maxVisits: 2), 1, '12:00', LinkState::Active];
        yield 'disabled before anything else' => [new LinkTerms(enabled: false, validUntil: $until, maxVisits: 1), 1, '16:00', LinkState::Disabled];
        yield 'expired before out of visits' => [new LinkTerms(validUntil: $until, maxVisits: 1), 1, '16:00', LinkState::Expired];
        yield 'out of visits before its start' => [new LinkTerms(validFrom: $from, maxVisits: 1), 1, '12:00', LinkState::Exhausted];
    }

    #[DataProvider('states')]
    public function testWhereALinkStands(LinkTerms $terms, int $visits, string $time, LinkState $state): void
    {
        $link = new ShortLink('abc', 'https://example.com/', $this->at('11:00'), $terms);
        new ReflectionProperty(ShortLink::class, 'visits')->setValue($link, $visits);

        self::assertSame($state, $link->stateAt($this->at($time)));
    }

    public function testTheStatesNoLongerInForceAreGone(): void
    {
        self::assertSame([false, false, true, true, true], array_map(static fn (LinkState $state): bool => $state->isGone(), LinkState::cases()));
    }

    public function testTheRemainingVisitsNeverFallBelowZero(): void
    {
        $link = new ShortLink('abc', 'https://example.com/', $this->at('11:00'), new LinkTerms(maxVisits: 3));
        new ReflectionProperty(ShortLink::class, 'visits')->setValue($link, 5);

        self::assertSame(0, $link->remainingVisits());
        new ReflectionProperty(ShortLink::class, 'visits')->setValue($link, 1);
        self::assertSame(2, $link->remainingVisits());
    }

    public function testALinkIsServedOnTheDomainsOfItsLocale(): void
    {
        $everywhere = new ShortLink('a', 'https://example.com/', $this->at('12:00'));
        $french = new ShortLink('b', 'https://example.com/', $this->at('12:00'), new LinkTerms(locale: 'fr'));

        self::assertTrue($everywhere->isServedIn(null));
        self::assertTrue($everywhere->isServedIn('en'));
        self::assertTrue($french->isServedIn(null));
        self::assertTrue($french->isServedIn('fr'));
        self::assertFalse($french->isServedIn('en'));
    }

    public function testAChangeKeepsTheCodeTheVisitsAndTheCreation(): void
    {
        $link = new ShortLink('abc', 'https://example.com/', $this->at('11:00'));
        new ReflectionProperty(ShortLink::class, 'visits')->setValue($link, 4);
        $link->change('https://example.fr/', new LinkTerms(RedirectStatus::MovedPermanently, label: 'Neuf'), $this->at('12:00'));

        self::assertSame(['abc', 'https://example.fr/', 'Neuf', RedirectStatus::MovedPermanently, 4], [$link->code(), $link->target(), $link->label(), $link->status(), $link->visits()]);
        self::assertEquals($this->at('11:00'), $link->createdAt());
        self::assertEquals($this->at('12:00'), $link->updatedAt());

        $link->enable(false, $this->at('13:00'));
        self::assertFalse($link->isEnabled());
        self::assertEquals($this->at('13:00'), $link->updatedAt());
        $link->enable(true, $this->at('14:00'));
        self::assertTrue($link->isEnabled());
    }

    public function testARefusedChangeLeavesTheLinkAsItWas(): void
    {
        $link = new ShortLink('abc', 'https://example.com/', $this->at('11:00'), new LinkTerms(label: 'Avant'));

        try {
            $link->change('https://example.fr/', new LinkTerms(label: 'Après', maxVisits: 0), $this->at('12:00'));
        } catch (InvalidShortLink) {
        }

        self::assertSame(['https://example.com/', 'Avant'], [$link->target(), $link->label()]);
        self::assertEquals($this->at('11:00'), $link->updatedAt());
    }

    /**
     * @param callable(): ShortLink $build
     */
    private function refusal(callable $build): string
    {
        try {
            $build();
        } catch (InvalidShortLink $invalidShortLink) {
            return $invalidShortLink->getMessage();
        }

        return '';
    }

    private function at(string $time): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-12 '.$time.' UTC');
    }
}
