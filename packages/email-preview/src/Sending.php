<?php

declare(strict_types=1);

namespace Phil\EmailPreview;

/**
 * How an email is sent, which its rendering does not say: whether it goes out in bulk, the headers the sender adds, and
 * the values that identify its recipient — the address, an identifier — which no image address may carry.
 */
final readonly class Sending
{
    /** @var array<string, string> the headers, their names in lowercase */
    public array $headers;

    /** @var list<string> */
    public array $recipientTokens;

    /**
     * @param array<string, string> $headers         the headers by name, whatever their case
     * @param list<string>          $recipientTokens the values that identify the recipient; empty ones are left out
     */
    public function __construct(
        public bool $bulk = false,
        array $headers = [],
        array $recipientTokens = [],
    ) {
        $lowercase = [];

        foreach ($headers as $name => $value) {
            $lowercase[strtolower($name)] = $value;
        }

        $this->headers = $lowercase;
        $this->recipientTokens = array_values(array_filter($recipientTokens, static fn (string $token): bool => '' !== $token));
    }

    /**
     * A bulk sending, as a newsletter or a campaign: it owes the headers of a one-click unsubscription.
     *
     * @param array<string, string> $headers
     * @param list<string>          $recipientTokens
     */
    public static function bulk(array $headers = [], array $recipientTokens = []): self
    {
        return new self(true, $headers, $recipientTokens);
    }

    /**
     * The value of a header, whatever the case of its name, or `null` when the sending does not add it.
     */
    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }
}
