<?php

declare(strict_types=1);

namespace Phil\TaxonomyBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\Resource\ResourceReference;
use Phil\TaxonomyBundle\Application\Taxonomy;
use Phil\TaxonomyBundle\Domain\Model\Tag;
use Phil\TaxonomyBundle\Infrastructure\Form\CategoryType;
use Phil\TaxonomyBundle\PhilTaxonomyBundle;
use Phil\TaxonomyBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\ChoiceList\View\ChoiceView;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[CoversClass(PhilTaxonomyBundle::class)]
final class TaxonomyBundleTest extends KernelTestCase
{
    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        if (!ContainerBuilder::willBeAvailable('symfony/event-dispatcher', EventDispatcher::class, ['symfony/dependency-injection'])) {
            self::markTestSkipped('The event dispatcher is installed for development only: no kernel can boot.');
        }

        self::bootKernel();
        $entityManager = $this->service('doctrine.orm.entity_manager', EntityManagerInterface::class);
        new SchemaTool($entityManager)->createSchema($entityManager->getMetadataFactory()->getAllMetadata());
    }

    public function testTagsAndCategoriesAreStoredAndPicked(): void
    {
        $taxonomy = $this->service(Taxonomy::class, Taxonomy::class);
        $page = ResourceReference::of('page', 'about');
        $taxonomy->setTags($page, ['Jeux', 'soirée']);
        $games = $taxonomy->createCategory('games', null, ['fr' => 'Jeux', 'en' => 'Games']);
        $taxonomy->categorize($page, [$games]);
        $this->service('doctrine.orm.entity_manager', EntityManagerInterface::class)->clear();

        self::assertSame(['Jeux', 'soirée'], array_map(static fn (Tag $tag): string => $tag->name(), $taxonomy->tagsOf($page)));
        self::assertSame(['page:about'], array_map(static fn (ResourceReference $reference): string => $reference->toString(), $taxonomy->categorizedIn($games)));

        $form = $this->service('test.form.factory', FormFactoryInterface::class)->create(CategoryType::class, null, ['locale' => 'fr_CA']);
        $choices = $form->createView()->vars['choices'];
        self::assertIsArray($choices);
        self::assertInstanceOf(ChoiceView::class, $choices[0] ?? null);
        self::assertSame('Jeux', $choices[0]->label);
        self::assertSame('Séparées par des virgules.', $this->service('test.translator', TranslatorInterface::class)->trans('taxonomy.help.tags', [], 'phil_taxonomy'));
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function service(string $id, string $class): object
    {
        $service = self::getContainer()->get($id);
        self::assertInstanceOf($class, $service);

        return $service;
    }
}
