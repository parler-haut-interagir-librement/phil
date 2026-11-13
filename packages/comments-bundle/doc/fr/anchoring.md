<!-- phil-dev source-sha256=f169127f3a75a59707ee1c5c5bf1baa4f42cb7b5f982cfdf9d9de58ea0c300e7 -->
# Ancrage

Un fil porte sur un passage : quelques mots qu'un lecteur a sélectionnés. Son `Anchor` dit où ils se trouvent et ce
qu'ils disent, pour que le fil puisse les retrouver une fois le texte changé.

## Une ancre

| Partie | Sens |
|---|---|
| `block` | L'identifiant du bloc UJO qui contient le passage ; `null` dans un texte simple |
| `field` | Le chemin du champ dans les données du bloc ; `null` dans un texte simple, ou un nom de champ propre à l'application |
| `start`, `end` | Les positions des caractères dans le texte du champ ; `end` découle de la longueur du passage |
| `quote` | Le passage lui-même, de 1 à 2000 caractères |
| `prefix`, `suffix` | Jusqu'à 32 caractères avant et après lui, gardés pour distinguer deux passages égaux |

Les positions comptent des caractères — des points de code Unicode, comme les compte `mb_strlen()` de PHP —, jamais
des octets, et jamais les unités UTF-16 des chaînes JavaScript : le contrôleur fait la conversion. Elles comptent le
**texte** du champ : balises omises, références de caractères décodées, comme un navigateur lit `textContent`.
`one <b>two</b> &amp; three` a le texte `one two & three`, et `two` commence à 4.

```php
use Phil\CommentsBundle\Domain\ValueObject\Anchor;

$anchor = Anchor::of('a1B2c3', 'text', 4, 'two', 'one ', ' & three');
```

## Les champs d'un bloc UJO

Le chemin d'un champ joint les clés depuis les `data` du bloc. Une chaîne est un texte quand sa clé — ou, dans une
liste, la clé de la liste — est l'une de `text`, `caption`, `title`, `message`, `content`, `description`, `name`,
`term`, `subtitle`, `code` ou `latex` ; les URL, les styles, les niveaux et les autres réglages n'en sont jamais.

| Bloc | Chemins |
|---|---|
| `paragraph`, `header`, `alert`, `toggle` | `text` |
| `quote` | `text`, `caption` |
| `warning` | `title`, `message` |
| `list` | `items.0.content`, `items.0.items.1.content` pour un élément imbriqué |
| `table` | `content.2.1`, la rangée puis la colonne |
| `image`, `embed` | `caption` |
| `definitionList` | `items.0.term`, `items.0.description` |
| `code`, `mermaid` | `code`, texte simple |
| `math` | `latex`, texte simple |

Les champs sont du HTML en ligne, lus comme leur texte, sauf la source des blocs `code`, `mermaid` et `math`, qui est
du texte simple. Les blocs imbriqués dans une bascule ou une colonne sont des blocs à part entière, avec leurs propres
identifiants. Un chemin a au plus 16 clés. `UjoPassages::of($document)` liste chaque texte d'un document, dans l'ordre
de lecture, pour un document décodé par `phil/ujo` (`Ujo::decode()`), dont les blocs imbriqués sont des objets
`Block`.

## Retrouver un passage

`Reanchoring::locate($anchor, $passages)` cherche dans les textes donnés, dans l'ordre de lecture, chaque endroit où
se trouve le texte exact du passage, chevauchements compris, et garde le meilleur :

1. son propre champ d'abord, puis un autre champ de son bloc, puis le reste du document — un passage déplacé par une
   modification reste habituellement là où il était ;
2. parmi les endroits de même rang, celui dont l'entourage partage le plus de caractères avec le préfixe et le suffixe
   gardés ;
3. puis, dans son propre champ, l'endroit le plus proche de son ancienne position ; ailleurs, le premier dans l'ordre
   de lecture.

L'ancre retournée porte le nouvel endroit et le contexte qui s'y trouve. Le choix est déterministe : la même ancre dans
les mêmes textes tombe toujours au même endroit, et à égalité complète le premier dans l'ordre de lecture l'emporte.
Quand le passage n'apparaît nulle part, `null` : le passage a disparu. Une modification à l'intérieur du passage — un mot changé en son milieu — le fait
disparaître : le passage est retrouvé par son texte exact, jamais approximativement. À l'inverse, un passage qui
disparaît alors que les mêmes mots restent ailleurs se déplace vers eux. Un passage court, un seul mot courant, est le
plus exposé ; une sélection plus longue fait une ancre plus sûre.

Les textes sont parcourus par octets, leurs caractères comptés une seule fois, d'un endroit au suivant, et le contexte
de chaque endroit est comparé octet par octet à celui qui a été gardé, de sorte qu'un long champ avec de nombreux
endroits ne coûte qu'une passe : un passage retrouvé parmi les cent blocs d'un document prend environ un dixième de
milliseconde, et un champ de cent mille caractères où un passage de quatre lettres apparaît à chaque endroit, quelques
centaines de millisecondes (voir [les tests](testing.md)).

## Suivre le texte

Le bundle ne connaît pas les documents : l'application lui signale qu'un document change, après l'avoir enregistré.

```php
$comments->reanchorDocument(ResourceReference::of('article', '42'), $document);   // a UJO document
$comments->reanchorText(ResourceReference::of('note', '7'), $text);               // a plain text
$comments->reanchor($subject, $passages);                                          // texts of its own
```

Chaque fil de la ressource est déplacé là où se trouve maintenant son passage, avec son contexte rafraîchi — vers une
autre occurrence des mêmes mots quand la sienne a disparu —, ou détaché, orphelin, quand les mots n'apparaissent nulle
part ; un fil orphelin dont le passage revient — une annulation, un collage — est rattaché. Les fils sur la page entière
n'ont pas de passage et restent tels quels. Seuls les fils qui ont changé sont enregistrés. Le résultat les compte :
`['updated' => 2, 'detached' => 1]`.

Un fil orphelin garde sa dernière place et sa citation. Les points de terminaison le répondent avec
`"detached": true`, et le panneau le liste après les autres, sa citation barrée, sous les mots *Orphaned: the quoted
passage is no longer in the text* ; on peut encore y répondre, le résoudre ou le rouvrir, et rejeter sa suggestion,
jamais l'accepter.

Dans le navigateur, le contrôleur trouve chaque passage dans l'éditeur au fil de la rédaction, avec les mêmes règles en
JavaScript, pour le surligner ; les ancres du serveur se mettent à jour quand l'application enregistre et réancre.
