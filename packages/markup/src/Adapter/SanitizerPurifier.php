<?php

declare(strict_types=1);

namespace Phil\Markup\Adapter;

use Phil\Markup\Exception\InputTooLarge;
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
 * `mathml` profile keeps them. **An HTML longer than `maxBytes` is refused whole**, by an `InputTooLarge`, before it is
 * purified: the sanitizer's own cap, which truncates silently, is turned off.
 *
 * **An image is a request to its host.** At the levels that keep images, `forum` and `all`, an image of another site
 * makes every reader's browser call that site, which learns their address: tracking without their consent when a
 * member, not an editor, wrote it. `defaultPolicies()` takes the hosts images may come from; an image from any other
 * loses its `src`. A relative image, served by the site itself, is always kept.
 *
 * At `all`, the checkbox of a checklist is kept, written again disabled: an `input` of any other type is removed, so
 * that no purified text holds a field a reader could type in or submit. Below `all`, an `input` is removed, and a
 * `label` unwrapped, its text kept.
 *
 * Classes are dropped, but for those of code (`CodeClasses`): `language-…` on `code` at every level, and `hl-…` on
 * `span` where spans are kept, so highlighted code keeps its colours.
 */
final readonly class SanitizerPurifier implements HtmlPurifier
{
    private const array INLINE = ['strong', 'b', 'em', 'i', 'u', 's', 'del', 'sub', 'sup', 'code', 'br'];

    private const array FORUM = ['p', 'ul', 'ol', 'li', 'blockquote', 'cite', 'pre', 'hr'];

    /** An `input` element as the sanitizer writes it: its attribute values never hold `>`. */
    private const string INPUT = '/<input\b[^>]*>/';

    /** The containers a level does not allow, removed with their text kept. */
    private const array CONTAINERS = ['p', 'div', 'span', 'a', 'label', 'ul', 'ol', 'li', 'blockquote', 'cite', 'pre', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'caption', 'dl', 'dt', 'dd', 'figure', 'figcaption', 'section', 'article', 'header', 'footer', 'aside', 'small', 'mark', 'abbr', 'q', 'details', 'summary'];

    /**
     * @param array{basic: HtmlSanitizerInterface, forum: HtmlSanitizerInterface, all: HtmlSanitizerInterface} $sanitizers
     * @param int<1, max>                                                                                      $maxBytes   the longest HTML purified, in bytes; a longer one is refused
     */
    public function __construct(
        private array $sanitizers,
        private int $maxBytes = self::DEFAULT_MAX_BYTES,
    ) {
    }

    /**
     * @param list<string>|null $mediaHosts the hosts images may come from, besides the site itself — `cdn.example.com`,
     *                                      a subdomain included —; `null` for any host, `[]` for none
     * @param int<1, max>       $maxBytes   the longest HTML purified, in bytes; a longer one is refused
     */
    public static function defaultPolicies(?array $mediaHosts = null, int $maxBytes = self::DEFAULT_MAX_BYTES): self
    {
        return new self(array_map(static fn (HtmlSanitizerConfig $config): HtmlSanitizer => new HtmlSanitizer($config), self::configurations($mediaHosts)), $maxBytes);
    }

    /**
     * The configuration of each level that `defaultPolicies()` builds its sanitizers from, which another purifier may
     * translate into its own policy.
     *
     * @param list<string>|null $mediaHosts the hosts images may come from, as `defaultPolicies()` takes them
     *
     * @return array{basic: HtmlSanitizerConfig, forum: HtmlSanitizerConfig, all: HtmlSanitizerConfig}
     */
    public static function configurations(?array $mediaHosts = null): array
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

        return ['basic' => $basic, 'forum' => $forum, 'all' => $all->allowElement('input', ['type', 'checked', 'disabled'])];
    }

    /**
     * @throws InputTooLarge when the HTML is longer than the purifier's ceiling
     */
    public function purify(string $html, Level $level): string
    {
        InputTooLarge::check($html, $this->maxBytes);
        $clean = $this->sanitizers[$level->value]->sanitize($html);

        return str_contains($clean, '<input') ? $this->checkboxes($clean) : $clean;
    }

    /**
     * The `input` elements the level `all` keeps, each written again as the disabled checkbox of a checklist, checked
     * or not: an input of another type — a text field, a button, an input with no type — is removed, so that no
     * purified text holds a field a reader could type in or submit.
     */
    private function checkboxes(string $html): string
    {
        return (string) preg_replace_callback(self::INPUT, static function (array $input): string {
            preg_match_all('/ ([^\s"=\/>]+)(?:="([^"]*)")?/', $input[0], $attributes, \PREG_SET_ORDER);
            $type = '';
            $checked = false;

            foreach ($attributes as $attribute) {
                $name = $attribute[1];
                $type = 'type' === $name ? strtolower($attribute[2] ?? '') : $type;
                $checked = $checked || 'checked' === $name;
            }

            return 'checkbox' === $type ? '<input type="checkbox" disabled'.($checked ? ' checked' : '').' />' : '';
        }, $html);
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
