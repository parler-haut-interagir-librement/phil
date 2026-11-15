<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Application;

use Phil\ModerationBundle\Domain\Identifiers;

/**
 * The reasons a member may give for a report, in the order the form offers them. Their labels are translated by
 * code, in the domain `phil_moderation`.
 */
final readonly class Reasons
{
    public const array DEFAULTS = ['spam', 'harassment', 'hate', 'violence', 'sexual', 'personal-information', 'illegal', 'off-topic', 'other'];

    /** The reasons of the reports the bundle files itself. */
    public const string WATCHED_WORDS = 'watched-words';

    public const string FIRST_POST = 'first-post';

    public const string HIDDEN_BY_MEMBER = 'hidden-by-member';

    /** @var list<string> */
    private array $codes;

    /**
     * @param list<string> $codes
     */
    public function __construct(array $codes = self::DEFAULTS)
    {
        $this->codes = array_values(array_unique(array_map(static fn (string $code): string => Identifiers::code($code, 'a reason'), $codes)));
    }

    /**
     * @return list<string>
     */
    public function all(): array
    {
        return $this->codes;
    }

    public function has(string $code): bool
    {
        return \in_array($code, $this->codes, true);
    }
}
