<?php

declare(strict_types=1);

namespace Phil\DoctrineBehavioursBundle\Tests\Functional;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\DoctrineBehaviours\SoftDeletedFilter;
use Phil\DoctrineBehaviours\VersionEraser;
use Phil\DoctrineBehaviours\Versions;
use Phil\DoctrineBehavioursBundle\PhilDoctrineBehavioursBundle;
use Phil\DoctrineBehavioursBundle\Tests\Functional\App\Entity\Notice;
use Phil\DoctrineBehavioursBundle\Tests\Functional\App\Entity\Page;
use Phil\DoctrineBehavioursBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Workflow\Registry;

/**
 * The instants the behaviours write, through the bundle's clock, in the columns the library maps. The expectations
 * follow the type each column of the library declares, never a type written here: a column of a UTC type reads back
 * the instant written, whatever the time zone of PHP; a column of a plain date-time type keeps the UTC wall clock.
 */
#[CoversClass(PhilDoctrineBehavioursBundle::class)]
final class InstantColumnsTest extends KernelTestCase
{
    use ClockSensitiveTrait;

    private const string LIBRARY = 'Phil\\DoctrineBehaviours\\';

    private const array INSTANT_TYPES = [
        Types::DATETIME_MUTABLE,
        Types::DATETIME_IMMUTABLE,
        Types::DATETIMETZ_MUTABLE,
        Types::DATETIMETZ_IMMUTABLE,
        Types::DATETIME_UTC_MUTABLE,
        Types::DATETIME_UTC_IMMUTABLE,
    ];

    private const array UTC_TYPES = [Types::DATETIME_UTC_MUTABLE, Types::DATETIME_UTC_IMMUTABLE];

    private string $timezone = 'UTC';

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        $this->timezone = date_default_timezone_get();
        date_default_timezone_set('America/Toronto');
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->timezone);
        parent::tearDown();
    }

    public function testTheMappingKeepsTheTypeTheLibraryDeclares(): void
    {
        $manager = $this->manager();
        $columns = $this->instantColumns($manager);

        self::assertNotSame([], $columns);

        foreach ($columns as [$class, $field, $declared]) {
            self::assertSame($declared, $manager->getClassMetadata($class)->getTypeOfField($field), \sprintf('%s::$%s', $class, $field));
        }
    }

    public function testEveryInstantIsWrittenInUtc(): void
    {
        self::mockTime(new DateTimeImmutable('2026-11-14 10:00:00', new DateTimeZone('America/Toronto')));
        $manager = $this->manager();

        $page = new Page('home');
        $notice = new Notice('Bonjour');
        $manager->persist($page);
        $manager->persist($notice);
        $manager->flush();

        $workflows = self::getContainer()->get('test.workflow_registry');
        self::assertInstanceOf(Registry::class, $workflows);
        $workflows->get($notice)->apply($notice, 'publish');
        $workflows->get($notice)->apply($notice, 'unpublish');
        $notice->deactivate();
        $versions = self::getContainer()->get(Versions::class);
        self::assertInstanceOf(Versions::class, $versions);
        $versions->freeze($notice);
        $eraser = self::getContainer()->get(VersionEraser::class);
        self::assertInstanceOf(VersionEraser::class, $eraser);
        $eraser->redactRecord($notice);
        $manager->remove($page);
        $manager->flush();

        $checked = 0;

        foreach ($this->instantColumns($manager) as [$class, $field]) {
            $metadata = $manager->getClassMetadata($class);
            $values = $manager->getConnection()->fetchFirstColumn(\sprintf('SELECT %s FROM %s', $metadata->getColumnName($field), $metadata->getTableName()));
            $values = array_values(array_filter($values, static fn (mixed $value): bool => null !== $value));

            self::assertNotSame([], $values, \sprintf('%s::$%s is written', $class, $field));

            foreach ($values as $value) {
                self::assertSame('2026-11-14 15:00:00', $value, \sprintf('%s::$%s', $class, $field));
                ++$checked;
            }
        }

        self::assertGreaterThanOrEqual(7, $checked);
    }

    public function testAnInstantReadsBackAsItsColumnTypeSays(): void
    {
        $written = new DateTimeImmutable('2026-11-14 10:00:00', new DateTimeZone('America/Toronto'));
        self::mockTime($written);
        $manager = $this->manager();
        $manager->persist(new Page('home'));
        $manager->flush();
        $manager->clear();
        $manager->getFilters()->disable(SoftDeletedFilter::NAME);

        $page = $manager->find(Page::class, 'home');
        self::assertInstanceOf(Page::class, $page);
        $read = $page->createdAt();
        self::assertInstanceOf(DateTimeInterface::class, $read);
        $declared = $manager->getClassMetadata(Page::class)->getTypeOfField('createdAt');

        if (\in_array($declared, self::UTC_TYPES, true)) {
            self::assertSame($written->getTimestamp(), $read->getTimestamp());
            self::assertSame('UTC', $read->getTimezone()->getName());
        } else {
            self::assertSame('2026-11-14 15:00:00', $read->format('Y-m-d H:i:s'));
        }
    }

    private function manager(): EntityManagerInterface
    {
        self::bootKernel();
        $manager = self::getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $manager);
        new SchemaTool($manager)->createSchema($manager->getMetadataFactory()->getAllMetadata());

        return $manager;
    }

    /**
     * The instant columns the library maps — by a trait an entity uses, or on an entity of its own — with the type
     * each declares.
     *
     * @return list<array{class-string, string, string}>
     */
    private function instantColumns(EntityManagerInterface $manager): array
    {
        $columns = [];

        foreach ($manager->getMetadataFactory()->getAllMetadata() as $metadata) {
            $class = $metadata->getName();
            $owners = str_starts_with($class, self::LIBRARY) ? [$class] : [];

            foreach (new ReflectionClass($class)->getTraitNames() as $trait) {
                if (str_starts_with($trait, self::LIBRARY)) {
                    $owners[] = $trait;
                }
            }

            foreach ($owners as $owner) {
                foreach (new ReflectionClass($owner)->getProperties() as $property) {
                    foreach ($property->getAttributes(Column::class) as $attribute) {
                        $type = $attribute->newInstance()->type;

                        if (\is_string($type) && \in_array($type, self::INSTANT_TYPES, true)) {
                            $columns[] = [$class, $property->getName(), $type];
                        }
                    }
                }
            }
        }

        return $columns;
    }
}
