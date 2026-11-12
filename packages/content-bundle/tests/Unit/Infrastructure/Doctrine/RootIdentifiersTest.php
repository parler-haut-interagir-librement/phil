<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Phil\ContentBundle\Infrastructure\Doctrine\RootIdentifiers;
use Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\EntityManagerFactory;
use Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\Label;
use Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\LabelCode;
use Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\Memo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(RootIdentifiers::class)]
final class RootIdentifiersTest extends TestCase
{
    /**
     * @return iterable<string, array{string, ArrayParameterType}>
     */
    public static function bindings(): iterable
    {
        yield 'an integer' => [Types::INTEGER, ArrayParameterType::INTEGER];
        yield 'an ASCII string' => [Types::ASCII_STRING, ArrayParameterType::ASCII];
        yield 'binary bytes' => [Types::BINARY, ArrayParameterType::BINARY];
        yield 'a large object' => [Types::BLOB, ArrayParameterType::BINARY];
        yield 'a string' => [Types::STRING, ArrayParameterType::STRING];
    }

    /**
     * A string the identifier's type refuses is no identifier, whether the type throws a `DomainError` or an
     * `InvalidArgumentException`.
     */
    public function testAStringTheTypeRefusesIsNoIdentifier(): void
    {
        $identifiers = new RootIdentifiers(EntityManagerFactory::create());

        self::assertNull($identifiers->fromString(Label::class, 'not a code'));
        self::assertNull($identifiers->fromString(Memo::class, 'not-a-uuid'));
        self::assertInstanceOf(LabelCode::class, $identifiers->fromString(Label::class, 'LBL'));
    }

    /**
     * Identifiers are bound in a list whose array type follows the binding type of the identifier's DBAL type.
     */
    #[DataProvider('bindings')]
    public function testIdentifiersAreBoundByTheirType(string $type, ArrayParameterType $bound): void
    {
        $metadata = new ClassMetadata(stdClass::class);
        $metadata->mapField(['fieldName' => 'id', 'type' => $type, 'id' => true]);
        $metadata->setIdentifier(['id']);

        $entityManager = self::createStub(EntityManagerInterface::class);
        $entityManager->method('getClassMetadata')->willReturn($metadata);
        $entityManager->method('getConnection')->willReturn(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]));

        self::assertSame($bound, new RootIdentifiers($entityManager)->bindable(stdClass::class, [])[1]);
    }
}
