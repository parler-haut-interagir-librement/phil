<!-- phil-dev source-sha256=7c8fed1f96b65273f4941128010f63cbf7509e528f179966ffd089b68baa2633 -->
# Installation

## Prérequis

| Prérequis | Pourquoi |
|---|---|
| PHP 8.5 ou plus récent | Classes en lecture seule, constantes typées, `array_any()`, `new` sans parenthèses |
| `ext-gd` avec la prise en charge de FreeType, de PNG et de WebP | Chaque image est décodée, dessinée et encodée par GD ; les textes sont dessinés par FreeType au travers de GD |
| `ext-mbstring` | Les caractères d'un texte, vérifiés par rapport à la table des caractères de sa police |
| `phil/fonts` | Le format, les noms et la table des caractères d'un fichier de police |

`php -r 'print_r(gd_info());'` montre ce que prend en charge une construction de GD : `FreeType Support`,
`PNG Support` et `WebP Support` doivent être vrais. Les images Docker officielles de PHP construisent GD avec eux quand
`docker-php-ext-configure gd --with-freetype --with-webp` s'exécute avant `docker-php-ext-install gd`. Un appel qui a
besoin d'une partie absente lève `MissingCapability`.

## Installation

```sh
composer require phil/print-compositor
```

C'est une bibliothèque, pas un bundle : il n'y a rien à enregistrer. `PrintCompositor`, `TextFitter` et
`MockupCompositor` ne gardent aucun état entre deux appels : une seule instance de chacun peut servir chaque tâche.

## Pourquoi GD plutôt qu'Imagick

Les deux extensions peuvent faire le travail ; GD est celle sur laquelle la bibliothèque est écrite, pour ces raisons :

- **Elle est fournie avec PHP.** GD est une extension intégrée, activée par chaque distribution et par les images
  Docker officielles, et elle suit chaque version de PHP le jour de sa publication. Imagick est une extension PECL
  au-dessus d'une installation distincte d'ImageMagick, et elle a pris du retard sur les nouvelles versions de PHP ; la
  bibliothèque est testée sur la version suivante de PHP dès sa première version candidate.
- **Elle a ce dont un fichier d'impression et une maquette ont besoin, et rien de plus.** Couleurs vraies avec canal
  alpha, rééchantillonnage, texte FreeType, PNG avec sa résolution écrite dans le fichier, et WebP. Une imprimante à
  transfert ou DTG accepte des fichiers PNG sRGB : la gestion des couleurs et la sortie CMJN d'ImageMagick n'apportent
  donc rien à ces fichiers.
- **Sa surface est petite.** ImageMagick décode des dizaines de formats par des délégués et a besoin d'une politique de
  sécurité pour tenir les fichiers non fiables à l'écart de ceux-ci ; GD ne décode que les formats Web courants. La
  bibliothèque vérifie aussi la taille qu'annonce une image avant de la décoder.
- **Ce qui manque à GD est écrit dans la bibliothèque.** GD n'a aucun mode de fusion qui garde l'ombrage du tissu sur
  un vêtement foncé : l'ombrage des maquettes est donc calculé pixel par pixel ([Maquettes](mockups.md)). La boîte
  englobante de FreeType peut s'écarter de quelques pixels de l'encre : un texte est donc mesuré sur les pixels qu'il
  dessine ([Textes](texts.md)).

Le prix de ce choix est la vitesse sur les très grandes images : l'ombrage d'une maquette s'exécute en PHP sur les
pixels de chaque zone, ce qui est assez rapide pour des photos de quelques mégapixels, la taille qu'affiche un site.
