<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\SettingsBundle\Domain\Contract\SettingMigrationStore;
use Phil\SettingsBundle\Domain\Contract\SettingRepository;
use Phil\SettingsBundle\Domain\Migration\StoredValue;
use Phil\SettingsBundle\Domain\ValueObject\Subject;
use PHPUnit\Framework\TestCase;

/**
 * What every repository does, whatever keeps the values.
 */
abstract class RepositoryTestCase extends TestCase
{
    public function testASubjectWithoutValuesHasNone(): void
    {
        self::assertSame([], $this->repository()->values(Subject::user('ada')));
        self::assertSame([], $this->repository()->erase(Subject::user('ada')));
    }

    public function testValuesAreWrittenReplacedAndRemovedBySubject(): void
    {
        $repository = $this->repository();
        $at = new DateTimeImmutable('2026-11-08 12:00:00 UTC');
        $repository->save(Subject::user('ada'), ['a.b' => 'true', 'a.c' => '"x"', 'a.e' => '"kept"'], [], $at);
        $repository->save(Subject::user('bob'), ['a.b' => 'false'], [], $at);
        $repository->save(Subject::site(), ['a.b' => 'true'], [], $at);

        self::assertEquals(['a.b' => 'true', 'a.c' => '"x"', 'a.e' => '"kept"'], $repository->values(Subject::user('ada')));

        $repository->save(Subject::user('ada'), ['a.c' => '"y"', 'a.d' => '3'], ['a.b'], $at);
        $repository->save(Subject::user('ada'), ['a.c' => '"z"', 'a.d' => '4'], [], $at);

        $values = $repository->values(Subject::user('ada'));
        ksort($values);

        self::assertSame(['a.c' => '"z"', 'a.d' => '4', 'a.e' => '"kept"'], $values);
        self::assertSame(['a.b' => 'false'], $repository->values(Subject::user('bob')));
        self::assertSame(['a.b' => 'true'], $repository->values(Subject::site()));
    }

    public function testASubjectIsErased(): void
    {
        $repository = $this->repository();
        $at = new DateTimeImmutable('2026-11-08 12:00:00 UTC');
        $repository->save(Subject::user('ada'), ['a.c' => '1', 'a.b' => '2'], [], $at);
        $repository->save(Subject::user('bob'), ['a.b' => '3'], [], $at);
        $repository->save(Subject::user('site'), ['a.b' => '4'], [], $at);
        $repository->save(Subject::site(), ['a.b' => '5'], [], $at);

        self::assertSame(['a.b', 'a.c'], $repository->erase(Subject::user('ada')));
        self::assertSame(['a.b'], $repository->erase(Subject::user('site')));
        self::assertSame(['a.b' => '5'], $repository->values(Subject::site()), 'a user named site is not the site');
        self::assertSame([], $repository->values(Subject::user('ada')));
        self::assertSame(['a.b' => '3'], $repository->values(Subject::user('bob')));
    }

    public function testAMigrationReadsEveryValueOfAKey(): void
    {
        $repository = $this->repository();
        $at = new DateTimeImmutable('2026-11-08 12:00:00 UTC');
        $repository->save(Subject::user('bob'), ['a.b' => '2', 'a.c' => '9'], [], $at);
        $repository->save(Subject::user('ada'), ['a.b' => '1'], [], $at);
        $repository->save(Subject::site(), ['a.b' => '3'], [], $at);

        $values = $repository->valuesOf('a.b');
        usort($values, static fn (StoredValue $one, StoredValue $other): int => [$one->scope, $one->subject] <=> [$other->scope, $other->subject]);

        self::assertEquals([new StoredValue('site', 'site', 'a.b', '3'), new StoredValue('user', 'ada', 'a.b', '1'), new StoredValue('user', 'bob', 'a.b', '2')], $values);
        self::assertSame([], $repository->valuesOf('a.z'));
        self::assertSame([], $repository->appliedMigrations());
    }

    public function testAMigrationWritesRemovesAndIsRecorded(): void
    {
        $repository = $this->repository();
        $at = new DateTimeImmutable('2026-11-08 12:00:00 UTC');
        $repository->save(Subject::user('ada'), ['a.b' => '1', 'a.c' => '2'], [], $at);
        $repository->save(Subject::user('bob'), ['a.b' => '5'], [], $at);

        $repository->applyMigration('v1', [
            new StoredValue('user', 'ada', 'a.b', null),
            new StoredValue('user', 'ada', 'a.d', '"new"'),
            new StoredValue('user', 'bob', 'a.b', '6'),
            new StoredValue('site', 'site', 'a.e', 'true'),
        ], $at);
        $repository->applyMigration('v0', [], $at);

        $ada = $repository->values(Subject::user('ada'));
        ksort($ada);
        self::assertSame(['a.c' => '2', 'a.d' => '"new"'], $ada);
        self::assertSame(['a.b' => '6'], $repository->values(Subject::user('bob')));
        self::assertSame(['a.e' => 'true'], $repository->values(Subject::site()));
        $applied = $repository->appliedMigrations();
        sort($applied);
        self::assertSame(['v0', 'v1'], $applied);
    }

    public function testAMigrationKeepsWhatIsStoredUnderAnotherScopeOfTheSameName(): void
    {
        $repository = $this->repository();
        $at = new DateTimeImmutable('2026-11-08 12:00:00 UTC');
        $repository->save(Subject::user('site'), ['a.b' => '1'], [], $at);
        $repository->save(Subject::site(), ['a.b' => '2'], [], $at);
        $repository->save(Subject::user('a:b'), ['a.b' => '3'], [], $at);

        $repository->applyMigration('v1', [new StoredValue('user', 'site', 'a.b', null)], $at);

        self::assertSame([], $repository->values(Subject::user('site')));
        self::assertSame(['a.b' => '2'], $repository->values(Subject::site()));
        self::assertEquals([new StoredValue('site', 'site', 'a.b', '2'), new StoredValue('user', 'a:b', 'a.b', '3')], $this->sorted($repository->valuesOf('a.b')));
    }

    /**
     * @param list<StoredValue> $values
     *
     * @return list<StoredValue>
     */
    private function sorted(array $values): array
    {
        usort($values, static fn (StoredValue $one, StoredValue $other): int => [$one->scope, $one->subject] <=> [$other->scope, $other->subject]);

        return $values;
    }

    abstract protected function repository(): SettingRepository&SettingMigrationStore;
}
