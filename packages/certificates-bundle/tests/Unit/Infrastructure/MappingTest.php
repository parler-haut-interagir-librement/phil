<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ToOneOwningSideMapping;
use Doctrine\ORM\ORMSetup;
use Phil\CertificatesBundle\Domain\Model\Certificate;
use Phil\CertificatesBundle\Domain\Model\Course;
use Phil\CertificatesBundle\Domain\Model\LearningActivity;
use Phil\CertificatesBundle\Domain\ValueObject\Credits;
use Phil\CertificatesBundle\Domain\ValueObject\MemberId;
use Phil\CertificatesBundle\Tests\Unit\Support\Kit;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The tables and columns the entities map to, as a migration generates them.
 */
#[CoversClass(Course::class)]
#[CoversClass(Certificate::class)]
#[CoversClass(LearningActivity::class)]
final class MappingTest extends TestCase
{
    public function testTheCourseTable(): void
    {
        // Built once, so that the attributes of the promoted parameters count as run.
        self::assertSame('first-aid', Kit::firstAid()->slug);
        self::assertSame([
            'table' => 'phil_certificates_course',
            'unique' => [['slug']],
            'indexes' => [],
            'columns' => [
                'name' => ['name', 'string', 255, false, []],
                'description' => ['description', 'text', null, false, []],
                'narrative' => ['narrative', 'text', null, false, []],
                'minimumScore' => ['minimum_score', 'smallint', null, true, []],
                'minimumMinutes' => ['minimum_minutes', 'integer', null, true, []],
                'requiredModules' => ['required_modules', 'json', null, false, []],
                'validityMonths' => ['validity_months', 'smallint', null, true, []],
                'image' => ['image', 'string', 2048, true, []],
                'retired' => ['retired', 'boolean', null, false, []],
                'recertificationHundredths' => ['recertification_credits', 'integer', null, true, []],
                'endsAt' => ['ends_at', 'datetime_utc_immutable', null, true, []],
                'completionSource' => ['completion_source', 'string', 255, true, []],
                'id' => ['id', 'string', 36, false, ['fixed' => true]],
                'slug' => ['slug', 'string', 64, false, []],
            ],
            'associations' => [],
        ], $this->describe(Course::class));
    }

    public function testTheCertificateTable(): void
    {
        self::assertSame('7KQ2M9XA04TZ', Kit::awarded()->code->value);
        self::assertSame([
            'table' => 'phil_certificates_certificate',
            'unique' => [['code'], ['status_index'], ['course_id', 'completion']],
            'indexes' => [['course_id', 'listed', 'awarded_at'], ['valid_until'], ['holder']],
            'columns' => [
                'revokedAt' => ['revoked_at', 'datetime_utc_immutable', null, true, []],
                'revocationReason' => ['revocation_reason', 'string', 500, true, []],
                'remindedAt' => ['reminded_at', 'datetime_utc_immutable', null, true, []],
                'codeValue' => ['code', 'string', 12, false, ['fixed' => true]],
                'id' => ['id', 'string', 36, false, ['fixed' => true]],
                'recipientName' => ['recipient_name', 'string', 255, false, []],
                'awardedAt' => ['awarded_at', 'datetime_utc_immutable', null, false, []],
                'validUntil' => ['valid_until', 'datetime_utc_immutable', null, true, []],
                'statusIndex' => ['status_index', 'integer', null, false, []],
                'credential' => ['credential', 'text', null, false, []],
                'listed' => ['listed', 'boolean', null, false, []],
                'holder' => ['holder', 'string', 191, true, []],
                'completion' => ['completion', 'string', 255, true, []],
            ],
            'associations' => ['course' => [Course::class, 'course_id', false], 'renews' => [Certificate::class, 'renews_id', true]],
        ], $this->describe(Certificate::class));
    }

    public function testTheActivityTable(): void
    {
        self::assertSame('member-1', LearningActivity::record(MemberId::fromString('member-1'), 'Workshop', Credits::fromString('1'), new DateTimeImmutable('2026-11-08 UTC'))->member);
        self::assertSame([
            'table' => 'phil_certificates_activity',
            'unique' => [['member', 'source']],
            'indexes' => [['member', 'completed_at']],
            'columns' => [
                'hundredths' => ['credits', 'integer', null, false, []],
                'id' => ['id', 'string', 36, false, ['fixed' => true]],
                'member' => ['member', 'string', 191, false, []],
                'title' => ['title', 'string', 255, false, []],
                'completedAt' => ['completed_at', 'datetime_utc_immutable', null, false, []],
                'source' => ['source', 'string', 255, true, []],
            ],
            'associations' => ['course' => [Course::class, 'course_id', true]],
        ], $this->describe(LearningActivity::class));
    }

    /**
     * @param class-string $class
     *
     * @return array<string, mixed>
     */
    private function describe(string $class): array
    {
        $configuration = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 3).'/src/Domain/Model'], true);
        $configuration->enableNativeLazyObjects(true);

        $metadata = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $configuration)->getClassMetadata($class);
        $columns = [];
        $associations = [];

        foreach ($metadata->fieldMappings as $name => $mapping) {
            $columns[$name] = [$mapping->columnName, $mapping->type, $mapping->length, true === $mapping->nullable, $mapping->options ?? []];
        }

        foreach ($metadata->associationMappings as $name => $mapping) {
            self::assertInstanceOf(ToOneOwningSideMapping::class, $mapping);
            $join = $mapping->joinColumns[0];
            $associations[$name] = [$mapping->targetEntity, $join->name, true === $join->nullable];
        }

        return [
            'table' => $metadata->getTableName(),
            'unique' => array_column($metadata->table['uniqueConstraints'] ?? [], 'columns'),
            'indexes' => array_column($metadata->table['indexes'] ?? [], 'columns'),
            'columns' => $columns,
            'associations' => $associations,
        ];
    }
}
