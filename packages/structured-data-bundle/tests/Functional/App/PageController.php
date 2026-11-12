<?php

declare(strict_types=1);

namespace Phil\StructuredDataBundle\Tests\Functional\App;

use Phil\IndexingConsent\Indexing;
use Phil\IndexingConsent\IndexingPolicy;
use Phil\StructuredData\Contract\NodeCollector;
use Phil\StructuredData\Reference;
use Phil\StructuredData\Schema;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

final readonly class PageController
{
    public function __construct(
        private Environment $twig,
        private NodeCollector $graph,
    ) {
    }

    public function event(Request $request): Response
    {
        $request->attributes->set('script_nonce', 'bm9uY2U=');
        $this->graph->add(Schema::event('Tournoi de printemps', '2027-04-10T10:00:00-04:00', 'Salle Saint-Denis', $request->getUri().'#event'));

        return new Response($this->twig->render('event.html.twig'));
    }

    public function shared(Request $request): Response
    {
        $url = $request->getSchemeAndHttpHost().$request->getPathInfo();
        $this->graph->add(Schema::webPage($url, $url.'#webpage', 'La page'), Schema::article('Le titre du modèle', $url.'#article', image: 'https://example.com/image.png', description: 'Le résumé', mainEntityOfPage: new Reference($url.'#webpage')));

        return new Response($this->twig->render('shared.html.twig', ['title' => $request->query->get('title')]));
    }

    public function withdrawn(Request $request): Response
    {
        $request->attributes->set(IndexingPolicy::REQUEST_ATTRIBUTE, Indexing::NotIndexed);
        $this->graph->add(Schema::event('Tournoi privé', '2027-04-10T10:00:00-04:00', 'Salle Saint-Denis', $request->getUri().'#event'));

        return new Response($this->twig->render('event.html.twig'));
    }

    public function plain(): Response
    {
        return new Response($this->twig->render('plain.html.twig'));
    }
}
