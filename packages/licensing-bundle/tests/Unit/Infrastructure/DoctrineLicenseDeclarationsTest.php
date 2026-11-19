<?php

declare(strict_types=1);

namespace Phil\LicensingBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Tools\SchemaTool;
use Phil\LicensingBundle\Domain\Catalog\LicenseCatalog;
use Phil\LicensingBundle\Domain\Model\LicenseDeclaration;
use Phil\LicensingBundle\Domain\ValueObject\SubjectReference;
use Phil\LicensingBundle\Infrastructure\Doctrine\DoctrineLicenseDeclarations;
use Phil\LicensingBundle\Tests\Unit\Support\Declarations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The declarations in an SQLite database in memory, read back by a fresh unit of work.
 */
#[CoversClass(DoctrineLicenseDeclarations::class)]
final class DoctrineLicenseDeclarationsTest extends TestCase
{
    public function testADeclarationIsKeptFoundAndRemoved(): void
    {
        $manager = $this->entityManager();
        $declarations = new DoctrineLicenseDeclarations($manager);
        $declaration = Declarations::complete();
        $other = Declarations::of(LicenseCatalog::CC0, '43');

        $declarations->add($declaration);
        $declarations->add($other);

        $manager->flush();
        $manager->clear();

        $found = $declarations->declarationOf(SubjectReference::of('book', '42'));
        self::assertInstanceOf(LicenseDeclaration::class, $found);
        self::assertSame($declaration->id, $found->id);
        self::assertEquals($declaration->attributions(), $found->attributions());
        self::assertSame('© 2026 Example Press', $found->notice());
        self::assertSame($other->id, $declarations->declarationOf(SubjectReference::of('book', '43'))?->id);
        self::assertNull($declarations->declarationOf(SubjectReference::of('page', '42')));
        self::assertNull($declarations->declarationOf(SubjectReference::of('book', '44')));

        $declarations->remove($found);
        $manager->flush();
        $manager->clear();

        self::assertNull($declarations->declarationOf(SubjectReference::of('book', '42')));
        self::assertNotNull($declarations->declarationOf(SubjectReference::of('book', '43')));
    }

    private function entityManager(): EntityManager
    {
        $config = new Configuration();
        $config->setMetadataDriverImpl(new AttributeDriver([\dirname(__DIR__, 3).'/src/Domain/Model']));
        $config->enableNativeLazyObjects(true);

        $manager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config), $config);
        new SchemaTool($manager)->createSchema($manager->getMetadataFactory()->getAllMetadata());

        return $manager;
    }
}
