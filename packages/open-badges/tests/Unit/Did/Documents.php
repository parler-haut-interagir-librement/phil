<?php

declare(strict_types=1);

namespace Phil\OpenBadges\Tests\Unit\Did;

use Override;
use Phil\OpenBadges\Contract\DocumentFetcher;
use Phil\OpenBadges\Did\DidWeb;
use Phil\OpenBadges\Exception\InvalidCredential;
use Phil\Url\Url;

/**
 * DID documents by identifier, as a fetcher would serve them, and the addresses it was asked.
 */
final class Documents implements DocumentFetcher
{
    /**
     * @var list<string>
     */
    public array $fetched = [];

    /**
     * @param array<string, string> $documents
     */
    public function __construct(
        private readonly array $documents,
    ) {
    }

    #[Override]
    public function fetch(Url $url): string
    {
        $this->fetched[] = $url->toString();

        foreach ($this->documents as $did => $document) {
            if (DidWeb::fromString($did)->url->equals($url)) {
                return $document;
            }
        }

        throw InvalidCredential::because('Not found.');
    }
}
