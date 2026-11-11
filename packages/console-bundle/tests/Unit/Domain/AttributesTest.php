<?php

declare(strict_types=1);

namespace Phil\ConsoleBundle\Tests\Unit\Domain;

use Attribute;
use Phil\ConsoleBundle\Domain\Attribute\ConfirmEnvironment;
use Phil\ConsoleBundle\Domain\Attribute\EnvironmentNames;
use Phil\ConsoleBundle\Domain\Attribute\ForbidEnvironment;
use Phil\ConsoleBundle\Domain\Exception\InvalidGuard;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[CoversClass(ForbidEnvironment::class)]
#[CoversClass(ConfirmEnvironment::class)]
#[CoversClass(EnvironmentNames::class)]
#[CoversClass(InvalidGuard::class)]
final class AttributesTest extends TestCase
{
    public function testTheEnvironmentsAreKeptOnceInOrder(): void
    {
        self::assertSame(['prod', 'staging'], new ForbidEnvironment('prod', 'staging', 'prod')->environments);
        self::assertSame(['prod'], new ConfirmEnvironment('prod', 'prod')->environments);
        self::assertSame([], new ConfirmEnvironment()->environments);
    }

    public function testAForbiddanceNeedsAnEnvironment(): void
    {
        $this->expectExceptionObject(InvalidGuard::withoutEnvironment(ForbidEnvironment::class));

        new ForbidEnvironment();
    }

    #[DataProvider('invalidNames')]
    public function testANameIsNotEmptyAndHoldsNoSpace(string $name): void
    {
        $this->expectException(InvalidGuard::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The attribute "%s" names the environment "%s": a name is not empty and holds no space.', ConfirmEnvironment::class, $name));

        new ConfirmEnvironment('prod', $name);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidNames(): iterable
    {
        yield 'empty' => [''];
        yield 'space' => ['pr od'];
        yield 'leading space' => [' prod'];
        yield 'trailing newline' => ["prod\n"];
    }

    public function testTheMessages(): void
    {
        self::assertSame('The attribute "'.ForbidEnvironment::class.'" needs at least one environment.', InvalidGuard::withoutEnvironment(ForbidEnvironment::class)->getMessage());
    }

    public function testTheTargets(): void
    {
        foreach ([ForbidEnvironment::class, ConfirmEnvironment::class] as $class) {
            $attribute = new ReflectionClass($class)->getAttributes(Attribute::class)[0]->newInstance();
            self::assertSame(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE, $attribute->flags);
        }
    }
}
