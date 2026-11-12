<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Tests\Unit\Fixture;

use Override;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * The kernel an event names, never asked to handle anything.
 */
final class NullKernel implements HttpKernelInterface
{
    #[Override]
    public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): Response
    {
        return new Response();
    }
}
