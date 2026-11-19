<?php

declare(strict_types=1);

namespace Phil\AiBundle\Infrastructure\Replicate;

use Override;
use Phil\AiBundle\Infrastructure\SymfonyAi\UserKeyPlatformFactory;
use SensitiveParameter;
use Symfony\AI\Platform\ModelCatalog\FallbackModelCatalog;
use Symfony\AI\Platform\Platform;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Provider;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * A Symfony AI platform that generates images on Replicate, by a model's name — `black-forest-labs/flux-schnell` —:
 * the adapter Symfony AI's own Replicate bridge, made for language models, does not offer. It is a Symfony AI
 * `Provider` of this bundle's model client and result converter, so that a task reaches it as any other platform; as a
 * `UserKeyPlatformFactory`, it builds the same platform on a person's own token.
 */
final readonly class ReplicatePlatformFactory implements UserKeyPlatformFactory
{
    public const string NAME = 'replicate-images';

    public function __construct(
        private HttpClientInterface $httpClient,
        private int $maxBytes = ReplicateImageConverter::MAX_BYTES,
        private int $wait = ReplicateImageClient::WAIT,
    ) {
    }

    #[Override]
    public function create(#[SensitiveParameter] string $apiKey): PlatformInterface
    {
        return new Platform([new Provider(
            self::NAME,
            [new ReplicateImageClient($this->httpClient, $apiKey, $this->wait)],
            [new ReplicateImageConverter($this->httpClient, $this->maxBytes)],
            new FallbackModelCatalog(),
        )]);
    }
}
