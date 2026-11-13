<?php

declare(strict_types=1);

namespace Phil\CheckinBundle\Tests\Unit\Rules;

use DateTimeImmutable;
use DateTimeZone;
use ErrorException;
use LogicException;
use Override;
use Phil\Checkin\CheckpointId;
use Phil\Checkin\Credential;
use Phil\CheckinBundle\Application\Contract\CredentialAttributes;
use Phil\CheckinBundle\Infrastructure\Rules\CheckpointRules;
use Phil\CheckinBundle\Infrastructure\Rules\ConfiguredAdmissionRules;
use Phil\CheckinBundle\Infrastructure\Rules\NoCredentialAttributes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Throwable;

#[CoversClass(CheckpointRules::class)]
#[CoversClass(ConfiguredAdmissionRules::class)]
#[CoversClass(NoCredentialAttributes::class)]
final class CheckpointRulesTest extends TestCase
{
    public function testACheckpointWithoutARuleAdmitsEveryCredential(): void
    {
        $rules = $this->rules([]);

        self::assertFalse($rules->has(CheckpointId::of('hall')));
        self::assertTrue($rules->admits(CheckpointId::of('hall'), Credential::of('T-1')));
        self::assertSame([], new NoCredentialAttributes()->of(Credential::of('T-1')));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function expressions(): iterable
    {
        yield 'the credential' => ["credential.id == 'T-1' and credential.label == 'Élise' and credential.maxEntries == 2", true];
        yield 'an attribute' => ["attributes['age'] >= 18", true];
        yield 'an attribute too low' => ["attributes['age'] >= 21", false];
        yield 'a list attribute' => ["'vip' in attributes['tags']", true];
        yield 'the checkpoint' => ["checkpoint == 'bar'", true];
        yield 'the hour in the time zone' => ["now.format('H') == '17'", true];
        yield 'the weekday' => ["now.format('N') == '7'", true];
        yield 'the opening of the credential' => ['credential.validFrom < now', true];
        yield 'a result that is not true itself' => ["attributes['age']", false];
        yield 'a missing attribute' => ["attributes['missing'] == 1", false];
        yield 'a missing attribute compared with null' => ["attributes['missing'] == null", false];
        yield 'a method that fails' => ['credential.nothing()', false];
    }

    #[DataProvider('expressions')]
    public function testARuleReadsTheCredentialItsAttributesTheCheckpointAndTheTime(string $rule, bool $admits): void
    {
        $rules = $this->rules(['bar' => $rule], new DateTimeZone('America/Toronto'));
        $credential = Credential::of('T-1', 'Élise', validFrom: new DateTimeImmutable('2026-11-01 00:00 UTC'), maxEntries: 2);

        self::assertTrue($rules->has(CheckpointId::of('bar')));
        self::assertSame($admits, $rules->admits(CheckpointId::of('bar'), $credential));
        self::assertTrue($rules->admits(CheckpointId::of('hall'), $credential), 'another checkpoint keeps no rule');
    }

    public function testTheErrorHandlerOfTheApplicationIsGivenBack(): void
    {
        $handler = static fn (): bool => false;
        set_error_handler($handler);

        try {
            $this->rules(['bar' => "attributes['missing'] == null"])->admits(CheckpointId::of('bar'), Credential::of('T-1'));
            self::assertSame($handler, set_error_handler(static fn (): bool => false));
            restore_error_handler();
        } finally {
            restore_error_handler();
        }
    }

    public function testTheEvaluatorGivenIsTheOneThatEvaluatesAndLints(): void
    {
        $language = new ExpressionLanguage();
        $language->register('always', static fn (): string => 'true', static fn (): bool => true);

        $rules = new CheckpointRules(new ConfiguredAdmissionRules(['bar' => 'always()']), new MockClock(), language: $language);

        self::assertTrue($rules->admits(CheckpointId::of('bar'), Credential::of('T-1')));
        self::assertNull(CheckpointRules::lint('always()', $language));
        self::assertNotNull(CheckpointRules::lint('always()'));
    }

    public function testAWarningDuringTheEvaluationBecomesAnErrorException(): void
    {
        $seen = null;
        $language = new class(static function (Throwable $exception) use (&$seen): void {
            $seen = $exception;
        }) extends ExpressionLanguage {
            /** @param callable(Throwable): void $seen */
            public function __construct(private $seen)
            {
                parent::__construct();
            }

            /** @param array<mixed> $values */
            #[Override]
            public function evaluate(Expression|string $expression, array $values = []): mixed
            {
                try {
                    trigger_error('missing attribute', \E_USER_WARNING);
                } catch (Throwable $throwable) {
                    ($this->seen)($throwable);
                }

                return true;
            }
        };
        $rules = new CheckpointRules(new ConfiguredAdmissionRules(['bar' => 'true']), new MockClock(), language: $language);

        self::assertTrue($rules->admits(CheckpointId::of('bar'), Credential::of('T-1')));
        self::assertInstanceOf(ErrorException::class, $seen);
        self::assertSame('missing attribute', $seen->getMessage());
        self::assertSame(0, $seen->getCode());
        self::assertSame(\E_USER_WARNING, $seen->getSeverity());
    }

    public function testTheTimeStaysTheClocksWithoutATimeZone(): void
    {
        self::assertTrue($this->rules(['bar' => "now.format('H') == '22'"])->admits(CheckpointId::of('bar'), Credential::of('T-1')));
    }

    public function testAttributesThatFailRefuseTheCredential(): void
    {
        $failing = new class implements CredentialAttributes {
            #[Override]
            public function of(Credential $credential): array
            {
                throw new LogicException('unavailable');
            }
        };
        $rules = new CheckpointRules(new ConfiguredAdmissionRules(['bar' => 'true']), new MockClock(), $failing);

        self::assertFalse($rules->admits(CheckpointId::of('bar'), Credential::of('T-1')));
    }

    public function testARuleTooLongIsRefusedWithoutBeingEvaluated(): void
    {
        $longest = 'true'.str_repeat(' ', CheckpointRules::MAX_LENGTH - 4);

        self::assertTrue($this->rules(['bar' => $longest])->admits(CheckpointId::of('bar'), Credential::of('T-1')));
        self::assertFalse($this->rules(['bar' => $longest.' '])->admits(CheckpointId::of('bar'), Credential::of('T-1')));
    }

    public function testARuleIsLinted(): void
    {
        self::assertNull(CheckpointRules::lint("credential.id != '' and attributes['a'] and checkpoint and now"));
        self::assertNull(CheckpointRules::lint('true'.str_repeat(' ', CheckpointRules::MAX_LENGTH - 4)));
        self::assertSame('A rule has at most 1000 bytes.', CheckpointRules::lint('true'.str_repeat(' ', CheckpointRules::MAX_LENGTH - 3)));
        self::assertStringStartsWith('Variable "user" is not valid', (string) CheckpointRules::lint('user == 1'));
        self::assertStringStartsWith('Unexpected token "end of expression"', (string) CheckpointRules::lint('1 +'));
        self::assertSame(['credential', 'attributes', 'checkpoint', 'now'], CheckpointRules::NAMES);
    }

    /**
     * @param array<string, string> $rules
     */
    private function rules(array $rules, ?DateTimeZone $timezone = null): CheckpointRules
    {
        $attributes = new class implements CredentialAttributes {
            #[Override]
            public function of(Credential $credential): array
            {
                return ['age' => 19, 'tags' => ['vip', 'crew']];
            }
        };

        return new CheckpointRules(new ConfiguredAdmissionRules($rules), new MockClock('2026-11-08 22:30:00 UTC'), $attributes, $timezone);
    }
}
