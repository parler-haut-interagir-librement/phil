<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Domain\ValueObject;

use DateTimeImmutable;

/**
 * One published version of a template for a site and a locale. Versions are numbered from 1 and never changed once
 * published: the next save of the draft starts the next version, and reverting to an earlier one copies it into the
 * draft.
 */
final readonly class TemplateVersion
{
    /**
     * @param int<1, max> $version
     * @param string|null $author  the identifier of who saved it last, never an email address
     */
    public function __construct(
        public TemplateKey $key,
        public Site $site,
        public Locale $locale,
        public int $version,
        public TemplateDefinition $definition,
        public DateTimeImmutable $savedAt,
        public ?string $author = null,
        public ?DateTimeImmutable $publishedAt = null,
    ) {
    }
}
