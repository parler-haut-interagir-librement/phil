<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Tests\Functional;

use DOMElement;
use Override;
use Phil\DataTableBundle\Infrastructure\Export\TableExports;
use Phil\DataTableBundle\Infrastructure\Http\TableExportController;
use Phil\DataTableBundle\Infrastructure\Twig\Components\TableComponent;
use Phil\DataTableBundle\PhilDataTableBundle;
use Phil\DataTableBundle\Tests\Fixtures\RecordingAccess;
use Phil\DataTableBundle\Tests\Fixtures\Shelf;
use Phil\DataTableBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use Symfony\UX\LiveComponent\Test\TestLiveComponent;

#[CoversClass(PhilDataTableBundle::class)]
#[CoversClass(TableComponent::class)]
#[CoversClass(TableExportController::class)]
#[CoversClass(TableExports::class)]
final class DataTableBundleTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    private KernelBrowser $client;

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();
        $this->client->setServerParameter('HTTP_SEC_FETCH_SITE', 'same-origin');
    }

    public function testAPlainGetShowsTheStateOfTheAddress(): void
    {
        $page = $this->page('/books?sort=-title&page=2&size=10');

        self::assertSame('The books', $page->filter('table caption')->first()->text());
        self::assertStringStartsWith('Book 50', $page->filter('tbody tr th[scope=row]')->first()->text());
        self::assertSame('descending', $page->filter('thead th')->eq(1)->attr('aria-sort'));
        self::assertSame('60 résultats.', $page->filter('[role=status]')->first()->text());
        self::assertSame('page', $page->filter('nav [aria-current]')->first()->attr('aria-current'));
        self::assertSame('2', $page->filter('nav [aria-current]')->first()->text());
        self::assertStringContainsString('Pagination de The books', (string) $page->filter('nav')->first()->attr('aria-label'));
        self::assertSame('Lignes 11 à 20 sur 60', $page->filter('.phil-data-table__range')->first()->text());
    }

    public function testFiltersGoThroughTheAddressAndUnknownValuesAreDropped(): void
    {
        $page = $this->page('/books?filters[status]=draft&filters[q]=Book%200&filters[inPrint]=1&filters[nope]=x&sort=nope');

        self::assertSame('3 résultats.', $page->filter('[role=status]')->first()->text());
        self::assertStringStartsWith('Book 02', $page->filter('tbody tr th[scope=row]')->first()->text());
        self::assertSame('Book 0', $page->filter('input[type=search]')->first()->attr('value'));
        self::assertCount(0, $page->filter('section.phil-data-table')->first()->filter('input[type=hidden][name=sort]'));

        $none = $this->page('/books?filters[published_from]=2030-01-01');
        self::assertSame('Aucun résultat.', $none->filter('[role=status]')->first()->text());
        self::assertSame('Rien à afficher.', $none->filter('.phil-data-table__empty')->first()->text());
    }

    public function testTheCellsAreWrittenByTheirColumns(): void
    {
        $row = $this->page('/books?size=10')->filter('tbody tr')->first();

        self::assertStringStartsWith('Book 01', $row->filter('th')->text());
        self::assertSame('Title', $row->filter('th')->attr('data-label'));
        self::assertSame('101', $row->filter('td')->eq(1)->text());
        self::assertSame('Oui', $row->filter('td')->eq(4)->text());
        self::assertSame('Live', $row->filter('td')->eq(3)->text());
        self::assertSame('/books/1', $row->filter('td')->eq(5)->filter('a')->attr('href'));
        self::assertSame('Book 01', $row->filter('td')->eq(6)->filter('img')->attr('alt'));
        self::assertSame('cover', $row->filter('td')->eq(6)->filter('img')->attr('class'), 'The cell theme of the application draws the block.');
    }

    public function testTwoTablesOfAPageKeepToTheirOwnParameters(): void
    {
        $page = $this->page('/books?second_page=1&second_sort=-pages&sort=title&other=kept');
        $second = $page->filter('section.phil-data-table')->eq(1);

        self::assertSame('5 résultats.', $second->filter('[role=status]')->text());
        self::assertStringStartsWith('Book 05', $second->filter('tbody tr th[scope=row]')->first()->text());
        self::assertSame('kept', $second->filter('form input[type=hidden][name=other]')->attr('value'));
    }

    public function testTheLiveComponentSortsPagesAndFilters(): void
    {
        $table = $this->live();

        self::assertSame('Book 01', $this->firstRow($table));

        $table->call('applySort', ['value' => '-title']);
        self::assertSame('Book 60', $this->firstRow($table));
        $table->call('goTo', ['page' => 3]);
        self::assertSame('Book 40', $this->firstRow($table));
        $table->set('filters.q', 'Book 1')->call('applyFilters');
        self::assertSame('Book 19', $this->firstRow($table));
        self::assertSame(1, $this->component($table)->page);
        $table->call('clearFilters');
        self::assertSame(['q' => '', 'status' => '', 'published_from' => '', 'published_until' => '', 'inPrint' => ''], $this->component($table)->filters);
    }

    public function testAnActionAsksThenRuns(): void
    {
        $table = $this->live();
        $table->call('ask', ['command' => 'archive', 'row' => '3', 'token' => 'csrf-token']);

        $dialog = $table->render()->crawler()->filter('dialog[open][role=alertdialog]');
        self::assertStringContainsString('Archive', $dialog->filter('form p')->text());
        self::assertSame($dialog->filter('form p')->attr('id'), $dialog->attr('aria-labelledby'));
        self::assertSame('cancel->phil--data-table-bundle--table#dismiss', $dialog->attr('data-action'));
        self::assertSame([], $this->shelf()->archived);

        $table->call('perform', ['token' => 'csrf-token']);
        self::assertSame([3], $this->shelf()->archived);
        self::assertSame('C’est fait. 60 résultats.', $table->render()->crawler()->filter('[role=status]')->text());
    }

    public function testAnActionWithoutConfirmationRunsAtOnce(): void
    {
        $this->live()->call('ask', ['command' => 'touch', 'row' => '4', 'token' => 'csrf-token']);

        self::assertSame([4], $this->shelf()->archived);
    }

    public function testACrossSiteActionIsRefused(): void
    {
        $this->client->setServerParameter('HTTP_SEC_FETCH_SITE', 'cross-site');
        $this->client->catchExceptions(false);

        $table = $this->live();

        try {
            $table->call('perform', ['token' => 'csrf-token']);
            self::fail('The action went through.');
        } catch (AccessDeniedHttpException) {
            self::assertSame([], $this->shelf()->archived);
        }
    }

    public function testACellIsEditedAndValidatedOnTheServer(): void
    {
        $table = $this->live();
        $table->call('startEdit', ['row' => '1', 'column' => 'title']);

        $input = $table->render()->crawler()->filter('form.phil-data-table__edit input[name=value]');
        self::assertSame('Book 01', $input->attr('value'));
        self::assertNotNull($input->attr('autofocus'));

        $table->set('draft', '')->call('save', ['token' => 'csrf-token']);
        $crawler = $table->render()->crawler();
        self::assertSame('true', $crawler->filter('input[name=value]')->attr('aria-invalid'));
        self::assertCount(1, $crawler->filter('ul.phil-data-table__errors[role=alert] li'));

        $table->set('draft', 'Renamed')->call('save', ['token' => 'csrf-token']);
        self::assertSame('Renamed', $this->shelf()->books[0]->title);
        self::assertSame('Enregistré. 60 résultats.', $table->render()->crawler()->filter('[role=status]')->text());
    }

    public function testAnEditMeetingANewerVersionIsAConflict(): void
    {
        $table = $this->live();
        $table->call('startEdit', ['row' => '1', 'column' => 'title']);
        $this->shelf()->books[0]->version = 7;
        $this->shelf()->books[0]->title = 'Book 01 bis';

        $table->set('draft', 'Mine')->call('save', ['token' => 'csrf-token']);
        self::assertSame('Book 01 bis', $this->shelf()->books[0]->title);
        self::assertStringContainsString('a changé', $table->render()->crawler()->filter('.phil-data-table__errors')->text());
        self::assertSame('Book 01 bis', $table->render()->crawler()->filter('input[name=value]')->attr('value'), 'the current value is shown again');
    }

    public function testAVoterRefusesAtEveryCall(): void
    {
        $access = $this->service(RecordingAccess::class);
        $access->refused = ['BOOK_EDIT', 'BOOK_ARCHIVE'];

        $crawler = $this->page('/books?edit=title&row=1');

        self::assertCount(0, $crawler->filter('form.phil-data-table__edit'));
        self::assertCount(0, $crawler->filter('.phil-data-table__edit-link'));
        self::assertStringNotContainsString('confirm=archive', $crawler->html());
    }

    public function testAPlainFormRunsAnActionAndComesBack(): void
    {
        $page = $this->page('/books?confirm=archive&row=2&size=10');
        $form = $page->filter('dialog[open] form.phil-data-table__confirm')->first();
        self::assertSame('Annuler', $form->filter('a[autofocus]')->text(), 'the focus starts on the answer that does no harm');
        self::assertSame('dismiss', $form->filter('a[autofocus]')->attr('data-phil--data-table-bundle--table-target'));
        self::assertSame('submit', $form->filter('button')->attr('type'));
        self::assertStringContainsString('isSmall', (string) $form->filter('button')->attr('class'), 'the Button recipe of the kit');

        $this->client->request('POST', (string) $form->attr('action'), $this->fields($form));

        self::assertResponseStatusCodeSame(303);
        self::assertSame('/books?page=1', $this->client->getResponse()->headers->get('Location'));
        self::assertSame([2], $this->shelf()->archived);
    }

    public function testAPlainFormRefusesAnotherSealOrSite(): void
    {
        $form = $this->page('/books?confirm=archive&row=2')->filter('form.phil-data-table__confirm')->first();
        $fields = $this->fields($form);

        $this->client->request('POST', (string) $form->attr('action'), [...$fields, 'context' => '{"limit":1}']);
        self::assertResponseStatusCodeSame(403);

        $this->client->setServerParameter('HTTP_SEC_FETCH_SITE', 'cross-site');
        $this->client->request('POST', (string) $form->attr('action'), $fields);
        self::assertResponseStatusCodeSame(403);
        self::assertSame([], $this->shelf()->archived);
    }

    public function testAPlainFormEditsACellAndSaysWhatStopsIt(): void
    {
        $form = $this->page('/books?edit=title&row=1')->filter('form.phil-data-table__edit')->first();
        $action = (string) $form->attr('action');
        $fields = $this->fields($form);

        $this->client->request('POST', $action, [...$fields, 'value' => '']);
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('Retour au tableau', (string) $this->client->getResponse()->getContent());

        $this->client->request('POST', $action, [...$fields, 'value' => 'Plain']);
        self::assertResponseStatusCodeSame(303);
        self::assertSame('Plain', $this->shelf()->books[0]->title);

        $this->client->request('POST', $action, [...$fields, 'value' => 'Late']);
        self::assertResponseStatusCodeSame(409);
    }

    public function testANoticeSaysWhenTheRowBeingEditedLeavesThePage(): void
    {
        $left = $this->page('/books?edit=title&row=1&page=2&size=10');
        self::assertSame('La ligne en cours de modification n’est plus sur cette page. 60 résultats.', $left->filter('[role=status]')->first()->text());

        $here = $this->page('/books?edit=title&row=1&size=10');
        self::assertSame('60 résultats.', $here->filter('[role=status]')->first()->text());
        self::assertCount(1, $here->filter('form.phil-data-table__edit'));
    }

    public function testATableIsExportedFilteredAndSortedInItsVisibleColumns(): void
    {
        $links = $this->page('/books?filters[q]=Book%200&sort=-title&columns[]=title&columns[]=pages&columns[]=status&columns[]=inPrint&columns[]=page&columns[]=cover&columns[]=published')->filter('section.phil-data-table')->first()->filter('.phil-data-table__export a[download]');
        self::assertSame(['Exporter en CSV', 'Exporter en XLSX'], $links->each(static fn (Crawler $link): string => $link->text()));
        $csv = (string) $links->first()->attr('href');
        self::assertStringStartsWith('/_tables/books/export.csv?', $csv);
        self::assertStringContainsString('seal=', $csv);

        $this->client->request('GET', $csv);
        $response = $this->client->getResponse();

        self::assertResponseIsSuccessful();
        self::assertSame('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));
        self::assertSame('attachment; filename=books.csv', $response->headers->get('Content-Disposition'));
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        self::assertSame(
            ["\u{FEFF}Title,Pages,Published,Status,\"In print\",Page", '"Book 09",109,"2026-01-10 12:00:00",Live,0,Open'],
            \array_slice(explode("\r\n", $this->client->getInternalResponse()->getContent()), 0, 2),
        );
        self::assertSame(10, substr_count($this->client->getInternalResponse()->getContent(), "\r\n"), 'the headings and the nine books of the filter');

        $this->client->request('GET', (string) $links->eq(1)->attr('href'));
        self::assertResponseIsSuccessful();
        self::assertStringStartsWith('PK', $this->client->getInternalResponse()->getContent());
        self::assertSame('attachment; filename=books.xlsx', $this->client->getResponse()->headers->get('Content-Disposition'));
    }

    public function testAnExportPastItsRowsIsRefusedBeforeAnythingIsWritten(): void
    {
        $href = (string) $this->page('/books')->filter('.phil-data-table__export a[download]')->first()->attr('href');

        $this->client->request('GET', $href);

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('Cet export compterait 60 lignes, plus que les 50 qu’il permet.', (string) $this->client->getResponse()->getContent());
    }

    public function testAnExportIsRefusedWithoutItsAttributeASealOrAFormat(): void
    {
        $href = (string) $this->page('/books')->filter('.phil-data-table__export a[download]')->first()->attr('href');

        $this->client->request('GET', str_replace('seal=', 'seal=x', $href));
        self::assertResponseStatusCodeSame(403);

        $this->client->request('GET', str_replace('export.csv', 'export.pdf', $href));
        self::assertResponseStatusCodeSame(404);

        $access = $this->service(RecordingAccess::class);
        $access->refused = ['BOOK_EXPORT'];
        self::assertCount(0, $this->page('/books')->filter('.phil-data-table__export'));
        $this->client->request('GET', $href);
        self::assertResponseStatusCodeSame(403);
    }

    public function testATableThatOffersNoExportHasNone(): void
    {
        $bench = $this->createLiveComponent('PhilDataTable:Table', ['name' => 'bench'], $this->client);

        self::assertCount(0, $bench->render()->crawler()->filter('.phil-data-table__export'));
        self::assertSame([], $this->component($bench)->getExports());
    }

    /**
     * @return array<string, string|list<string>>
     */
    private function fields(Crawler $form): array
    {
        $fields = [];

        foreach ($form->filter('input') as $input) {
            $this->assertInstanceOf(DOMElement::class, $input);
            $name = $input->getAttribute('name');

            if ('ids[]' === $name) {
                $fields['ids'] = [$input->getAttribute('value')];
            } else {
                $fields[$name] = $input->getAttribute('value');
            }
        }

        return $fields;
    }

    private function page(string $url): Crawler
    {
        return $this->client->request('GET', $url);
    }

    private function live(): TestLiveComponent
    {
        return $this->createLiveComponent('PhilDataTable:Table', ['name' => 'books'], $this->client);
    }

    private function component(TestLiveComponent $table): TableComponent
    {
        $component = $table->component();
        self::assertInstanceOf(TableComponent::class, $component);

        return $component;
    }

    private function firstRow(TestLiveComponent $table): string
    {
        return substr($table->render()->crawler()->filter('tbody tr th[scope=row]')->first()->text(), 0, 7);
    }

    private function shelf(): Shelf
    {
        return $this->service(Shelf::class);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function service(string $class): object
    {
        $service = $this->client->getContainer()->get($class);
        \assert($service instanceof $class);

        return $service;
    }
}
