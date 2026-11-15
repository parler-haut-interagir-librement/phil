<?php

declare(strict_types=1);

namespace Phil\EnumsBundle;

/*
 * PHP resolves a function called without a backslash in a namespace to the namespace's own function first: this
 * one stands in for class_exists() in the bundle's code, so that a test can make a class look absent, which the
 * installed dependencies never are. It is loaded with the test classes, before any code of the bundle runs.
 */

/**
 * @param list<class-string>|null $hidden the classes that look absent from now on; null leaves them as they are
 *
 * @return list<class-string>
 */
function hidden_classes(?array $hidden = null): array
{
    static $classes = [];

    if (null !== $hidden) {
        $classes = $hidden;
    }

    return $classes;
}

function class_exists(string $class, bool $autoload = true): bool
{
    // A call by name reaches the global function: a call written class_exists() here would be this one again.
    $native = 'class_exists';

    return !\in_array($class, hidden_classes(), true) && $native($class, $autoload);
}
