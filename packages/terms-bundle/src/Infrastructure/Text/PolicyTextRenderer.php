<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Infrastructure\Text;

use Override;
use Phil\TermsBundle\Application\Contract\PolicyTexts;
use Phil\TermsBundle\Domain\Exception\InvalidTerms;
use Phil\TermsBundle\Domain\TextFormat;
use Phil\TermsBundle\Domain\ValueObject\PolicyVersion;
use Phil\TextDiff\TextDiff;
use Phil\Ujo\Exception\UjoException;
use Phil\Ujo\Ujo;

/**
 * The texts as HTML: a plain text — valid UTF-8, as every version holds — escaped, its paragraphs — separated by a
 * blank line — each a `<p>`, its other line breaks kept as `<br>`; a UJO (Universal JSON Output) document rendered by
 * `phil/ujo`, which sanitizes what it renders. The changes between two versions are those of their HTML, marked by
 * `phil/text-diff` with `<ins>` and `<del>`.
 */
final readonly class PolicyTextRenderer implements PolicyTexts
{
    public function __construct(
        private ?Ujo $ujo = null,
        private ?TextDiff $differ = null,
    ) {
    }

    #[Override]
    public function check(TextFormat $format, string $text): void
    {
        if (TextFormat::Text === $format) {
            return;
        }

        if (!$this->ujo instanceof Ujo) {
            throw InvalidTerms::because('format_unavailable', 'The "ujo" format needs phil/ujo.');
        }

        try {
            $this->ujo->decode($text);
        } catch (UjoException $ujoException) {
            throw new InvalidTerms('The text is not a UJO document: '.$ujoException->getMessage(), 'invalid_document', previous: $ujoException);
        }
    }

    #[Override]
    public function toHtml(PolicyVersion $version, string $locale, string $fallback): string
    {
        $text = $version->text($locale, $fallback);

        if (TextFormat::Ujo === $version->format && $this->ujo instanceof Ujo) {
            return $this->ujo->toHtml($text);
        }

        $paragraphs = [];
        $lines = [];

        foreach (explode("\n", str_replace(["\r\n", "\r"], "\n", $text)) as $line) {
            if ('' !== trim($line)) {
                $lines[] = htmlspecialchars(trim($line), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
            } elseif ([] !== $lines) {
                $paragraphs[] = '<p>'.implode("<br>\n", $lines).'</p>';
                $lines = [];
            }
        }

        if ([] !== $lines) {
            $paragraphs[] = '<p>'.implode("<br>\n", $lines).'</p>';
        }

        return implode("\n", $paragraphs);
    }

    #[Override]
    public function changes(PolicyVersion $old, PolicyVersion $new, string $locale, string $fallback): ?string
    {
        if (!$this->differ instanceof TextDiff) {
            return null;
        }

        return $this->differ->toHtml($this->differ->html($this->toHtml($old, $locale, $fallback), $this->toHtml($new, $locale, $fallback)));
    }
}
