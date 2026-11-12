<?php

declare(strict_types=1);

namespace Phil\SearchSynonyms\Application;

use Phil\SearchSynonyms\Domain\ValueObject\SynonymList;
use Phil\SearchSynonyms\Domain\ValueObject\Violation;

/**
 * What a list of synonyms holds that is allowed but likely a mistake, before an administrator saves it: a phrase on the
 * left of two rules, which a search reads as both merged; a one-way rule leading to a phrase another rule rewrites,
 * which a search does not follow — synonyms are applied once, never in chain. A rule that breaks a limit is refused
 * when it is built, and never reaches the validator.
 */
final readonly class SynonymValidator
{
    /**
     * @return list<Violation> in the order of the rules
     */
    public function validate(SynonymList $list): array
    {
        $violations = [];
        $firstRule = [];

        foreach ($list->sets as $position => $set) {
            foreach ($set->from as $phrase) {
                if (isset($firstRule[$phrase])) {
                    $violations[] = new Violation($position, Violation::OVERLAP, \sprintf('"%s" is already on the left of rule %d: a search reads it as both.', $phrase, $firstRule[$phrase] + 1));
                } else {
                    $firstRule[$phrase] = $position;
                }
            }
        }

        foreach ($list->sets as $position => $set) {
            if ($set->equivalent) {
                continue;
            }

            foreach (array_diff($set->to, $set->from) as $phrase) {
                if (isset($firstRule[$phrase]) && $firstRule[$phrase] !== $position) {
                    $violations[] = new Violation($position, Violation::CHAIN, \sprintf('"%s" is rewritten by rule %d, which a search does not follow from this one.', $phrase, $firstRule[$phrase] + 1));
                }
            }
        }

        usort($violations, static fn (Violation $a, Violation $b): int => $a->set <=> $b->set);

        return $violations;
    }
}
