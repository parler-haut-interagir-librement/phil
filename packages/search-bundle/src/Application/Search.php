<?php

declare(strict_types=1);

namespace Phil\SearchBundle\Application;

use Generator;
use Phil\SearchBundle\Application\Contract\SynonymAwareIndex;
use Phil\SearchBundle\Domain\Exception\InvalidSearch;
use Phil\SearchBundle\Domain\ValueObject\Document;
use Phil\SearchBundle\Domain\ValueObject\Index;
use Phil\SearchBundle\Domain\ValueObject\SearchQuery;
use Phil\SearchBundle\Domain\ValueObject\SearchResult;

/**
 * Resources in their index: indexed when they change — or removed, when they must no longer be found —, searched,
 * and rebuilt from their provider.
 *
 * With synonyms, a search reads the words of its language with their alternatives; an index that keeps synonyms
 * itself is given those of the configured languages instead, at each rebuild and when asked.
 */
final readonly class Search
{
    /**
     * @param iterable<DocumentProvider> $providers
     * @param list<string>               $synonymLanguages the languages whose synonyms an index that keeps them gets
     */
    public function __construct(
        private SearchIndex $searchIndex,
        private iterable $providers,
        private int $batch = 500,
        private ?SynonymExpander $synonyms = null,
        private array $synonymLanguages = [],
    ) {
    }

    /**
     * Indexes resources their providers know, the documents in one call to the index and the identifiers to remove in
     * one per index; returns how many indexes they went to.
     */
    public function index(object ...$resources): int
    {
        $documents = [];
        $forgotten = [];

        foreach ($resources as $resource) {
            foreach ($this->providersOf($resource) as $provider) {
                $document = $provider->document($resource);

                if ($document instanceof Document) {
                    $documents[] = $document;
                } else {
                    $forgotten[$provider->index()][] = $provider->id($resource);
                }
            }
        }

        if ([] !== $documents) {
            $this->searchIndex->index(...$documents);
        }

        foreach ($forgotten as $index => $ids) {
            $this->forget($index, ...$ids);
        }

        return \count($documents);
    }

    public function remove(object $resource): void
    {
        $this->removeAt($this->addressesOf($resource));
    }

    /**
     * Removes the documents at these addresses, those of one index at once.
     *
     * @param iterable<array{string, string}> $addresses each index and an identifier there
     */
    public function removeAt(iterable $addresses): void
    {
        $byIndex = [];

        foreach ($addresses as [$index, $id]) {
            $byIndex[$index][] = $id;
        }

        foreach ($byIndex as $index => $ids) {
            $this->forget($index, ...$ids);
        }
    }

    /**
     * Where a resource is in the indexes: each index and the resource's identifier there. Read it before the resource
     * is deleted, while its identifier is still known.
     *
     * @return list<array{string, string}>
     */
    public function addressesOf(object $resource): array
    {
        $addresses = [];

        foreach ($this->providersOf($resource) as $provider) {
            $addresses[] = [$provider->index(), $provider->id($resource)];
        }

        return $addresses;
    }

    /**
     * Removes documents from an index by their identifiers.
     */
    public function forget(string $index, string ...$ids): void
    {
        $this->searchIndex->remove(Index::check($index), array_values($ids));
    }

    public function search(string $index, SearchQuery $query): SearchResult
    {
        Index::check($index);

        if ($this->synonyms instanceof SynonymExpander && !$this->searchIndex instanceof SynonymAwareIndex) {
            $query = $this->synonyms->expand($query);
        }

        return $this->searchIndex->search($index, $query);
    }

    /**
     * Gives an index that keeps synonyms itself those of the configured languages; returns whether it took them —
     * not when it reads the alternatives of each query, or when there are no synonyms or languages.
     */
    public function publishSynonyms(string $index): bool
    {
        Index::check($index);

        if (!$this->searchIndex instanceof SynonymAwareIndex || !$this->synonyms instanceof SynonymExpander || [] === $this->synonymLanguages) {
            return false;
        }

        $this->searchIndex->replaceSynonyms($index, $this->synonyms->engineSynonyms($this->synonymLanguages));

        return true;
    }

    /**
     * Rebuilds an index from its providers; returns how many documents it holds. The index answers searches all along
     * — with the documents it held, until the rebuild is complete —, and a rebuild that fails leaves it as it was.
     *
     * @throws InvalidSearch when no provider gives that index
     */
    public function reindex(string $index): int
    {
        Index::check($index);
        $providers = [];

        foreach ($this->providers as $provider) {
            if ($provider->index() === $index) {
                $providers[] = $provider;
            }
        }

        if ([] === $providers) {
            throw InvalidSearch::because(\sprintf('No provider gives the index "%s".', $index));
        }

        $count = 0;
        $this->searchIndex->rebuild($index, $this->batches($index, $providers, $count));
        $this->publishSynonyms($index);

        return $count;
    }

    /**
     * The indexes the providers give, each once.
     *
     * @return list<string>
     */
    public function indexes(): array
    {
        $indexes = [];

        foreach ($this->providers as $provider) {
            if (!\in_array($provider->index(), $indexes, true)) {
                $indexes[] = $provider->index();
            }
        }

        return $indexes;
    }

    /**
     * The providers' documents, a batch at a time.
     *
     * @param list<DocumentProvider> $providers
     *
     * @return Generator<int, list<Document>>
     */
    private function batches(string $index, array $providers, int &$count): Generator
    {
        $pending = [];

        foreach ($providers as $provider) {
            foreach ($provider->all() as $resource) {
                $document = $provider->document($resource);

                if (!$document instanceof Document) {
                    continue;
                }

                if ($document->index !== $index) {
                    throw InvalidSearch::because(\sprintf('A provider of the index "%s" gave a document of the index "%s".', $index, $document->index));
                }

                $pending[] = $document;
                ++$count;

                if (\count($pending) >= $this->batch) {
                    yield $pending;
                    $pending = [];
                }
            }
        }

        if ([] !== $pending) {
            yield $pending;
        }
    }

    /**
     * @return list<DocumentProvider>
     */
    private function providersOf(object $resource): array
    {
        $providers = [];

        foreach ($this->providers as $provider) {
            if ($provider->supports($resource)) {
                $providers[] = $provider;
            }
        }

        return $providers;
    }
}
