<?php

declare(strict_types=1);

namespace Phil\StructuredData\Tests\Benchmark;

use Generator;
use Phil\StructuredData\Graph;
use Phil\StructuredData\JsonLd;
use Phil\StructuredData\Node;
use Phil\StructuredData\Type\BreadcrumbList;
use Phil\StructuredData\Type\Event;
use Phil\StructuredData\Type\Offer;
use Phil\StructuredData\Type\Organization;
use Phil\StructuredData\Type\Place;
use Phil\StructuredData\Type\PostalAddress;
use Phil\StructuredData\Type\WebPage;
use Phil\StructuredData\Type\WebSite;

/**
 * The graph of a page is assembled and written on every page rendered: the site, the organisation, the page and its
 * breadcrumb, then the nodes of the content — none for a plain page, a calendar of events for a listing. The
 * organisation is given again by each event, so that every one of them merges into the first.
 *
 * @BeforeMethods("setUp")
 *
 * @Iterations(5)
 */
final class JsonLdBench
{
    /** @var list<Node> */
    private array $nodes = [];

    private ?Graph $graph = null;

    /**
     * @param array{events: int} $params
     */
    public function setUp(array $params): void
    {
        $base = 'https://example.com/';
        $organization = Organization::of('Le Gnome', $base, $base.'#organization', $base.'logo.png', address: PostalAddress::of('1 rue Principale', 'Montréal', 'QC', 'H2X 1Y4', 'CA'));
        $this->nodes = [
            WebSite::of('Le Gnome', $base, $base.'#website', inLanguage: 'fr-CA', publisher: $organization->reference(), searchUrl: $base.'recherche?q={search_term_string}'),
            $organization,
            WebPage::of($base.'calendrier', $base.'calendrier#webpage', 'Calendrier', isPartOf: $organization->reference()),
            BreadcrumbList::of([['name' => 'Accueil', 'url' => $base], ['name' => 'Calendrier <&>']], $base.'calendrier#breadcrumb'),
        ];

        for ($index = 0; $index < $params['events']; ++$index) {
            $this->nodes[] = Event::of(
                \sprintf('Tournoi « %d » </script>', $index),
                '2027-04-10T10:00:00-04:00',
                Place::of('Salle Saint-Denis', PostalAddress::of('2 rue', 'Montréal', 'QC', 'H2X 1Y4', 'CA')),
                \sprintf('%se/%d#event', $base, $index),
                description: 'Une journée de jeux',
                offers: Offer::of('10.00', 'CAD', url: \sprintf('%se/%d', $base, $index)),
                organizer: $organization,
            );
        }

        $this->graph = Graph::of(...$this->nodes);
    }

    /**
     * @ParamProviders("pages")
     *
     * @Revs(20)
     */
    public function benchAssemble(): Graph
    {
        return Graph::of(...$this->nodes);
    }

    /**
     * @ParamProviders("pages")
     *
     * @Revs(20)
     */
    public function benchScript(): string
    {
        return JsonLd::script($this->graph ?? Graph::of(), 'bm9uY2U=');
    }

    /**
     * @return Generator<string, array{events: int}>
     */
    public function pages(): Generator
    {
        foreach ([0, 20, 500] as $events) {
            yield \sprintf('%d events', $events) => ['events' => $events];
        }
    }
}
