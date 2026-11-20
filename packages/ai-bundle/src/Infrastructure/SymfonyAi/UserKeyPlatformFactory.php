<?php

declare(strict_types=1);

namespace Phil\AiBundle\Infrastructure\SymfonyAi;

use SensitiveParameter;
use Symfony\AI\Platform\PlatformInterface;

/**
 * Builds a platform on a person's own key — usually by the `PlatformFactory::create()` of the provider's Symfony AI
 * bridge, with the application's HTTP client.
 */
interface UserKeyPlatformFactory
{
    public function create(#[SensitiveParameter] string $apiKey): PlatformInterface;
}
