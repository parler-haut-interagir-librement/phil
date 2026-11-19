<?php

declare(strict_types=1);

namespace Phil\LicensingBundle\Infrastructure\Translation;

use Phil\LicensingBundle\Domain\Finder\CustomTerms;
use Phil\LicensingBundle\Domain\Finder\TermsClause;

/**
 * The text of custom terms, in the reader's language: a heading naming the holder, then one paragraph per clause,
 * `terms.<clause>` of the domain `phil_licensing`. The text is what a declaration under `custom` keeps as its license
 * text.
 */
final readonly class CustomTermsWriter
{
    public function __construct(
        private LicenseNames $names,
    ) {
    }

    /**
     * @param string $holder who offers the work under the terms: "Example Press"
     */
    public function write(CustomTerms $terms, string $holder, ?string $locale = null): string
    {
        $parameters = ['%holder%' => $holder];
        $paragraphs = [$this->names->trans('terms.heading', $parameters, $locale)];

        foreach ($terms->clauses as $clause) {
            $paragraphs[] = $this->clause($clause, $parameters, $locale);
        }

        return implode("\n\n", $paragraphs);
    }

    /**
     * @param array<string, string> $parameters
     */
    private function clause(TermsClause $clause, array $parameters, ?string $locale): string
    {
        return $this->names->trans('terms.'.$clause->value, $parameters, $locale);
    }
}
