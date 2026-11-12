<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ManyToOneAssociationMapping;
use Phil\ContentBundle\Infrastructure\Doctrine\ContentRoots;
use Phil\ContentBundle\Infrastructure\Doctrine\DimensionContentMappingListener;
use Phil\ContentBundle\Infrastructure\Doctrine\InvalidContentMapping;
use Phil\ContentBundle\Infrastructure\Doctrine\Mapping\BodyCapabilityMapping;
use Phil\ContentBundle\Infrastructure\Doctrine\Mapping\CapabilityMapping;
use Phil\ContentBundle\Infrastructure\Doctrine\Mapping\ExcerptCapabilityMapping;
use Phil\ContentBundle\Infrastructure\Doctrine\Mapping\LinkCapabilityMapping;
use Phil\ContentBundle\Infrastructure\Doctrine\Mapping\RoutableCapabilityMapping;
use Phil\ContentBundle\Infrastructure\Doctrine\Mapping\SeoCapabilityMapping;
use Phil\ContentBundle\Infrastructure\Doctrine\Mapping\TemplateCapabilityMapping;
use Phil\ContentBundle\Infrastructure\Doctrine\Mapping\WorkflowCapabilityMapping;
use Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\Article;
use Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\ArticleDimension;
use Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\BriefDimension;
use Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\CapableWithoutTrait;
use Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\EntityManagerFactory;
use Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\ForeignKeyPrefixNamingStrategy;
use Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\Memo;
use Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\MemoDimension;
use Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\NoteDimension;
use Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\OrphanDimension;
use Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\PageDimension;
use Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\TemplatedDimension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The mapping the listener completes, read from the metadata of an entity manager that never connects.
 */
#[CoversClass(DimensionContentMappingListener::class)]
#[CoversClass(CapabilityMapping::class)]
#[CoversClass(TemplateCapabilityMapping::class)]
#[CoversClass(WorkflowCapabilityMapping::class)]
#[CoversClass(SeoCapabilityMapping::class)]
#[CoversClass(ExcerptCapabilityMapping::class)]
#[CoversClass(LinkCapabilityMapping::class)]
#[CoversClass(RoutableCapabilityMapping::class)]
#[CoversClass(BodyCapabilityMapping::class)]
final class DimensionContentMappingListenerTest extends TestCase
{
    public function testTheBaseClassMapsTheIdentifierAndTheDimension(): void
    {
        $metadata = $this->metadata(ArticleDimension::class);

        self::assertSame(['id'], $metadata->getIdentifierFieldNames());
        self::assertSame('locale', $metadata->getColumnName('locale'));
        self::assertTrue($metadata->isNullable('locale'));
        self::assertSame('locale_key', $metadata->getColumnName('localeKey'));
        self::assertFalse($metadata->isNullable('localeKey'));
        self::assertSame('phil_content_stage', $metadata->getTypeOfField('stage'));
        self::assertSame('phil_content_version', $metadata->getTypeOfField('version'));
    }

    public function testARowPointsToTheRootThatNamesIt(): void
    {
        $association = $this->metadata(ArticleDimension::class)->getAssociationMapping('root');

        self::assertInstanceOf(ManyToOneAssociationMapping::class, $association);
        self::assertSame(Article::class, $association->targetEntity);
        self::assertCount(1, $association->joinColumns);
        self::assertSame('root_id', $association->joinColumns[0]->name);
        self::assertSame('id', $association->joinColumns[0]->referencedColumnName);
        self::assertFalse($association->joinColumns[0]->nullable);
        self::assertSame('CASCADE', $association->joinColumns[0]->onDelete);
    }

    /**
     * The order serves the three lookups of the repository, each a prefix of the one index.
     */
    public function testARowHasOneUniqueConstraintOverItsRootAndDimension(): void
    {
        $constraints = $this->metadata(ArticleDimension::class)->table['uniqueConstraints'] ?? [];

        self::assertSame([['columns' => ['root_id', 'stage', 'version', 'locale_key']]], array_values($constraints));
    }

    public function testEveryCapabilityAClassImplementsGetsItsColumns(): void
    {
        $metadata = $this->metadata(ArticleDimension::class);

        self::assertSame('template_key', $metadata->getColumnName('templateKey'));
        self::assertTrue($metadata->isNullable('templateKey'));
        self::assertSame(64, $metadata->getFieldMapping('templateKey')->length);
        self::assertSame('json', $metadata->getTypeOfField('templateData'));
        self::assertFalse($metadata->isNullable('templateData'));
        self::assertSame('workflow_place', $metadata->getColumnName('workflowPlace'));
        self::assertSame(32, $metadata->getFieldMapping('workflowPlace')->length);
        self::assertTrue($metadata->isNullable('workflowPlace'));
        self::assertSame('datetime_immutable', $metadata->getTypeOfField('workflowPublished'));
        self::assertTrue($metadata->isNullable('workflowPublished'));
    }

    /**
     * @param array{string, string, bool, int|null} $expected column, type, nullable, length
     */
    #[DataProvider('capabilityColumns')]
    public function testEachCapabilityOfTheBundleMapsItsColumns(string $field, array $expected): void
    {
        $mapping = $this->metadata(ArticleDimension::class)->getFieldMapping($field);

        self::assertSame($expected, [$mapping->columnName, $mapping->type, $mapping->nullable ?? false, $mapping->length]);
    }

    /**
     * @return iterable<string, array{string, array{string, string, bool, int|null}}>
     */
    public static function capabilityColumns(): iterable
    {
        yield 'SEO title' => ['seoTitle', ['seo_title', 'string', true, 255]];
        yield 'SEO description' => ['seoDescription', ['seo_description', 'text', true, null]];
        yield 'SEO keywords' => ['seoKeywords', ['seo_keywords', 'json', false, null]];
        yield 'canonical URL' => ['seoCanonicalUrl', ['seo_canonical_url', 'string', true, 2048]];
        yield 'noindex' => ['seoNoIndex', ['seo_no_index', 'boolean', true, null]];
        yield 'nofollow' => ['seoNoFollow', ['seo_no_follow', 'boolean', true, null]];
        yield 'hidden from the sitemap' => ['seoHideInSitemap', ['seo_hide_in_sitemap', 'boolean', true, null]];
        yield 'excerpt title' => ['excerptTitle', ['excerpt_title', 'string', true, 255]];
        yield 'excerpt description' => ['excerptDescription', ['excerpt_description', 'text', true, null]];
        yield 'excerpt image' => ['excerptImage', ['excerpt_image', 'string', true, 2048]];
        yield 'link resource key' => ['linkResourceKey', ['link_resource_key', 'string', true, 64]];
        yield 'link resource identifier' => ['linkResourceId', ['link_resource_id', 'string', true, 64]];
        yield 'link locale' => ['linkLocale', ['link_locale', 'phil_content_locale', true, 12]];
        yield 'link URL' => ['linkUrl', ['link_url', 'string', true, 2048]];
        yield 'slug' => ['slug', ['slug', 'string', true, 255]];
        yield 'body source' => ['bodySource', ['body_source', 'text', true, null]];
        yield 'body format' => ['bodyFormat', ['body_format', 'string', true, 16]];
        yield 'body HTML' => ['bodyHtml', ['body_html', 'text', true, null]];
        yield 'body by a raw HTML author' => ['bodyRawHtml', ['body_raw_html', 'boolean', false, null]];
    }

    /**
     * The format is kept by its name, so the format an add-on brings is stored as a built-in one.
     */
    public function testTheBodyFormatIsKeptByItsName(): void
    {
        self::assertNull($this->metadata(ArticleDimension::class)->getFieldMapping('bodyFormat')->enumType);
    }

    public function testACapabilityAClassDoesNotImplementAddsNothing(): void
    {
        $metadata = $this->metadata(PageDimension::class);

        self::assertTrue($metadata->hasField('templateKey'));
        self::assertFalse($metadata->hasField('workflowPlace'));
        self::assertFalse($metadata->hasField('workflowPublished'));
        self::assertFalse($metadata->hasField('seoTitle'));
        self::assertFalse($metadata->hasField('excerptImage'));
        self::assertFalse($metadata->hasField('linkUrl'));
        self::assertFalse($metadata->hasField('slug'));
        self::assertFalse($metadata->hasField('bodySource'));
    }

    /**
     * The root is an entity like any other: neither an association nor a constraint is added to it.
     */
    public function testTheRootIsLeftAlone(): void
    {
        $metadata = $this->metadata(Article::class);

        self::assertSame([], $metadata->getAssociationNames());
        self::assertArrayNotHasKey('uniqueConstraints', $metadata->table);
    }

    /**
     * The trait's private properties live in an unmapped parent: the field records the class that declares
     * them, and the row is written and read through it.
     */
    public function testAPropertyOfAnUnmappedParentIsReachedThroughThatParent(): void
    {
        $metadata = $this->metadata(NoteDimension::class);
        $row = $metadata->newInstance();

        self::assertSame(TemplatedDimension::class, $metadata->getFieldMapping('templateKey')->declared);
        $metadata->setFieldValue($row, 'templateKey', 'note');
        self::assertInstanceOf(NoteDimension::class, $row);
        self::assertSame('note', $row->getTemplateKey());
    }

    /**
     * A field the class maps itself is left as it is mapped, and the other fields of the capability are still
     * added.
     */
    public function testAFieldTheClassMapsItselfIsKeptAndTheOthersAreAdded(): void
    {
        $metadata = $this->metadata(BriefDimension::class);

        self::assertSame('layout', $metadata->getColumnName('templateKey'));
        self::assertSame(200, $metadata->getFieldMapping('templateKey')->length);
        self::assertSame('template_data', $metadata->getColumnName('templateData'));
    }

    /**
     * The unique constraint names `root_id`: the join column keeps that name whatever the application's
     * naming strategy would make of it.
     */
    public function testTheJoinColumnIsRootIdWhateverTheNamingStrategy(): void
    {
        $metadata = EntityManagerFactory::create(namingStrategy: new ForeignKeyPrefixNamingStrategy())->getClassMetadata(ArticleDimension::class);

        self::assertSame('root_id', $metadata->getSingleAssociationJoinColumnName('root'));
    }

    /**
     * A class that maps `root` itself keeps its join column, and the unique constraint names that column.
     */
    public function testARootAssociationTheClassMapsItselfKeepsItsJoinColumn(): void
    {
        $entityManager = EntityManagerFactory::create();
        $metadata = new ClassMetadata(MemoDimension::class);
        $metadata->initializeReflection($entityManager->getMetadataFactory()->getReflectionService());
        $metadata->mapManyToOne([
            'fieldName' => 'root',
            'targetEntity' => Memo::class,
            'joinColumns' => [['name' => 'memo_id', 'referencedColumnName' => 'id', 'nullable' => false]],
        ]);
        $listener = new DimensionContentMappingListener(ContentRoots::fromClassNames([Memo::class]), []);

        $listener->loadClassMetadata(new LoadClassMetadataEventArgs($metadata, $entityManager));

        self::assertSame('memo_id', $metadata->getSingleAssociationJoinColumnName('root'));
        self::assertSame([['columns' => ['memo_id', 'stage', 'version', 'locale_key']]], array_values($metadata->table['uniqueConstraints'] ?? []));
    }

    public function testAUniqueConstraintTheClassDeclaresIsKept(): void
    {
        $constraints = $this->metadata(NoteDimension::class)->table['uniqueConstraints'] ?? [];

        $own = $constraints['phil_content_probe_note_template'] ?? null;
        self::assertIsArray($own);
        self::assertSame(['template_key'], $own['columns'] ?? null);
        self::assertContains(['columns' => ['root_id', 'stage', 'version', 'locale_key']], $constraints);
        self::assertCount(2, $constraints);
    }

    /**
     * Loaded twice, a class is mapped once: no second association, field or constraint.
     */
    public function testMappingAClassAgainChangesNothing(): void
    {
        $entityManager = EntityManagerFactory::create();
        $metadata = $entityManager->getClassMetadata(ArticleDimension::class);
        $listener = new DimensionContentMappingListener(
            ContentRoots::fromClassNames([Article::class]),
            [new TemplateCapabilityMapping(), new WorkflowCapabilityMapping()],
        );

        $listener->loadClassMetadata(new LoadClassMetadataEventArgs($metadata, $entityManager));

        self::assertCount(1, $metadata->table['uniqueConstraints'] ?? []);
        self::assertSame(['root'], $metadata->getAssociationNames());
    }

    /**
     * The capability mappings may come as a generator, as a tagged iterator does: every class still sees all.
     */
    public function testCapabilityMappingsGivenAsAGeneratorServeEveryClass(): void
    {
        $entityManager = EntityManagerFactory::create(capabilities: (static function (): iterable {
            yield new TemplateCapabilityMapping();
            yield new WorkflowCapabilityMapping();
        })());

        self::assertTrue($entityManager->getClassMetadata(PageDimension::class)->hasField('templateKey'));
        self::assertTrue($entityManager->getClassMetadata(ArticleDimension::class)->hasField('workflowPlace'));
    }

    public function testAnEntityThatImplementsACapabilityWithoutItsPropertyIsRefused(): void
    {
        $this->expectException(InvalidContentMapping::class);
        $this->expectExceptionMessageIsOrContains('has no property "templateKey"');

        $this->metadata(CapableWithoutTrait::class);
    }

    public function testARowClassOfNoDeclaredRootIsRefused(): void
    {
        $this->expectException(InvalidContentMapping::class);
        $this->expectExceptionMessageIsOrContains(OrphanDimension::class);

        $this->metadata(OrphanDimension::class);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return ClassMetadata<T>
     */
    private function metadata(string $class): ClassMetadata
    {
        return EntityManagerFactory::create()->getClassMetadata($class);
    }
}
