<?php

declare(strict_types=1);

/*
 * The messages of the SafeCss constraint in French, in ICU MessageFormat: what was removed follows the reason, and
 * the plural follows the count. The keys are the default messages of the constraint.
 */
return [
    'This CSS holds {{ removed }} on line {{ line }}: {{ subject }}.' => 'Ce CSS contient {reason, select, import {une règle @import} at_rule {une règle @ qui n’est pas permise} prelude {une requête média, une condition ou un sélecteur de page qui n’est pas permis} selector {un sélecteur qui n’est pas permis} property {une propriété qui n’est pas permise} denied_property {une propriété qui exécute du code} function {une fonction qui n’est pas permise} url {une URL qui n’est pas permise} dangerous_url {une URL qui exécute du code} value {une valeur qui n’est pas permise} important {un indicateur !important} syntax {du texte qui n’est pas du CSS valide} font_source {une source de police inutilisable} too_long {une feuille de style trop longue} too_deep {des blocs imbriqués trop profondément} nesting {une règle imbriquée plus profondément que permis} other {{removed}}} à la ligne {line} : {subject}.',
    'This CSS holds {{ count }} more parts that are not allowed.' => '{count, plural, one {Ce CSS contient # autre partie qui n’est pas permise.} other {Ce CSS contient # autres parties qui ne sont pas permises.}}',
];
