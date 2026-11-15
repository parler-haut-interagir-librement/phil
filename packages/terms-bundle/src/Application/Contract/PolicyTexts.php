<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Application\Contract;

use Phil\TermsBundle\Domain\Exception\InvalidTerms;
use Phil\TermsBundle\Domain\TextFormat;
use Phil\TermsBundle\Domain\ValueObject\PolicyVersion;

/**
 * The texts of the versions: checked before a version is published, rendered as HTML for a page, and compared to show
 * what changed from one version to another.
 */
interface PolicyTexts
{
    /**
     * @throws InvalidTerms when the text cannot be read in its format, or the format is not available
     */
    public function check(TextFormat $format, string $text): void;

    /**
     * The HTML of the text of a version in a locale, else in the fallback locale.
     */
    public function toHtml(PolicyVersion $version, string $locale, string $fallback): string;

    /**
     * The HTML of the changes from one version to another in a locale, or `null` when they cannot be shown.
     */
    public function changes(PolicyVersion $old, PolicyVersion $new, string $locale, string $fallback): ?string;
}
