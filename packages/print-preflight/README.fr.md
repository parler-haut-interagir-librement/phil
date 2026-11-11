<!-- phil-dev source-sha256=48e15b0a2149d6ffd2037d240edf993382e1c071ad894be7505e2e91136e5d57 -->
# phil/print-preflight

**Ce qu'un imprimeur refuserait, avant que le fichier lui parvienne.**

phil/print-preflight vérifie un document selon les règles d'un imprimeur et signale ce qu'il trouve. Il repère les
images dont la résolution est trop basse pour leur taille placée, les polices non intégrées ou dont les permissions
d'intégration l'interdisent, le contenu qui franchit le bord de coupe ou se trouve dans la marge de sécurité, les fonds
censés déborder qui s'arrêtent avant le bord du fond perdu, les pages d'un format que l'imprimeur n'imprime pas ou avec
trop peu de fond perdu, les nombres de pages sous le minimum, au-dessus du maximum ou hors du multiple qu'exige une
reliure, les couleurs RVB ou en tons directs dans un flux CMJN, les images et les aplats au-dessus de la couverture
d'encre totale que la presse accepte, le blanc en surimpression, les filets trop fins pour être imprimés, et la
transparence là où l'imprimeur veut un fichier aplati. Chaque constat est une erreur bloquante, un avertissement ou une
remarque, avec un code, une phrase et les valeurs qu'elle cite.

Il sert à toute application PHP qui envoie des fichiers à l'impression : un livre ou un zine fait dans le navigateur,
un outil de conception de cartes ou d'affiches, une boutique d'impression à la demande, une chaîne qui rend du HTML et
du CSS en PDF. La vérification se fait avant que le fichier soit écrit ou envoyé, sur une description de ce que le
document place et où. Un éditeur peut ainsi montrer les problèmes page par page pendant que le document est encore
ouvert.

C'est une simple bibliothèque PHP qui a besoin de `phil/fonts` pour lire les permissions
d'intégration, et de rien d'autre. Elle n'ouvre pas de fichiers PDF : l'application décrit son document avec un petit
modèle — des pages avec leur format rogné et leur fond perdu, des images placées avec leur taille en pixels et leur
taille placée, d'autres boîtes, des polices. Elle le remplit à partir de ce qu'elle a placé avec `phil/paged-media`, ou
de ce qu'un navigateur a mesuré dans un rendu HTML. Ce qu'elle ne sait pas — la couverture d'encre d'une image,
l'épaisseur d'un filet —, elle l'omet, et la vérification qui en a besoin reste muette. Rien n'est installé avec elle.

La conception est simple : un `Document` contient des valeurs `Page`, chacune avec ses éléments `PlacedImage` et `Box`,
et la liste des `UsedFont`. Une `PrintProfile` contient les règles de l'imprimeur. `Preflight` exécute ses
vérifications, chacune un `Check`, et renvoie un `Report` de valeurs `Finding`. Une application ajoute ses propres
règles en ajoutant un `Check`, et change la gravité de toute règle, ou l'ignore, par le profil.

## État

| Partie | État |
|---|---|
| Le modèle : longueurs en points, millimètres, pouces et pixels CSS ; formats ; rectangles à partir de coordonnées CSS ou PDF | Implémenté et testé |
| Nombre de pages par rapport à un minimum, un maximum et un multiple, avec le nombre de pages blanches à ajouter | Implémenté et testé |
| Formats rognés par rapport à la liste de l'imprimeur et à la première page ; fond perdu par rapport à celui de l'imprimeur | Implémenté et testé |
| Polices : non intégrées, restreintes, en bitmap seulement, sous-ensemble malgré une licence qui l'interdit, lues par `phil/fonts` | Implémenté et testé |
| Résolution effective des images à leur taille placée ou mise à l'échelle, par rapport à un minimum, une cible et un maximum | Implémenté et testé |
| Espaces colorimétriques par rapport au flux : RVB dans un flux CMJN, CMJN dans un flux RVB, tons directs | Implémenté et testé |
| Placement : hors du format rogné, à cheval sur le bord de coupe, dans la marge de sécurité, avant le bord du fond perdu | Implémenté et testé |
| Couverture d'encre totale des images placées et des aplats par rapport à un maximum, dans un flux CMJN | Implémenté et testé |
| Surimpression : blanc en surimpression, surimpression là où l'imprimeur l'ignore | Implémenté et testé |
| Filets trop fins : traits plus fins que l'épaisseur minimale de l'imprimeur | Implémenté et testé |
| Transparence là où l'imprimeur ne l'accepte pas | Implémenté et testé |
| Lecture d'un fichier PDF pour remplir le modèle | Non prévu : l'application décrit ce qu'elle a placé |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis et installation. |
| [Le modèle de document](doc/fr/model.md) | `Document`, `Page`, `PlacedImage`, `Box`, `UsedFont`, unités et coordonnées, encre, surimpression, filets et transparence ; le remplir à partir de `phil/paged-media` ou d'un rendu HTML. |
| [Profils d'imprimeur](doc/fr/profiles.md) | `PrintProfile` : chaque règle, sa valeur par défaut, les gravités et les règles ignorées. |
| [Vérifications et constats](doc/fr/checks.md) | Ce que signale chaque vérification intégrée, les codes et leurs gravités, le rapport, et l'ajout d'une vérification. |
| [Exceptions](doc/fr/exceptions.md) | `PrintPreflightException` et ses deux exceptions. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports, arguments et données qu'une application choisit ou remplace. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque et son seuil de mutation, et comment tester du code qui exécute une vérification. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/print-preflight
```

```php
use Phil\Fonts\FontFile;
use Phil\PrintPreflight\Box;
use Phil\PrintPreflight\Document;
use Phil\PrintPreflight\Length;
use Phil\PrintPreflight\Page;
use Phil\PrintPreflight\PlacedImage;
use Phil\PrintPreflight\Preflight;
use Phil\PrintPreflight\PrintProfile;
use Phil\PrintPreflight\Rectangle;
use Phil\PrintPreflight\Size;
use Phil\PrintPreflight\UsedFont;

$page = new Page(Size::a5(), Length::mm(3),
    images: [new PlacedImage('cover', Rectangle::mm(-3, -3, 154, 216), 1200, 1700, bleeds: true)],
    boxes: [new Box('title', Rectangle::mm(4, 20, 138, 30))],
);
$font = UsedFont::fromFont(FontFile::fromFile('/path/to/ExampleSans.ttf')->face(), subset: true);

$profile = new PrintProfile(trimSizes: [Size::a5()], pageMultiple: 4);
$report = new Preflight($profile)->check(new Document([$page], [$font]));

foreach ($report->findings as $finding) {
    echo "{$finding->severity->value}: {$finding->message}\n";
}
// error: The document has 1 page, not a multiple of 4; add 3 blank pages or remove 1.
// warning: The image "cover" on page 1 prints at 197 ppi, below the 300 ppi target.
// notice: "cover" on page 1 is in RGB; the printer converts it to CMYK, and bright colours may dull.
// warning: "title" on page 1 is inside the 5 mm safety margin at the left edge.

$report->passes(); // false: one error
```

## Prérequis

PHP 8.5 ou plus récent et `phil/fonts`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
