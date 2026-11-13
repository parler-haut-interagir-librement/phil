<!-- phil-dev source-sha256=3fc9b73dc0c9751e90e6e8b55e82324875f88a239a91564b6f79d9f6a1f66695 -->
# phil/latex-mathml

**Des formules LaTeX changées côté serveur en MathML que les navigateurs dessinent, sans rien à installer.**

phil/latex-mathml convertit une formule écrite en LaTeX en MathML Core : identifiants, nombres et opérateurs, indices,
exposants et limites, fractions et coefficients binomiaux, racines, accents, polices, délimiteurs étirés, matrices,
cas et équations alignées, macros simples, couleurs, liens, boîtes, numéros d'équation, filets dans les tableaux et
notation chimique. La formule est gardée à côté du MathML sous forme d'annotation TeX, pour qu'un lecteur puisse la
copier. Une commande hors de l'ensemble pris en charge est refusée avec sa position, jamais transmise telle quelle, et
la longueur, la profondeur et l'expansion d'une formule sont bornées.

Il s'adresse à tout code PHP qui affiche des formules écrites par des personnes : un bloc de document converti une fois
à l'enregistrement, un EPUB, une page qui doit se lire sans script. Les navigateurs dessinent nativement MathML Core
depuis 2023.

C'est une simple bibliothèque PHP sur `ext-mbstring` et `phil/url`. Ce n'est pas TeX : elle lit les commandes
courantes des formules écrites à la main et les macros qui les représentent, pas les paquets ni la mise en page du
texte.

## État

| Partie | État |
|---|---|
| Identifiants, nombres, opérateurs, lettres grecques et symboles courants | Implémentés et testés |
| Indices et exposants, primes, grands opérateurs et leurs limites, fonctions | Implémentés et testés |
| `\frac`, `\binom`, `\sqrt`, accents, `\overset`, accolades au-dessus et au-dessous | Implémentés et testés |
| `\mathbf` et ses semblables en lettres mathématiques Unicode, `\text` | Implémentés et testés |
| `\left` … `\middle` … `\right`, `\big` et ses semblables | Implémentés et testés |
| Matrices, `cases`, `aligned`, `array`, ses filets et `\hline` | Implémentés et testés |
| `\newcommand`, `\renewcommand` et `\def`, avec arguments, dans un budget d'expansion | Implémentés et testés |
| Couleurs, liens par `phil/url`, `\boxed`, tailles et styles d'un groupe | Implémentés et testés |
| `\tag` et `\tag*`, pour une formule et pour les lignes d'un environnement | Implémentés et testés |
| Notation chimique, `\ce{…}` : formules, charges, états, isotopes, liaisons, flèches et leurs conditions | Implémentés et testés |
| Limites de longueur, de profondeur, de cellules et d'expansion ; chaque refus avec sa position en octets ; fuzzing | Implémentés et testés |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis et installation. |
| [Conversion](doc/fr/converting.md) | `LatexToMathMl`, sa sortie, ses limites et ses refus. |
| [Commandes](doc/fr/commands.md) | Chaque commande et environnement lus, et ce qui ne l'est pas. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application peut remplacer ou ajouter, et à partir de quand c'est stable. |
| [Tests](doc/fr/testing.md) | Les tests du paquet, sa cible de fuzzing et son seuil de mutation. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/latex-mathml
```

```php
use Phil\LatexMathMl\LatexToMathMl;

$mathml = new LatexToMathMl()->convert('\sum_{i=1}^{n} i = \frac{n(n+1)}{2}');
// <math xmlns="http://www.w3.org/1998/Math/MathML" display="block"><semantics><mrow><munderover>…
```

## Prérequis

PHP 8.5 ou plus récent, avec `ext-ctype`, `ext-mbstring` et `ext-intl`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
