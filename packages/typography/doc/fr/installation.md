<!-- phil-dev source-sha256=7aa2d202dbfdbc3d489c87bacb9f29ba979f1025d23dc0ea4e9f4d855a5bcf3b -->
# Installation

## Prérequis

| Prérequis | Pourquoi |
|---|---|
| PHP 8.5 ou plus récent | `Dom\HTMLDocument`, l'analyseur HTML5 de PHP, les classes en lecture seule, les constantes typées |
| `ext-dom` | Le HTML est analysé et réécrit en HTML5 |
| `ext-intl` | `UnicodeNormalization` utilise `Normalizer`, et `WordBreak` le dictionnaire thaï d'`IntlBreakIterator` |
| `ext-libxml` | Son indicateur garde l'analyseur silencieux sur les documents entiers |
| `ext-mbstring` | Le texte est lu caractère par caractère, et les mots sont mis en minuscules pour la césure |

La bibliothèque n'a aucune autre dépendance. Ses motifs de césure sont des fichiers texte sous `data/hyphenation/`,
lus à la première utilisation et gardés par le `Dictionary` qui les a lus.

## Installer

```sh
composer require phil/typography
```

C'est une bibliothèque, pas un bundle : il n'y a rien à enregistrer. Une application Symfony installe
`phil/typography-bundle` pour la configuration, les filtres Twig et l'intégration UJO, ou construit un `Typographer`
là où elle en a besoin.
