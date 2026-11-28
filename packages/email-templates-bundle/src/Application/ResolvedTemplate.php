<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Application;

use Phil\EmailTemplatesBundle\Domain\Status\TemplateOrigin;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Locale;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Site;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateDefinition;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateKey;

/**
 * The template a render uses, and where it was found: the locale it was found in may be the language of the one asked
 * for, or the default locale. `draft` says it is a working copy, which only a preview renders.
 */
final readonly class ResolvedTemplate
{
    /**
     * @param int|null $version `null` for a default file
     */
    public function __construct(
        public TemplateKey $key,
        public Site $site,
        public Locale $locale,
        public TemplateDefinition $definition,
        public TemplateOrigin $origin,
        public ?int $version = null,
        public bool $draft = false,
    ) {
    }
}
