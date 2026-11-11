<!-- phil-dev source-sha256=26c379a326cbd43b7620fa73467189e57f0e46f425ff42120480311f3bdbdd7f -->
# phil/svg-chart

**Des graphiques dessinés en SVG sur le serveur, à partir de la configuration qu'écrit déjà un éditeur de graphiques.**

phil/svg-chart lit la forme d'une configuration Chart.js — la sorte de graphique, ses libellés, ses séries et leurs
couleurs, son titre — et la dessine dans une image SVG : des barres groupées ou des lignes sur un axe de valeurs aux
graduations rondes, ou les pointes d'un graphique circulaire ou en anneau, avec un titre au-dessus et une légende
au-dessous. Chaque marque porte son libellé et sa valeur pour le pointeur, et l'image porte une description qui lit
chaque valeur pour les lecteurs d'écran.

Il s'adresse à tout code PHP qui doit afficher un graphique sans script : un bloc de document dessiné une seule fois à
son enregistrement, un PDF, un courriel, une page lue sans JavaScript. La configuration est vérifiée par rapport à des
limites : celle qu'envoie un client ne coûte donc pas plus qu'une grande configuration honnête.

C'est une simple bibliothèque PHP, écrite de zéro, au-dessus de `ext-mbstring`. Sa sortie est déterministe.

## État

| Partie | État |
|---|---|
| `Chart::fromConfig()` : type, libellés, séries, couleurs et titre d'une configuration Chart.js, avec des limites | Implémenté et testé |
| Barres groupées par libellé, valeurs négatives sous la ligne du zéro | Implémenté et testé |
| Lignes passant par les valeurs, interrompues là où une valeur manque | Implémenté et testé |
| Graphiques circulaires et en anneau, à partir de la première série | Implémenté et testé |
| Graduations rondes, libellés éclaircis quand ils se chevauchent, légende en rangées, couleurs Okabe-Ito | Implémenté et testé |
| `<title>` sur chaque marque, `<desc>` de chaque valeur, `role="img"` | Implémenté et testé |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis et l'installation. |
| [Configuration](doc/fr/configuration.md) | Ce que lit `Chart::fromConfig()`, ce qu'il ignore, et ses limites. |
| [Dessin](doc/fr/drawing.md) | L'image que dessine `SvgChartWriter`, ses options et son accessibilité. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application peut remplacer ou ajouter, et quand c'est stable. |
| [Tests](doc/fr/testing.md) | Les tests du paquet et son seuil de mutation. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/svg-chart
```

```php
use Phil\SvgChart\Chart;
use Phil\SvgChart\SvgChartWriter;

$svg = new SvgChartWriter(640, 400)->write(Chart::fromConfig([
    'type' => 'bar',
    'data' => ['labels' => ['Jan', 'Feb'], 'datasets' => [['label' => 'Sales', 'data' => [12, 19]]]],
    'options' => ['plugins' => ['title' => ['text' => 'Sales']]],
]));
```

## Prérequis

PHP 8.5 ou plus récent, avec `ext-mbstring`.

## Contribuer

Les contributions sont les bienvenues dans le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
