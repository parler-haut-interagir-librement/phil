<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Application\Content;

use Closure;
use Phil\Markup\Level;
use Phil\Markup\Markup;
use Phil\Markup\MarkupFormat;
use Phil\NewsletterBundle\Domain\Model\Template;
use Symfony\Component\Mime\HtmlToTextConverter\HtmlToTextConverterInterface;
use Twig\Environment;
use Twig\Markup as TwigMarkup;

/**
 * Turns what an editor wrote into a message: the Markdown body compiled and purified by `phil/markup`, its links
 * rewritten for click tracking when the campaign counts clicks, then laid out by the bundle's own layout — the house
 * layout, a trusted template of the bundle — or by a template an administrator or a member wrote, rendered in the
 * sandbox. The text part is drawn from the HTML by `TextPart`, which writes the address of each link after its words:
 * a reader of the text still finds the unsubscription, the preferences and the confirmation.
 *
 * A layout reads `subject`, `preheader`, `content` (the body, already purified), `list_name`, `site`,
 * `unsubscribe_url` and `preferences_url`.
 */
final readonly class ContentRenderer
{
    public const string LAYOUT = '@PhilNewsletter/email/layout.html.twig';

    public function __construct(
        private Markup $markup,
        private Environment $twig,
        private SandboxedTemplates $sandbox,
        private LinkRewriter $links = new LinkRewriter(),
        private HtmlToTextConverterInterface $text = new TextPart(),
    ) {
    }

    /**
     * @param array{list_name?: string, site?: string, unsubscribe_url?: string, preferences_url?: string} $context
     * @param (Closure(string): string)|null                                                               $track   the tracked address of a link, when clicks are counted
     */
    public function campaign(string $subject, string $preheader, string $markdown, ?Template $layout, array $context, ?Closure $track = null): RenderedMessage
    {
        $content = $this->markup->toHtml($markdown, MarkupFormat::Markdown, Level::All);

        if ($track instanceof Closure) {
            $content = $this->links->rewrite($content, $track);
        }

        $variables = [...$context, 'subject' => $subject, 'preheader' => $preheader, 'content' => new TwigMarkup($content, 'UTF-8')];
        $html = $layout instanceof Template ? $this->sandbox->render($layout->source, $variables) : $this->twig->render(self::LAYOUT, $variables);

        return new RenderedMessage($subject, $html, $this->text($html));
    }

    /**
     * A message of a template on its own — sent by an automation, a confirmation an editor wrote —: its subject and its
     * body both rendered in the sandbox.
     *
     * @param array<string, string> $context
     */
    public function template(Template $template, array $context): RenderedMessage
    {
        $subject = trim(html_entity_decode(strip_tags($this->sandbox->render($template->subject, $context)), \ENT_QUOTES | \ENT_HTML5, 'UTF-8'));
        $html = $this->sandbox->render($template->source, [...$context, 'subject' => $subject]);

        return new RenderedMessage($subject, $html, $this->text($html));
    }

    /**
     * The text part: what the converter writes from the HTML, runs of blank lines folded.
     */
    public function text(string $html): string
    {
        return trim((string) preg_replace("/\n[ \t]*\n(?:[ \t]*\n)+/", "\n\n", $this->text->convert($html, 'UTF-8')));
    }
}
