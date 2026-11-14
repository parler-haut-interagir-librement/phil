<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Domain\ValueObject;

use Phil\SettingsBundle\Domain\Exception\InvalidSetting;
use Phil\SettingsBundle\Domain\Setting\Scope;

/**
 * Whose values are read and written: one user, by the identifier the application gives them, or the site.
 */
final readonly class Subject
{
    /** The longest identifier of a user, the length of its column. */
    public const int ID_LENGTH = 190;

    /** The identifier the site's values are stored under. */
    public const string SITE = 'site';

    private function __construct(
        public Scope $scope,
        public string $id,
    ) {
    }

    /**
     * @throws InvalidSetting when the identifier is empty, longer than 190 bytes, or holds a space or a control character
     */
    public static function user(string $id): self
    {
        if ('' === $id || \strlen($id) > self::ID_LENGTH || 1 === preg_match('/[\s\x00-\x1F\x7F]/', $id)) {
            throw InvalidSetting::because(\sprintf('The user "%s" is not an identifier: at most %d bytes, without a space or a control character.', $id, self::ID_LENGTH));
        }

        return new self(Scope::User, $id);
    }

    public static function site(): self
    {
        return new self(Scope::Site, self::SITE);
    }

    /**
     * The subject as one string, `user:42` or `site:site`, to index what is kept of it.
     */
    public function key(): string
    {
        return $this->scope->value.':'.$this->id;
    }
}
