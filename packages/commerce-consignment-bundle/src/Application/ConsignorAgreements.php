<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Application;

use DateTimeImmutable;
use Phil\CommerceConsignmentBundle\Domain\Consignor\StatementFrequency;
use Phil\CommerceConsignmentBundle\Domain\Consignor\UnsoldPolicy;
use Phil\CommerceConsignmentBundle\Domain\Contract\ConsignorRepository;
use Phil\CommerceConsignmentBundle\Domain\Exception\ConsignmentRefused;
use Phil\CommerceConsignmentBundle\Domain\Model\ConsignmentAgreement;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\Owner;

/**
 * What the shop agreed with each consignor, version after version. The first version applies from any instant, the
 * consignor's units already on hand included; each next one starts after the last and never before it is written, so
 * that a changed agreement never changes the units received under the previous one.
 *
 * Nothing is committed here: the caller flushes.
 */
final readonly class ConsignorAgreements
{
    public function __construct(
        private ConsignorRepository $consignors,
    ) {
    }

    /**
     * Writes a new version of a consignor's agreement, which applies to the units received from `$effectiveFrom` on.
     *
     * @see ConsignmentAgreement::version() for the term, the policy and the extensions
     */
    public function agree(
        Owner $consignor,
        DateTimeImmutable $effectiveFrom,
        ?int $termDays,
        UnsoldPolicy $unsoldPolicy,
        StatementFrequency $frequency,
        DateTimeImmutable $at,
        ?int $extensionDays = null,
        int $extensions = 1,
    ): ConsignmentAgreement {
        $agreement = ConsignmentAgreement::version($consignor, $effectiveFrom, $termDays, $unsoldPolicy, $frequency, $at, $extensionDays, $extensions);
        $versions = $this->consignors->agreementsOf($agreement->consignor);

        if ([] !== $versions) {
            $since = max($at, $versions[\count($versions) - 1]->effectiveFrom()->modify('+1 second'));

            if ($agreement->effectiveFrom() < $since) {
                throw ConsignmentRefused::agreementInThePast($agreement->consignor, $agreement->effectiveFrom()->format(\DATE_ATOM), $since->format(\DATE_ATOM));
            }
        }

        $this->consignors->addAgreement($agreement);

        return $agreement;
    }

    /**
     * The version of a consignor's agreement in effect at the instant — for units received then —; null before the first.
     */
    public function agreementAt(Owner $consignor, DateTimeImmutable $instant): ?ConsignmentAgreement
    {
        return self::versionAt($this->consignors->agreementsOf($consignor->key()), $instant);
    }

    /**
     * @param list<ConsignmentAgreement> $versions the earliest effective first
     */
    public static function versionAt(array $versions, DateTimeImmutable $instant): ?ConsignmentAgreement
    {
        $current = null;

        foreach ($versions as $version) {
            if ($version->effectiveFrom() <= $instant) {
                $current = $version;
            }
        }

        return $current;
    }
}
