<?php

declare(strict_types=1);

namespace Phil\Epub;

use Phil\Epub\Exception\InvalidBook;

/**
 * What the media overlays of a book share: the people who read them, and the classes the reading system puts on the
 * element being read and on the document while it plays, which the book's stylesheets can style.
 */
final readonly class Narration
{
    private const string CLASS_NAME = '/^-?[_A-Za-z][_A-Za-z0-9-]*$/D';

    /**
     * @param list<string> $narrators
     *
     * @throws InvalidBook
     */
    public function __construct(
        public array $narrators = [],
        public ?string $activeClass = null,
        public ?string $playbackActiveClass = null,
    ) {
        foreach ($narrators as $narrator) {
            if ('' === trim($narrator)) {
                throw InvalidBook::because('A narrator needs a name.');
            }
        }

        foreach ([$activeClass, $playbackActiveClass] as $class) {
            if (null !== $class && 1 !== preg_match(self::CLASS_NAME, $class)) {
                throw InvalidBook::because(\sprintf('"%s" is not a CSS class name.', $class));
            }
        }
    }
}
