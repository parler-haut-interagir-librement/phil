<?php

declare(strict_types=1);

namespace Phil\HttpCacheTagsBundle\Tests\Functional\App;

use Doctrine\ORM\EntityManagerInterface;
use Phil\HttpCacheTagsBundle\Infrastructure\Http\CacheTag;
use Phil\HttpCacheTagsBundle\Tests\Functional\App\Entity\Page;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

final readonly class PageController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Environment $twig,
    ) {
    }

    #[CacheTag('page-list', 'site:{site}')]
    public function show(string $id): Response
    {
        $page = $this->entityManager->find(Page::class, $id);

        return new Response($this->twig->render('page.html.twig', ['page' => $page]), Response::HTTP_OK, ['Cache-Control' => 'public, s-maxage=600']);
    }

    public function rename(string $id): Response
    {
        $page = $this->entityManager->find(Page::class, $id);
        \assert($page instanceof Page);
        $page->title = 'Renamed';
        $this->entityManager->flush();

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
