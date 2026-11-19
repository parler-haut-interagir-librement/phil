<!-- phil-dev source-sha256=f8e226c9fa7b19522f69417f494c1a55998f2057fd08a057c474f87ce51c71a6 -->
# phil/avatar

**Des avatars générés en SVG à partir d'un germe opaque : initiales, formes géométriques et grilles de pixels, accessibles et sûrs.**

phil/avatar dessine un avatar pour chaque membre qui n'a pas choisi d'image, à partir d'un germe : un identifiant
opaque et stable de l'application, comme l'UUID du membre. Le même germe dessine toujours le même avatar, sur toutes
les machines, sans rien stocker et sans appeler de service. Le germe n'est jamais une adresse courriel ni son
empreinte — l'empreinte d'une adresse se retrouve avec un dictionnaire d'adresses —, et la bibliothèque refuse un germe
qui contient une `@`.

Trois styles sont fournis : les **initiales** du membre sur un fond coloré, des **formes géométriques** — cercles,
carrés et triangles — placées, dimensionnées et tournées par le germe, et une **grille de pixels** en miroir, à la
manière d'un identicon. Les initiales sont prises par groupes de graphèmes, de sorte que les accents, les syllabes
hangul et les signes combinants restent entiers ; un nom en han, hiragana, katakana ou hangul donne son premier
caractère, et les initiales arabes et hébraïques sont disposées de droite à gauche. Leur encre atteint toujours le
contraste WCAG AA de 4,5:1 avec leur fond, ce que vérifie chaque palette.

Un avatar est un document SVG, avec un `<title>`, `role="img"` et `aria-label` quand il a un nom, et caché des
technologies d'assistance quand il est décoratif. Chaque élément est écrit par la bibliothèque à partir de nombres, de
couleurs et de texte échappé : aucune chaîne de l'appelant n'atteint le balisage comme balisage. Une application ajoute
ses propres styles par un port, en dessinant sur la même toile.

C'est une bibliothèque PHP simple, sans autre dépendance que `mbstring`. Son générateur ne garde aucun état entre les
appels et s'emploie sans risque dans un *worker* de longue durée.

## État

| Partie | État |
|---|---|
| Avatars en documents SVG et en URI `data:`, à partir d'un germe opaque, en trois contours et en tailles de 16 à 1024 pixels | Implémenté et testé |
| Le style des initiales, par groupe de graphèmes, pour les noms latins, grecs, cyrilliques, indiens, CJC, arabes et hébraïques | Implémenté, testé et soumis au fuzzing |
| Les styles des formes et des pixels | Implémenté et testé |
| Cinq palettes, les palettes de l'application et le contraste WCAG AA des initiales | Implémenté et testé |
| Les styles de l'application, par le port `AvatarStyle` | Implémenté et testé |
| Styles illustrés, importés comme données sous CC0 | Pas commencé : prévu pour la version 2 |
| Rastérisation en PNG ou en WebP | Hors de la bibliothèque : le navigateur dessine le SVG, et une application qui a besoin d'une image matricielle le convertit |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis et l'installation. |
| [Avatars](doc/fr/avatars.md) | Dessiner un avatar, ses options, son contour et son affichage dans une page. |
| [Initiales](doc/fr/initials.md) | Comment les initiales sont prises dans des noms de toutes les écritures. |
| [Palettes et contraste](doc/fr/palettes.md) | Les palettes, leurs encres et le contraste des initiales. |
| [Sécurité et vie privée](doc/fr/security.md) | Le germe, le balisage sûr par construction, les bornes de chaque entrée. |
| [Points d'extension](doc/fr/extension-points.md) | Les styles et les palettes de l'application, et quand ils sont stables. |
| [Tests](doc/fr/testing.md) | Les tests du paquet, et comment tester du code qui dessine des avatars. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/avatar
```

```php
use Phil\Avatar\AvatarGenerator;
use Phil\Avatar\AvatarOptions;

$avatars = new AvatarGenerator();

echo $avatars->svg($member->uuid, new AvatarOptions(name: 'Ada Lovelace', size: 48));
// <svg … role="img" aria-label="Ada Lovelace"><title>Ada Lovelace</title>… AL …</svg>

$src = $avatars->dataUri($member->uuid, new AvatarOptions(style: 'shapes'));
```

## Prérequis

PHP 8.5 ou une version plus récente, avec `mbstring`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
