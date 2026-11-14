<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\OnboardingBundle\Domain\Model\Progress;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The columns the application's migrations create, as the mapping declares them on the entity.
 */
#[CoversClass(Progress::class)]
final class MappingTest extends TestCase
{
    public function testTheTable(): void
    {
        self::assertSame('ada', Progress::start('ada', 'author', new DateTimeImmutable())->user);

        $metadata = DoctrineRepositoryTest::entityManager()->getClassMetadata(Progress::class);
        $columns = [];

        foreach ($metadata->getFieldNames() as $field) {
            $mapping = $metadata->getFieldMapping($field);
            $columns[$field] = [$mapping->columnName, $mapping->type, $mapping->length, $mapping->nullable ?? false];
        }

        self::assertSame('phil_onboarding_progress', $metadata->getTableName());
        self::assertSame(['user', 'flow'], $metadata->getIdentifierFieldNames());
        self::assertSame([
            'steps' => ['steps', 'json', null, false],
            'updatedAt' => ['updated_at', 'datetime_immutable', null, false],
            'finishedAt' => ['finished_at', 'datetime_immutable', null, true],
            'dismissedAt' => ['dismissed_at', 'datetime_immutable', null, true],
            'user' => ['user_identifier', 'string', 320, false],
            'flow' => ['flow', 'string', 64, false],
            'startedAt' => ['started_at', 'datetime_immutable', null, false],
        ], $columns);
    }
}
