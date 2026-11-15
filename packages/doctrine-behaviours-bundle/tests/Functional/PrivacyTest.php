<?php

declare(strict_types=1);

namespace Phil\DoctrineBehavioursBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\DoctrineBehaviours\OwnerId;
use Phil\DoctrineBehavioursBundle\Infrastructure\Privacy\BlamePrivacy;
use Phil\DoctrineBehavioursBundle\Infrastructure\Privacy\OwnerPrivacy;
use Phil\DoctrineBehavioursBundle\Infrastructure\Privacy\Stores;
use Phil\DoctrineBehavioursBundle\Tests\Functional\App\Entity\Notice;
use Phil\DoctrineBehavioursBundle\Tests\Functional\App\Entity\Page;
use Phil\DoctrineBehavioursBundle\Tests\Functional\App\TestKernel;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[CoversClass(BlamePrivacy::class)]
#[CoversClass(OwnerPrivacy::class)]
#[CoversClass(Stores::class)]
final class PrivacyTest extends KernelTestCase
{
    private EntityManagerInterface $manager;

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        self::bootKernel();
        $manager = self::getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $manager);
        new SchemaTool($manager)->createSchema($manager->getMetadataFactory()->getAllMetadata());
        $this->manager = $manager;
    }

    public function testTheBlameIsExportedAndErased(): void
    {
        $page = new Page('home');
        $page->blameCreated('ada');

        $this->manager->persist($page);
        $this->manager->persist(new Page('about'));
        $this->manager->flush();

        $privacy = self::getContainer()->get('test.blame_privacy');
        self::assertInstanceOf(BlamePrivacy::class, $privacy);
        $ada = DataSubject::fromString('ada');

        self::assertSame('behaviours.blame', $privacy->module()->value);
        self::assertSame(['rows_by_table' => ['page' => 1]], $privacy->export($ada));
        self::assertSame([], $privacy->export(DataSubject::fromString('grace')));

        $report = $privacy->erase($ada);
        self::assertSame('behaviours.blame', $report->module->value);
        self::assertSame(['page' => ErasureEffect::Redacted, 'phil_record_version' => ErasureEffect::Redacted], $report->effects, 'every blamed table, the frozen versions too');
        self::assertSame([], $privacy->export($ada));
        self::assertSame($report->effects, $privacy->erase($ada)->effects, 'a second run reports the same');
        self::assertNull($this->manager->getConnection()->fetchOne("SELECT created_by FROM page WHERE id = 'home'"));
    }

    public function testTheOwnerIsExportedAndHandedToThePseudonym(): void
    {
        $notice = new Notice('Bonjour');
        $notice->claimOwner(new OwnerId('ada'));

        $this->manager->persist($notice);
        $this->manager->flush();

        $privacy = self::getContainer()->get('test.owner_privacy');
        self::assertInstanceOf(OwnerPrivacy::class, $privacy);
        $ada = DataSubject::fromString('ada');

        self::assertSame('behaviours.owner', $privacy->module()->value);
        self::assertSame(['rows_by_table' => ['notice' => 1]], $privacy->export($ada));

        $report = $privacy->erase($ada);
        self::assertSame('behaviours.owner', $report->module->value);
        self::assertSame(['notice' => ErasureEffect::Pseudonymised], $report->effects);
        self::assertSame([], $privacy->export($ada));
        self::assertSame('former', $this->manager->getConnection()->fetchOne('SELECT owner_id FROM notice'));
    }

    public function testASubjectThatCannotOwnOwnsNothing(): void
    {
        $privacy = self::getContainer()->get('test.owner_privacy');
        self::assertInstanceOf(OwnerPrivacy::class, $privacy);
        $subject = DataSubject::fromString('ada@example.org');

        self::assertSame([], $privacy->export($subject));
        self::assertSame(['notice' => ErasureEffect::Pseudonymised], $privacy->erase($subject)->effects);
    }

    public function testAReportNamesTheModuleWhenNoTableHasTheBehaviour(): void
    {
        self::assertSame(['behaviours.blame' => ErasureEffect::Redacted], Stores::of([], 'behaviours.blame', ErasureEffect::Redacted));
        self::assertSame(['a' => ErasureEffect::Deleted, 'b' => ErasureEffect::Deleted], Stores::of(['a', 'b'], 'm', ErasureEffect::Deleted));
    }
}
