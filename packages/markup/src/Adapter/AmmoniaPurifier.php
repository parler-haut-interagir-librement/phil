<?php

declare(strict_types=1);

namespace Phil\Markup\Adapter;

use Phil\Markup\Exception\InputTooLarge;
use Phil\Markup\Exception\MissingExtension;
use Phil\Markup\HtmlPurifier;
use Phil\Markup\Level;
use Switon\Ammonia\Builder;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * `HtmlPurifier` on the `ammonia` extension of PHP, which parses with the HTML5 parser of Servo and rewrites the tree
 * from allow lists, in Rust: the policies of `SanitizerPurifier::defaultPolicies()` translated into one `Builder` per
 * level, so that both keep the same elements, the same attributes and the same link schemes.
 *
 * Where the two differ, nothing that runs is kept by either:
 * - `style` is never kept, nor any attribute holding an address the extension does not check (`srcset`, `cite`,
 *   `poster`…): only `href` and `src`, whose schemes it checks;
 * - the extension checks links and images with one list of schemes, the link schemes: an image may keep a `mailto:`
 *   or `tel:` address, which no browser loads;
 * - an element a level neither allows nor unwraps is removed with its content when it is an element of HTML, SVG or
 *   MathML; the text of an element no standard defines is kept, escaped;
 * - at `all`, the safe attributes every HTML element keeps are kept on the elements of MathML too;
 * - classes are filtered after the extension, as `CodeClasses` filters them.
 *
 * An HTML longer than `maxBytes` is refused whole, by an `InputTooLarge`, before either purifies it.
 *
 * Six kinds of HTML are given to `SanitizerPurifier` instead, which purifies them faster or checks what the extension
 * cannot:
 * - an HTML shorter than `$minimumBytes` (512 bytes by default): the extension rebuilds its policy at each call, about
 *   25 to 45 µs, which a short text does not repay;
 * - an HTML of more than 10,000 tags: the HTML5 parser costs time in proportion to the square of the depth of the
 *   elements, which grows faster in the extension than in the sanitizer;
 * - an HTML that holds `<noscript`, whose content the HTML5 parser of the extension reads as text, as a browser running
 *   scripts does, and the sanitizer as markup: both outputs are inert, but they keep different elements;
 * - an HTML that is not valid UTF-8, which the extension refuses;
 * - an HTML that holds `<input`, whose checkboxes the sanitizer writes again as those of a checklist at `all`, any
 *   other input removed;
 * - at `forum` and `all`, an HTML that holds `src` when images are limited to some hosts.
 *
 * The extension is built for PHP 8.2 to 8.5: `isAvailable()` tells whether it is loaded, and `Purifiers` chooses this
 * purifier when it is.
 */
final readonly class AmmoniaPurifier implements HtmlPurifier
{
    /**
     * The attributes the sanitizer keeps at `all` and checks as addresses, which the extension does not check: only
     * `href` and `src` are kept. `style` too, which `phil/css-sanitizer` purifies, never kept by the extension.
     */
    private const array UNCHECKED = ['srcset', 'imagesrcset', 'lowsrc', 'poster', 'cite', 'data', 'action', 'codebase', 'archive', 'longdesc', 'style'];

    /** The elements of HTML, SVG and MathML a level drops with their content when it neither allows nor unwraps them. */
    private const array ELEMENTS = [
        'a', 'abbr', 'acronym', 'address', 'applet', 'area', 'article', 'aside', 'audio', 'b', 'base', 'basefont', 'bdi',
        'bdo', 'bgsound', 'big', 'blink', 'blockquote', 'body', 'br', 'button', 'canvas', 'caption', 'center', 'cite',
        'code', 'col', 'colgroup', 'command', 'data', 'datalist', 'dd', 'del', 'details', 'dfn', 'dialog', 'dir', 'div',
        'dl', 'dt', 'em', 'embed', 'fieldset', 'figcaption', 'figure', 'font', 'footer', 'form', 'frame', 'frameset',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'head', 'header', 'hgroup', 'hr', 'html', 'i', 'iframe', 'image', 'img',
        'input', 'ins', 'isindex', 'kbd', 'keygen', 'label', 'layer', 'legend', 'li', 'link', 'listing', 'main', 'map',
        'mark', 'marquee', 'menu', 'menuitem', 'meta', 'meter', 'multicol', 'nav', 'nextid', 'nobr', 'noembed',
        'noframes', 'noscript', 'object', 'ol', 'optgroup', 'option', 'output', 'p', 'param', 'picture', 'plaintext',
        'popup', 'portal', 'pre', 'progress', 'q', 'rb', 'rp', 'rt', 'rtc', 'ruby', 's', 'samp', 'script', 'search',
        'section', 'select', 'selectedcontent', 'selectmenu', 'slot', 'small', 'source', 'spacer', 'span', 'strike',
        'strong', 'style', 'sub', 'summary', 'sup', 'table', 'tbody', 'td', 'template', 'textarea', 'tfoot', 'th',
        'thead', 'time', 'title', 'tr', 'track', 'tt', 'u', 'ul', 'var', 'video', 'wbr', 'xmp',
        // SVG and MathML, whose content `phil/svg-sanitizer` and the level `all` handle.
        'svg', 'math', 'foreignobject', 'annotation-xml', 'mi', 'mn', 'mo', 'ms', 'mtext', 'mrow', 'mfrac', 'msqrt',
        'mroot', 'msub', 'msup', 'msubsup', 'munder', 'mover', 'munderover', 'mtable', 'mtr', 'mtd', 'semantics',
        'annotation', 'mspace', 'mpadded', 'mphantom', 'menclose', 'merror', 'mstyle', 'mmultiscripts', 'mprescripts',
    ];

    /** The tags beyond which an HTML goes to the sanitizer, whose parser costs less on a deep tree. */
    public const int MAXIMUM_TAGS = 10_000;

    /** The length below which an HTML goes to the sanitizer, faster on a short text. */
    public const int MINIMUM_BYTES = 256;

    /** The opening tag of an element that may keep classes, and its `class` attribute, in the extension's output. */
    private const string CLASSES = '/<(code|span)((?: [^\s"\'>\/=]+(?:="[^"]*")?)*?) class="([^"]*)"/';

    /**
     * @param array{basic: Builder, forum: Builder, all: Builder} $builders     the policy of each level
     * @param array{basic: bool, forum: bool, all: bool}          $media        whether a level keeps an element with `src`
     * @param list<string>|null                                   $hosts        the hosts images may come from
     * @param int<0, max>                                         $minimumBytes the length from which the extension purifies
     * @param int<1, max>                                         $maxBytes     the longest HTML purified, in bytes
     */
    private function __construct(
        private array $builders,
        private array $media,
        private ?array $hosts,
        private SanitizerPurifier $sanitizer,
        private int $minimumBytes,
        private int $maxBytes,
    ) {
    }

    /**
     * Whether the extension is loaded, in a version this purifier is written for.
     */
    public static function isAvailable(): bool
    {
        return \extension_loaded('ammonia') && class_exists(Builder::class);
    }

    /**
     * The policies of `SanitizerPurifier::defaultPolicies()`, on the extension.
     *
     * @param list<string>|null $mediaHosts   the hosts images may come from, besides the site itself; `null` for any host,
     *                                        `[]` for none
     * @param int<0, max>       $minimumBytes the length from which the extension purifies, a shorter HTML going to the
     *                                        sanitizer
     * @param int<1, max>       $maxBytes     the longest HTML purified, in bytes; a longer one is refused
     *
     * @throws MissingExtension when the extension is not loaded
     */
    public static function defaultPolicies(?array $mediaHosts = null, int $minimumBytes = self::MINIMUM_BYTES, int $maxBytes = self::DEFAULT_MAX_BYTES): self
    {
        if (!self::isAvailable()) {
            throw MissingExtension::named('ammonia', self::class);
        }

        $configurations = SanitizerPurifier::configurations($mediaHosts);
        $builders = [];
        $media = [];

        foreach ($configurations as $level => $configuration) {
            $builders[$level] = self::builder($configuration);
            $media[$level] = array_any($configuration->getAllowedElements(), static fn (array $attributes): bool => isset($attributes['src']));
        }

        return new self($builders, $media, $mediaHosts, SanitizerPurifier::defaultPolicies($mediaHosts, $maxBytes), $minimumBytes, $maxBytes);
    }

    /**
     * @throws InputTooLarge when the HTML is longer than the purifier's ceiling
     */
    public function purify(string $html, Level $level): string
    {
        InputTooLarge::check($html, $this->maxBytes);

        if (\strlen($html) < $this->minimumBytes
            || substr_count($html, '<') > self::MAXIMUM_TAGS
            || false !== stripos($html, '<noscript')
            || false !== stripos($html, '<input')
            || !mb_check_encoding($html, 'UTF-8')
            || (null !== $this->hosts && $this->media[$level->value] && false !== stripos($html, 'src'))) {
            return $this->sanitizer->purify($html, $level);
        }

        $clean = $this->builders[$level->value]->clean($html);

        if (!str_contains($clean, ' class="')) {
            return $clean;
        }

        return (string) preg_replace_callback(self::CLASSES, static function (array $tag): string {
            $kept = CodeClasses::keep($tag[1], $tag[3]);

            return '<'.$tag[1].$tag[2].(null === $kept ? '' : ' class="'.$kept.'"');
        }, $clean);
    }

    /**
     * One level's configuration as a `Builder`: its elements and their attributes, the elements it unwraps keeping
     * their text, every other known element dropped with its content, its schemes, no `rel` added to links.
     */
    private static function builder(HtmlSanitizerConfig $configuration): Builder
    {
        $allowed = [];

        // A title is an element of the head, which the sanitizer, reading a fragment of a body, never keeps.
        foreach (array_diff_key($configuration->getAllowedElements(), ['title' => true]) as $element => $attributes) {
            $allowed[$element] = array_values(array_diff(array_keys($attributes), self::UNCHECKED));
        }

        // The extension rebuilds its policy at each call: the attributes most elements keep — the safe attributes of
        // `all` — are given once for every element rather than for each.
        $generic = self::mostShared($allowed);
        $allowed = array_map(static fn (array $attributes): array => array_values(array_diff($attributes, $generic)), $allowed);
        $unwrapped = array_keys($configuration->getBlockedElements());

        // The default policies allow relative links and images, and their image schemes are link schemes: the
        // extension, which checks both with one list, is given the link schemes.
        return new Builder()
            ->tags(array_keys($allowed))
            ->genericAttributes($generic)
            ->genericAttributePrefixes([])
            ->tagAttributes(array_filter($allowed, static fn (array $attributes): bool => [] !== $attributes))
            ->tagAttributeValues([])
            ->setTagAttributeValues([])
            ->allowedClasses([])
            ->urlSchemes($configuration->getAllowedLinkSchemes())
            ->passRelativeUrls()
            ->linkRel(null)
            ->stripComments(true)
            ->cleanContentTags(array_values(array_diff(self::ELEMENTS, array_keys($allowed), $unwrapped)));
    }

    /**
     * The set of attributes shared by more than half of the elements, as a whole; none when no set is.
     *
     * @param array<string, list<string>> $allowed
     *
     * @return list<string>
     */
    private static function mostShared(array $allowed): array
    {
        $counts = [];

        foreach ($allowed as $attributes) {
            sort($attributes);
            $key = implode(' ', $attributes);
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        arsort($counts);
        $set = (string) array_key_first($counts);

        return '' !== $set && $counts[$set] * 2 > \count($allowed) ? explode(' ', $set) : [];
    }
}
