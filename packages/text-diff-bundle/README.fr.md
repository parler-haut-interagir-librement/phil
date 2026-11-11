<!-- phil-dev source-sha256=2d5bb58487e885b589d875f2f75c5a07999617eb45df4605b564838ebe3ec8c2 -->
# phil/text-diff-bundle

**Ce qui a changé entre deux versions, affiché dans un gabarit et parcouru changement par changement.**

phil/text-diff-bundle intègre `phil/text-diff` à une application Symfony. Il enregistre le comparateur avec la borne de
modifications que fixe la configuration, et deux filtres Twig : `text_diff` compare deux textes par mots, lignes,
paragraphes ou HTML et écrit la différence avec `<ins>` et `<del>` ; `text_diff_document` compare deux documents UJO
bloc par bloc et produit le rendu de chaque bloc avec le moteur de rendu UJO de l'application quand `phil/ujo-bundle`
est installé. Le contrôleur Stimulus `comparison` fait passer une différence de la vue en ligne à l'ancien texte seul
ou au nouveau texte seul, passe d'un changement au suivant — un remplacement compte pour un changement — et annonce sa
position dans une région dynamique. Une feuille de style colore les marques, souligne les insertions et barre les
suppressions, pour que la couleur ne soit jamais le seul indice.

Il s'adresse à toute application Symfony qui conserve des versions de ce que les gens écrivent : l'historique des
révisions d'une page ou d'un article, la relecture d'une modification proposée, la vérification d'un document avant sa
nouvelle publication.

Il est écrit de zéro, sur les composants que Symfony fournit déjà : Config et DependencyInjection pour la
configuration, les extensions par attributs de Twig pour les filtres, AssetMapper et StimulusBundle pour le
contrôleur, et HtmlSanitizer, quand il est configuré, pour le HTML qu'un gabarit ne marque pas comme sûr.

## État

| Partie | État |
|---|---|
| Le service `TextDiff`, sa borne de modifications et son découpeur par défaut tirés de la configuration | Implémenté et testé |
| `text_diff` : texte brut échappé, HTML digne de confiance seulement s'il est marqué comme sûr, assaini ou échappé sinon | Implémenté et testé |
| `text_diff_document` : documents UJO bloc par bloc, rendus par le moteur de rendu de l'application | Implémenté et testé |
| Le contrôleur `comparison` : vues en ligne, ancienne et nouvelle ; changement suivant et précédent ; un compteur dynamique | Implémenté et testé |
| La feuille de style : clair et sombre, impression, vues et changement courant, associée pour AssetMapper | Implémenté et testé |
| Une vue côte à côte en deux colonnes | Pas commencé : les vues ancienne et nouvelle montrent un texte à la fois |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation, le contrôleur et la feuille de style. |
| [Configuration](doc/fr/configuration.md) | Chaque clé de `phil_text_diff`. |
| [Twig](doc/fr/twig.md) | `text_diff` et `text_diff_document`, et ce à quoi chacun fait confiance. |
| [JavaScript](doc/fr/javascript.md) | Le contrôleur `comparison` : cibles, valeurs, actions, accessibilité. |
| [Mise en forme](doc/fr/styling.md) | La feuille de style, ses propriétés personnalisées et les vues. |
| [Points d'extension](doc/fr/extension-points.md) | Les services UJO, l'assainisseur HTML, le comparateur, les libellés du contrôleur. |
| [Exceptions](doc/fr/exceptions.md) | Ce que lève le bundle, et quand. |
| [Tests](doc/fr/testing.md) | Les tests du bundle, et comment tester une page qui montre des différences. |
| [Référence](doc/fr/reference.md) | Chaque classe, service, constante et ressource du bundle. |

## Démarrage rapide

```sh
composer require phil/text-diff-bundle
```

```twig
<link rel="stylesheet" href="{{ asset('phil-text-diff/text-diff.css') }}">

<section data-controller="phil--text-diff-bundle--comparison">
    <div role="group" aria-label="View">
        <button type="button" data-action="phil--text-diff-bundle--comparison#show" data-phil--text-diff-bundle--comparison-mode-param="inline" data-phil--text-diff-bundle--comparison-target="mode">Both</button>
        <button type="button" data-action="phil--text-diff-bundle--comparison#show" data-phil--text-diff-bundle--comparison-mode-param="old" data-phil--text-diff-bundle--comparison-target="mode">Before</button>
        <button type="button" data-action="phil--text-diff-bundle--comparison#show" data-phil--text-diff-bundle--comparison-mode-param="new" data-phil--text-diff-bundle--comparison-target="mode">After</button>
    </div>
    <button type="button" data-action="phil--text-diff-bundle--comparison#previous">Previous change</button>
    <button type="button" data-action="phil--text-diff-bundle--comparison#next">Next change</button>
    <p role="status" data-phil--text-diff-bundle--comparison-target="counter"></p>

    <div data-phil--text-diff-bundle--comparison-target="output">
        {{ previous.body|text_diff_document(current.body) }}
    </div>
</section>
```

## Prérequis

PHP 8.5 ou plus récent, `phil/text-diff`, `phil/ujo`, et les composants Config et DependencyInjection de Symfony 8.2.
`twig/twig` pour les filtres, `symfony/stimulus-bundle` et `symfony/asset-mapper` pour le contrôleur et la feuille de
style, `phil/ujo-bundle` pour les types de blocs de l'application, `symfony/html-sanitizer` pour le HTML qu'un gabarit
ne marque pas comme sûr — chacun seulement s'il est utilisé.

## Contribuer

Les contributions sont les bienvenues dans le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
