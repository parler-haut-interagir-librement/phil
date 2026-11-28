<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\Column;
use Phil\EmailTemplatesBundle\Domain\Exception\InvalidTemplate;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateDefinition;
use Phil\EmailTemplatesBundle\Infrastructure\Content\Entity\EmailTemplateContent;
use Phil\EmailTemplatesBundle\Infrastructure\Content\Entity\EmailTemplateDimension;
use Phil\EmailTemplatesBundle\Infrastructure\Content\TemplateKeysCollation;
use Phil\EmailTemplatesBundle\Infrastructure\Content\TemplatePayload;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * The parts of the content store that need no database: the field values a template is kept in, the collation of the
 * keys of its root, and the root itself.
 */
#[CoversClass(TemplatePayload::class)]
#[CoversClass(TemplateKeysCollation::class)]
#[CoversClass(EmailTemplateContent::class)]
final class ContentAdaptersTest extends TestCase
{
    public function testAPayloadIsWrittenAndReadBackWhole(): void
    {
        $definition = TemplateDefinition::create('Hi', '<p>x</p>');
        $payload = new TemplatePayload($definition, 3, new DateTimeImmutable('2026-11-18 05:00:00.123456', new DateTimeZone('America/Toronto')), 'ada');
        $data = $payload->toData();

        self::assertSame(['definition', 'revision', 'savedAt', 'author'], array_keys($data));
        self::assertSame($definition->toArray(), $data['definition']);
        self::assertSame(3, $data['revision']);
        self::assertSame('2026-11-18T10:00:00.123456+00:00', $data['savedAt']);
        self::assertSame('ada', $data['author']);

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode((string) json_encode($data), true, 16, \JSON_THROW_ON_ERROR);
        $read = TemplatePayload::fromData($decoded, 'fr/draft/1');
        self::assertEquals($definition, $read->definition);
        self::assertSame(3, $read->revision);
        self::assertSame('2026-11-18T10:00:00.123456+00:00', $read->savedAt->format('Y-m-d\TH:i:s.uP'));
        self::assertSame('ada', $read->author);
        self::assertNull(TemplatePayload::fromData([...$data, 'author' => null], 'fr/draft/1')->author);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function unreadablePayloads(): iterable
    {
        $data = new TemplatePayload(TemplateDefinition::create('Hi', '<p>x</p>'), 1, new DateTimeImmutable('@0'))->toData();

        yield 'nothing' => [[]];
        yield 'no definition' => [[...$data, 'definition' => 'x']];
        yield 'a revision as text' => [[...$data, 'revision' => '1']];
        yield 'revision zero' => [[...$data, 'revision' => 0]];
        yield 'no moment' => [[...$data, 'savedAt' => null]];
        yield 'a moment of another shape' => [[...$data, 'savedAt' => '2026-11-18 10:00:00']];
        yield 'an author that is no text' => [[...$data, 'author' => 7]];
    }

    /**
     * @param array<string, mixed> $data
     */
    #[DataProvider('unreadablePayloads')]
    public function testAnUnreadablePayloadIsRefused(array $data): void
    {
        $this->expectException(InvalidTemplate::class);
        $this->expectExceptionMessageIsOrContains('The stored email template en/live/2 holds no readable template.');

        TemplatePayload::fromData($data, 'en/live/2');
    }

    public function testADefinitionThatNoLongerHoldsIsRefusedWithItsReasons(): void
    {
        $data = new TemplatePayload(TemplateDefinition::create('Hi', '<p>x</p>'), 1, new DateTimeImmutable('@0'))->toData();

        $this->expectException(InvalidTemplate::class);
        $this->expectExceptionMessageIsOrContains('A template has no key "extra"');

        TemplatePayload::fromData([...$data, 'definition' => [...$data['definition'], 'extra' => 1]], 'en/draft/1');
    }

    /**
     * @return iterable<string, array{AbstractPlatform, bool}>
     */
    public static function platforms(): iterable
    {
        yield 'MySQL' => [new MySQLPlatform(), true];
        yield 'MariaDB' => [new MariaDBPlatform(), true];
        yield 'PostgreSQL' => [new PostgreSQLPlatform(), false];
        yield 'SQLite' => [new SQLitePlatform(), false];
    }

    #[DataProvider('platforms')]
    public function testTheKeysOfARootCompareByteForByteOnMySqlAndMariaDb(AbstractPlatform $platform, bool $binary): void
    {
        $metadata = $this->metadata(EmailTemplateContent::class, $platform);

        foreach (['site', 'templateKey', 'locale'] as $field) {
            $options = $metadata->getFieldMapping($field)->options ?? [];
            self::assertSame($binary ? ['charset' => 'utf8mb4', 'collation' => 'utf8mb4_bin'] : [], $options, $field);
        }

        self::assertSame([], $metadata->getFieldMapping('id')->options ?? []);
    }

    public function testTheOptionsAFieldAlreadyHasAreKept(): void
    {
        $metadata = new ClassMetadata(EmailTemplateContent::class);
        $metadata->mapField(['fieldName' => 'site', 'columnName' => 'site', 'type' => 'string', 'options' => ['comment' => 'The site']]);
        $metadata->mapField(['fieldName' => 'templateKey', 'columnName' => 'template_key', 'type' => 'string']);
        $metadata->mapField(['fieldName' => 'locale', 'columnName' => 'locale', 'type' => 'string']);
        new TemplateKeysCollation()->loadClassMetadata(new LoadClassMetadataEventArgs($metadata, $this->entities(new MySQLPlatform())));

        self::assertSame(['comment' => 'The site', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_bin'], $metadata->getFieldMapping('site')->options);
    }

    public function testTheColumnsOfARootFitTheKeysTheDomainAccepts(): void
    {
        $lengths = [];

        foreach (['templateKey', 'site', 'locale'] as $field) {
            $column = new ReflectionProperty(EmailTemplateContent::class, $field)->getAttributes(Column::class)[0]->newInstance();
            $lengths[$column->name ?? $field] = $column->length;
        }

        self::assertSame(['template_key' => 64, 'site' => 64, 'locale' => 16], $lengths);
    }

    public function testAnotherEntityKeepsItsMapping(): void
    {
        $metadata = new ClassMetadata(EmailTemplateDimension::class);
        $metadata->mapField(['fieldName' => 'site', 'columnName' => 'site', 'type' => 'string']);
        new TemplateKeysCollation()->loadClassMetadata(new LoadClassMetadataEventArgs($metadata, $this->entities(new MySQLPlatform())));

        self::assertSame([], $metadata->getFieldMapping('site')->options ?? []);
    }

    public function testARootHoldsItsScopeAndAnIdentifierOfItsOwn(): void
    {
        $root = new EmailTemplateContent('ticket', 'main', 'fr_CA');
        $other = new EmailTemplateContent('ticket', 'main', 'fr_CA');

        self::assertSame(['ticket', 'main', 'fr_CA'], [$root->templateKey(), $root->site(), $root->locale()]);
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $root->id());
        self::assertNotSame($root->id(), $other->id());
        self::assertSame('phil_email_template', EmailTemplateContent::resourceKey());
        self::assertSame(EmailTemplateDimension::class, EmailTemplateContent::dimensionContentClass());
    }

    /**
     * @param class-string $class
     *
     * @return ClassMetadata<object>
     */
    private function metadata(string $class, AbstractPlatform $platform): ClassMetadata
    {
        $metadata = new ClassMetadata($class);

        foreach (['id' => 'id', 'site' => 'site', 'templateKey' => 'template_key', 'locale' => 'locale'] as $field => $column) {
            $metadata->mapField(['fieldName' => $field, 'columnName' => $column, 'type' => 'string']);
        }

        new TemplateKeysCollation()->loadClassMetadata(new LoadClassMetadataEventArgs($metadata, $this->entities($platform)));

        return $metadata;
    }

    private function entities(AbstractPlatform $platform): EntityManagerInterface
    {
        $connection = self::createStub(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn($platform);
        $entities = self::createStub(EntityManagerInterface::class);
        $entities->method('getConnection')->willReturn($connection);

        return $entities;
    }
}
