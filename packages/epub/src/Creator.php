<?php

declare(strict_types=1);

namespace Phil\Epub;

use Phil\Epub\Exception\InvalidBook;

/**
 * A person or an organisation that made the publication, with its MARC relator role (`aut` for an author, `ill` for an
 * illustrator, `edt` for an editor, `trl` for a translator) and the form of its name used to sort.
 */
final readonly class Creator
{
    /**
     * @throws InvalidBook
     */
    public function __construct(public string $name, public ?string $role = null, public ?string $fileAs = null)
    {
        if ('' === trim($name)) {
            throw InvalidBook::because('A creator needs a name.');
        }

        if (null !== $role && 1 !== preg_match('/^[a-z]{3}$/D', $role)) {
            throw InvalidBook::because(\sprintf('A role is a MARC relator code of three lowercase letters; "%s" given.', $role));
        }
    }
}
