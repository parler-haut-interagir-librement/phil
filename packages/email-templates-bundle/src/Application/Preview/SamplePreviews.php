<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Application\Preview;

use Override;
use Phil\EmailTemplatesBundle\Application\Contract\PreviewRenderer;
use Phil\EmailTemplatesBundle\Application\Rendering\EmailRenderer;
use Phil\EmailTemplatesBundle\Application\Rendering\RenderedEmail;
use Phil\EmailTemplatesBundle\Application\TemplateResolver;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Locale;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Site;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateKey;

/**
 * The preview of the bundle: the variants of a template are the recipient in no segment, in each segment the template
 * declares, then in all of them when it declares several; each renders with the sample values of the schema, as the site
 * sends it, as its draft, or at a published version.
 */
final readonly class SamplePreviews implements PreviewRenderer
{
    public function __construct(
        private TemplateResolver $resolver,
        private EmailRenderer $renderer,
    ) {
    }

    #[Override]
    public function variants(TemplateKey $key, Site $site, Locale $locale, bool $draft = false): array
    {
        $segments = ($draft ? $this->resolver->resolveDraft($key, $site, $locale) : $this->resolver->resolve($key, $site, $locale))->definition->segments;
        $variants = [new PreviewVariant($key, $site, $locale, draft: $draft)];

        foreach ($segments as $segment) {
            $variants[] = new PreviewVariant($key, $site, $locale, [$segment], draft: $draft);
        }

        if (\count($segments) > 1) {
            $variants[] = new PreviewVariant($key, $site, $locale, $segments, draft: $draft);
        }

        return $variants;
    }

    #[Override]
    public function render(PreviewVariant $variant): RenderedEmail
    {
        $template = match (true) {
            null !== $variant->version => $this->resolver->resolveVersion($variant->key, $variant->site, $variant->locale, $variant->version),
            $variant->draft => $this->resolver->resolveDraft($variant->key, $variant->site, $variant->locale),
            default => $this->resolver->resolve($variant->key, $variant->site, $variant->locale),
        };

        return $this->renderer->renderSamples($template, $variant->site, $variant->segments);
    }
}
