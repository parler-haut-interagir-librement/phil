<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Retention;

use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;
use Phil\PrivacyBundle\Domain\Subject\DataCategory;

/**
 * One line of a retention schedule: a store, what it holds, how long, what enforces it, what erasure does to
 * it, whether an access request returns it, and what the register of processing says of it — purpose, recipients,
 * processors and location.
 *
 * The identifier is the application's (`R-001`, `RET-042`, `users.email`): it is added and never renumbered,
 * because documents and `#[PersonalData]` declarations cite it rather than a position.
 *
 * Two combinations are refused because each is a contradiction a reader would otherwise have to notice:
 *
 * - **a purge job on an indefinite period**: a job with nothing it may ever purge;
 * - **"awaits expiry" without a fixed period**: the answer to an erasure request is the duration itself, and
 *   there is none to give.
 */
final readonly class RetentionRule
{
    public const string ID_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._-]{0,63}$/D';

    /**
     * @var list<DataCategory> an empty list states that the store holds no personal information
     */
    public array $categories;

    /**
     * @param list<DataCategory> $categories
     */
    public function __construct(
        public string $id,
        public string $store,
        array $categories,
        public RetentionPeriod $period,
        public Mechanism $mechanism,
        public ErasureEffect $erasure,
        public AccessResponse $access,
        public Processing $processing = new Processing(),
    ) {
        if (1 !== preg_match(self::ID_PATTERN, $id)) {
            throw InvalidPrivacyValue::because(\sprintf('"%s" is not a retention rule identifier: letters, digits, ".", "_" and "-", at most 64 characters.', $id));
        }

        if ('' === trim($store)) {
            throw InvalidPrivacyValue::because(\sprintf('Retention rule %s names no store.', $id));
        }

        $seen = [];

        foreach ($categories as $category) {
            if (isset($seen[$category->value])) {
                throw InvalidPrivacyValue::because(\sprintf('Retention rule %s lists the category "%s" twice.', $id, $category->value));
            }

            $seen[$category->value] = $category;
        }

        if (Mechanism::Job === $mechanism && RetentionPeriodKind::Indefinite === $period->kind) {
            throw InvalidPrivacyValue::because(\sprintf('Retention rule %s has a purge job on an indefinite period: the job could never purge anything.', $id));
        }

        if (ErasureEffect::AwaitsExpiry === $erasure && !$period->isFixed()) {
            throw InvalidPrivacyValue::because(\sprintf('Retention rule %s answers erasure with "awaits expiry" but has no fixed period to give as the answer.', $id));
        }

        $this->categories = $categories;
    }

    public function holdsPersonalInformation(): bool
    {
        return [] !== $this->categories;
    }
}
