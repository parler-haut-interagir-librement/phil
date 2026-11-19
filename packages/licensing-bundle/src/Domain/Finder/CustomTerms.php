<?php

declare(strict_types=1);

namespace Phil\LicensingBundle\Domain\Finder;

/**
 * The terms of a custom license, as clauses in reading order: sharing, commercial use, adaptations, credit, warranty.
 */
final readonly class CustomTerms
{
    /**
     * @param list<TermsClause> $clauses
     */
    private function __construct(
        public array $clauses,
    ) {
    }

    public static function of(bool $attribution, bool $commercialUse, Modifications $modifications): self
    {
        return new self([
            TermsClause::Share,
            $commercialUse ? TermsClause::CommercialUse : TermsClause::NoCommercialUse,
            match ($modifications) {
                Modifications::Allowed => TermsClause::Adaptations,
                Modifications::ShareAlike => TermsClause::AdaptationsShareAlike,
                Modifications::Forbidden => TermsClause::NoAdaptations,
            },
            $attribution ? TermsClause::Attribution : TermsClause::NoAttribution,
            TermsClause::NoWarranty,
        ]);
    }

    public function has(TermsClause $clause): bool
    {
        return \in_array($clause, $this->clauses, true);
    }
}
