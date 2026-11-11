<!-- phil-dev source-sha256=2d84cb8e84fcdf4c9e3486b3c4979719773f07300cc35cb2f8f2d97ee082c3d7 -->
# Points d'extension

Ce qu'une application implémente, remplace ou utilise.

## Dictionnaires

`StrengthEstimator` prend la liste des `Dictionary` dans lesquels il cherche des mots : ceux qui sont fournis, par
défaut. Ajoutez ceux de l'application — le nom du site, ses produits, des mots de son domaine — avec
`Dictionary::of()`, classés du plus courant au moins courant, à côté de `Dictionary::bundled()`. Le nom d'un
dictionnaire est libre ; `passwords`, `names`, `surnames` et `user_inputs` donnent leurs propres avertissements.

## Dispositions de clavier

`StrengthEstimator` prend la liste des `KeyboardLayout` sur lesquels il cherche des parcours : QWERTY, AZERTY, QWERTZ
et le pavé numérique par défaut. `KeyboardLayout::staggered()` décrit un autre clavier de machine à écrire par ses
rangées et leurs décalages en demi-touches, `KeyboardLayout::aligned()` un autre pavé numérique.

## Données de l'utilisateur

`estimate()` prend ce que l'application sait de la personne. Un objet validé les donne en implémentant
`Contract\PasswordUserInputs`.

## Sources de fuites

`Breach\Contract\BreachedPasswordSource` est le port des vérifications de fuites : `PwnedPasswordsSource` par HTTP,
`InMemoryBreachSource` en mémoire, ou celle de l'application — un miroir atteint autrement, un décorateur avec un
disjoncteur ou un cache. Une source lance `BreachCheckUnavailable` quand elle ne peut pas répondre. Voyez
[Vérifier les fuites](breaches.md).

## Listes de mots et hasard

`PassphraseGenerator` prend une `Wordlist` : une liste fournie ou `Wordlist::of()`. Les deux générateurs prennent un
`Random\Randomizer` : le moteur sûr par défaut, un moteur avec germe dans un test.

## Traductions

La rétroaction et les contraintes sont traduites par clés : `Warning::translationKey()` et
`Suggestion::translationKey()` dans le domaine `phil_passwords`, les messages des contraintes dans `validators`. Une
application ajoute une locale en écrivant `phil_passwords.<locale>.php` et `validators.<locale>.php` dans son propre
chemin de traductions.

## Valeurs stables

`Score`, `Pattern`, `Warning`, `Suggestion`, `BreachStatus`, `CharacterSet` et `Capitalization` sont des enums
adossées dont les valeurs sont stables : une application peut ainsi les stocker et les traduire par elles.
