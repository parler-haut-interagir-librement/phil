<?php

declare(strict_types=1);

namespace Phil\LicensingBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\LicensingBundle\Application\Exception\ExportBlocked;
use Phil\LicensingBundle\Application\Exception\PrintSubjectRequired;
use Phil\LicensingBundle\PhilLicensingBundle;
use Phil\LicensingBundle\Tests\Functional\App\StrictPrintingKernel;
use Phil\PagedMedia\Pdf\Document;
use Phil\PagedMedia\Pdf\Metadata;
use Phil\PagedMedia\Pdf\PageSize;
use Phil\PagedMediaBundle\Application\PagedMedia;
use Phil\PagedMediaBundle\Application\PrintSubject;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The strict printing in an application: a print that names no work is refused before anything is rendered, an
 * unlicensed subject is printed, and a work without a declaration is still guarded.
 */
#[CoversClass(PhilLicensingBundle::class)]
final class StrictPrintingTest extends KernelTestCase
{
    #[Override]
    protected static function getKernelClass(): string
    {
        return StrictPrintingKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        self::bootKernel();
        $manager = self::getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $manager);
        new SchemaTool($manager)->createSchema($manager->getMetadataFactory()->getAllMetadata());
    }

    public function testAPrintThatNamesNoWorkIsRefused(): void
    {
        $translator = self::getContainer()->get('translator');
        self::assertInstanceOf(TranslatorInterface::class, $translator);

        try {
            $this->pagedMedia()->render($this->document(), 'book.pdf');
            self::fail('The print was not refused.');
        } catch (PrintSubjectRequired $printSubjectRequired) {
            self::assertStringStartsWith('Cette impression ne nomme aucune œuvre', $printSubjectRequired->trans($translator, 'fr'));
        }
    }

    public function testAnUnlicensedSubjectIsPrintedAndAWorkIsStillGuarded(): void
    {
        self::assertStringStartsWith('%PDF', $this->pagedMedia()->render($this->document(), 'invoice.pdf', subject: new PrintSubject('invoice', 'F-12'))->contents);

        $this->expectException(ExportBlocked::class);

        $this->pagedMedia()->render($this->document(), 'book.pdf', subject: new PrintSubject('book', '42'));
    }

    private function pagedMedia(): PagedMedia
    {
        $pagedMedia = self::getContainer()->get(PagedMedia::class);
        self::assertInstanceOf(PagedMedia::class, $pagedMedia);

        return $pagedMedia;
    }

    private function document(): Document
    {
        $document = new Document(PageSize::a5(), new Metadata(title: 'Invoice'), false);
        $document->addPage();

        return $document;
    }
}
