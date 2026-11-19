<?php

declare(strict_types=1);

namespace Phil\GuardianConsent;

use Override;
use Phil\GuardianConsent\Exception\InvalidToken;
use Phil\GuardianConsent\Internal\RandomId;
use Random\Randomizer;
use SensitiveParameter;
use Stringable;

/**
 * The secret of a confirmation link: the identifier of the request, 16 random bytes, and a verifier, 32 random bytes
 * of a cryptographically secure generator, both in URL-safe Base64 without padding, joined by a dot — 66 characters.
 *
 * The request is found by its identifier; the verifier is never stored, only its keyed hash (see `TokenHasher`), so
 * a copy of the store opens no link.
 */
final readonly class ConfirmationToken implements Stringable
{
    public const int REQUEST_ID_BYTES = 16;

    public const int VERIFIER_BYTES = 32;

    /** The length of a token: 22 characters, a dot and 43 characters. */
    public const int LENGTH = 66;

    private function __construct(
        public string $requestId,
        #[SensitiveParameter]
        public string $verifier,
    ) {
    }

    public static function generate(Randomizer $randomizer = new Randomizer()): self
    {
        return new self(RandomId::generate($randomizer, self::REQUEST_ID_BYTES), RandomId::generate($randomizer, self::VERIFIER_BYTES));
    }

    /**
     * The token as it was issued, from the text of a link.
     *
     * @throws InvalidToken when the text is not a request identifier and a verifier, as `generate()` writes them
     */
    public static function parse(#[SensitiveParameter] string $text): self
    {
        $parts = explode('.', $text);

        if (2 !== \count($parts) || !self::isRequestId($parts[0]) || !RandomId::isValid($parts[1], self::VERIFIER_BYTES)) {
            throw InvalidToken::rejected();
        }

        return new self($parts[0], $parts[1]);
    }

    /**
     * Whether the text is a request identifier as `generate()` writes it.
     */
    public static function isRequestId(string $text): bool
    {
        return RandomId::isValid($text, self::REQUEST_ID_BYTES);
    }

    public function toString(): string
    {
        return $this->requestId.'.'.$this->verifier;
    }

    #[Override]
    public function __toString(): string
    {
        return $this->toString();
    }
}
