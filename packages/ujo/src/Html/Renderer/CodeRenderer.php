<?php

declare(strict_types=1);

namespace Phil\Ujo\Html\Renderer;

use Closure;
use Override;
use Phil\Ujo\Block;
use Phil\Ujo\Html\BlockRenderer;
use Phil\Ujo\Html\Rendering;

/**
 * Code blocks, as `<pre><code class="language-…">`: the class names the language for a highlighter in the browser, and
 * a highlighter given here colours the code on the server.
 *
 * A highlighter takes the code and its language and returns HTML, or `null` when it does not know the language. Its
 * HTML is kept only when it holds nothing but the code's text, escaped, and `<span class="…">` elements around it —
 * the output of `phil/highlight`, whose `Highlighter::highlight()` has this shape; anything else falls back to the
 * escaped code, so the renderer stays purified by construction.
 */
final readonly class CodeRenderer implements BlockRenderer
{
    public const array TYPES = ['code'];

    /**
     * @param (Closure(string, string): ?string)|null $highlighter
     */
    public function __construct(
        private ?Closure $highlighter = null,
    ) {
    }

    #[Override]
    public function render(Block $block, Rendering $rendering): string
    {
        $code = \is_string($block->data['code'] ?? null) ? $block->data['code'] : '';
        $language = \is_string($block->data['language'] ?? null) ? $block->data['language'] : null;
        $html = null === $language || !$this->highlighter instanceof Closure ? null : ($this->highlighter)($code, $language);

        return $rendering->element('pre', $block, Rendering::tag('code', [
            'class' => null === $language ? null : 'language-'.$language,
        ], null !== $html && self::holds($html, $code) ? $html : Rendering::text($code)));
    }

    /**
     * Whether `$html` is the text of `$code`, escaped, inside `<span class="…">` elements that open and close in order.
     */
    public static function holds(string $html, string $code): bool
    {
        $text = '';
        $depth = 0;
        $at = 0;

        while (false !== $open = strpos($html, '<', $at)) {
            $text .= substr($html, $at, $open - $at);

            if ('</span>' === substr($html, $open, 7) && $depth > 0) {
                --$depth;
                $at = $open + 7;
            } elseif (1 === preg_match('/\G<span class="[a-z0-9 _-]+">/', $html, $match, 0, $open)) {
                ++$depth;
                $at = $open + \strlen($match[0]);
            } else {
                return false;
            }
        }

        $text .= substr($html, $at);

        return 0 === $depth && !str_contains($text, '>') && html_entity_decode($text, \ENT_QUOTES | \ENT_HTML5, 'UTF-8') === $code;
    }
}
