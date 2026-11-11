<!-- phil-dev source-sha256=15ba7bb556ea713928f00cd4b68cb450429587fae418e33e9e1e06d66d32e1a4 -->
# phil/highlight-bundle

**Du code coloré sur le serveur dans une application Symfony : dans les documents UJO, dans les gabarits, avec un thème
prêt à lier.**

phil/highlight-bundle intègre `phil/highlight` à une application Symfony. Il enregistre le `Highlighter` comme service,
avec les langages que l'application écrit elle-même (tout service qui implémente `Language`, configuré automatiquement)
et les alias de sa configuration. Il colore les blocs de code de chaque document UJO dont `phil/ujo-bundle` produit le
rendu, ajoute le filtre Twig `highlight` et déclare dans AssetMapper sa propre feuille de style de thème.

Il s'adresse à toute application Symfony qui affiche du code : une documentation, un blogue, un forum, un wiki. Un bloc
de code écrit dans un éditeur par blocs ou en Markdown ressort coloré sans rien écrire, et un gabarit colore un extrait
avec `{{ snippet|highlight('php') }}`. La page n'a besoin d'aucun script ; le contrôleur de coloration de
`phil/ux-bundle` colore quand même, dans le navigateur, les blocs dont le serveur ne connaît pas le langage.

C'est un bundle mince, écrit de zéro, sur `symfony/config` et `symfony/dependency-injection`. Twig, `phil/ujo-bundle` et
AssetMapper sont facultatifs : le filtre n'est enregistré que si Twig est installé, les blocs de code ne sont colorés
que si le moteur de rendu de ce bundle se trouve dans le conteneur, et la feuille de style n'est déclarée que si
AssetMapper est installé.

La conception est simple. `PhilHighlightBundle` lit la configuration et déclare la feuille de style, `HighlightServices`
enregistre les services, `HighlighterPass` ajoute les langages et les alias et décore le moteur de rendu UJO,
`AliasedLanguage` donne à un langage ses alias configurés, et `HighlightExtension` contient le filtre.

## État

| Partie | État |
|---|---|
| `Highlighter` comme service : les langages fournis, ceux de l'application par configuration automatique, les alias configurés | Implémenté et testé |
| Les blocs de code des documents UJO colorés, par le moteur de rendu de `phil/ujo-bundle` ; désactivable | Implémenté et testé |
| Twig : `highlight`, le code échappé et coloré, ou seulement échappé pour un langage inconnu | Implémenté et testé |
| La feuille de style du thème, claire et sombre, déclarée sous le nom `phil-highlight/highlight.css` | Implémenté |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation et l'activation du bundle. |
| [Configuration](doc/fr/configuration.md) | Chaque option et les services qu'elle fournit. |
| [Langages](doc/fr/languages.md) | Les langages propres à l'application, et d'autres noms pour n'importe quel langage. |
| [Documents UJO](doc/fr/ujo.md) | Les blocs de code des documents UJO colorés sur le serveur. |
| [Twig](doc/fr/twig.md) | Le filtre `highlight` et le HTML qui l'entoure. |
| [Thème](doc/fr/theme.md) | La feuille de style, ses couleurs et leur modification. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application implémente, remplace ou utilise. |
| [Exceptions](doc/fr/exceptions.md) | Ce que lancent le bundle et la bibliothèque. |
| [Tests](doc/fr/testing.md) | Les tests du bundle et les tests d'une application qui l'utilise. |
| [Référence](doc/fr/reference.md) | Chaque classe et chaque service du bundle. |

## Démarrage rapide

```sh
composer require phil/highlight-bundle
```

```yaml
# config/packages/phil_highlight.yaml
phil_highlight:
    aliases:
        vue: html
```

```twig
<link rel="stylesheet" href="{{ asset('phil-highlight/highlight.css') }}">

<pre><code class="language-php">{{ snippet|highlight('php') }}</code></pre>
{{ article.body|ujo_html }}  {# its code blocks coloured #}
```

## Prérequis

PHP 8.5 ou plus récent, Symfony 8.2 et `phil/highlight`. Twig 3.21, pour le filtre ; `phil/ujo-bundle`, pour les blocs
de code des documents UJO ; `symfony/asset-mapper`, pour la feuille de style.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la [politique de
sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
