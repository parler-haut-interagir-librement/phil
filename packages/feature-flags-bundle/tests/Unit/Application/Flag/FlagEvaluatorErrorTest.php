<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Tests\Unit\Application\Flag;

use Error;
use Override;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\FeatureFlagsBundle\Application\Flag\FlagEvaluator;
use Phil\FeatureFlagsBundle\Domain\Flag\Evaluation;
use Phil\FeatureFlagsBundle\Domain\Flag\EvaluationContext;
use Phil\FeatureFlagsBundle\Domain\Flag\EvaluationErrorCode;
use Phil\FeatureFlagsBundle\Domain\Flag\EvaluationReason;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagDefinition;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagName;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagProvider;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagStateSource;
use Phil\FeatureFlagsBundle\Tests\Unit\Fixture\CountingResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use RuntimeException;
use Stringable;
use Throwable;

/**
 * A provider or a state source that fails gives an evaluation that is off, reason `ERROR`, with the OpenFeature
 * error code of the failure; an evaluation never throws an exception.
 */
#[CoversClass(FlagEvaluator::class)]
#[CoversClass(Evaluation::class)]
final class FlagEvaluatorErrorTest extends TestCase
{
    public function testADefinitionThatNoLongerReadsAsOneIsAParseError(): void
    {
        $evaluation = new FlagEvaluator([$this->failing(InvalidValue::because('Not a definition.'))], $this->resolver())
            ->evaluate(FlagName::of('f'));

        self::assertFalse($evaluation->enabled);
        self::assertSame(EvaluationReason::Error, $evaluation->reason);
        self::assertSame(EvaluationErrorCode::ParseError, $evaluation->errorCode);
        self::assertNull($evaluation->variant);
    }

    public function testAProviderThatFailsIsAGeneralError(): void
    {
        $evaluation = new FlagEvaluator([$this->failing(new RuntimeException('Connection refused.'))], $this->resolver())
            ->evaluate(FlagName::of('f'));

        self::assertFalse($evaluation->enabled);
        self::assertSame(EvaluationReason::Error, $evaluation->reason);
        self::assertSame(EvaluationErrorCode::General, $evaluation->errorCode);
    }

    public function testAStateSourceThatFailsIsAGeneralError(): void
    {
        $states = new class implements FlagStateSource {
            #[Override]
            public function isActive(FlagName $flag): ?bool
            {
                throw new RuntimeException('Connection refused.');
            }
        };
        $evaluator = new FlagEvaluator([$this->known()], $this->resolver(), $states);

        self::assertFalse($evaluator->isEnabled(FlagName::of('f')));
        self::assertSame(EvaluationErrorCode::General, $evaluator->evaluate(FlagName::of('f'))->errorCode);
    }

    /**
     * The failing source is not asked again for the rest of the request; after `reset()` it is.
     */
    public function testAFailureIsMemoizedUntilReset(): void
    {
        $provider = $this->failing(new RuntimeException('Connection refused.'));
        $evaluator = new FlagEvaluator([$provider], $this->resolver());

        $evaluator->evaluate(FlagName::of('f'));
        $evaluator->evaluate(FlagName::of('f'));
        self::assertSame(1, $provider->calls);

        $evaluator->reset();
        $evaluator->evaluate(FlagName::of('f'));
        self::assertSame(2, $provider->calls);
    }

    public function testAFailureIsLoggedAsAnErrorWithTheFlagAndTheCode(): void
    {
        $exception = new RuntimeException('Connection refused.');
        $logger = $this->logger();
        new FlagEvaluator([$this->failing($exception)], $this->resolver(), logger: $logger)->evaluate(FlagName::of('f'));

        self::assertCount(1, $logger->records);
        [$level, $message, $context] = $logger->records[0];
        self::assertSame('error', $level);
        self::assertSame('The flag "{flag}" could not be evaluated ({code}): {message}', $message);
        self::assertSame(['flag' => 'f', 'code' => 'GENERAL', 'message' => 'Connection refused.', 'exception' => $exception], $context);
    }

    public function testAParseErrorIsLoggedWithItsCode(): void
    {
        $logger = $this->logger();
        new FlagEvaluator([$this->failing(InvalidValue::because('Not a definition.'))], $this->resolver(), logger: $logger)
            ->evaluate(FlagName::of('f'));

        self::assertSame('PARSE_ERROR', $logger->records[0][2]['code']);
        self::assertSame('Not a definition.', $logger->records[0][2]['message']);
    }

    public function testAnEvaluationThatSucceedsLogsNothing(): void
    {
        $logger = $this->logger();
        new FlagEvaluator([$this->known()], $this->resolver(), logger: $logger)->evaluate(FlagName::of('f'));
        new FlagEvaluator([$this->known()], $this->resolver(), logger: $logger)->evaluate(FlagName::of('missing'));

        self::assertSame([], $logger->records);
    }

    /**
     * An `Error` is a bug, not a failure of the source: it is not turned into an evaluation.
     */
    public function testAnErrorIsNotCaught(): void
    {
        $evaluator = new FlagEvaluator([$this->failing(new Error('A bug.'))], $this->resolver());

        $this->expectException(Error::class);
        $evaluator->evaluate(FlagName::of('f'));
    }

    public function testAFailedEvaluationIsOffWithTheReasonError(): void
    {
        $evaluation = Evaluation::failed(FlagName::of('f'), EvaluationErrorCode::General);

        self::assertSame('f', $evaluation->flag->value);
        self::assertFalse($evaluation->enabled);
        self::assertSame(EvaluationReason::Error, $evaluation->reason);
        self::assertSame(EvaluationErrorCode::General, $evaluation->errorCode);
        self::assertSame(EvaluationErrorCode::FlagNotFound, Evaluation::notFound(FlagName::of('f'))->errorCode);
    }

    /**
     * @return FlagProvider&object{calls: int}
     */
    private function failing(Throwable $failure): FlagProvider
    {
        return new class($failure) implements FlagProvider {
            public int $calls = 0;

            public function __construct(private readonly Throwable $failure)
            {
            }

            #[Override]
            public function find(FlagName $name): ?FlagDefinition
            {
                ++$this->calls;

                throw $this->failure;
            }
        };
    }

    private function known(): FlagProvider
    {
        return new class implements FlagProvider {
            #[Override]
            public function find(FlagName $name): ?FlagDefinition
            {
                return 'f' === $name->value ? new FlagDefinition($name, true) : null;
            }
        };
    }

    /**
     * @return AbstractLogger&object{records: list<array{mixed, string, array<mixed>}>}
     */
    private function logger(): AbstractLogger
    {
        return new class extends AbstractLogger {
            /** @var list<array{mixed, string, array<mixed>}> */
            public array $records = [];

            /**
             * @param array<mixed> $context
             */
            #[Override]
            public function log(mixed $level, string|Stringable $message, array $context = []): void
            {
                $this->records[] = [$level, (string) $message, $context];
            }
        };
    }

    private function resolver(): CountingResolver
    {
        return new CountingResolver(EvaluationContext::anonymous());
    }
}
