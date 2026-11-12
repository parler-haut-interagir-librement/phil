<?php

declare(strict_types=1);

namespace Phil\SearchBundle\Tests\Unit\Infrastructure;

use LogicException;
use Phil\SearchBundle\Application\SearchRequest;
use Phil\SearchBundle\Domain\ValueObject\SearchResult;
use Phil\SearchBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Phil\SearchBundle\Infrastructure\Twig\Components\SearchFiltersComponent;
use Phil\SearchBundle\Infrastructure\Twig\KitComponents;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[CoversClass(SearchFiltersComponent::class)]
#[CoversClass(KitComponents::class)]
#[CoversClass(KitSubdirectoryPass::class)]
final class FiltersComponentTest extends TestCase
{
    public function testEachFacetShowsItsValuesAndTheValuesChosen(): void
    {
        $component = new SearchFiltersComponent(new KitComponents('ui'));
        $component->request = SearchRequest::fromQuery(['q' => 'jeu', 'filter' => ['genre' => ['jdr', 'gone'], 'year' => ['2026']]], ['genre', 'year']);
        $component->result = new SearchResult([], 3, ['genre' => ['jdr' => 2, 'plateau' => 1], 'year' => ['2026' => 3], 'locale' => ['fr' => 3]]);
        $component->labels = ['genre' => 'Genre', 'year' => 'Année', 'empty' => 'Vide'];
        $component->valueLabels = ['genre' => ['jdr' => 'Jeu de rôle']];

        $id = static fn (string $field, string $value): string => 'phil-search-filters-'.$field.'-'.hash('xxh3', $value);

        self::assertSame('ui:Checkbox', $component->kit('Checkbox'));
        self::assertSame([
            ['field' => 'genre', 'label' => 'Genre', 'values' => [
                ['value' => 'jdr', 'label' => 'Jeu de rôle', 'count' => 2, 'checked' => true, 'id' => $id('genre', 'jdr')],
                ['value' => 'plateau', 'label' => 'plateau', 'count' => 1, 'checked' => false, 'id' => $id('genre', 'plateau')],
                ['value' => 'gone', 'label' => 'gone', 'count' => 0, 'checked' => true, 'id' => $id('genre', 'gone')],
            ]],
            ['field' => 'year', 'label' => 'Année', 'values' => [
                ['value' => '2026', 'label' => '2026', 'count' => 3, 'checked' => true, 'id' => $id('year', '2026')],
            ]],
        ], $component->facets());
        self::assertSame([
            ['facet' => 'Genre', 'label' => 'Jeu de rôle', 'value' => 'jdr', 'href' => '?q=jeu&filter%5Bgenre%5D%5B0%5D=gone&filter%5Byear%5D%5B0%5D=2026'],
            ['facet' => 'Genre', 'label' => 'gone', 'value' => 'gone', 'href' => '?q=jeu&filter%5Bgenre%5D%5B0%5D=jdr&filter%5Byear%5D%5B0%5D=2026'],
            ['facet' => 'Année', 'label' => '2026', 'value' => '2026', 'href' => '?q=jeu&filter%5Bgenre%5D%5B0%5D=jdr&filter%5Bgenre%5D%5B1%5D=gone'],
        ], $component->chosen());
        self::assertSame('?q=jeu', $component->clearHref());

        $component->labels = ['genre' => 'Genre'];
        $component->request = SearchRequest::fromQuery(['filter' => ['year' => ['2026']]], ['year']);
        self::assertSame([['facet' => 'year', 'label' => '2026', 'value' => '2026', 'href' => '?']], $component->chosen());
    }

    public function testTheRecipesAreNamedAfterTheToolkitSubdirectory(): void
    {
        self::assertSame(['Badge', 'ui:Badge', 'ui:kit:Field:Set'], [new KitComponents()->name('Badge'), new KitComponents('/ui/')->name('Badge'), new KitComponents('ui/kit')->name('Field:Set')]);

        $container = new ContainerBuilder();
        new KitSubdirectoryPass()->process($container);
        self::assertFalse($container->hasDefinition(KitComponents::class), 'without the component, nothing to name');

        $container->register(KitComponents::class, KitComponents::class)->setArguments([null]);
        $container->setParameter('.ux_toolkit.components.subdirectory', 'ui');
        new KitSubdirectoryPass()->process($container);
        self::assertSame('ui', $container->getDefinition(KitComponents::class)->getArgument(0));

        $container->getDefinition(KitComponents::class)->setArguments(['mine']);
        new KitSubdirectoryPass()->process($container);
        self::assertSame('mine', $container->getDefinition(KitComponents::class)->getArgument(0));

        $bare = new ContainerBuilder();
        $bare->register(KitComponents::class, KitComponents::class)->setArguments([null]);
        $bare->setParameter('.ux_toolkit.components.subdirectory', null);
        new KitSubdirectoryPass()->process($bare);
        self::assertSame('', $bare->getDefinition(KitComponents::class)->getArgument(0));
    }

    public function testTheRequestAndTheResultAreGivenOrAnExceptionSaysSo(): void
    {
        $component = new SearchFiltersComponent(new KitComponents());
        $component->result = new SearchResult([], 0);

        try {
            $component->text();
            self::fail('No request was given.');
        } catch (LogicException $logicException) {
            self::assertSame('PhilSearchFilters is given the request of the page: :request="request".', $logicException->getMessage());
        }

        $component->request = SearchRequest::fromQuery([], []);
        $component->result = null;

        try {
            $component->total();
            self::fail('No result was given.');
        } catch (LogicException $logicException) {
            self::assertSame('PhilSearchFilters is given the result of the search: :result="result".', $logicException->getMessage());
        }
    }
}
