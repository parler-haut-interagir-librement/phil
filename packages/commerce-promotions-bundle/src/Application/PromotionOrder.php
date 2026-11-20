<?php

declare(strict_types=1);

namespace Phil\CommercePromotionsBundle\Application;

use Phil\CommercePromotionsBundle\Domain\Model\Promotion;
use Symfony\Component\DependencyInjection\Compiler\BeforeAfterSorter;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

/**
 * The order promotions are tried in: by code, then moved so that each one comes after the promotions its `after`
 * list names, by the sorter Symfony orders tagged services with. A name that is no active promotion is ignored.
 * Lists that loop — two promotions each after the other — are set aside, and those promotions are tried by code.
 */
final class PromotionOrder
{
    /**
     * @param array<Promotion> $promotions
     *
     * @return list<Promotion>
     */
    public static function sort(array $promotions): array
    {
        $byCode = [];
        $constraints = [];

        foreach ($promotions as $promotion) {
            $byCode[$promotion->code] = $promotion;

            if ([] !== $promotion->after) {
                $constraints[$promotion->code] = ['after' => $promotion->after];
            }
        }

        ksort($byCode, \SORT_STRING);
        $seed = array_map(strval(...), array_keys($byCode));

        try {
            $sorted = BeforeAfterSorter::sort($seed, $constraints);
        } catch (InvalidArgumentException) {
            $sorted = $seed;
        }

        return array_map(static fn (string $code): Promotion => $byCode[$code], $sorted);
    }
}
