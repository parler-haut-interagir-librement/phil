<?php

declare(strict_types=1);

/*
 * The messages of the bundle's constraints that show a number, in ICU MessageFormat: the plural follows the limit.
 * The keys are the default messages of the constraints.
 */
return [
    'You must select at least {{ limit }} choice.|You must select at least {{ limit }} choices.' => '{limit, plural, one {You must select at least # choice.} other {You must select at least # choices.}}',
    'You must select at most {{ limit }} choice.|You must select at most {{ limit }} choices.' => '{limit, plural, one {You must select at most # choice.} other {You must select at most # choices.}}',
];
