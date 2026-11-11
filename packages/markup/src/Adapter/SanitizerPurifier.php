<?php

declare(strict_types=1);

namespace Phil\Markup\Adapter;

use Phil\Markup\HtmlPurifier;
use Phil\Markup\Level;
use Phil\Ujo\Html\InlineProfile;
use Phil\Ujo\Html\SafeUrl;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

/**
 * `HtmlPurifier` on `symfony/html-sanitizer`, one sanitizer per level. A container a level does not allow — a
 * paragraph, a list, a heading, a table — is removed and its text kept; any other element is removed with its content.
 * Links may use the schemes `phil/ujo` allows — the web, `mailto`, `tel`, `sms`, `geo` and the messengers —, or be
 * relative; images `http` and `https`, or be relative. At `all`, formulas in MathML Core are kept as `phil/ujo`'s
 * `mathml` profile keeps them. **The input length is not capped**: the sanitizer's default cap truncates silently.
 *
 * **An image is a request to its host.** At the levels that keep images, `forum` and `all`, an image of another site
 * makes every reader's browser call that site, which learns their address: tracking without their consent when a
 * member, not an editor, wrote it. `defaultPolicies()` takes the hosts images may come from; an image from any other
 * loses its `src`. A relative image, served by the site itself, is always kept.
 *
 * Classes are dropped, but for those of code (`CodeClasses`): `language-…` on `code` at every level, and `hl-…` on
 * `span` where spans are kept, so highlighted code keeps its colours.
 */
final readonly class SanitizerPurifier implements HtmlPurifier
{
    private const array INLINE = ['strong', 'b', 'em', 'i', 'u', 's', 'del', 'sub', 'sup', 'code', 'br'];

    private const array FORUM = ['p', 'ul', 'ol', 'li', 'blockquote', 'cite', 'pre', 'hr'];

    /** The containers a level does not allow, removed with their text kept. */
    private const array CONTAINERS = ['p', 'div', 'span', 'a', 'ul', 'ol', 'li', 'blockquote', 'cite', 'pre', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'caption', 'dl', 'dt', 'dd', 'figure', 'figcaption', 'section', 'article', 'header', 'footer', 'aside', 'small', 'mark', 'abbr', 'q', 'details', 'summary'];

    /**
     * @param array{basic: HtmlSanitizerInterface, forum: HtmlSanitizerInterface, all: HtmlSanitizerInterface} $sanitizers
     */
    public function __construct(
        private array $sanitizers,
    ) {
    }

    /**
     * @param list<string>|null $mediaHosts the hosts images may come from, besides the site itself — `cdn.example.com`,
     *                                      a subdomain included —; `null` for any host, `[]` for none
     */
    public static function defaultPolicies(?array $mediaHosts = null): self
    {
        $basic = self::schemes(new HtmlSanitizerConfig(), $mediaHosts);
        $forum = $basic->allowElement('a', ['href', 'title'])->allowElement('img', ['src', 'alt', 'title']);

        foreach (self::INLINE as $element) {
            $attributes = 'code' === $element ? ['class'] : [];
            $basic = $basic->allowElement($element, $attributes);
            $forum = $forum->allowElement($element, $attributes);
        }

        foreach (self::FORUM as $element) {
            $forum = $forum->allowElement($element);
        }

        foreach (self::CONTAINERS as $element) {
            $basic = $basic->blockElement($element);
            $forum = \in_array($element, [...self::FORUM, 'a'], true) ? $forum : $forum->blockElement($element);
        }

        $all = self::schemes(new HtmlSanitizerConfig()->allowSafeElements(), $mediaHosts);

        foreach (InlineProfile::mathMl()->tools as $tool) {
            $all = $all->allowElement($tool->tag, array_keys($tool->attributes));
        }

        return new self([
            'basic' => new HtmlSanitizer($basic),
            'forum' => new HtmlSanitizer($forum),
            'all' => new HtmlSanitizer($all),
        ]);
    }

    public function purify(string $html, Level $level): string
    {
        return $this->sanitizers[$level->value]->sanitize($html);
    }

    /**
     * @param list<string>|null $mediaHosts
     */
    private static function schemes(HtmlSanitizerConfig $config, ?array $mediaHosts): HtmlSanitizerConfig
    {
        return $config
            ->allowAttribute('class', ['code', 'span'])
            ->withAttributeSanitizer(new CodeClasses())
            ->allowLinkSchemes(SafeUrl::LINK)
            ->allowRelativeLinks()
            ->allowMediaSchemes(['http', 'https'])
            ->allowMediaHosts($mediaHosts)
            ->allowRelativeMedias()
            ->withMaxInputLength(-1);
    }
}
