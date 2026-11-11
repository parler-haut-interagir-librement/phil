<?php

declare(strict_types=1);

namespace Phil\OpenBadges\JsonLd;

use Phil\OpenBadges\Contract\ContextLoader;

/**
 * A JSON-LD document as the quads of its RDF dataset, for the subset of JSON-LD 1.1 verifiable credentials use, in
 * safe mode: a property or a type that expands to no absolute IRI is refused rather than dropped, so that nothing of a
 * document escapes the signature over its canonical form.
 *
 * @internal
 */
final readonly class ToRdf
{
    /**
     * The most quads a document yields.
     */
    public const int MAX_QUADS = 10_000;

    private ContextProcessor $contexts;

    public function __construct(ContextLoader $loader)
    {
        $this->contexts = new ContextProcessor($loader);
    }

    /**
     * @param array<array-key, mixed> $document
     *
     * @return list<array{string, string, string, string}> subject, predicate, object, and graph or '' for the default
     */
    public function quads(array $document): array
    {
        return new Conversion($this->contexts, self::MAX_QUADS)->document($document);
    }
}
