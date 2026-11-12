<?php

declare(strict_types=1);

namespace Phil\SearchBundle\Tests\Functional;

use Override;
use Phil\SearchBundle\Infrastructure\Twig\Components\SearchFiltersComponent;
use Phil\SearchBundle\PhilSearchBundle;
use Phil\SearchBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * The results page without JavaScript: the form, its boxes and their counts, the links that remove the filters, and
 * the synonyms read from the directory of the application.
 */
#[CoversClass(PhilSearchBundle::class)]
#[CoversClass(SearchFiltersComponent::class)]
final class FiltersPageTest extends WebTestCase
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
    }

    public function testThePageDrawsTheFiltersWithTheRecipesOfTheKit(): void
    {
        $page = $this->page('/games');

        self::assertSame('5 résultats', trim($page->filter('[role=status]')->text()));
        self::assertSame('polite', $page->filter('[role=status]')->attr('aria-live'));
        self::assertSame('phil--search-bundle--filters', $page->filter('#phil-search-filters')->attr('data-controller'));
        self::assertSame('results', $page->filter('#phil-search-filters')->attr('data-phil--search-bundle--filters-results-value'));
        self::assertSame(['Genre', 'Année'], $page->filter('fieldset[data-slot=field-set] legend')->each(static fn (Crawler $legend): string => trim($legend->text())));
        self::assertSame(['fantasy', 'jdr', 'cartes', 'dés', 'plateau', 'sf', '2026', '2024', '2025'], $page->filter('input[type=checkbox]')->each(static fn (Crawler $box): string => (string) $box->attr('value')));
        self::assertSame(['filter[genre][]'], array_values(array_unique($page->filter('fieldset')->first()->filter('input')->each(static fn (Crawler $box): string => (string) $box->attr('name')))));
        self::assertSame('Jeu de rôle 2 résultats2', preg_replace('/\s+/', ' ', trim($page->filter('label[for="'.$page->filter('input[value=jdr]')->attr('id').'"]')->text())));
        self::assertSame('isBadge isSecondary', $page->filter('[data-slot=badge]')->first()->attr('class'));
        self::assertSame('submit', $page->filter('button[data-phil--search-bundle--filters-target=submit]')->attr('type'));
        self::assertCount(0, $page->filter('.phil-search-filters__chosen'));
        self::assertCount(0, $page->filter('.phil-search-filters__form input[name=q]'), 'no words, no field');
        self::assertSame(['Un jeu de rôle', 'Un JDR de science-fiction', 'Un jeu de plateau', 'Un jeu de cartes', 'Un jeu de dés'], $page->filter('#results li')->each(static fn (Crawler $item): string => $item->text()));
    }

    public function testTheFiltersAreAppliedAndRemovedByPlainLinks(): void
    {
        $client = self::createClient();
        $page = $client->request('GET', '/games');
        $form = $page->filter('.phil-search-filters__form')->form();
        $boxes = $form['filter[genre]'];
        self::assertIsArray($boxes);
        self::assertInstanceOf(ChoiceFormField::class, $boxes[1]);
        $boxes[1]->tick();
        $page = $client->submit($form);

        self::assertSame(['genre' => ['jdr']], array_map(static fn (mixed $values): array => \is_array($values) ? array_values($values) : [], $client->getRequest()->query->all('filter')));
        self::assertSame('2 résultats', trim($page->filter('[role=status]')->text()));
        self::assertSame(['Un jeu de rôle', 'Un JDR de science-fiction'], $page->filter('#results li')->each(static fn (Crawler $item): string => $item->text()));
        self::assertNotNull($page->filter('input[value=jdr]')->attr('checked'));
        self::assertNull($page->filter('input[value=plateau]')->attr('checked'));
        self::assertSame(['2', '2', '1'], \array_slice($page->filter('input[name="filter[genre][]"] + label [aria-hidden]')->each(static fn (Crawler $count): string => $count->text()), 0, 3), 'the genres counted without their own filter');
        self::assertSame(['Retirer le filtre Genre : Jeu de rôle ×'], $page->filter('.phil-search-filters__chips a')->each(static fn (Crawler $link): string => preg_replace('/\s+/', ' ', trim($link->text())) ?? ''));
        self::assertSame('?', $page->filter('.phil-search-filters__chips a')->attr('href'));

        $page = $client->request('GET', '/games?q=jdr&filter[genre][]=jdr&filter[year][]=2026');
        self::assertSame('Un résultat', trim($page->filter('[role=status]')->text()), 'jdr reads as jeu de rôle too');
        self::assertSame('jdr', $page->filter('.phil-search-filters__form input[name=q]')->attr('value'));
        self::assertSame(['Un jeu de rôle'], $page->filter('#results li')->each(static fn (Crawler $item): string => $item->text()));

        $page = $client->click($page->selectLink('Retirer tous les filtres')->link());
        self::assertSame('/games?q=jdr', $client->getRequest()->getRequestUri());
        self::assertSame('2 résultats', trim($page->filter('[role=status]')->text()));

        $page = $client->request('GET', '/games?filter[genre][]=jdr&filter[year][]=2026');
        $page = $client->click($page->filter('.phil-search-filters__chips a')->eq(1)->link());
        self::assertSame('/games?filter%5Bgenre%5D%5B0%5D=jdr', $client->getRequest()->getRequestUri());
        self::assertSame('2 résultats', trim($page->filter('[role=status]')->text()));

        $page = $client->request('GET', '/games?filter[genre][]=nothing');
        self::assertSame('Aucun résultat', trim($page->filter('[role=status]')->text()));
        self::assertNotNull($page->filter('input[value=nothing]')->attr('checked'), 'a value chosen and found nowhere can still be unticked');
    }

    private function page(string $uri): Crawler
    {
        $client = self::createClient();
        $page = $client->request('GET', $uri);
        self::assertResponseIsSuccessful();

        return $page;
    }
}
