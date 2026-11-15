<?php

declare(strict_types=1);

namespace Phil\DoctrineBehavioursBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\DoctrineBehaviours\DeactivatedFilter;
use Phil\DoctrineBehaviours\Positions;
use Phil\DoctrineBehaviours\SoftDeletedFilter;
use Phil\DoctrineBehaviours\Translations;
use Phil\DoctrineBehaviours\Tree;
use Phil\DoctrineBehaviours\Versions;
use Phil\DoctrineBehavioursBundle\PhilDoctrineBehavioursBundle;
use Phil\DoctrineBehavioursBundle\Tests\Functional\App\Entity\Article;
use Phil\DoctrineBehavioursBundle\Tests\Functional\App\Entity\Faq;
use Phil\DoctrineBehavioursBundle\Tests\Functional\App\Entity\FaqTranslation;
use Phil\DoctrineBehavioursBundle\Tests\Functional\App\Entity\Notice;
use Phil\DoctrineBehavioursBundle\Tests\Functional\App\Entity\Page;
use Phil\DoctrineBehavioursBundle\Tests\Functional\App\Entity\Step;
use Phil\DoctrineBehavioursBundle\Tests\Functional\App\TestKernel;
use Phil\LocaleDimensions\FallbackChain;
use Phil\LocaleDimensions\Locale;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Uid\UuidV7;
use Symfony\Component\Workflow\Registry;

#[CoversClass(PhilDoctrineBehavioursBundle::class)]
final class DoctrineBehavioursBundleTest extends KernelTestCase
{
    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    public function testEveryBehaviourWorksInTheApplication(): void
    {
        self::bootKernel();
        $manager = $this->service('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $manager);
        new SchemaTool($manager)->createSchema($manager->getMetadataFactory()->getAllMetadata());

        $home = new Page('home');
        $about = new Page('about', $home);
        $manager->persist($home);
        $manager->persist($about);
        $manager->flush();

        self::assertNotNull($about->createdAt());
        self::assertNull($about->createdBy());
        self::assertSame('/home/about/', $about->treePath());
        self::assertTrue($manager->getFilters()->isEnabled(SoftDeletedFilter::NAME));

        $manager->remove($about);
        $manager->flush();
        $manager->clear();

        self::assertNull($manager->find(Page::class, 'about'));
        $tree = $this->service(Tree::class);
        self::assertInstanceOf(Tree::class, $tree);
        self::assertCount(1, $tree->roots(Page::class));
    }

    public function testTheAddedBehavioursWorkInTheApplication(): void
    {
        self::bootKernel();
        $manager = $this->service('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $manager);
        new SchemaTool($manager)->createSchema($manager->getMetadataFactory()->getAllMetadata());

        $notice = new Notice('Bonjour tout le monde');
        $twin = new Notice('Bonjour tout le monde');
        $manager->persist($notice);
        $manager->persist($twin);
        $manager->flush();

        self::assertTrue(UuidV7::isValid((string) $notice->datedId()));
        self::assertSame(['bonjour-tout-le-monde', 'bonjour-tout-le-monde-2'], [$notice->slug(), $twin->slug()]);
        self::assertNull($notice->ownerId());
        self::assertTrue($manager->getFilters()->isEnabled(DeactivatedFilter::NAME));

        // What phil/scheduled-transitions-bundle does for a transition: the registry, by the subject, then the name.
        $workflows = $this->service('test.workflow_registry');
        self::assertInstanceOf(Registry::class, $workflows);
        self::assertTrue($workflows->has($notice));
        $workflows->get($notice)->apply($notice, 'publish');
        self::assertTrue($notice->isPublished());
        self::assertNotNull($notice->publishedAt());
        $manager->flush();

        $versions = $this->service(Versions::class);
        self::assertInstanceOf(Versions::class, $versions);
        self::assertSame(1, $versions->freeze($notice)->number());
        $notice->title = 'Autre';
        $manager->flush();
        self::assertSame(2, $notice->versionNumber());

        $twin->deactivate();
        $manager->flush();
        $hidden = (string) $twin->datedId();
        $manager->clear();
        self::assertNull($manager->find(Notice::class, $hidden));

        $this->expectException(AccessDeniedException::class);
        $reread = $manager->find(Notice::class, $notice->datedId());
        self::assertInstanceOf(Notice::class, $reread);
        $versions->restore($reread, 1);
    }

    public function testTranslationsWorkInTheApplication(): void
    {
        self::bootKernel();
        $manager = $this->service('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $manager);
        new SchemaTool($manager)->createSchema($manager->getMetadataFactory()->getAllMetadata());
        $translations = $this->service(Translations::class);
        self::assertInstanceOf(Translations::class, $translations);

        $faq = new Faq('opening-hours');
        $manager->persist($faq);
        $french = $translations->translate($faq, Locale::fromString('fr'));
        self::assertInstanceOf(FaqTranslation::class, $french);
        $french->question = 'Quand ouvrez-vous ?';
        $manager->flush();
        $manager->clear();

        $reread = $manager->find(Faq::class, 'opening-hours');
        self::assertInstanceOf(Faq::class, $reread);
        $served = $translations->find($reread, FallbackChain::of([Locale::fromString('fr_CA'), Locale::fromString('fr')]));
        self::assertNotNull($served);
        self::assertSame(['question' => 'Quand ouvrez-vous ?'], $translations->values($served));
        self::assertSame(['faq_translation_translatable_locale' => ['columns' => ['faq_id', 'locale']]], $manager->getClassMetadata(FaqTranslation::class)->table['uniqueConstraints'] ?? []);
    }

    public function testPositionsKeepEachGroupInAnOrderOfItsOwn(): void
    {
        self::bootKernel();
        $manager = $this->service('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $manager);
        new SchemaTool($manager)->createSchema($manager->getMetadataFactory()->getAllMetadata());

        $first = new Step('pack', 'trip');
        $second = new Step('lock', 'trip');
        $other = new Step('water', 'garden');
        $manager->persist($first);
        $manager->persist($second);
        $manager->persist($other);
        $manager->flush();

        self::assertSame([1, 2, 1], [$first->position(), $second->position(), $other->position()]);

        $positions = $this->service(Positions::class);
        self::assertInstanceOf(Positions::class, $positions);
        $positions->moveBefore($second, $first);
        $manager->flush();
        $manager->clear();

        $order = $manager->getRepository(Step::class)->findBy(['checklist' => 'trip'], ['position' => 'ASC']);
        self::assertSame(['lock', 'pack'], array_map(static fn (Step $step): string => $step->id, $order));
        self::assertSame(1, $manager->find(Step::class, 'water')?->position());
    }

    public function testASlugIsUniqueWithinTheScopeTheEntityGives(): void
    {
        self::bootKernel();
        $manager = $this->service('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $manager);
        new SchemaTool($manager)->createSchema($manager->getMetadataFactory()->getAllMetadata());

        $french = new Article('1', 'fr', 'Bienvenue');
        $english = new Article('2', 'en', 'Bienvenue');
        $twin = new Article('3', 'fr', 'Bienvenue');
        $manager->persist($french);
        $manager->persist($english);
        $manager->flush();
        $manager->persist($twin);
        $manager->flush();

        self::assertSame(['bienvenue', 'bienvenue', 'bienvenue-2'], [$french->slug(), $english->slug(), $twin->slug()]);
    }

    private function service(string $id): object
    {
        $service = self::getContainer()->get($id);
        self::assertIsObject($service);

        return $service;
    }
}
