<?php

declare(strict_types=1);

namespace Phil\ReceiptEmail\Infrastructure\EmailTemplates;

use Phil\EmailTemplatesBundle\Application\Rendering\EmailRenderer;
use Phil\EmailTemplatesBundle\Application\Rendering\EmbeddedImage;
use Phil\EmailTemplatesBundle\Application\Rendering\RenderOptions;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Site;
use Phil\ReceiptEmail\Application\InlineImage;
use Phil\ReceiptEmail\Application\Port\ReceiptRenderer;
use Phil\ReceiptEmail\Application\ReceiptMessage;
use Phil\ReceiptEmail\Application\RenderedReceipt;

/**
 * Renders a receipt with `phil/email-templates-bundle`: the site's published template of the key and the locale, else
 * the shared one, else the default file — `email-templates/receipt.<locale>.yaml` of this package, once its directory
 * is in `defaults_paths` —, in the bundle's Twig sandbox. The site is the current one unless one is given.
 */
final readonly class EmailTemplatesRenderer implements ReceiptRenderer
{
    /** The directory of the package's default templates, for `phil_email_templates.defaults_paths`. */
    public const string DEFAULTS = __DIR__.'/../../../email-templates';

    public function __construct(
        private EmailRenderer $renderer,
        private ?Site $site = null,
    ) {
    }

    public function render(ReceiptMessage $message): RenderedReceipt
    {
        $email = $this->renderer->render($message->template, $message->variables, new RenderOptions($this->site, $message->locale));

        return new RenderedReceipt(
            $email->subject,
            $email->preheader,
            $email->html,
            $email->text,
            array_map(static fn (EmbeddedImage $image): InlineImage => new InlineImage($image->name, $image->body, $image->contentType), $email->embeds),
        );
    }
}
