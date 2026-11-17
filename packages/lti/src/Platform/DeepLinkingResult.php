<?php

declare(strict_types=1);

namespace Phil\Lti\Platform;

use Phil\Lti\Contract\ContentItem;
use Phil\Lti\DeepLinking\ContentItems;
use Phil\Lti\Exception\InvalidToken;
use Phil\Lti\Message\Claim;
use Phil\Lti\Support\Fields;

/**
 * A deep linking response the platform verified: the tool and deployment it comes from, the items picked, the data the
 * platform put in the request, and the messages for the person and for the logs.
 *
 * `key` names the response for good — a hash of its client and of its nonce, which the platform takes once —, so that
 * an application that keeps the response can handle it again under the same name; `claims` are the verified claims it
 * was read from, which `fromClaims()` reads again without the token.
 */
final readonly class DeepLinkingResult
{
    /**
     * @param list<ContentItem>       $items
     * @param array<array-key, mixed> $claims
     */
    public function __construct(
        public string $key,
        public string $clientId,
        public string $deploymentId,
        public array $items,
        public ?string $data = null,
        public ?string $message = null,
        public ?string $log = null,
        public ?string $errorMessage = null,
        public ?string $errorLog = null,
        public array $claims = [],
    ) {
    }

    /**
     * Reads the claims of a response already verified — its signature, audience, nonce and deployment —, as
     * `DeepLinkingReturn` does once, or as a store gives them back: nothing here checks them again.
     *
     * @param array<array-key, mixed> $claims
     *
     * @throws InvalidToken when a claim is missing or not of its type
     */
    public static function fromClaims(array $claims, bool $allowLocalhost = false): self
    {
        $fields = new Fields($claims, allowLocalhost: $allowLocalhost);
        $clientId = $fields->string('iss', 255);

        return new self(
            self::key($clientId, $fields->string('nonce', 255)),
            $clientId,
            $fields->string(Claim::DEPLOYMENT_ID, 255),
            ContentItems::fromClaims($claims, Claim::CONTENT_ITEMS, $allowLocalhost),
            $fields->optionalString(Claim::DATA),
            $fields->optionalString(Claim::MESSAGE),
            $fields->optionalString(Claim::LOG),
            $fields->optionalString(Claim::ERROR_MESSAGE),
            $fields->optionalString(Claim::ERROR_LOG),
            $claims,
        );
    }

    /**
     * The key of the response of a client with a nonce: 64 hexadecimal digits, the length of the client first so that no
     * other pair gives the same text to hash.
     */
    public static function key(string $clientId, string $nonce): string
    {
        return hash('sha256', \strlen($clientId).':'.$clientId."\n".$nonce);
    }
}
