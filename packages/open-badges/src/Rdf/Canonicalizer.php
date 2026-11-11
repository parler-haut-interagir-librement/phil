<?php

declare(strict_types=1);

namespace Phil\OpenBadges\Rdf;

use Phil\OpenBadges\Exception\InvalidCredential;

/**
 * The RDF Dataset Canonicalization of the W3C, RDFC-1.0 with SHA-256: every blank node gets a canonical label
 * (`c14n0`, `c14n1`…) that depends only on the dataset, and the quads are returned as sorted canonical N-Quads.
 *
 * A dataset built to make the labelling explode — blank nodes nobody can tell apart, by the dozen — is refused once
 * the algorithm has spent `MAX_CALLS` hashes of the n-degree, or meets a group of more than `MAX_PERMUTED` related
 * blank nodes to permute, rather than hold a worker.
 *
 * The quads given form a set: a quad given twice counts once.
 *
 * @internal
 */
final class Canonicalizer
{
    public const int MAX_CALLS = 2_000;

    public const int MAX_PERMUTED = 5;

    /**
     * @var array<string, list<array{string, string, string, string}>> the quads of each blank node
     */
    private array $mentions = [];

    /**
     * @var array<string, string>
     */
    private array $firstDegree = [];

    private readonly IdentifierIssuer $canonical;

    private int $calls = 0;

    private function __construct()
    {
        $this->canonical = new IdentifierIssuer('c14n');
    }

    /**
     * @param list<array{string, string, string, string}> $quads subject, predicate, object, and graph or ''
     */
    public static function canonicalize(array $quads): string
    {
        return new self()->run($quads);
    }

    /**
     * @param list<array{string, string, string, string}> $quads
     */
    private function run(array $quads): string
    {
        $unique = [];

        foreach ($quads as $quad) {
            $unique[implode(' ', $quad)] = $quad;
        }

        foreach ($unique as $quad) {
            foreach ([0, 2, 3] as $position) {
                if (str_starts_with($quad[$position], '_:')) {
                    $this->mentions[$quad[$position]][] = $quad;
                }
            }
        }

        $this->issueCanonical();
        $lines = [];

        foreach ($unique as $quad) {
            foreach ([0, 2, 3] as $position) {
                if (str_starts_with($quad[$position], '_:')) {
                    $quad[$position] = '_:'.$this->canonical->issue($quad[$position]);
                }
            }

            $lines[] = NQuads::line($quad);
        }

        sort($lines, \SORT_STRING);

        return implode('', $lines);
    }

    /**
     * The canonical labels: first the blank nodes whose first-degree hash is unique, in the order of their hashes,
     * then the others, group by group, in the order of their n-degree hashes.
     */
    private function issueCanonical(): void
    {
        $byHash = [];

        foreach (array_keys($this->mentions) as $blank) {
            $byHash[$this->hashFirstDegree($blank)][] = $blank;
        }

        ksort($byHash, \SORT_STRING);

        foreach ($byHash as $hash => $blanks) {
            if (1 === \count($blanks)) {
                $this->canonical->issue($blanks[0]);
                unset($byHash[$hash]);
            }
        }

        foreach ($byHash as $blanks) {
            $results = [];

            foreach ($blanks as $blank) {
                if (!$this->canonical->has($blank)) {
                    $issuer = new IdentifierIssuer('b');
                    $issuer->issue($blank);
                    $results[] = $this->hashNDegree($blank, $issuer);
                }
            }

            usort($results, static fn (array $one, array $other): int => strcmp($one[0], $other[0]));

            foreach ($results as [, $issuer]) {
                foreach (array_keys($issuer->issued) as $blank) {
                    $this->canonical->issue($blank);
                }
            }
        }
    }

    private function hashFirstDegree(string $blank): string
    {
        return $this->firstDegree[$blank] ??= $this->firstDegreeOf($blank);
    }

    private function firstDegreeOf(string $blank): string
    {
        $lines = [];

        foreach ($this->mentions[$blank] as $quad) {
            foreach ([0, 2, 3] as $position) {
                if (str_starts_with($quad[$position], '_:')) {
                    $quad[$position] = $quad[$position] === $blank ? '_:a' : '_:z';
                }
            }

            $lines[] = NQuads::line($quad);
        }

        sort($lines, \SORT_STRING);

        return hash('sha256', implode('', $lines));
    }

    /**
     * @param array{string, string, string, string} $quad
     */
    private function hashRelated(string $related, array $quad, IdentifierIssuer $issuer, string $position): string
    {
        $input = $position.('g' === $position ? '' : $quad[1]).'_:';

        if ($this->canonical->has($related)) {
            $input .= $this->canonical->issue($related);
        } elseif ($issuer->has($related)) {
            $input .= $issuer->issue($related);
        } else {
            $input = $position.('g' === $position ? '' : $quad[1]).$this->hashFirstDegree($related);
        }

        return hash('sha256', $input);
    }

    /**
     * @return array{string, IdentifierIssuer}
     */
    private function hashNDegree(string $blank, IdentifierIssuer $issuer): array
    {
        if (++$this->calls > self::MAX_CALLS) {
            throw InvalidCredential::because('The canonical form of the document takes too much work to compute.');
        }

        $related = [];

        foreach ($this->mentions[$blank] as $quad) {
            foreach (['s' => 0, 'o' => 2, 'g' => 3] as $position => $index) {
                if (str_starts_with($quad[$index], '_:') && $quad[$index] !== $blank) {
                    $related[$this->hashRelated($quad[$index], $quad, $issuer, $position)][] = $quad[$index];
                }
            }
        }

        ksort($related, \SORT_STRING);
        $data = '';

        foreach ($related as $hash => $blanks) {
            $data .= $hash;
            [$path, $issuer] = $this->choosePath($blanks, $issuer);
            $data .= $path;
        }

        return [hash('sha256', $data), $issuer];
    }

    /**
     * @param list<string> $blanks
     *
     * @return array{string, IdentifierIssuer}
     */
    private function choosePath(array $blanks, IdentifierIssuer $issuer): array
    {
        if (\count($blanks) > self::MAX_PERMUTED) {
            throw InvalidCredential::because('The canonical form of the document permutes too many blank nodes nothing tells apart.');
        }

        $chosenPath = '';
        $chosenIssuer = null;

        foreach (self::permutations($blanks) as $permutation) {
            $copy = clone $issuer;
            $path = '';
            $recursion = [];

            foreach ($permutation as $related) {
                if ($this->canonical->has($related)) {
                    $path .= '_:'.$this->canonical->issue($related);
                } else {
                    if (!$copy->has($related)) {
                        $recursion[] = $related;
                    }

                    $path .= '_:'.$copy->issue($related);
                }

                if ($this->worse($path, $chosenPath)) {
                    continue 2;
                }
            }

            foreach ($recursion as $related) {
                [$hash, $copy] = $this->hashNDegree($related, $copy);
                $path .= '_:'.$copy->issue($related).'<'.$hash.'>';

                if ($this->worse($path, $chosenPath)) {
                    continue 2;
                }
            }

            if (!$chosenIssuer instanceof IdentifierIssuer || strcmp($path, $chosenPath) < 0) {
                $chosenPath = $path;
                $chosenIssuer = $copy;
            }
        }

        return [$chosenPath, $chosenIssuer ?? $issuer];
    }

    private function worse(string $path, string $chosen): bool
    {
        return '' !== $chosen && \strlen($path) >= \strlen($chosen) && strcmp($path, $chosen) > 0;
    }

    /**
     * Every order of the blank nodes.
     *
     * @param array<int, string> $items
     *
     * @return iterable<list<string>>
     */
    private static function permutations(array $items): iterable
    {
        if ([] === $items) {
            yield [];
        }

        foreach ($items as $index => $item) {
            $rest = $items;
            unset($rest[$index]);

            foreach (self::permutations($rest) as $permutation) {
                yield [$item, ...$permutation];
            }
        }
    }
}
