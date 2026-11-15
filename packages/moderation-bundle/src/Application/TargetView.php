<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Application;

use Phil\ModerationBundle\Domain\Exception\InvalidModeration;
use Phil\ModerationBundle\Domain\Identifiers;
use Phil\ModerationBundle\Domain\ValueObject\MemberId;

/**
 * What the queue shows of a content: a title, an excerpt, the address of the content, and its author.
 */
final readonly class TargetView
{
    public const int MAX_TITLE_LENGTH = 200;

    public const int MAX_EXCERPT_LENGTH = 500;

    public string $title;

    public ?string $excerpt;

    public function __construct(
        string $title,
        ?string $excerpt = null,
        public ?string $url = null,
        public ?MemberId $author = null,
    ) {
        $this->title = Identifiers::text($title, self::MAX_TITLE_LENGTH, 'A title') ?? throw InvalidModeration::because('A title is not empty.');
        $excerpt = null === $excerpt ? null : trim($excerpt);
        $this->excerpt = null === $excerpt || '' === $excerpt ? null : (mb_strlen($excerpt) > self::MAX_EXCERPT_LENGTH ? mb_substr($excerpt, 0, self::MAX_EXCERPT_LENGTH - 1).'…' : $excerpt);
    }
}
