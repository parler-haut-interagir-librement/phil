<?php

declare(strict_types=1);

namespace Phil\LicensingBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\Persistence\Mapping\RuntimeReflectionService;
use Phil\LicensingBundle\Domain\Catalog\LicenseCatalog;
use Phil\LicensingBundle\Domain\Catalog\ResourceKind;
use Phil\LicensingBundle\Domain\Exception\InvalidLicensing;
use Phil\LicensingBundle\Domain\Model\LicenseDeclaration;
use Phil\LicensingBundle\Domain\ValueObject\Attribution;
use Phil\LicensingBundle\Domain\ValueObject\SubjectReference;
use Phil\LicensingBundle\Tests\Unit\Support\Declarations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LicenseDeclaration::class)]
final class LicenseDeclarationTest extends TestCase
{
    public function testADeclaration(): void
    {
        $at = Declarations::at();
        $declaration = LicenseDeclaration::declare(SubjectReference::of('book', '7'), new LicenseCatalog()->get(LicenseCatalog::OGL), $at);

        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $declaration->id);
        self::assertTrue($declaration->subject()->equals(SubjectReference::of('book', '7')));
        self::assertSame('ogl-1.0a', $declaration->license());
        self::assertSame($at, $declaration->declaredAt);
        self::assertSame($at, $declaration->changedAt());
        self::assertSame(1, $declaration->version());
        self::assertNull($declaration->notice());
        self::assertNull($declaration->reservedMaterial());
        self::assertNull($declaration->productIdentity());
        self::assertNull($declaration->designatedContent());
        self::assertNull($declaration->licenseText());
        self::assertSame([], $declaration->attributions());
        self::assertNotSame($declaration->id, Declarations::of(LicenseCatalog::OGL)->id);
    }

    public function testEachChangeIsDated(): void
    {
        $declaration = Declarations::of(LicenseCatalog::ORC);

        $declaration->relicense(new LicenseCatalog()->get(LicenseCatalog::CC_BY), $first = new DateTimeImmutable('2026-11-09'));
        self::assertSame('cc-by-4.0', $declaration->license());
        self::assertSame($first, $declaration->changedAt());

        $declaration->describe(' © 2026 ', ' Names ', ' Logos ', ' Rules ', $second = new DateTimeImmutable('2026-11-10'));
        self::assertSame('© 2026', $declaration->notice());
        self::assertSame('Names', $declaration->reservedMaterial());
        self::assertSame('Logos', $declaration->productIdentity());
        self::assertSame('Rules', $declaration->designatedContent());
        self::assertSame($second, $declaration->changedAt());

        $declaration->includeLicenseText(' Terms ', $third = new DateTimeImmutable('2026-11-11'));
        self::assertSame('Terms', $declaration->licenseText());
        self::assertSame($third, $declaration->changedAt());

        $attributions = [Attribution::of(ResourceKind::Image, 'Cover', 'cc0-1.0'), Attribution::of(ResourceKind::Text, 'SRD', 'orc', 'Example')];
        $declaration->attribute($attributions, $fourth = new DateTimeImmutable('2026-11-12'));
        self::assertEquals($attributions, $declaration->attributions());
        self::assertSame($fourth, $declaration->changedAt());

        $declaration->describe(null, ' ', null, null, $fourth);
        self::assertNull($declaration->notice());
        self::assertNull($declaration->reservedMaterial());
    }

    public function testTheLimits(): void
    {
        $declaration = Declarations::of(LicenseCatalog::ORC);
        $declaration->describe(str_repeat('n', 4000), str_repeat('r', 20000), str_repeat('p', 20000), str_repeat('d', 20000), Declarations::at());
        $declaration->includeLicenseText(str_repeat('t', 100000), Declarations::at());
        $declaration->attribute(array_fill(0, 500, Attribution::of(ResourceKind::Image, 'Cover', 'cc0-1.0')), Declarations::at());

        self::assertCount(500, $declaration->attributions());

        foreach ([
            'The notice holds 4000 characters at most.' => static fn () => $declaration->describe(str_repeat('n', 4001), null, null, null, Declarations::at()),
            'The reserved material holds 20000 characters at most.' => static fn () => $declaration->describe(null, str_repeat('r', 20001), null, null, Declarations::at()),
            'The product identity holds 20000 characters at most.' => static fn () => $declaration->describe(null, null, str_repeat('p', 20001), null, Declarations::at()),
            'The designated content holds 20000 characters at most.' => static fn () => $declaration->describe(null, null, null, str_repeat('d', 20001), Declarations::at()),
            'The license text holds 100000 characters at most.' => static fn () => $declaration->includeLicenseText(str_repeat('t', 100001), Declarations::at()),
            'A declaration holds 500 attributions at most.' => static fn () => $declaration->attribute(array_fill(0, 501, Attribution::of(ResourceKind::Image, 'Cover', 'cc0-1.0')), Declarations::at()),
        ] as $message => $change) {
            try {
                $change();
                self::fail($message);
            } catch (InvalidLicensing $refused) {
                self::assertSame($message, $refused->getMessage());
            }
        }
    }

    public function testTheTable(): void
    {
        $metadata = new ClassMetadata(LicenseDeclaration::class);
        $metadata->initializeReflection(new RuntimeReflectionService());
        new AttributeDriver([\dirname(__DIR__, 3).'/src/Domain/Model'])->loadMetadataForClass(LicenseDeclaration::class, $metadata);
        $columns = [];

        foreach ($metadata->fieldMappings as $field => $mapping) {
            $columns[$field] = [$mapping->columnName, $mapping->type, $mapping->length, $mapping->nullable];
        }

        self::assertSame('phil_licensing_declaration', $metadata->getTableName());
        self::assertSame(['id'], $metadata->getIdentifierFieldNames());
        self::assertSame([
            'license' => ['license', 'string', 64, false],
            'notice' => ['notice', 'text', null, true],
            'reservedMaterial' => ['reserved_material', 'text', null, true],
            'productIdentity' => ['product_identity', 'text', null, true],
            'designatedContent' => ['designated_content', 'text', null, true],
            'licenseText' => ['license_text', 'text', null, true],
            'attributions' => ['attributions', 'json', null, false],
            'changedAt' => ['changed_at', 'datetime_immutable', null, false],
            'version' => ['version', 'integer', null, false],
            'id' => ['id', 'string', 36, false],
            'subjectType' => ['subject_type', 'string', 128, false],
            'subjectId' => ['subject_id', 'string', 64, false],
            'declaredAt' => ['declared_at', 'datetime_immutable', null, false],
        ], $columns);
        self::assertSame(['phil_licensing_declaration_subject' => ['columns' => ['subject_type', 'subject_id']]], $metadata->table['uniqueConstraints'] ?? null);
        self::assertTrue($metadata->isVersioned);
        self::assertTrue($metadata->fieldMappings['id']->options['fixed'] ?? null);
    }
}
