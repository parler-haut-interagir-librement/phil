<?php

declare(strict_types=1);

namespace Phil\FaqBundle\Application;

use DateTimeZone;
use Phil\FaqBundle\Application\Contract\Throttle;
use Phil\FaqBundle\Application\Exception\TooManyRequests;
use Phil\FaqBundle\Domain\Contract\UnansweredSearchRepository;
use Phil\FaqBundle\Domain\ValueObject\Locale;
use Psr\Clock\ClockInterface;

/**
 * Counts the searches that found no question, normalized, per language and per day: a total, which says what readers
 * look for and the questions lack, never who looked.
 */
final readonly class UnansweredSearches
{
    /** The shortest normalized search counted, in characters: shorter ones say nothing. */
    public const int MIN_LENGTH = 3;

    public function __construct(
        private UnansweredSearchRepository $repository,
        private QueryNormalizer $normalizer,
        private Throttle $throttle,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return bool whether the search was counted: a search shorter than three characters is not
     *
     * @throws TooManyRequests when the language counted too many searches lately
     */
    public function record(Locale $locale, string $query): bool
    {
        $normalized = $this->normalizer->normalize($query);

        if (mb_strlen($normalized, 'UTF-8') < self::MIN_LENGTH) {
            return false;
        }

        $this->throttle->consume($locale->value);
        $this->repository->record($locale->value, $normalized, $this->clock->now()->setTimezone(new DateTimeZone('UTC'))->setTime(0, 0));

        return true;
    }
}
