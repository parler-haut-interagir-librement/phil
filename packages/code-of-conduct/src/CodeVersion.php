<?php

declare(strict_types=1);

namespace Phil\CodeOfConduct;

use DateTimeImmutable;
use Phil\CodeOfConduct\Exception\InvalidCodeOfConduct;

/**
 * One published version of the code of conduct of a scope — a community, an event, a space — numbered from 1, never
 * changed once published. Its hash, SHA-256 of its title and text, is what an acceptance records.
 */
final readonly class CodeVersion
{
    public const int MAX_TITLE = 200;

    public const int MAX_TEXT = 100_000;

    public string $title;

    public string $text;

    public string $hash;

    public function __construct(
        public string $scope,
        public int $number,
        string $title,
        string $text,
        public ChangeKind $kind,
        public DateTimeImmutable $publishedAt,
    ) {
        Identifier::check($scope, 'A scope');

        if ($number < 1) {
            throw InvalidCodeOfConduct::because('A version of a code is numbered from 1.');
        }

        if (1 === $number && ChangeKind::Major !== $kind) {
            throw InvalidCodeOfConduct::because('The first version of a code is a major one.');
        }

        $this->title = Text::check($title, self::MAX_TITLE, \sprintf('The title of a code holds 1 to %d characters.', self::MAX_TITLE));

        if (1 === preg_match('/[\r\n\t]/', $this->title)) {
            throw InvalidCodeOfConduct::because(\sprintf('The title of a code holds 1 to %d characters.', self::MAX_TITLE));
        }

        $this->text = Text::check($text, self::MAX_TEXT, \sprintf('The text of a code holds 1 to %d characters.', self::MAX_TEXT));
        $this->hash = hash('sha256', $this->title."\n\n".$this->text);
    }
}
