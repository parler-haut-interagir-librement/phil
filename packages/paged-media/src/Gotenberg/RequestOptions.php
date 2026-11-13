<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg;

use Phil\PagedMedia\Gotenberg\Internal\Url;

/**
 * The headers of a request that produces files: the result's name, a trace identifier for Gotenberg's logs, and a
 * webhook to send the result to instead of answering with it.
 */
final readonly class RequestOptions
{
    /**
     * @param string|null $outputFilename the result's name without extension: Gotenberg adds the right one
     * @param string|null $trace          an identifier Gotenberg logs and sends back
     */
    public function __construct(
        public ?string $outputFilename = null,
        public ?string $trace = null,
        public ?Webhook $webhook = null,
    ) {
        if (null !== $outputFilename) {
            Url::headerSafe($outputFilename, 'An output file name');
        }

        if (null !== $trace) {
            Url::headerSafe($trace, 'A trace identifier');
        }
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return array_filter([
            'Gotenberg-Output-Filename' => $this->outputFilename,
            'Gotenberg-Trace' => $this->trace,
        ], is_string(...)) + ($this->webhook?->headers() ?? []);
    }
}
