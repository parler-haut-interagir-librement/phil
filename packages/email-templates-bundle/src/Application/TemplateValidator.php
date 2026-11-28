<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Application;

use Phil\EmailTemplatesBundle\Application\Contract\TemplateEngine;
use Phil\EmailTemplatesBundle\Application\Rendering\EmailRenderer;
use Phil\EmailTemplatesBundle\Domain\Exception\EmailTemplatesException;
use Phil\EmailTemplatesBundle\Domain\Exception\InvalidTemplate;
use Phil\EmailTemplatesBundle\Domain\Status\TemplateOrigin;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Locale;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Site;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateDefinition;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateKey;

/**
 * Checks a template before it is saved: its sources compile in the sandbox and read only what the schema declares,
 * then every variant — no segment, then each segment declared — renders with the sample values, the layout, the
 * inlined styles and the embedded images included.
 */
final readonly class TemplateValidator
{
    public function __construct(
        private TemplateEngine $engine,
        private EmailRenderer $renderer,
    ) {
    }

    /**
     * @throws InvalidTemplate listing every reason
     */
    public function validate(TemplateKey $key, Site $site, Locale $locale, TemplateDefinition $definition): void
    {
        $errors = $this->engine->check($definition);

        if ([] === $errors) {
            $template = new ResolvedTemplate($key, $site, $locale, $definition, $site->isShared() ? TemplateOrigin::Shared : TemplateOrigin::Site);

            foreach ([[], ...array_map(static fn (string $segment): array => [$segment], $definition->segments)] as $segments) {
                try {
                    $this->renderer->renderSamples($template, $site, $segments);
                } catch (EmailTemplatesException $exception) {
                    $errors[] = [] === $segments ? $exception->getMessage() : \sprintf('In the segment "%s": %s', $segments[0], $exception->getMessage());
                }
            }
        }

        if ([] !== $errors) {
            throw InvalidTemplate::withErrors(array_values(array_unique($errors)));
        }
    }
}
