<?php

declare(strict_types=1);

namespace Phil\Citations;

use Normalizer;
use Phil\Citations\Exception\InvalidReference;
use Phil\Citations\Exception\UnknownReference;
use Phil\Citations\Internal\Creators;
use Phil\Citations\Model\DateVariable;
use Phil\Citations\Model\Reference;

/**
 * The references a text may cite, without duplicates: a reference with the DOI of one already listed, or with its
 * title, year and first author when it has no DOI, is the same document under another id. Citing either id
 * cites the first, and the bibliography lists it once. Two different references under one id are refused; the same
 * reference given twice is kept once.
 */
final readonly class ReferenceList
{
    /** @var array<string, Reference> */
    private array $references;

    /** @var array<string, string> */
    private array $aliases;

    /**
     * @param iterable<Reference> $references
     *
     * @throws InvalidReference two different references under one id
     */
    public function __construct(iterable $references)
    {
        $json = new CslJson();
        $kept = [];
        $aliases = [];
        $byKey = [];

        foreach ($references as $reference) {
            $id = $reference->id;

            if (isset($aliases[$id])) {
                if ($aliases[$id] === $id && $json->encode([$kept[$id]]) !== $json->encode([$reference])) {
                    throw InvalidReference::because(\sprintf('two different references share the id "%s"', $id));
                }

                continue;
            }

            $key = $this->key($reference);
            $canonical = $byKey[$key] ?? $id;
            $byKey[$key] = $canonical;
            $aliases[$id] = $canonical;

            if ($canonical === $id) {
                $kept[$id] = $reference;
            }
        }

        $this->references = $kept;
        $this->aliases = $aliases;
    }

    /**
     * The reference an id names, or the one it duplicates.
     *
     * @throws UnknownReference
     */
    public function get(string $id): Reference
    {
        return $this->references[$this->aliases[$id] ?? ''] ?? throw UnknownReference::id($id);
    }

    public function has(string $id): bool
    {
        return isset($this->aliases[$id]);
    }

    /**
     * The references without duplicates, in the order given.
     *
     * @return list<Reference>
     */
    public function all(): array
    {
        return array_values($this->references);
    }

    private function key(Reference $reference): string
    {
        $doi = strtolower(trim((string) $reference->field('DOI')));

        if ('' !== $doi) {
            return 'doi:'.preg_replace('#^(?:https?://(?:dx\.)?doi\.org/|doi:)#', '', $doi);
        }

        $title = $this->folded((string) $reference->title());

        if ('' === $title) {
            return 'id:'.$reference->id;
        }

        $first = Creators::of($reference)[0] ?? null;

        return 'work:'.implode('|', [
            $title,
            (string) $reference->date(DateVariable::Issued)?->startYear(),
            $this->folded((string) $first?->familyName()),
        ]);
    }

    private function folded(string $text): string
    {
        $decomposed = (string) Normalizer::normalize(mb_strtolower($text), Normalizer::FORM_D);

        return (string) preg_replace('/[^\p{Ll}\p{Lo}\p{N}]+/u', '', $decomposed);
    }
}
