<?php

declare(strict_types=1);

namespace Phil\NewsletterArchive\Application;

use Dom\Element;
use Dom\HTMLDocument;
use Override;
use Phil\Markup\Level;
use Phil\Markup\Markup;
use Phil\Markup\MarkupFormat;
use Phil\NewsletterArchive\Application\Contract\WebVersionRenderer;
use Phil\NewsletterBundle\Application\Content\ContentRenderer;
use Phil\NewsletterBundle\Domain\Contract\TemplateRepository;
use Phil\NewsletterBundle\Domain\Model\Campaign;
use Phil\NewsletterBundle\Domain\Model\MailingList;
use Phil\NewsletterBundle\Domain\Model\Template;

/**
 * The web version of a campaign with neutral data in place of the subscriber's.
 *
 * A campaign laid out by the house layout gives its body alone: the Markdown compiled by `phil/markup`, without the
 * footer of an email. A campaign laid out by a template of the site is rendered by the newsletter's sandbox, as it was
 * sent, but with neutral values: the name of its list and its site, and, for the unsubscription and preference
 * addresses, a placeholder on a reserved domain. Then, in both cases, the links to the placeholder, the elements an
 * inline style hides — the preheader of an email — and the images of one pixel or less are taken out, with the paragraph or item a removed link leaves empty, the placeholder written
 * anywhere else is erased, and the HTML is purified by `phil/markup` at its widest level, which drops scripts, frames,
 * forms, styles and event handlers. Clicks are never tracked: no address is rewritten.
 */
final readonly class NeutralWebVersion implements WebVersionRenderer
{
    /** What stands for the addresses of a subscriber while the template renders, on a domain that never resolves. */
    public const string PLACEHOLDER = 'https://subscriber.invalid/';

    public function __construct(
        private Markup $markup,
        private ContentRenderer $renderer,
        private TemplateRepository $templates,
    ) {
    }

    #[Override]
    public function render(Campaign $campaign, ?MailingList $list): string
    {
        $layout = null === $campaign->template ? null : $this->templates->find($campaign->site, $campaign->template);

        if ($layout instanceof Template) {
            $document = $this->renderer->campaign($campaign->subject, $campaign->preheader, $campaign->body, $layout, [
                'list_name' => $list->name ?? '',
                'site' => $campaign->site->key,
                'unsubscribe_url' => self::PLACEHOLDER,
                'preferences_url' => self::PLACEHOLDER,
            ])->html;
            $html = $this->neutralize($document);
        } else {
            $html = $this->neutralize($this->markup->toHtml($campaign->body, MarkupFormat::Markdown, Level::All));
        }

        return trim(str_replace(self::PLACEHOLDER, '', $this->markup->toHtml($html, MarkupFormat::Html, Level::All)));
    }

    /**
     * The body of a document, or a fragment, without the links to the placeholder and the tracking pixels.
     */
    private function neutralize(string $html): string
    {
        $page = HTMLDocument::createFromString($html, \LIBXML_NOERROR);
        $body = $page->body;

        if (!$body instanceof Element) {
            return '';
        }

        foreach ($body->querySelectorAll('a[href^="'.self::PLACEHOLDER.'"]') as $link) {
            $parent = $link->parentElement;
            $link->remove();

            if ($parent instanceof Element && !$parent->firstElementChild instanceof Element && '' === trim((string) $parent->textContent, " \t\n\r\0\x0B·•|-–—")) {
                $parent->remove();
            }
        }

        foreach ($body->querySelectorAll('[style]') as $element) {
            if (1 === preg_match('/(?:^|;)\s*display\s*:\s*none\b/i', (string) $element->getAttribute('style'))) {
                $element->remove();
            }
        }

        foreach ($body->querySelectorAll('img') as $image) {
            if ($this->isPixel($image)) {
                $image->remove();
            }
        }

        return $body->innerHTML;
    }

    private function isPixel(Element $image): bool
    {
        foreach (['width', 'height'] as $dimension) {
            $value = $image->getAttribute($dimension);

            if (null !== $value && 1 === preg_match('/^\s*[01](?:\.0*)?\s*(?:px)?\s*$/D', $value)) {
                return true;
            }
        }

        return str_starts_with((string) $image->getAttribute('src'), self::PLACEHOLDER);
    }
}
