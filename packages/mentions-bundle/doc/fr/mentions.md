<!-- phil-dev source-sha256=190dc54363697778713ae510a952ab68e129d7f7ea3d36ae832ce7dd3f6f26f6 -->
# Mentions

Une `Mention` est l'une de deux choses :

| Créée par | Contient |
|---|---|
| `Mention::ofHandle('ada', 6)` | Un identifiant tapé après `@`, et la position du `@` dans le texte, en caractères |
| `Mention::ofMember($reference, '@Ada')` | Un membre nommé par sa référence, et le libellé qu'affiche la marque |

`key()` distingue deux mentions du même membre des autres : `handle:ada`, comparé sans tenir compte de la casse, ou
`member:user:42`. Un texte qui mentionne deux fois un membre ne le compte qu'une fois.

## Texte brut et Markdown

```php
use Phil\MentionsBundle\Application\Extraction\TextMentions;

new TextMentions()->find('Thanks @ada, and @Bob.');            // @ada at 7, @Bob at 17
new TextMentions()->find('See `@code` and @ada', markdown: true); // @ada only
```

Un `@` ne compte que si aucun mot ne le précède directement : `ada@example.com`, `\@ada`, `@@ada` et
`https://example.test/@ada` ne contiennent aucune mention. En Markdown, ces éléments ne sont pas lus :

| Construction | Exemple |
|---|---|
| Les blocs de code délimités, par des accents graves ou des tildes, jusqu'à la même clôture ou la fin du texte | ```` ``` ```` … ```` ``` ```` |
| Le code en ligne sur une seule ligne, entre un ou deux accents graves | `` `@ada` `` |
| Les destinations des liens et des images | `[text](https://example.test/@ada)` |
| Les liens entre chevrons | `<https://example.test/@ada>` |

Les positions sont comptées en caractères du texte d'origine, quoi qu'on ait masqué. Un texte qui n'est pas de l'UTF-8
valide ne contient aucune mention.

## Le motif

`MentionPattern` décrit la forme d'un identifiant après son `@`. Le motif par défaut accepte de 1 à 64 lettres de
n'importe quelle écriture, des chiffres, `_`, `.` et `-`, et commence et se termine par une lettre, un chiffre ou `_` :
dans `Thanks @ada.`, l'identifiant est `ada`, et dans `@jean-luc.picard`, c'est `jean-luc.picard`.

Une application fournit le sien dans `pattern` :

```yaml
phil_mentions:
    pattern: '[a-z0-9_]{3,20}'
```

L'expression est vérifiée une fois, à la construction du conteneur. Elle est refusée si elle :

- est vide ou dépasse 256 octets ;
- contient un `~`, ou un groupe nommé `handle` ;
- ne compile pas seule — des parenthèses mal équilibrées pourraient sortir du groupe qui l'entoure —, ou autour du `@` ;
- reconnaît un identifiant vide.

Elle est lue en UTF-8 : `\p{L}` désigne donc n'importe quelle lettre.

## Documents UJO

```php
use Phil\MentionsBundle\Application\Extraction\UjoMentions;

UjoMentions::find($document); // the marks <a data-mention="kind:id">…</a>, block by block
```

L'éditeur par blocs de `phil/block-editor-bundle` écrit un membre choisi sous la forme
`<a data-mention="user:42" href="…">@Ada</a>`, que `phil/ujo` conserve. `UjoMentions` lit chaque chaîne de chaque bloc,
blocs imbriqués compris, dans l'ordre de lecture ; le libellé est le texte de la marque, entités décodées. Une marque
dont la référence n'en est pas une, `kind:identifier`, est ignorée. Le document est lu sous la forme canonique
qu'écrit `phil/ujo` : décodez-le ou normalisez-le d'abord.

## Limites

`MentionLimits` borne ce qu'un texte peut faire :

| Limite | Valeur par défaut | Plage | Refusé par |
|---|---|---|---|
| `perText` | 20 | 1 à 1 000 | `MentionsRefused::tooMany()`, avant toute question à l'annuaire |
| `textLength` | 100 000 caractères | 1 à 10 000 000 | `MentionsRefused::tooLong()`, avant la lecture du texte |

Un texte qui dépasse une limite est refusé en entier, pour qu'un seul message ne puisse pas notifier toute une
communauté ; l'application affiche le message de l'exception, ou le sien. La lecture est linéaire en fonction de la
longueur du texte : voir les bancs d'essai dans [Tests](testing.md#bancs-dessai).
