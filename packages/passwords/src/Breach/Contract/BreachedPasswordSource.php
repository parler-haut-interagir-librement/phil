<?php

declare(strict_types=1);

namespace Phil\Passwords\Breach\Contract;

use Phil\Passwords\Exception\BreachCheckUnavailable;
use SensitiveParameter;

/**
 * Where the passwords of known data breaches are looked up: the Pwned Passwords range API, a mirror of it, a list held
 * in memory, or the application's own. A source that sends anything over the network sends at most the first five
 * characters of the password's SHA-1 hash, never the password.
 */
interface BreachedPasswordSource
{
    /**
     * How many times the password appears in the breaches the source knows, 0 when it appears in none.
     *
     * @throws BreachCheckUnavailable when the source cannot answer
     */
    public function occurrences(#[SensitiveParameter] string $password): int;
}
