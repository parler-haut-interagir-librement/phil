<?php

declare(strict_types=1);

namespace Phil\OpenBadges\JsonLd;

use JsonException;
use Override;
use Phil\OpenBadges\Context;
use Phil\OpenBadges\Contract\ContextLoader;
use Phil\OpenBadges\Exception\InvalidCredential;

/**
 * The JSON-LD contexts the library bundles in `data/contexts` — Verifiable Credentials 2.0, Open Badges 3.0.3 and CLR
 * 2.0.1 — and the ones an application adds as decoded documents; any other URL is refused, so that nothing is ever
 * fetched while a credential is verified.
 */
final readonly class BundledContexts implements ContextLoader
{
    public const array FILES = [
        Context::CREDENTIALS_V2 => 'credentials-v2.jsonld',
        Context::OPEN_BADGES => 'ob-v3p0-context-3.0.3.json',
        Context::CLR => 'clr-v2p0-context-2.0.1.json',
    ];

    /**
     * @param array<string, array<array-key, mixed>> $extra more context documents by URL, each with its `@context`
     */
    public function __construct(
        private array $extra = [],
    ) {
    }

    #[Override]
    public function load(string $url): array
    {
        if (isset($this->extra[$url])) {
            return $this->extra[$url];
        }

        $file = self::FILES[$url] ?? throw InvalidCredential::because(\sprintf('The JSON-LD context "%s" is not one the verifier knows.', $url));

        try {
            $document = json_decode((string) file_get_contents(\dirname(__DIR__, 2).'/data/contexts/'.$file), true, flags: \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw InvalidCredential::because(\sprintf('The bundled JSON-LD context "%s" is not JSON.', $url));
        }

        return \is_array($document) ? $document : [];
    }
}
