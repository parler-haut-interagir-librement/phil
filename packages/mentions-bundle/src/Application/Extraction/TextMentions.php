<?php

declare(strict_types=1);

namespace Phil\MentionsBundle\Application\Extraction;

use Phil\MentionsBundle\Domain\ValueObject\Mention;
use Phil\MentionsBundle\Domain\ValueObject\MentionPattern;

/**
 * The handles typed after `@` in a plain text or in Markdown, in reading order, with the character offset of each
 * `@`. In Markdown, what is code or an address is not read: fenced code blocks, code spans on one line, the
 * destinations of links and images, and autolinks. A text that is not valid UTF-8 holds no mention.
 */
final readonly class TextMentions
{
    private const array MARKDOWN = [
        // A fenced code block, up to the same fence or the end of the text.
        '/^ {0,3}(`{3,}|~{3,})[^\n]*+\n.*?(?:^ {0,3}\1[ \t]*+$|\z)/ms',
        // A code span of two backticks, then of one, on one line.
        '/``[^\n]*?``|`[^`\n]*+`/',
        // The destination of a link or an image, after its text.
        '/\]\([^)\s]*+\)/',
        // An autolink, `<scheme:…>`.
        '/<[A-Za-z][A-Za-z0-9+.\-]{1,31}:[^>\s]*+>/',
    ];

    public MentionPattern $pattern;

    public function __construct(?MentionPattern $pattern = null)
    {
        $this->pattern = $pattern ?? MentionPattern::default();
    }

    /**
     * @return list<Mention>
     */
    public function find(string $text, bool $markdown = false): array
    {
        // A text that is not valid UTF-8 matches nothing.
        preg_match_all($this->pattern->regex, $markdown ? $this->masked($text) : $text, $matches, \PREG_OFFSET_CAPTURE);
        $mentions = [];
        $byte = 0;
        $character = 0;

        foreach ($matches['handle'] ?? [] as [$handle, $offset]) {
            // The `@` is the byte before the handle; characters are counted from the previous mention only.
            $character += mb_strlen(substr($text, $byte, $offset - 1 - $byte));
            $byte = $offset - 1;
            $mentions[] = Mention::ofHandle($handle, $character);
        }

        return $mentions;
    }

    /**
     * The text with its code and addresses blanked, byte for byte, so that offsets stay where they were.
     */
    private function masked(string $text): string
    {
        return (string) preg_replace_callback(self::MARKDOWN, static fn (array $match): string => str_repeat(' ', \strlen($match[0])), $text);
    }
}
