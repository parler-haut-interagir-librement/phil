<?php

declare(strict_types=1);

namespace Phil\FeedBundle\Domain\ValueObject;

use Phil\FeedBundle\Domain\Exception\InvalidFeed;
use Phil\Url\Url;

/**
 * A person or an organisation that wrote a feed or an entry: a name, and perhaps a page and a picture of them.
 */
final readonly class Author
{
    public const int MAX_NAME_LENGTH = 255;

    public string $name;

    /**
     * @throws InvalidFeed when the name is empty, longer than 255 characters or holds a control character
     */
    public function __construct(
        string $name,
        public ?Url $url = null,
        public ?Url $avatar = null,
    ) {
        $this->name = Text::line('author name', $name, self::MAX_NAME_LENGTH);
    }
}
