<?php

declare(strict_types=1);

namespace Phil\StatusPageBundle\Infrastructure\Http;

use Phil\StatusPageBundle\Application\Contract\PageRenderer;
use Phil\StatusPageBundle\Application\Render\AtomFeed;
use Phil\StatusPageBundle\Application\Render\JsonDocument;
use Phil\StatusPageBundle\Application\Render\PageLinks;
use Phil\StatusPageBundle\Application\StatusPageBuilder;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The page served by the application, as HTML, JSON and Atom. It reads no session and sets no cookie; browsers and
 * shared caches may keep each answer `$maxAge` seconds, and the JSON and the feed may be read by pages of other
 * sites. The HTML works without any script.
 */
final readonly class StatusPageController
{
    public const string ROUTE = 'phil_status_page';

    public const string JSON_ROUTE = 'phil_status_page_json';

    public const string ATOM_ROUTE = 'phil_status_page_atom';

    public function __construct(
        private StatusPageBuilder $builder,
        private PageRenderer $renderer,
        private JsonDocument $json,
        private AtomFeed $atom,
        private UrlGeneratorInterface $urls,
        private int $maxAge = 60,
    ) {
    }

    public function page(): Response
    {
        $html = $this->renderer->render($this->builder->build(), $this->links(UrlGeneratorInterface::ABSOLUTE_PATH), false);

        return $this->respond($html, 'text/html; charset=UTF-8', false);
    }

    public function json(): Response
    {
        return $this->respond($this->json->render($this->builder->build(), $this->links(UrlGeneratorInterface::ABSOLUTE_URL)), 'application/json', true);
    }

    public function atom(): Response
    {
        return $this->respond($this->atom->render($this->builder->build(), $this->links(UrlGeneratorInterface::ABSOLUTE_URL)), 'application/atom+xml; charset=UTF-8', true);
    }

    private function links(int $type): PageLinks
    {
        return new PageLinks(
            $this->urls->generate(self::ROUTE, [], $type),
            $this->urls->generate(self::JSON_ROUTE, [], $type),
            $this->urls->generate(self::ATOM_ROUTE, [], $type),
        );
    }

    private function respond(string $content, string $type, bool $shared): Response
    {
        $headers = [
            'Content-Type' => $type,
            'Cache-Control' => 0 === $this->maxAge ? 'no-cache' : 'public, max-age='.$this->maxAge,
            'X-Content-Type-Options' => 'nosniff',
        ];

        if ($shared) {
            $headers['Access-Control-Allow-Origin'] = '*';
        }

        return new Response($content, Response::HTTP_OK, $headers);
    }
}
