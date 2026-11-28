<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Infrastructure\Twig;

use Phil\EmailAddress\EmailAddress;
use Phil\EmailAddress\Exception\InvalidEmailAddress;
use Phil\EmailTemplatesBundle\Application\Rendering\Theme;
use Phil\Url\Exception\InvalidUrl;
use Phil\Url\Url;
use Stringable;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;
use Twig\Error\RuntimeError;

/**
 * The email components a sandboxed template draws with, each a table with inline styles that the email clients render
 * alike, Outlook's included, and each with the class the dark mode of the layout restyles:
 *
 * | Component | Writes |
 * |---|---|
 * | `button(label, url, {align})` | a button drawn by a table cell, its link an `http`, `https` or `mailto` address |
 * | `table(rows, {header, caption})` | a data table: the header cells, then each row of cells |
 * | `image(src, alt, {width})` | an image embedded by the template, `cid:logo`, or an `https` address |
 * | `{% apply panel %}…{% endapply %}` | a panel on the panel colour |
 * | `{% apply footer %}…{% endapply %}` | the small, muted footer of the message |
 * | `segment(name)` | whether the recipient is in a segment the template declares |
 *
 * The sandboxed engine registers it through Twig's `AttributeExtension`, a runtime of its own environment.
 *
 * In the subject, the preheader and the plain-text part, a component writes text: « label: address », the rows of a
 * table, the alternative text of an image. In a Markdown body, the content of a panel or a footer is Markdown too.
 */
final readonly class EmailComponents
{
    public const int MAX_IMAGE_WIDTH = 1200;

    public function __construct(
        private Theme $theme,
    ) {
    }

    /**
     * @param array<array-key, mixed> $context
     * @param array<array-key, mixed> $options
     */
    #[AsTwigFunction(name: 'button', needsContext: true, isSafe: ['html'])]
    public function button(array $context, mixed $label, mixed $url, array $options = []): string
    {
        $stash = $this->stash($context);
        $text = $this->text($label, 'The label of a button');
        $href = $this->link($url);

        if (RenderMode::Text === $stash->mode) {
            return $text.': '.(str_starts_with($href, 'mailto:') ? substr($href, 7) : $href);
        }

        $align = $this->align($options['align'] ?? 'left');
        $font = $this->escape($this->theme->font);

        return $stash->keep(\sprintf(
            '<table role="presentation" border="0" cellpadding="0" cellspacing="0" class="phil-email-button" align="%1$s" style="margin:16px 0;border-collapse:separate;"><tr><td align="center" bgcolor="%2$s" style="border-radius:6px;background-color:%2$s;"><a href="%3$s" target="_blank" rel="noopener" style="display:inline-block;padding:12px 24px;font-family:%4$s;font-size:16px;font-weight:600;line-height:1.25;color:%5$s;text-decoration:none;border-radius:6px;">%6$s</a></td></tr></table>',
            $align,
            $this->theme->light('primary'),
            $this->escape($href),
            $font,
            $this->theme->light('primary_text'),
            $this->escape($text),
        ));
    }

    /**
     * @param array<array-key, mixed> $context
     * @param array<array-key, mixed> $options
     */
    #[AsTwigFunction(name: 'table', needsContext: true, isSafe: ['html'])]
    public function table(array $context, mixed $rows, array $options = []): string
    {
        $stash = $this->stash($context);

        if (!is_iterable($rows)) {
            throw new RuntimeError('The rows of a table are a list of lists of cells.');
        }

        $header = $this->cells($options['header'] ?? []);
        $body = [];

        foreach ($rows as $row) {
            $body[] = $this->cells($row);
        }

        if (RenderMode::Text === $stash->mode) {
            return implode("\n", array_map(static fn (array $cells): string => implode(' | ', $cells), [] === $header ? $body : [$header, ...$body]));
        }

        $font = $this->escape($this->theme->font);
        $border = $this->theme->light('border');
        $html = '<table border="0" cellpadding="0" cellspacing="0" width="100%" class="phil-email-table" style="width:100%;margin:16px 0;border-collapse:collapse;font-family:'.$font.';font-size:15px;">';
        $caption = $options['caption'] ?? null;

        if (null !== $caption) {
            $html .= '<caption style="text-align:left;padding:0 0 8px;font-weight:600;">'.$this->escape($this->text($caption, 'The caption of a table')).'</caption>';
        }

        if ([] !== $header) {
            $html .= '<thead><tr>';

            foreach ($header as $cell) {
                $html .= '<th scope="col" align="left" style="padding:8px;border-bottom:2px solid '.$border.';text-align:left;">'.$this->escape($cell).'</th>';
            }

            $html .= '</tr></thead>';
        }

        $html .= '<tbody>';

        foreach ($body as $cells) {
            $html .= '<tr>';

            foreach ($cells as $cell) {
                $html .= '<td style="padding:8px;border-bottom:1px solid '.$border.';">'.$this->escape($cell).'</td>';
            }

            $html .= '</tr>';
        }

        return $stash->keep($html.'</tbody></table>');
    }

    /**
     * @param array<array-key, mixed> $context
     * @param array<array-key, mixed> $options
     */
    #[AsTwigFunction(name: 'image', needsContext: true, isSafe: ['html'])]
    public function image(array $context, mixed $src, mixed $alt, array $options = []): string
    {
        $stash = $this->stash($context);
        $source = $this->text($src, 'The source of an image');
        $text = $this->text($alt, 'The alternative text of an image');

        if (str_starts_with($source, 'cid:')) {
            if (!isset($stash->definition->embeds[substr($source, 4)])) {
                throw new RuntimeError(\sprintf('The image "%s" is not one the template embeds.', substr($source, 4)));
            }
        } else {
            $source = $this->link($source, true);
        }

        if (RenderMode::Text === $stash->mode) {
            return '' === $text ? '' : '['.$text.']';
        }

        $width = $options['width'] ?? null;
        $sized = '';

        if (null !== $width) {
            if (!\is_int($width) || $width < 1 || $width > self::MAX_IMAGE_WIDTH) {
                throw new RuntimeError(\sprintf('The width of an image is an integer from 1 to %d.', self::MAX_IMAGE_WIDTH));
            }

            $sized = ' width="'.$width.'"';
        }

        return $stash->keep(html: '<img src="'.$this->escape($source).'" alt="'.$this->escape($text).'"'.$sized.' style="display:block;border:0;outline:none;text-decoration:none;max-width:100%;height:auto;">', block: false);
    }

    /**
     * @param array<array-key, mixed> $context
     */
    #[AsTwigFunction(name: 'segment', needsContext: true)]
    public function segment(array $context, mixed $name): bool
    {
        $stash = $this->stash($context);

        if (!\is_string($name) || !$stash->definition->hasSegment($name)) {
            throw new RuntimeError(\sprintf('"%s" is not a segment the template declares; declare it in its segments.', \is_string($name) ? $name : get_debug_type($name)));
        }

        return $stash->context->inSegment($name);
    }

    /**
     * @param array<array-key, mixed> $context
     */
    #[AsTwigFilter(name: 'panel', needsContext: true, isSafe: ['html'], preEscape: 'html')]
    public function panel(array $context, string $content): string
    {
        $stash = $this->stash($context);

        if (RenderMode::Text === $stash->mode) {
            return $content;
        }

        return $stash->keep('<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" class="phil-email-panel" style="width:100%;margin:16px 0;"><tr><td bgcolor="'.$this->theme->light('panel').'" style="padding:16px 20px;border-radius:6px;background-color:'.$this->theme->light('panel').';">'.$stash->markdown($content).'</td></tr></table>');
    }

    /**
     * @param array<array-key, mixed> $context
     */
    #[AsTwigFilter(name: 'footer', needsContext: true, isSafe: ['html'], preEscape: 'html')]
    public function footer(array $context, string $content): string
    {
        $stash = $this->stash($context);

        if (RenderMode::Text === $stash->mode) {
            return $content;
        }

        return $stash->keep('<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" class="phil-email-footer" style="width:100%;margin:24px 0 0;"><tr><td align="center" style="padding:16px 0 0;border-top:1px solid '.$this->theme->light('border').';font-size:13px;line-height:1.5;color:'.$this->theme->light('muted').';text-align:center;">'.$stash->markdown($content).'</td></tr></table>');
    }

    /**
     * @param array<array-key, mixed> $context
     */
    private function stash(array $context): RenderStash
    {
        $stash = $context[RenderStash::CONTEXT_NAME] ?? null;

        if (!$stash instanceof RenderStash) {
            throw new RuntimeError('The email components draw only inside a template rendered by the email templates.');
        }

        return $stash;
    }

    private function text(mixed $value, string $what): string
    {
        if (\is_string($value) || \is_int($value) || \is_float($value) || $value instanceof Stringable) {
            return (string) $value;
        }

        throw new RuntimeError(\sprintf('%s is a text.', $what));
    }

    /**
     * An absolute address, checked and normalized: `http`, `https` or `mailto`, or `https` alone for an image.
     */
    private function link(mixed $value, bool $httpsOnly = false): string
    {
        $text = trim($this->text($value, 'An address'));
        $schemes = $httpsOnly ? ['https'] : ['http', 'https'];

        if (!$httpsOnly && 0 === strncasecmp($text, 'mailto:', 7)) {
            try {
                return 'mailto:'.EmailAddress::parse(substr($text, 7))->toString();
            } catch (InvalidEmailAddress) {
                throw new RuntimeError('A mailto address holds one email address.');
            }
        }

        try {
            $url = Url::fromString($text);
        } catch (InvalidUrl) {
            $url = null;
        }

        if (!$url instanceof Url || !\in_array($url->scheme, $schemes, true)) {
            throw new RuntimeError(\sprintf('An address is an absolute %s address.', implode(' or ', $schemes)));
        }

        return $url->toString();
    }

    private function align(mixed $value): string
    {
        if (!\in_array($value, ['left', 'center', 'right'], true)) {
            throw new RuntimeError('A button is aligned "left", "center" or "right".');
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    private function cells(mixed $row): array
    {
        if (!is_iterable($row)) {
            throw new RuntimeError('A row of a table is a list of cells.');
        }

        $cells = [];

        foreach ($row as $cell) {
            $cells[] = null === $cell ? '' : $this->text($cell, 'A cell of a table');
        }

        return $cells;
    }

    private function escape(string $text): string
    {
        return htmlspecialchars($text, \ENT_QUOTES | \ENT_SUBSTITUTE | \ENT_HTML5, 'UTF-8');
    }
}
