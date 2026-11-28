<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Application\Contract;

use Phil\EmailTemplatesBundle\Domain\ValueObject\Locale;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateDefinition;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateKey;

/**
 * The templates shipped as files: those of the bundle, and those of the application, which override them by key and
 * locale. Every site renders them until it saves a version of its own.
 */
interface DefaultTemplates
{
    public function find(TemplateKey $key, Locale $locale): ?TemplateDefinition;

    /**
     * The locales of each default template, by key, both sorted.
     *
     * @return array<string, list<string>>
     */
    public function catalog(): array;
}
