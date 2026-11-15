<?php

declare(strict_types=1);

/*
 * The messages of the bundle's constraints in French, in ICU MessageFormat. The keys are the default messages of
 * the constraints.
 */
return [
    'This value is not a set of allowed choices.' => 'Cette valeur n’est pas un ensemble de choix permis.',
    'The value {{ value }} is not an allowed choice.' => 'La valeur {value} n’est pas un choix permis.',
    'You must select at least {{ limit }} choice.|You must select at least {{ limit }} choices.' => '{limit, plural, one {Vous devez sélectionner au moins # choix.} other {Vous devez sélectionner au moins # choix.}}',
    'You must select at most {{ limit }} choice.|You must select at most {{ limit }} choices.' => '{limit, plural, one {Vous devez sélectionner au plus # choix.} other {Vous devez sélectionner au plus # choix.}}',
    'The state cannot move from {{ from }} to {{ to }}.' => 'L’état ne peut pas passer de {from} à {to}.',
    'The state cannot start as {{ to }}.' => 'L’état ne peut pas commencer à {to}.',
];
