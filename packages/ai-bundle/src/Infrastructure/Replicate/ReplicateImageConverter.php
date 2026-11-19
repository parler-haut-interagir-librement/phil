<?php

declare(strict_types=1);

namespace Phil\AiBundle\Infrastructure\Replicate;

use Override;
use Phil\Url\Exception\InvalidUrl;
use Phil\Url\Url;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Reads a finished Replicate prediction into an image: its first output, an address on Replicate's delivery network,
 * fetched within a size bound. Any other address is refused, so that an answer cannot send the server elsewhere.
 */
final readonly class ReplicateImageConverter implements ResultConverterInterface
{
    /** The largest image fetched, in bytes. */
    public const int MAX_BYTES = 20 * 1024 * 1024;

    /** The domain Replicate serves its outputs from. */
    public const string DELIVERY_DOMAIN = 'replicate.delivery';

    public function __construct(
        private HttpClientInterface $httpClient,
        private int $maxBytes = self::MAX_BYTES,
    ) {
    }

    #[Override]
    public function supports(Model $model): bool
    {
        return true;
    }

    /**
     * @param array<string, mixed> $options
     */
    #[Override]
    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        $prediction = $result->getData();
        $status = $prediction['status'] ?? null;

        if ('succeeded' !== $status) {
            throw new RuntimeException(\sprintf('The Replicate prediction did not succeed: %s.', \is_string($status) ? $status : 'no status'));
        }

        $output = $prediction['output'] ?? null;
        $first = \is_array($output) ? reset($output) : $output;

        return $this->fetch(\is_string($first) ? $first : throw new RuntimeException('The Replicate prediction has no image address.'));
    }

    #[Override]
    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
    }

    private function fetch(string $address): BinaryResult
    {
        try {
            $url = Url::fromString($address);
        } catch (InvalidUrl $invalidUrl) {
            throw new RuntimeException('The Replicate prediction gave no valid image address.', $invalidUrl->getCode(), previous: $invalidUrl);
        }

        if ('https' !== $url->scheme || !$url->isOn(self::DELIVERY_DOMAIN)) {
            throw new RuntimeException(\sprintf('An image is fetched from %s only, over HTTPS.', self::DELIVERY_DOMAIN));
        }

        $response = $this->httpClient->request('GET', $url->toString(), ['max_redirects' => 0]);

        if (200 !== $response->getStatusCode()) {
            throw new RuntimeException(\sprintf('The image could not be fetched: HTTP %d.', $response->getStatusCode()));
        }

        $bytes = '';

        foreach ($this->httpClient->stream($response) as $chunk) {
            $bytes .= $chunk->getContent();

            if (\strlen($bytes) > $this->maxBytes) {
                $response->cancel();

                throw new RuntimeException(\sprintf('The image is larger than %d bytes.', $this->maxBytes));
            }
        }

        return new BinaryResult($bytes, $response->getHeaders()['content-type'][0] ?? 'application/octet-stream');
    }
}
