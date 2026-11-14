<?php

declare(strict_types=1);

namespace Phil\OpenBadgesBundle\Application;

use Phil\OpenBadges\Exception\InvalidBadge;

/**
 * A credential once secured, as it is stored, served or downloaded: its content — a compact JSON Web Token, or the
 * JSON of the document with its proof — and the media type of that content, `application/vc+jwt` or `application/vc`.
 */
final readonly class SecuredCredential
{
    public const string TOKEN = 'application/vc+jwt';

    public const string DOCUMENT = 'application/vc';

    private function __construct(
        public string $content,
        public string $mediaType,
    ) {
    }

    /**
     * A credential secured as a compact JSON Web Token.
     */
    public static function token(string $token): self
    {
        if (1 !== preg_match('/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/D', $token)) {
            throw InvalidBadge::because('A secured credential token is three base64url parts joined by dots.');
        }

        return new self($token, self::TOKEN);
    }

    /**
     * A credential secured with an embedded proof, written as JSON with its slashes and its letters unescaped.
     *
     * @param array<array-key, mixed> $document
     */
    public static function document(array $document): self
    {
        if (!\is_array($document['proof'] ?? null)) {
            throw InvalidBadge::because('A secured credential document holds its "proof".');
        }

        return new self(json_encode($document, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR), self::DOCUMENT);
    }

    public function isToken(): bool
    {
        return self::TOKEN === $this->mediaType;
    }

    /**
     * The name of a file that holds it: `credential.jwt` or `credential.json`.
     */
    public function filename(): string
    {
        return $this->isToken() ? 'credential.jwt' : 'credential.json';
    }
}
