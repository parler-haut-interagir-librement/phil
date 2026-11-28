<?php

declare(strict_types=1);

namespace Phil\ReceiptEmail\Application;

use Dom\Element;
use Dom\HTMLDocument;
use Phil\ReceiptEmail\Domain\Exception\NotTransactional;

/**
 * Checks a rendered receipt before it is sent, whoever wrote its template: a receipt is a transactional message.
 *
 * - **A plain-text part**, never blank, beside the HTML.
 * - **No remote content**: every image is embedded and shown as `cid:` — no pixel, no address the client loads and
 *   the sender could count —; no script, frame, object, embed, linked style sheet, `srcset`, nor a `url()` in a style
 *   that is not `cid:`.
 * - **No tracked link**: no link carries a campaign or click-tracking parameter (`utm_*`, `gclid`, `fbclid`,
 *   `mc_eid`, `_hsenc`…).
 *
 * The HTML is read by PHP's HTML5 parser, at most 2 MiB of it.
 */
final readonly class TransactionalCheck
{
    /** The largest HTML part checked, in bytes. */
    public const int MAX_HTML_BYTES = 2_097_152;

    /** The elements that load something other than an embedded image. */
    private const string REMOTE_ELEMENTS = 'script, iframe, frame, object, embed, link, audio, video, source, track, picture, input[type=image], meta[http-equiv=refresh i], [srcset]';

    /** The query parameters that tag a link for tracking. */
    private const string TRACKING_PARAMETER = '/^(?:utm_[a-z0-9_]*|gclid|gbraid|wbraid|dclid|fbclid|msclkid|twclid|ttclid|li_fat_id|mc_cid|mc_eid|_hsenc|_hsmi|__hssc|__hstc|__hsfp|hsctatracking|mkt_tok|vero_id|vero_conv|oly_anon_id|oly_enc_id|_kx|ml_subscriber|ml_subscriber_hash|ck_subscriber_id|trk|trkid|ref_id|s_cid|icid|wickedid|yclid|_openstat|igshid|srsltid)$/i';

    /**
     * @return RenderedReceipt the receipt checked
     *
     * @throws NotTransactional when the receipt is not transactional
     */
    public function check(RenderedReceipt $receipt): RenderedReceipt
    {
        if ('' === trim($receipt->text)) {
            throw NotTransactional::because('A receipt has a plain-text part beside its HTML.');
        }

        if (\strlen($receipt->html) > self::MAX_HTML_BYTES) {
            throw NotTransactional::because(\sprintf('A receipt has at most %d KiB of HTML.', intdiv(self::MAX_HTML_BYTES, 1024)));
        }

        $document = HTMLDocument::createFromString($receipt->html, \LIBXML_NOERROR);

        if ($document->querySelector(self::REMOTE_ELEMENTS) instanceof Element) {
            throw NotTransactional::because('A receipt loads nothing: no script, frame, object, media, linked style sheet, srcset or refresh.');
        }

        foreach ($document->querySelectorAll('img') as $image) {
            $this->embedded($image->getAttribute('src') ?? '', 'An image');
        }

        foreach ($document->querySelectorAll('[background]') as $element) {
            $this->embedded($element->getAttribute('background') ?? '', 'A background');
        }

        foreach ($document->querySelectorAll('[style]') as $element) {
            $this->styles($element->getAttribute('style') ?? '');
        }

        foreach ($document->querySelectorAll('style') as $style) {
            $this->styles($style->textContent ?? '');
        }

        foreach ($document->querySelectorAll('[href]') as $link) {
            $this->untracked($link);
        }

        return $receipt;
    }

    private function embedded(string $source, string $what): void
    {
        if (!str_starts_with(strtolower(trim($source)), 'cid:')) {
            throw NotTransactional::because(\sprintf('%s of a receipt is embedded and shown as "cid:", never loaded from an address: no tracking pixel.', $what));
        }
    }

    private function styles(string $css): void
    {
        if (preg_match_all('/url\(\s*([\'"]?)(.*?)\1\s*\)/is', $css, $matches) > 0) {
            foreach ($matches[2] as $source) {
                $this->embedded($source, "A style's image");
            }
        }

        if (1 === preg_match('/@import/i', $css)) {
            throw NotTransactional::because('A receipt loads no style sheet: no "@import".');
        }
    }

    private function untracked(Element $link): void
    {
        // The fragment is read by the browser only: what follows "#" is never sent, "?" included.
        $href = explode('#', $link->getAttribute('href') ?? '', 2)[0];
        $start = strpos($href, '?');

        if (false === $start) {
            return;
        }

        $query = substr($href, $start + 1);

        foreach (explode('&', str_replace(';', '&', $query)) as $pair) {
            $name = rawurldecode(str_replace('+', ' ', explode('=', $pair, 2)[0]));

            if (1 === preg_match(self::TRACKING_PARAMETER, trim($name))) {
                throw NotTransactional::because('A link of a receipt carries no tracking parameter.');
            }
        }
    }
}
