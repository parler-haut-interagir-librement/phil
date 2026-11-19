<?php

declare(strict_types=1);

namespace Phil\AiBundle\Tests\Functional\App;

use Override;
use Phil\AiBundle\Infrastructure\SymfonyAi\UserKeyPlatformFactory;
use SensitiveParameter;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Test\MockPlatformFactory;
use Symfony\AI\Platform\TokenUsage\TokenUsage;

/**
 * Symfony AI's mock platform, as the application's and as a person's: it answers whose key it runs on and what it
 * was asked, and counts ten tokens in and five out.
 */
final class Platforms implements UserKeyPlatformFactory
{
    public static function application(): PlatformInterface
    {
        return self::answering('application');
    }

    #[Override]
    public function create(#[SensitiveParameter] string $apiKey): PlatformInterface
    {
        return self::answering('key '.$apiKey);
    }

    private static function answering(string $whose): PlatformInterface
    {
        return MockPlatformFactory::createPlatform(static function (Model $model, array|string $payload) use ($whose): TextResult {
            $messages = \is_array($payload) && \is_array($payload['messages'] ?? null) ? $payload['messages'] : [];
            $result = new TextResult(\sprintf('%s, %s: %s', $whose, $model->getName(), json_encode($messages, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE)));
            $result->getMetadata()->add('token_usage', new TokenUsage(promptTokens: 10, completionTokens: 5));

            return $result;
        });
    }
}
