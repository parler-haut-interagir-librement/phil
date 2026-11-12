<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Tests\Unit\Fixture;

use Phil\BrowserSecurityBundle\Application\Attribute\ContentSecurityPolicy;
use Symfony\Component\HttpFoundation\Response;

/**
 * A controller whose class and method change the policy.
 */
#[ContentSecurityPolicy(add: ['img-src' => ['data:']])]
final class PreviewController
{
    #[ContentSecurityPolicy(policy: 'preview')]
    public function show(): Response
    {
        return new Response('preview');
    }

    public function plain(): Response
    {
        return new Response('plain');
    }
}
