<!-- phil-dev source-sha256=9fd78ab8f860e4c15722951d920ece62b10c8e9622dcf2946b8572198ffe0b20 -->
# phil/ujo

**Des documents UJO (Universal JSON Output) comme des valeurs fiables : lus strictement, vérifiés bloc par bloc, rendus
en HTML sûr.**

phil/ujo est le modèle de UJO (Universal JSON Output), un format JSON pour des documents faits de blocs : une heure,
une version et une liste ordonnée de blocs, chacun avec un identifiant, un type, ses données et ses réglages (*tunes*).
Il lit ce JSON strictement en valeurs immuables et vérifie chaque bloc selon la forme de son type. Il met à niveau les
formes anciennes et tierces des données vers une seule forme canonique, assainit le HTML en ligne que chaque texte peut
contenir, et produit le rendu du document en HTML ou en texte brut. Il connaît chaque outil officiel d'Editor.js, les
outils communautaires qui valent la peine d'être lus (alertes, bascules, colonnes, formules, diagrammes, graphiques,
boutons, galeries), et les types des pages imprimées et des livres de jeu : sauts de page et de colonne, notes de bas
de page, tables des matières, index, encadrés, blocs de statistiques. Il ne modifie pas les documents, ne convertit pas
d'autre balisage en UJO, et ne dessine ni formules, ni diagrammes, ni codes QR sur le serveur.

Il s'adresse à toute application PHP qui stocke ce que ses rédacteurs composent dans un éditeur par blocs et le montre
à des lecteurs : un CMS, un wiki, un forum, un outil de mise en page de livres. Un document est vérifié une fois, à sa
réception, et stocké sous sa forme canonique ; le rendre de nouveau le vérifie de nouveau, pour qu'une règle resserrée
atteigne les documents plus anciens. Dans une application Symfony, `phil/ujo-bundle` ajoute les filtres Twig
`ujo_html` et `ujo_text`, un champ de formulaire qui contient du JSON UJO et une contrainte de validation.

C'est une bibliothèque PHP simple, écrite de zéro, sans dépendance. Les données écrites par les outils des éditeurs
Editor.js sont lues selon leur forme ; aucune ligne de ces outils n'est copiée. Son assainisseur HTML lui est propre :
il garde, champ par champ, les éléments en ligne qu'un profil permet, réécrits sous une seule forme canonique, et rien
d'autre.

La conception est réduite : `Document` et `Block` sont les valeurs ; `BlockTypeRegistry` contient un `BlockType` par
type, dont la `Shape` de champs vérifie les données ; `Normalizer` les applique ; `HtmlRenderer` produit le rendu avec
un `BlockRenderer` par type ; `PlainText` convertit ; `Ujo` assemble le tout.

## État

| Partie | État |
|---|---|
| `Document`, `Block` : valeurs, structure vérifiée, blocs imbriqués trouvés, forme JSON | Implémenté et testé |
| `JsonDecoder`, `JsonEncoder` : lecture stricte avec limites de taille et de profondeur, écriture sous forme canonique | Implémenté et testé |
| Types de blocs : 13 officiels, 8 communautaires, 21 types de page et de jeu ; `raw` sur demande | Implémenté et testé |
| Formes anciennes et tierces : listes 1.x, listes de contrôle, listes de contrôle imbriquées, images simples, bascules plates et blocs repliables, colonnes calumk, LaTeX, boutons, carrousels, alignement et ancres enregistrés dans les données | Implémenté et testé |
| Deux niveaux de validation : la forme à chaque enregistrement, les valeurs exigées remplies et les bornes pour la publication | Implémenté et testé |
| Réglages : alignement, ancre, variante de texte, avis, retrait, mise en page, masque, table des matières, variante | Implémenté et testé |
| `InlineSanitizer` : profils `rich`, `plain` et `raw` des outils en ligne | Implémenté et testé |
| `HtmlRenderer` : un moteur de rendu par type, remplaçable ; réglages en classes, identifiants et styles | Implémenté et testé |
| `PlainText` : une conversion par type, remplaçable | Implémenté et testé |
| L'outil communautaire `layout` (mises en page CSS libres) | Non lu, par choix |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis et installation. |
| [Documents](doc/fr/documents.md) | `Document` et `Block`, la forme JSON, la lecture et l'écriture strictes, les blocs imbriqués. |
| [Types de blocs](doc/fr/block-types.md) | Chaque type, ses données, les formes qu'il lit aussi, et les réglages. |
| [Normalisation](doc/fr/normalization.md) | Ce que `Normalizer` vérifie et change, les registres, et l'ajout d'un type. |
| [HTML en ligne](doc/fr/inline-html.md) | L'assainisseur, ses profils et ses outils en ligne, et pourquoi il est propre à la bibliothèque. |
| [Rendu HTML](doc/fr/html-rendering.md) | Le HTML de chaque type, les réglages, le remplacement d'un moteur de rendu, et la purification. |
| [Texte brut](doc/fr/plain-text.md) | Le texte de chaque type, et le remplacement d'une conversion. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application peut remplacer ou ajouter, et quand c'est stable. |
| [Exceptions](doc/fr/exceptions.md) | `UjoException` et ses quatre exceptions, avec leurs messages. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque et son seuil de mutation, et comment tester du code qui stocke des documents. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/ujo
```

```php
use Phil\Ujo\Ujo;

$ujo = new Ujo();

$document = $ujo->decode($json);          // strict, checked, canonical — or an exception naming the path
$stored = $ujo->encode($document);        // the canonical JSON to keep

echo $ujo->toHtml($stored);               // <h2 id="…" class="ujo-header">…</h2>…
echo $ujo->toText($stored);               // for a search index or an excerpt
```

## Prérequis

PHP 8.5 ou plus récent, avec `ext-json` et `ext-mbstring`. Rien d'autre.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
