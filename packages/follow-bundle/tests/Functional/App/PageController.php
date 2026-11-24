<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Tests\Functional\App;

use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * A page that shows every template of the bundle.
 */
final readonly class PageController
{
    public function __construct(
        private Environment $twig,
    ) {
    }

    public function __invoke(string $subject = 'thread:1', string $member = 'bob'): Response
    {
        return new Response($this->twig->render('page.html.twig', ['subject' => $subject, 'member' => $member]));
    }
}
