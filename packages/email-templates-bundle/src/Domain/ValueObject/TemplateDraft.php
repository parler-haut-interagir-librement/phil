<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Domain\ValueObject;

use DateTimeImmutable;
use Phil\EmailTemplatesBundle\Domain\Status\PublicationState;

/**
 * The working copy of a template for a site and a locale: what its editors change, sent only once published.
 *
 * `version` is the version the draft belongs to, which publishing freezes; `revision` counts the saves of the draft, so
 * that a save made from a stale page is refused; `liveVersion` is the version the sites send, `null` while nothing is
 * published.
 */
final readonly class TemplateDraft
{
    /**
     * @param int<1, max>      $version
     * @param int<1, max>      $revision
     * @param int<1, max>|null $liveVersion
     * @param string|null      $author      the identifier of who saved it last, never an email address
     */
    public function __construct(
        public TemplateKey $key,
        public Site $site,
        public Locale $locale,
        public int $version,
        public int $revision,
        public TemplateDefinition $definition,
        public PublicationState $state,
        public DateTimeImmutable $savedAt,
        public ?string $author = null,
        public ?int $liveVersion = null,
    ) {
    }

    /**
     * Whether the draft holds something the sites do not send yet.
     */
    public function isPending(): bool
    {
        return PublicationState::Published !== $this->state;
    }
}
