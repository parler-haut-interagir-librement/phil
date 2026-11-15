<?php

declare(strict_types=1);

/*
 * The message of the SafeCss constraint that counts, in ICU MessageFormat: the plural follows the count. The key is
 * the default message of the constraint.
 */
return [
    'This CSS holds {{ count }} more parts that are not allowed.' => '{count, plural, one {This CSS holds # more part that is not allowed.} other {This CSS holds # more parts that are not allowed.}}',
];
