<?php

declare(strict_types=1);

namespace Phil\WellKnownBundle\Tests\Functional\App;

use Phil\WellKnownBundle\Application\Attribute\AsWellKnownDocument;
use Phil\WellKnownBundle\Application\Contract\WellKnownDocument;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * A document another bundle adds, by its attribute.
 */
#[AsWellKnownDocument('matrix/client')]
final readonly class MatrixClientDocument implements WellKnownDocument
{
    public function serve(Request $request): JsonResponse
    {
        return new JsonResponse(['m.homeserver' => ['base_url' => 'https://matrix.example.com']]);
    }
}
