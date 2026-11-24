<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use LogicException;
use Phil\TermsBundle\Domain\AcceptanceChannel;
use Phil\TermsBundle\Domain\Exception\InvalidTerms;
use Phil\TermsBundle\Domain\TextFormat;
use Phil\TermsBundle\Domain\ValueObject\Acceptance;
use Phil\TermsBundle\Domain\ValueObject\Policy;
use Phil\TermsBundle\Domain\ValueObject\PolicyVersion;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(PolicyVersion::class)]
#[CoversClass(Acceptance::class)]
#[CoversClass(Policy::class)]
#[CoversClass(InvalidTerms::class)]
final class ModelsTest extends TestCase
{
    public function testAVersionKeepsWhatItWasPublishedWith(): void
    {
        $published = new DateTimeImmutable('2026-05-01 10:00');
        $effective = new DateTimeImmutable('2026-06-01 00:00');
        $version = new PolicyVersion('terms', 2, ['en' => 'Hello', 'fr' => 'Bonjour'], TextFormat::Text, $published, $effective, false);

        self::assertSame('terms', $version->policy);
        self::assertSame(2, $version->number);
        self::assertSame(['en' => 'Hello', 'fr' => 'Bonjour'], $version->texts);
        self::assertSame(['en', 'fr'], $version->locales());
        self::assertSame($published, $version->publishedAt);
        self::assertSame($effective, $version->effectiveAt);
        self::assertFalse($version->material);
        self::assertSame(TextFormat::Text, $version->format);
    }

    public function testTheTextOfALocaleFallsBack(): void
    {
        $version = $this->version(texts: ['fr' => 'Bonjour', 'en' => 'Hello']);

        self::assertSame('Bonjour', $version->text('fr', 'en'));
        self::assertSame('Hello', $version->text('de', 'en'));
        self::assertSame('Bonjour', $version->text('de', 'it'));
        self::assertSame('fr', $version->textLocale('fr', 'en'));
        self::assertSame('en', $version->textLocale('de', 'en'));
        self::assertSame('fr', $version->textLocale('de', 'it'));
        self::assertSame('en', $version->textLocale('en', 'fr'));
    }

    public function testAVersionIsInForceFromItsMoment(): void
    {
        $version = $this->version(effective: '2026-06-01 00:00');

        self::assertFalse($version->isInForceAt(new DateTimeImmutable('2026-05-31 23:59:59')));
        self::assertTrue($version->isInForceAt(new DateTimeImmutable('2026-06-01 00:00')));
        self::assertTrue($version->isInForceAt(new DateTimeImmutable('2026-06-02')));
    }

    public function testTheNumberGoesFrom1To999999(): void
    {
        self::assertSame(1, $this->version(number: 1)->number);
        self::assertSame(999999, $this->version(number: 999999)->number);

        foreach ([0, 1000000] as $number) {
            try {
                $this->version(number: $number);
                self::fail('Accepted.');
            } catch (InvalidTerms $invalidTerms) {
                self::assertSame('version_number', $invalidTerms->reason);
            }
        }
    }

    public function testTheFirstVersionIsMaterial(): void
    {
        $this->expectExceptionObject(InvalidTerms::because('first_version_material', 'The first version of a policy is material: there is nothing before it to have accepted.'));

        $this->version(number: 1, material: false);
    }

    public function testAVersionTakesEffectWhenPublishedOrLater(): void
    {
        self::assertTrue($this->version(effective: '2026-05-01 10:00')->isInForceAt(new DateTimeImmutable('2026-05-01 10:00')));

        $this->expectExceptionObject(InvalidTerms::because('effective_before_publication', 'A version takes effect when it is published or later.'));

        $this->version(effective: '2026-05-01 09:59:59');
    }

    public function testThePolicyKeyIsChecked(): void
    {
        $this->expectException(InvalidTerms::class);

        new PolicyVersion('Terms', 1, ['en' => 'x'], TextFormat::Text, new DateTimeImmutable(), new DateTimeImmutable(), true);
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string, array<string, string>}>
     */
    public static function badTexts(): iterable
    {
        yield 'none' => [[], 'no_text', []];
        yield 'a bad locale' => [['EN' => 'x'], 'locale', ['%locale%' => 'EN']];
        yield 'a number as locale' => [[0 => 'x'], 'locale', ['%locale%' => '0']];
        yield 'empty' => [['en' => ''], 'empty_text', ['%locale%' => 'en']];
        yield 'blank' => [['en' => " \n "], 'empty_text', ['%locale%' => 'en']];
        yield 'not a string' => [['en' => 12], 'empty_text', ['%locale%' => 'en']];
        yield 'too long' => [['en' => str_repeat('a', 262145)], 'text_too_long', ['%locale%' => 'en']];
        yield 'not UTF-8' => [['en' => "caf\xE9"], 'text_too_long', ['%locale%' => 'en']];
        yield 'too many locales' => [array_fill_keys(self::locales(33), 'x'), 'too_many_locales', []];
    }

    /**
     * @param array<array-key, mixed> $texts
     * @param array<string, string>   $parameters
     */
    #[DataProvider('badTexts')]
    public function testABadTextIsRefused(array $texts, string $reason, array $parameters): void
    {
        try {
            $this->version(texts: $texts);
            self::fail('Accepted.');
        } catch (InvalidTerms $invalidTerms) {
            self::assertSame($reason, $invalidTerms->reason);
            self::assertSame($parameters, $invalidTerms->parameters);
        }
    }

    public function testTheBoundsOfTheTexts(): void
    {
        $longest = str_repeat('a', 262144);
        $locales = self::locales(32);

        self::assertSame($longest, $this->version(texts: ['en' => $longest])->text('en', 'en'));
        self::assertCount(32, $this->version(texts: array_fill_keys($locales, 'x'))->locales());
        self::assertSame(['en' => ' x '], $this->version(texts: ['en' => ' x '])->texts);
    }

    public function testAnAcceptanceKeepsWhoWhatWhenAndWhere(): void
    {
        $at = new DateTimeImmutable('2026-05-01 10:00');
        $acceptance = new Acceptance('42', 'terms', 3, $at, AcceptanceChannel::Signup, '192.0.2.0');

        self::assertSame('42', $acceptance->member);
        self::assertSame('terms', $acceptance->policy);
        self::assertSame(3, $acceptance->version);
        self::assertSame($at, $acceptance->acceptedAt);
        self::assertSame(AcceptanceChannel::Signup, $acceptance->channel);
        self::assertSame('192.0.2.0', $acceptance->network);
        self::assertNull(new Acceptance('42', 'terms', 999999, $at, AcceptanceChannel::Form)->network);
        self::assertSame(str_repeat('a', 64), new Acceptance('42', 'terms', 1, $at, AcceptanceChannel::Form, str_repeat('a', 64))->network);
        self::assertNull($acceptance->locale);
        self::assertNull($acceptance->textSha256);

        $proved = new Acceptance('42', 'terms', 3, $at, AcceptanceChannel::Form, null, 'fr-CA', 'a3f1000000000000000000000000000000000000000000000000000000000000');
        self::assertSame('fr-CA', $proved->locale);
        self::assertSame('a3f1000000000000000000000000000000000000000000000000000000000000', $proved->textSha256);
        self::assertSame(64, Acceptance::SHA256_LENGTH);
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2: int, 3: string|null, 4: string, 5?: string|null, 6?: string|null}>
     */
    public static function badAcceptances(): iterable
    {
        yield 'a bad member' => ['a b', 'terms', 1, null, 'member'];
        yield 'a bad policy' => ['42', 'Terms', 1, null, 'policy_key'];
        yield 'version 0' => ['42', 'terms', 0, null, 'version_number'];
        yield 'version 1000000' => ['42', 'terms', 1000000, null, 'version_number'];
        yield 'an empty network' => ['42', 'terms', 1, '', 'network'];
        yield 'a long network' => ['42', 'terms', 1, str_repeat('a', 65), 'network'];
        yield 'a bad locale' => ['42', 'terms', 1, null, 'locale', 'French'];
        yield 'an empty SHA-256' => ['42', 'terms', 1, null, 'text_sha256', 'en', ''];
        yield 'a short SHA-256' => ['42', 'terms', 1, null, 'text_sha256', 'en', str_repeat('a', 63)];
        yield 'a long SHA-256' => ['42', 'terms', 1, null, 'text_sha256', 'en', str_repeat('a', 65)];
        yield 'an uppercase SHA-256' => ['42', 'terms', 1, null, 'text_sha256', 'en', str_repeat('A', 64)];
        yield 'a SHA-256 with a line break' => ['42', 'terms', 1, null, 'text_sha256', 'en', str_repeat('a', 64)."\n"];
        yield 'a SHA-256 with a prefix' => ['42', 'terms', 1, null, 'text_sha256', 'en', 'x'.str_repeat('a', 64)];
    }

    #[DataProvider('badAcceptances')]
    public function testABadAcceptanceIsRefused(string $member, string $policy, int $version, ?string $network, string $reason, ?string $locale = null, ?string $sha256 = null): void
    {
        try {
            new Acceptance($member, $policy, $version, new DateTimeImmutable(), AcceptanceChannel::Api, $network, $locale, $sha256);
            self::fail('Accepted.');
        } catch (InvalidTerms $invalidTerms) {
            self::assertSame($reason, $invalidTerms->reason);
        }
    }

    public function testAPolicyIsRequiredAndNeverRenewedByDefault(): void
    {
        $policy = new Policy('terms');

        self::assertSame('terms', $policy->key);
        self::assertTrue($policy->required);
        self::assertNull($policy->renewAfterDays);
        self::assertNull($policy->acceptedSince(new DateTimeImmutable()));
    }

    public function testARenewedPolicyCountsTheAcceptancesOfItsDays(): void
    {
        $policy = new Policy('privacy', false, 30);

        self::assertFalse($policy->required);
        self::assertEquals(new DateTimeImmutable('2026-04-01 10:00'), $policy->acceptedSince(new DateTimeImmutable('2026-05-01 10:00')));
        self::assertSame(1, new Policy('a', renewAfterDays: 1)->renewAfterDays);
        self::assertSame(3650, new Policy('a', renewAfterDays: 3650)->renewAfterDays);
    }

    public function testTheRenewalAndTheKeyOfAPolicyAreChecked(): void
    {
        foreach ([0, 3651] as $days) {
            try {
                new Policy('terms', renewAfterDays: $days);
                self::fail('Accepted.');
            } catch (InvalidTerms $invalidTerms) {
                self::assertSame('renewal', $invalidTerms->reason);
            }
        }

        $this->expectException(InvalidTerms::class);

        new Policy('Terms');
    }

    public function testAnExceptionKeepsWhatCausedIt(): void
    {
        $cause = new LogicException('cause');
        $exception = new InvalidTerms('message', 'reason', ['%a%' => 'b'], $cause);

        self::assertSame($cause, $exception->getPrevious());
        self::assertSame('message', $exception->getMessage());
        self::assertSame('reason', $exception->reason);
        self::assertSame(['%a%' => 'b'], $exception->parameters);
        self::assertSame(0, $exception->getCode());
    }

    /**
     * @return list<string>
     */
    private static function locales(int $count): array
    {
        $locales = [];

        foreach (['a', 'b'] as $second) {
            foreach (range('a', 'z') as $third) {
                $locales[] = 'a'.$second.$third;
            }
        }

        return \array_slice($locales, 0, $count);
    }

    /**
     * @param array<array-key, mixed> $texts
     */
    private function version(int $number = 2, array $texts = ['en' => 'Hello'], string $effective = '2026-05-01 10:00', bool $material = true): PolicyVersion
    {
        return new PolicyVersion('terms', $number, $texts, TextFormat::Text, new DateTimeImmutable('2026-05-01 10:00'), new DateTimeImmutable($effective), $material);
    }
}
