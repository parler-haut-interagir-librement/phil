<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Application;

use Phil\ModerationBundle\Domain\Identifiers;
use Phil\ModerationBundle\Domain\Status\Ground;

/**
 * What each reason of the catalogue rests on — the terms of the site or the law — and the provision a statement of
 * reasons names by default. A reason the configuration does not list rests on the terms, with no provision; the
 * reason `illegal` rests on the law unless the configuration says otherwise.
 */
final readonly class ReasonGrounds
{
    /** @var array<string, Ground> */
    private array $grounds;

    /** @var array<string, string> */
    private array $references;

    /**
     * @param array<string, array{ground: string, reference?: string|null}> $grounds by reason
     */
    public function __construct(array $grounds = [])
    {
        $kinds = ['illegal' => Ground::Illegal];
        $references = [];

        foreach ($grounds as $reason => $ground) {
            Identifiers::code($reason, 'a reason');
            $kinds[$reason] = Ground::from($ground['ground']);
            $reference = Identifiers::text($ground['reference'] ?? null, 255, 'A reference');

            if (null !== $reference) {
                $references[$reason] = $reference;
            }
        }

        $this->grounds = $kinds;
        $this->references = $references;
    }

    public function ground(string $reason): Ground
    {
        return $this->grounds[$reason] ?? Ground::Terms;
    }

    public function reference(string $reason): ?string
    {
        return $this->references[$reason] ?? null;
    }
}
