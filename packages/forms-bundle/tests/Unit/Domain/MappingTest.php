<?php

declare(strict_types=1);

namespace Phil\FormsBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Mapping\JoinColumnMapping;
use Doctrine\ORM\Mapping\ManyToOneAssociationMapping;
use Doctrine\Persistence\Mapping\RuntimeReflectionService;
use Phil\FormsBundle\Domain\Field\FieldType;
use Phil\FormsBundle\Domain\Model\ChoiceCount;
use Phil\FormsBundle\Domain\Model\Entry;
use Phil\FormsBundle\Domain\Model\Form;
use Phil\FormsBundle\Domain\ValueObject\Field;
use Phil\FormsBundle\Tests\Unit\Fixtures\Database;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The columns an application's migrations create for forms and their entries.
 */
#[CoversClass(Form::class)]
#[CoversClass(Entry::class)]
#[CoversClass(ChoiceCount::class)]
final class MappingTest extends TestCase
{
    public function testTheTables(): void
    {
        $form = $this->metadata(Form::class);
        $entry = $this->metadata(Entry::class);

        self::assertSame(['phil_form', [
            'id' => ['id', 'string', 36, false, true],
            'key' => ['form_key', 'string', 64, false, null],
            'title' => ['title', 'string', 255, false, null],
            'fields' => ['fields', 'json', null, false, null],
            'recipients' => ['recipients', 'json', null, false, null],
            'webhookUrl' => ['webhook_url', 'string', 2000, true, null],
            'retentionDays' => ['retention_days', 'integer', null, false, null],
            'open' => ['is_open', 'boolean', null, false, null],
            'subjectField' => ['subject_field', 'string', 64, true, null],
            'notifications' => ['notifications', 'json', null, false, null],
        ]], [$form->getTableName(), $this->columns($form)]);
        self::assertArrayNotHasKey('default', $form->fieldMappings['notifications']->options ?? [], 'MySQL refuses a literal default on a JSON column.');
        self::assertSame([['columns' => ['form_key']]], array_values($form->table['uniqueConstraints'] ?? []));

        self::assertSame(['phil_form_entry', [
            'id' => ['id', 'string', 36, false, true],
            'values' => ['entry_values', 'json', null, false, null],
            'submittedAt' => ['submitted_at', 'datetime_immutable', null, false, null],
            'purgeAfter' => ['purge_after', 'datetime_immutable', null, false, null],
            'locale' => ['locale', 'string', 12, true, null],
            'subject' => ['subject', 'string', 255, true, null],
            'emailDigest' => ['email_digest', 'string', 64, true, null],
            'sealed' => ['sealed_fields', 'json', null, false, null],
        ]], [$entry->getTableName(), $this->columns($entry)]);
        self::assertArrayNotHasKey('default', $entry->fieldMappings['sealed']->options ?? [], 'MySQL refuses a literal default on a JSON column.');

        $count = $this->metadata(ChoiceCount::class);
        self::assertSame(['phil_form_choice_count', [
            'field' => ['field_name', 'string', 64, false, null],
            'choice' => ['choice', 'string', 255, false, null],
            'taken' => ['taken', 'integer', null, false, null],
        ]], [$count->getTableName(), $this->columns($count)]);
        self::assertSame(['form', 'field', 'choice'], $count->getIdentifierFieldNames());
        $countForm = $count->getAssociationMapping('form');
        self::assertInstanceOf(ManyToOneAssociationMapping::class, $countForm);
        self::assertSame([['form', 'id', false, 'CASCADE']], array_map(static fn (JoinColumnMapping $column): array => [$column->name, $column->referencedColumnName, $column->nullable, $column->onDelete], $countForm->joinColumns));
        self::assertSame([['columns' => ['purge_after']], ['columns' => ['subject']], ['columns' => ['email_digest']]], array_values($entry->table['indexes'] ?? []));
        $mapping = $entry->getAssociationMapping('form');
        self::assertInstanceOf(ManyToOneAssociationMapping::class, $mapping);
        self::assertSame([['form', 'id', false, 'CASCADE']], array_map(static fn (JoinColumnMapping $column): array => [$column->name, $column->referencedColumnName, $column->nullable, $column->onDelete], $mapping->joinColumns));

        $created = Form::create('a', 'A', [Field::of('a', 'A', FieldType::Text)]);
        self::assertSame([36, 36], [\strlen($created->id), \strlen($created->submit([], new DateTimeImmutable())->id)]);
    }

    public function testTheFieldsAreBuiltOnceWhileTheirDefinitionsHold(): void
    {
        $entityManager = Database::entityManager();
        $form = Form::create('a', 'A', [Field::of('name', 'Nom', FieldType::Text), Field::of('email', 'Courriel', FieldType::Email)]);
        $entityManager->persist($form);
        $entityManager->flush();

        $fields = $form->fields();
        self::assertSame($fields, $form->fields(), 'the same fields, not built again');
        self::assertSame($fields[0], $form->fields()[0]);

        $form->define([Field::of('name', 'Nom', FieldType::Text)]);
        self::assertSame(['name'], array_map(static fn (Field $field): string => $field->name, $form->fields()), 'defined anew');
        self::assertNotSame($fields[0], $form->fields()[0]);

        $entityManager->flush();
        $entityManager->getConnection()->executeStatement("UPDATE phil_form SET fields = '[{\"name\":\"team\",\"label\":\"\u00c9quipe\",\"type\":\"text\",\"required\":true,\"choices\":[],\"max_length\":null}]'");
        $entityManager->refresh($form);
        self::assertSame([['team', 'Équipe', true]], array_map(static fn (Field $field): array => [$field->name, $field->label, $field->required], $form->fields()), 'read again by Doctrine');

        $entityManager->clear();
        $loaded = $entityManager->find(Form::class, $form->id);
        self::assertInstanceOf(Form::class, $loaded);
        self::assertSame(['team'], array_map(static fn (Field $field): string => $field->name, $loaded->fields()), 'loaded, never built');
    }

    /**
     * @param class-string $class
     *
     * @return ClassMetadata<object>
     */
    private function metadata(string $class): ClassMetadata
    {
        $metadata = new ClassMetadata($class);
        $metadata->initializeReflection(new RuntimeReflectionService());
        new AttributeDriver([\dirname(__DIR__, 3).'/src/Domain/Model'])->loadMetadataForClass($class, $metadata);

        return $metadata;
    }

    /**
     * @param ClassMetadata<object> $metadata
     *
     * @return array<string, list<mixed>>
     */
    private function columns(ClassMetadata $metadata): array
    {
        $columns = [];

        foreach ($metadata->fieldMappings as $field => $mapping) {
            $columns[$field] = [$mapping->columnName, $mapping->type, $mapping->length, $mapping->nullable, $mapping->options['fixed'] ?? null];
        }

        return $columns;
    }
}
