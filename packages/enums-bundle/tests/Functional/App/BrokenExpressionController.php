<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Tests\Functional\App;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\Cache;

/**
 * A controller of the kernel's broken environment only: its expression misspells `enum_in_group()`.
 */
final class BrokenExpressionController
{
    public const string EXPRESSION = "enum_in_grup(request.attributes.get('status'), 'open')";

    #[Cache(maxage: 60, if: self::EXPRESSION)]
    public function cached(): Response
    {
        return new Response('cached');
    }
}
