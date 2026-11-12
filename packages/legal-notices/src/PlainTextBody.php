<?php

declare(strict_types=1);

namespace Phil\LegalNotices;

use Override;
use Phil\LegalNotices\Contract\BodyRenderer;
use Phil\LegalNotices\Exception\InvalidLegalNotice;

/**
 * The body written as plain text, the format `text`: escaped, each paragraph — lines up to a blank line — a `<p>`, its
 * other line breaks kept as `<br>`. Any other format is refused.
 */
final readonly class PlainTextBody implements BodyRenderer
{
    public const string FORMAT = 'text';

    #[Override]
    public function toHtml(string $format, string $body): string
    {
        if (self::FORMAT !== $format) {
            throw InvalidLegalNotice::because('unknown_format', \sprintf('The format "%s" has no renderer.', $format));
        }

        $paragraphs = [];
        $lines = [];

        foreach (explode("\n", str_replace(["\r\n", "\r"], "\n", $body)."\n") as $line) {
            $line = trim($line);

            if ('' !== $line) {
                $lines[] = htmlspecialchars($line, \ENT_QUOTES | \ENT_HTML5 | \ENT_SUBSTITUTE, 'UTF-8');
            } elseif ([] !== $lines) {
                $paragraphs[] = '<p>'.implode("<br>\n", $lines).'</p>';
                $lines = [];
            }
        }

        return implode("\n", $paragraphs);
    }
}
