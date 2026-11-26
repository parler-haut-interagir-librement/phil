<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Integration;

use Override;
use Phil\CoursesBundle\Infrastructure\Doctrine\CourseTables;
use Phil\CoursesBundle\Infrastructure\Doctrine\DbalCatalogueRepository;
use Phil\CoursesBundle\Infrastructure\Doctrine\DbalCourseRepository;
use Phil\CoursesBundle\Infrastructure\Doctrine\DbalEnrollmentRepository;
use Phil\CoursesBundle\Infrastructure\Doctrine\DbalLaunchSessionRepository;
use Phil\CoursesBundle\Infrastructure\Doctrine\DbalLearningRecordStore;
use Phil\CoursesBundle\Infrastructure\Doctrine\DbalRetryRequestRepository;
use Phil\CoursesBundle\Infrastructure\Doctrine\DbalScormAttemptRepository;
use Phil\CoursesBundle\Infrastructure\Doctrine\Rows;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * The tables and queries on MySQL 8.4, at `PHIL_TEST_MYSQL_URL`.
 */
#[CoversClass(CourseTables::class)]
#[CoversClass(DbalCourseRepository::class)]
#[CoversClass(DbalCatalogueRepository::class)]
#[CoversClass(DbalEnrollmentRepository::class)]
#[CoversClass(DbalRetryRequestRepository::class)]
#[CoversClass(DbalLaunchSessionRepository::class)]
#[CoversClass(DbalLearningRecordStore::class)]
#[CoversClass(DbalScormAttemptRepository::class)]
#[CoversClass(Rows::class)]
#[Group('mysql')]
final class MySqlCoursesTest extends ServerTestCase
{
    #[Override]
    protected function variable(): string
    {
        return 'PHIL_TEST_MYSQL_URL';
    }
}
