<?php

declare(strict_types=1);

namespace Phil\FormLogic\Expression;

use Override;
use Phil\FormLogic\Contract\Expression;
use Phil\FormLogic\Exception\InvalidLogic;

/**
 * A value of the answer, by name: `{"var": "age"}`. A dotted name reads into a map or a list, `{"var": "guest.0"}`;
 * a name not answered, or answered `null`, reads as the default when there is one, `{"var": ["age", 18]}`, else as
 * `null`.
 */
final readonly class Variable implements Expression
{
    /** The pattern of a name: segments of letters, digits and `_`, the first starting with a letter or `_`. */
    public const string NAME = '/^[A-Za-z_][A-Za-z0-9_]{0,63}(?:\.[A-Za-z0-9_]{1,64}){0,7}$/D';

    /**
     * @throws InvalidLogic when the name does not match `NAME`
     */
    public function __construct(public string $name, public ?Expression $default = null)
    {
        self::checkName($name);
    }

    /**
     * @throws InvalidLogic when the name does not match `NAME`
     */
    public static function checkName(string $name): void
    {
        if (1 !== preg_match(self::NAME, $name)) {
            throw InvalidLogic::because(\sprintf('A variable is named by 1 to 8 segments of letters, digits or "_", joined by dots, the first starting with a letter or "_"; "%s" is not.', mb_substr($name, 0, 80)));
        }
    }

    /**
     * The segments of the name, in the order they are read.
     *
     * @return non-empty-list<string>
     */
    public function path(): array
    {
        return explode('.', $this->name);
    }

    #[Override]
    public function jsonLogic(): array
    {
        return $this->default instanceof Expression ? ['var' => [$this->name, $this->default->jsonLogic()]] : ['var' => $this->name];
    }

    #[Override]
    public function children(): array
    {
        return $this->default instanceof Expression ? [$this->default] : [];
    }
}
