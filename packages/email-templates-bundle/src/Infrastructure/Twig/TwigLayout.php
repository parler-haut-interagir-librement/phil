<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Infrastructure\Twig;

use Override;
use Phil\EmailTemplatesBundle\Application\Contract\EmailLayout;
use Phil\EmailTemplatesBundle\Application\Rendering\RenderContext;
use Phil\EmailTemplatesBundle\Application\Rendering\RenderedParts;
use Phil\EmailTemplatesBundle\Application\Rendering\Theme;
use Twig\Environment;

/**
 * The house layout, a template of the application's own Twig: `@PhilEmailTemplates/email/layout.html.twig` by default,
 * which the application overrides as any bundle template, or another named by `phil_email_templates.layout`. It is
 * trusted code, not sandboxed, and receives:
 *
 * | Variable | Holds |
 * |---|---|
 * | `subject`, `preheader` | the rendered subject and preheader, as text |
 * | `body` | the rendered body, as HTML |
 * | `email` | the key, the site, the locale and the segments of the render |
 * | `theme` | the colours, light and dark, and the font |
 * | `brand` | the name and the logo address the configuration gives, `null` when none |
 */
final readonly class TwigLayout implements EmailLayout
{
    /**
     * @param array{name: ?string, logo: ?string} $brand
     */
    public function __construct(
        private Environment $twig,
        private Theme $theme,
        private string $template = '@PhilEmailTemplates/email/layout.html.twig',
        private array $brand = ['name' => null, 'logo' => null],
    ) {
    }

    #[Override]
    public function wrap(RenderedParts $parts, RenderContext $context): string
    {
        return $this->twig->render($this->template, [
            'subject' => $parts->subject,
            'preheader' => $parts->preheader,
            'body' => $parts->html,
            'email' => $context->toArray(),
            'theme' => ['light' => $this->theme->light, 'dark' => $this->theme->dark, 'font' => $this->theme->font],
            'brand' => $this->brand,
        ]);
    }
}
