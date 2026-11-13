<?php

declare(strict_types=1);

namespace Phil\Automations\Tests\Unit\Expression;

use ErrorException;
use Phil\Automations\Exception\ActionFailed;
use Phil\Automations\Exception\InvalidDefinition;
use Phil\Automations\Expression\ExpressionConditions;
use Phil\Automations\Expression\SafeExpressionLanguage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

#[CoversClass(ExpressionConditions::class)]
#[CoversClass(SafeExpressionLanguage::class)]
final class ExpressionConditionsTest extends TestCase
{
    private const array VARIABLES = ['payload' => ['plan' => 'pro', 'count' => 3], 'outputs' => ['add' => ['created' => true]], 'subject' => '42'];

    #[DataProvider('conditions')]
    public function testItEvaluatesOverThePayloadTheOutputsAndTheSubject(string $source, bool $holds): void
    {
        $conditions = new ExpressionConditions();
        $conditions->check($source);

        self::assertSame($holds, $conditions->evaluate($source, self::VARIABLES));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function conditions(): iterable
    {
        yield 'payload' => ["payload['plan'] == 'pro'", true];
        yield 'arithmetic' => ["payload['count'] + 1 > 4", false];
        yield 'outputs' => ["outputs['add']['created']", true];
        yield 'missing with a default' => ["payload['other'] ?? false", false];
        yield 'subject' => ["subject in ['41', '42']", true];
        yield 'starts with' => ["payload['plan'] starts with 'p'", true];
    }

    public function testItKnowsNoFunction(): void
    {
        $this->expectException(InvalidDefinition::class);
        $this->expectExceptionMessageIsOrContains('The condition "constant(\'PHP_VERSION\') != \'\'" does not read: The function "constant" does not exist');

        new ExpressionConditions()->check("constant('PHP_VERSION') != ''");
    }

    public function testItKnowsNoOtherVariable(): void
    {
        $this->expectException(InvalidDefinition::class);
        $this->expectExceptionMessageIsOrContains('Variable "kernel" is not valid');

        new ExpressionConditions()->check('kernel');
    }

    public function testAFailureWhileEvaluatingIsPermanent(): void
    {
        try {
            new ExpressionConditions()->evaluate("payload['missing'] == 1", self::VARIABLES);
            self::fail('Expected a failure.');
        } catch (ActionFailed $actionFailed) {
            self::assertFalse($actionFailed->retryable);
            self::assertSame('The condition "payload[\'missing\'] == 1" failed: Undefined array key "missing"', $actionFailed->getMessage());
            self::assertNotNull($actionFailed->getPrevious());
        }
    }

    public function testAValueThatIsNotABooleanIsRefused(): void
    {
        $this->expectException(ActionFailed::class);
        $this->expectExceptionMessageIsOrContains('The condition "payload[\'plan\']" gives a string, not true or false.');

        new ExpressionConditions()->evaluate("payload['plan']", self::VARIABLES);
    }

    public function testItSaysItsLanguage(): void
    {
        self::assertSame('expression', new ExpressionConditions()->language());
    }

    public function testAWarningWhileEvaluatingBecomesAPermanentFailureThatKeepsTheError(): void
    {
        try {
            new ExpressionConditions()->evaluate("payload['missing'] == 1", self::VARIABLES);
            self::fail('A key that is missing fails the condition.');
        } catch (ActionFailed $actionFailed) {
            self::assertFalse($actionFailed->retryable);
            $error = $actionFailed->getPrevious();
            self::assertInstanceOf(ErrorException::class, $error);
            self::assertSame(0, $error->getCode());
            self::assertSame(\E_WARNING, $error->getSeverity());
        }
    }

    public function testTheParsedConditionsAreKeptInABoundedCacheThatNeverExpires(): void
    {
        $language = new ReflectionProperty(ExpressionConditions::class, 'language')->getValue(new ExpressionConditions());
        self::assertInstanceOf(ExpressionLanguage::class, $language);
        $cache = new ReflectionProperty(ExpressionLanguage::class, 'cache')->getValue($language);
        self::assertInstanceOf(ArrayAdapter::class, $cache);

        self::assertSame(0, new ReflectionProperty(ArrayAdapter::class, 'defaultLifetime')->getValue($cache));
        self::assertFalse(new ReflectionProperty(ArrayAdapter::class, 'deepClone')->getValue($cache));
        self::assertEqualsWithDelta(0.0, new ReflectionProperty(ArrayAdapter::class, 'maxLifetime')->getValue($cache), 0.0);
        self::assertSame(256, new ReflectionProperty(ArrayAdapter::class, 'maxItems')->getValue($cache));
    }

    public function testTheLanguageGivenIsTheOneUsed(): void
    {
        $language = new class extends ExpressionLanguage {
            public int $evaluations = 0;

            /**
             * @param array<array-key, mixed> $values
             */
            public function evaluate(Expression|string $expression, array $values = []): mixed
            {
                ++$this->evaluations;

                return true;
            }
        };

        self::assertTrue(new ExpressionConditions($language)->evaluate('anything', ['payload' => [], 'outputs' => [], 'subject' => null]));
        self::assertSame(1, $language->evaluations);
    }
}
