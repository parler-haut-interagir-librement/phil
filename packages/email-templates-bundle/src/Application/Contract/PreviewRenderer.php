<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Application\Contract;

use Phil\EmailTemplatesBundle\Application\Preview\PreviewVariant;
use Phil\EmailTemplatesBundle\Application\Rendering\RenderedEmail;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Locale;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Site;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateKey;

/**
 * What the preview of the administration draws: the variants of a template — each segment, each locale — and each one
 * rendered with its sample data. Replace it to preview with data of your own, or with a dedicated preview package.
 */
interface PreviewRenderer
{
    /**
     * The variants of what the site sends, or of the draft it keeps in exactly that locale when `$draft` is true.
     *
     * @return list<PreviewVariant>
     */
    public function variants(TemplateKey $key, Site $site, Locale $locale, bool $draft = false): array;

    public function render(PreviewVariant $variant): RenderedEmail;
}
