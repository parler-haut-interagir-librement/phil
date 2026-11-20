<?php

declare(strict_types=1);

namespace Phil\AiBundle\Infrastructure\Replicate;

use Override;
use SensitiveParameter;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Creates a prediction of a Replicate model from a prompt, and waits for it: `POST /v1/models/{owner}/{name}/predictions`
 * with `Prefer: wait`, the prompt and the task's options as the model's input.
 */
final readonly class ReplicateImageClient implements ModelClientInterface
{
    public const string ENDPOINT = 'https://api.replicate.com/v1/models/%s/predictions';

    /** How long Replicate holds the request open for the prediction, in seconds: 1 to 60. */
    public const int WAIT = 60;

    private const string MODEL_PATTERN = '#^[a-z0-9][a-z0-9_.-]{0,99}/[a-z0-9][a-z0-9_.-]{0,99}$#D';

    public function __construct(
        private HttpClientInterface $httpClient,
        #[SensitiveParameter]
        private string $apiKey,
        private int $wait = self::WAIT,
    ) {
        if ($wait < 1 || $wait > 60) {
            throw new InvalidArgumentException(\sprintf('Replicate waits 1 to 60 seconds for a prediction; got %d.', $wait));
        }
    }

    #[Override]
    public function supports(Model $model): bool
    {
        return 1 === preg_match(self::MODEL_PATTERN, $model->getName());
    }

    /**
     * @param array<string, mixed> $options
     */
    #[Override]
    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        if (!\is_string($payload)) {
            throw new InvalidArgumentException('A Replicate image model takes the prompt as text.');
        }

        unset($options['stream']);

        return new RawHttpResult($this->httpClient->request('POST', \sprintf(self::ENDPOINT, $model->getName()), [
            'auth_bearer' => $this->apiKey,
            'headers' => ['Prefer' => 'wait='.$this->wait],
            'json' => ['input' => ['prompt' => $payload] + $options],
            'timeout' => $this->wait + 5,
        ]));
    }
}
