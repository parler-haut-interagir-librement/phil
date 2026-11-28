<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Application\Rendering;

use Phil\EmailTemplatesBundle\Application\Contract\EmailLayout;
use Phil\EmailTemplatesBundle\Application\Contract\EmbedLoader;
use Phil\EmailTemplatesBundle\Application\Contract\SiteScope;
use Phil\EmailTemplatesBundle\Application\Contract\StyleInliner;
use Phil\EmailTemplatesBundle\Application\Contract\TemplateEngine;
use Phil\EmailTemplatesBundle\Application\ResolvedTemplate;
use Phil\EmailTemplatesBundle\Application\TemplateResolver;
use Phil\EmailTemplatesBundle\Domain\Exception\InvalidTemplate;
use Phil\EmailTemplatesBundle\Domain\Exception\InvalidVariables;
use Phil\EmailTemplatesBundle\Domain\Exception\RenderingFailed;
use Phil\EmailTemplatesBundle\Domain\Exception\UnknownTemplate;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Locale;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Site;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateKey;

/**
 * Renders an email without sending it: the template a site renders in a locale is found, the values are checked against
 * its schema, its sources are rendered in the sandbox, wrapped in the house layout, the styles inlined, and the
 * plain-text part written from the HTML unless the template writes its own. The embedded images are read.
 *
 * ```php
 * $email = $renderer->render('order.confirmation', ['order' => ['number' => 'A-1042']], new RenderOptions(locale: 'fr_CA', segments: ['vip']));
 * $email->subject; $email->html; $email->text;
 * ```
 */
final readonly class EmailRenderer
{
    /**
     * @param int<1, max> $maxHtmlBytes the largest document rendered, in bytes
     */
    public function __construct(
        private TemplateResolver $resolver,
        private TemplateEngine $engine,
        private EmailLayout $layout,
        private StyleInliner $inliner,
        private SiteScope $sites,
        private ?EmbedLoader $embeds = null,
        private int $maxHtmlBytes = 1_048_576,
    ) {
    }

    /**
     * @param array<array-key, mixed> $variables
     *
     * @throws InvalidTemplate  when the key or the locale is out of shape
     * @throws UnknownTemplate  when no template is found
     * @throws InvalidVariables when the values do not match the schema
     * @throws RenderingFailed  when the template fails to render
     */
    public function render(TemplateKey|string $key, array $variables = [], RenderOptions $options = new RenderOptions()): RenderedEmail
    {
        $key = \is_string($key) ? TemplateKey::fromString($key) : $key;
        $site = $options->site ?? $this->sites->current();
        $locale = null === $options->locale ? $this->resolver->defaultLocale() : Locale::fromString($options->locale);
        $resolved = null === $options->version ? $this->resolver->resolve($key, $site, $locale) : $this->resolver->resolveVersion($key, $site, $locale, $options->version);

        return $this->renderResolved($resolved, $resolved->definition->schema->validate($variables), $site, $options->segments);
    }

    /**
     * Renders a template already found, with its sample values: what the preview and the checks draw.
     *
     * @param list<string> $segments
     *
     * @throws RenderingFailed when the template fails to render
     */
    public function renderSamples(ResolvedTemplate $template, Site $site, array $segments = []): RenderedEmail
    {
        return $this->renderResolved($template, $template->definition->schema->samples(), $site, $segments);
    }

    /**
     * @param array<string, mixed> $values   the values, already checked
     * @param list<string>         $segments the recipient's segments; those the template does not declare are ignored
     *
     * @throws RenderingFailed when the template fails to render
     */
    public function renderResolved(ResolvedTemplate $template, array $values, Site $site, array $segments = []): RenderedEmail
    {
        $definition = $template->definition;
        $segments = array_values(array_intersect($definition->segments, $segments));
        $context = new RenderContext($template->key, $site, $template->locale, $segments);
        $parts = $this->engine->render($definition, $values, $context);
        $html = $this->inliner->inline($this->layout->wrap($parts, $context));

        if (\strlen($html) > $this->maxHtmlBytes) {
            throw RenderingFailed::because(\sprintf('The email renders more than %d KiB of HTML.', intdiv($this->maxHtmlBytes, 1024)));
        }

        $embeds = [];

        foreach ($definition->embeds as $name => $path) {
            $embeds[] = ($this->embeds ?? throw RenderingFailed::because(\sprintf('The template embeds the image "%s", and no images directory is configured.', $name)))->load($name, $path);
        }

        return new RenderedEmail($template->key, $site, $template->locale, $template->version, $template->origin, $parts->subject, $parts->preheader, $html, $parts->text ?? HtmlToText::convert($html), $embeds, $segments);
    }
}
