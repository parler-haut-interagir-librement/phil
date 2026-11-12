<?php

declare(strict_types=1);

namespace Phil\LegalNotices;

/**
 * What `LegalNotices::check()` finds wrong in a public document: a field of the identity it requires and lacks
 * (`missing_field`), a body that cannot be rendered (`unreadable_body`) or a placeholder without a value
 * (`unknown_placeholder`), with the version and the locale it was found in, when it is one.
 */
final readonly class Problem
{
    public function __construct(
        public DocumentType $type,
        public string $code,
        public string $message,
        public ?int $version = null,
        public ?string $locale = null,
    ) {
    }
}
