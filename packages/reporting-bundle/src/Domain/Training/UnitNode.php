<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Domain\Training;

use Phil\ReportingBundle\Domain\Exception\InvalidFigures;

/**
 * An organisational unit on the path of another: its identifier and its name.
 */
final readonly class UnitNode
{
    public const int MAX_NAME = 255;

    private const string ID = '/^[\x21-\x7E]{1,128}$/D';

    /**
     * @throws InvalidFigures when the identifier is not 1 to 128 printable ASCII characters, or the name not 1 to 255 characters without control characters
     */
    public function __construct(
        public string $id,
        public string $name,
    ) {
        if (1 !== preg_match(self::ID, $id)) {
            throw InvalidFigures::because('a unit is named by 1 to 128 printable ASCII characters');
        }

        if (1 !== preg_match('/^[^\p{Cc}]{1,'.self::MAX_NAME.'}$/Du', $name)) {
            throw InvalidFigures::because(\sprintf('the name of a unit has 1 to %d characters, none of them a control character', self::MAX_NAME));
        }
    }
}
