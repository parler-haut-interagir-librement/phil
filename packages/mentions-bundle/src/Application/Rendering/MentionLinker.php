<?php

declare(strict_types=1);

namespace Phil\MentionsBundle\Application\Rendering;

use Phil\MentionsBundle\Application\Contract\MemberDirectory;
use Phil\MentionsBundle\Application\Member;
use Phil\MentionsBundle\Domain\ValueObject\MentionPattern;
use Phil\Ujo\Html\SafeUrl;

/**
 * Turns the handles of an HTML text into links to the members' profiles: `@ada` becomes
 * `<a class="mention" href="/members/ada" data-mention="user:42">@ada</a>`, or a `<span>` of the same class for a member
 * without a profile or whose address is not safe. Only the text between tags is read, never inside a link, code, a
 * preformatted block, a script, a style or a text area; a handle nobody has stays as it is. The directory is asked
 * once per text, for its first handles up to the limit — the others stay as they are.
 *
 * The HTML is the application's: an escaped plain text, or Markdown already rendered.
 */
final readonly class MentionLinker
{
    private const string TAG = '~(<[^>]*+>)~';

    private const string SKIPPED = '~^<(/?)(?:a|code|pre|script|style|textarea)\b~i';

    public function __construct(
        private MemberDirectory $directory,
        private MentionPattern $pattern,
        private int $limit,
    ) {
    }

    public function link(string $html): string
    {
        /** @var list<string> $parts the texts at even indexes, the tags at odd ones */
        $parts = preg_split(self::TAG, $html, -1, \PREG_SPLIT_DELIM_CAPTURE);
        $texts = $this->readable($parts);
        $members = $this->members($parts, $texts);

        foreach ($texts as $index) {
            $parts[$index] = preg_replace_callback(
                $this->pattern->regex,
                static fn (array $match): string => isset($members[mb_strtolower($match['handle'])]) ? self::mark($members[mb_strtolower($match['handle'])], $match[0]) : $match[0],
                $parts[$index],
            ) ?? $parts[$index];
        }

        return implode('', $parts);
    }

    /**
     * The indexes of the parts that are text outside the elements never read.
     *
     * @param list<string> $parts
     *
     * @return list<int>
     */
    private function readable(array $parts): array
    {
        $texts = [];
        $depth = 0;

        foreach ($parts as $index => $part) {
            if (1 === $index % 2) {
                if (1 === preg_match(self::SKIPPED, $part, $tag)) {
                    $depth = max(0, $depth + ('' === $tag[1] ? 1 : -1));
                }
            } elseif (0 === $depth && str_contains($part, '@')) {
                $texts[] = $index;
            }
        }

        return $texts;
    }

    /**
     * The members of the first handles of the texts, by handle in lower case.
     *
     * @param list<string> $parts
     * @param list<int>    $texts
     *
     * @return array<string, Member>
     */
    private function members(array $parts, array $texts): array
    {
        $handles = [];

        foreach ($texts as $index) {
            // A text that is not valid UTF-8 matches nothing, and is left as it is.
            preg_match_all($this->pattern->regex, $parts[$index], $matches);

            foreach ($matches['handle'] ?? [] as $handle) {
                $handles[mb_strtolower($handle)] ??= $handle;
            }
        }

        $handles = \array_slice(array_values($handles), 0, $this->limit);
        $members = [];

        foreach ([] === $handles ? [] : $this->directory->resolve($handles) as $member) {
            $members[mb_strtolower($member->handle)] = $member;
        }

        return $members;
    }

    private static function mark(Member $member, string $text): string
    {
        $reference = htmlspecialchars($member->reference->toString(), \ENT_QUOTES | \ENT_SUBSTITUTE | \ENT_HTML5);

        if (null !== $member->url && SafeUrl::isSafe($member->url, SafeUrl::MEDIA)) {
            return \sprintf('<a class="mention" href="%s" data-mention="%s">%s</a>', htmlspecialchars($member->url, \ENT_QUOTES | \ENT_SUBSTITUTE | \ENT_HTML5), $reference, $text);
        }

        return \sprintf('<span class="mention" data-mention="%s">%s</span>', $reference, $text);
    }
}
