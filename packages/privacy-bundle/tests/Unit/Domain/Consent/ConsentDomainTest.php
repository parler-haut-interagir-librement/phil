<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Domain\Consent;

use DateTimeImmutable;
use Phil\PrivacyBundle\Domain\Consent\ConsentCategory;
use Phil\PrivacyBundle\Domain\Consent\ConsentChoice;
use Phil\PrivacyBundle\Domain\Consent\ConsentPolicy;
use Phil\PrivacyBundle\Domain\Consent\ConsentState;
use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConsentCategory::class)]
#[CoversClass(ConsentPolicy::class)]
#[CoversClass(ConsentChoice::class)]
#[CoversClass(ConsentState::class)]
final class ConsentDomainTest extends TestCase
{
    private const string ID = '0123456789abcdef0123456789abcdef';

    public function testACategoryNameHasAShape(): void
    {
        foreach (['', 'Analytics', '1a', 'a b', "a\n", str_repeat('a', 33)] as $name) {
            try {
                new ConsentCategory($name);
                self::fail('Refused: '.$name);
            } catch (InvalidPrivacyValue $invalidPrivacyValue) {
                self::assertStringContainsString(\sprintf('"%s" is not a consent category', $name), $invalidPrivacyValue->getMessage());
            }
        }

        self::assertSame('a_b-1', new ConsentCategory('a_b-1')->name);
        self::assertSame(str_repeat('a', 32), new ConsentCategory(str_repeat('a', 32))->name);
    }

    public function testALabelFallsBackOnTheNameAndADescriptionOnNothing(): void
    {
        $category = new ConsentCategory('analytics', labels: ['fr' => 'Mesure'], descriptions: ['fr' => 'Compte les visites']);

        self::assertSame('Mesure', $category->label('fr'));
        self::assertSame('analytics', $category->label('en'));
        self::assertSame('Compte les visites', $category->description('fr'));
        self::assertSame('', $category->description('en'));
    }

    public function testAVersionHasAShape(): void
    {
        foreach (['', '.1', 'a b', "1\n", str_repeat('1', 33)] as $version) {
            try {
                new ConsentPolicy($version, []);
                self::fail('Refused: '.$version);
            } catch (InvalidPrivacyValue $invalidPrivacyValue) {
                self::assertStringContainsString(\sprintf('"%s" is not a version', $version), $invalidPrivacyValue->getMessage());
            }
        }

        self::assertSame('2026-11.1_a', new ConsentPolicy('2026-11.1_a', [])->version);
        self::assertSame(str_repeat('1', 32), new ConsentPolicy(str_repeat('1', 32), [])->version);
    }

    public function testACategoryDeclaredTwiceIsRefused(): void
    {
        $this->expectExceptionMessageIsOrContains('The consent category "a" is declared twice.');

        new ConsentPolicy('1', [new ConsentCategory('a'), new ConsentCategory('a')]);
    }

    public function testThePolicyAsksAboutWhatIsNotNecessaryInOrder(): void
    {
        $policy = $this->policy();

        self::assertSame(['analytics', 'marketing'], $policy->optional());
        self::assertTrue($policy->isNecessary('session'));
        self::assertFalse($policy->isNecessary('analytics'));
        self::assertFalse($policy->isNecessary('unknown'));
        self::assertSame(['analytics', 'marketing'], $policy->retain(['marketing', 'session', 'unknown', 'analytics']));
        self::assertSame(['marketing'], $policy->retain(['marketing']));
    }

    public function testAChoiceHasAShape(): void
    {
        $now = new DateTimeImmutable('2026-01-01T00:00:00Z');

        foreach ([
            'identifier' => [str_repeat('A', 32), '1', []],
            'version' => [self::ID, 'a b', []],
            'category' => [self::ID, '1', ['a b']],
        ] as $what => [$id, $version, $granted]) {
            try {
                new ConsentChoice($id, $version, $granted, $now);
                self::fail('Refused: '.$what);
            } catch (InvalidPrivacyValue $invalidPrivacyValue) {
                self::assertStringContainsString('consent', $invalidPrivacyValue->getMessage());
            }
        }
    }

    public function testAChoiceGrantsWhatItLists(): void
    {
        $choice = $this->choice(['analytics']);

        self::assertTrue($choice->grants('analytics'));
        self::assertFalse($choice->grants('marketing'));
        self::assertFalse($choice->globalPrivacyControl);
        self::assertSame(1767225600, $choice->decidedAtTimestamp());
        self::assertSame('2026-01-01T00:00:00+00:00', ConsentChoice::dateOf(1767225600)->format('c'));
        self::assertSame('UTC', ConsentChoice::dateOf(1767225600)->getTimezone()->getName());
    }

    public function testWithoutAChoiceOnlyWhatIsNecessaryIsGrantedAndThePersonIsAsked(): void
    {
        $state = new ConsentState($this->policy(), null, false);

        self::assertTrue($state->isGranted('session'));
        self::assertFalse($state->isGranted('analytics'));
        self::assertTrue($state->isPending());
        self::assertSame([], $state->granted());
        self::assertNull($state->choice);
    }

    public function testAChoiceCountsOnItsOwnVersion(): void
    {
        $state = new ConsentState($this->policy(), $this->choice(['analytics']), false);

        self::assertTrue($state->isGranted('analytics'));
        self::assertFalse($state->isGranted('marketing'));
        self::assertFalse($state->isPending());
        self::assertSame(['analytics'], $state->granted());
        self::assertSame(['marketing'], new ConsentState($this->policy(), $this->choice(['marketing']), false)->granted());
    }

    public function testAChoiceOnAnotherVersionIsAsGoodAsNone(): void
    {
        $state = new ConsentState($this->policy('2'), $this->choice(['analytics']), false);

        self::assertFalse($state->isGranted('analytics'));
        self::assertTrue($state->isPending());
        self::assertNull($state->choice);
    }

    public function testTheGlobalPrivacyControlWinsAndAsksNothing(): void
    {
        $state = new ConsentState($this->policy(), $this->choice(['analytics']), true);

        self::assertFalse($state->isGranted('analytics'));
        self::assertTrue($state->isGranted('session'));
        self::assertFalse($state->isPending());
        self::assertFalse(new ConsentState($this->policy(), null, true)->isPending());
    }

    public function testNobodyIsAskedWhenNothingIsOptional(): void
    {
        self::assertFalse(new ConsentState(new ConsentPolicy('1', [new ConsentCategory('session', true)]), null, false)->isPending());
    }

    private function policy(string $version = '1'): ConsentPolicy
    {
        return new ConsentPolicy($version, [new ConsentCategory('session', true), new ConsentCategory('analytics'), new ConsentCategory('marketing')]);
    }

    /**
     * @param list<string> $granted
     */
    private function choice(array $granted): ConsentChoice
    {
        return new ConsentChoice(self::ID, '1', $granted, new DateTimeImmutable('2026-01-01T00:00:00Z'));
    }
}
