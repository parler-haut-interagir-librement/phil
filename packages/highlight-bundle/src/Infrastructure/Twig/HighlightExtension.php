<?php

declare(strict_types=1);

namespace Phil\HighlightBundle\Infrastructure\Twig;

use InvalidArgumentException;
use Phil\Highlight\Exception\InvalidLineRanges;
use Phil\Highlight\Highlighter;
use Phil\Highlight\HtmlFormatter;
use Phil\Highlight\LineOptions;
use Phil\Highlight\LineRanges;
use Phil\Highlight\Token;
use Stringable;
use Twig\Attribute\AsTwigFilter;

/**
 * Code coloured in templates: `{{ snippet|highlight('php') }}` gives the escaped code wrapped in `hl-` spans, marked
 * safe, to put inside `<pre><code class="language-php">`. A language the highlighter does not know, or none, gives the
 * code escaped, without spans; `null` gives an empty string. The code is always text: a string marked safe is read as
 * code all the same, its tags shown, never run.
 *
 * `lines` writes the block line by line: `{numbers: true, highlight: '2,4-5', annotations: true}`, or `LineOptions`.
 */
final readonly class HighlightExtension
{
    private const array LINE_KEYS = ['numbers', 'highlight', 'annotations'];

    public function __construct(
        private Highlighter $highlighter,
    ) {
    }

    /**
     * @param LineOptions|array<array-key, mixed>|null $lines
     *
     * @throws InvalidArgumentException when `lines` holds a key or a value it does not read
     * @throws InvalidLineRanges        when its ranges or its first number cannot be read
     */
    #[AsTwigFilter(name: 'highlight', isSafe: ['html'])]
    public function highlight(string|Stringable|null $code, ?string $language = null, LineOptions|array|null $lines = null): string
    {
        $code = null === $code ? '' : (\is_string($code) ? $code : $code->__toString());
        $options = \is_array($lines) ? $this->lineOptions($lines) : $lines;
        $html = null === $language ? null : $this->highlighter->highlight($code, $language, $options);

        if (null !== $html) {
            return $html;
        }

        return $options instanceof LineOptions ? HtmlFormatter::formatLines([new Token($code)], $options) : htmlspecialchars($code, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * @param array<array-key, mixed> $lines
     *
     * @throws InvalidArgumentException
     * @throws InvalidLineRanges
     */
    private function lineOptions(array $lines): LineOptions
    {
        foreach (array_keys($lines) as $key) {
            if (!\in_array($key, self::LINE_KEYS, true)) {
                throw new InvalidArgumentException(\sprintf('The lines of highlight() read "numbers", "highlight" and "annotations", not "%s".', $key));
            }
        }

        $numbers = $lines['numbers'] ?? false;
        $highlight = $lines['highlight'] ?? '';
        $annotations = $lines['annotations'] ?? false;

        if (!\is_bool($numbers) && !\is_int($numbers)) {
            throw new InvalidArgumentException('The "numbers" of highlight() is true, false or the number of the first line.');
        }

        if (!\is_string($highlight)) {
            throw new InvalidArgumentException('The "highlight" of highlight() is a text of line ranges, such as "1,3-5".');
        }

        if (!\is_bool($annotations)) {
            throw new InvalidArgumentException('The "annotations" of highlight() is true or false.');
        }

        return new LineOptions(
            true === $numbers ? 1 : (false === $numbers ? null : $numbers),
            LineRanges::parse($highlight),
            $annotations,
        );
    }
}
