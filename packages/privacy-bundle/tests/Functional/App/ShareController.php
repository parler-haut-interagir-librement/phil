<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Functional\App;

use Symfony\Component\HttpFoundation\Response;

/**
 * A page reached by a secret link, `/p/{token}`: the token in its path is as sensitive as a password.
 */
final readonly class ShareController
{
    public function __invoke(): Response
    {
        return new Response('shared', Response::HTTP_OK);
    }
}
