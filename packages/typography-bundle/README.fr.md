<!-- phil-dev source-sha256=85f2718a2e70ae9aaa064f5755d1c2e4d50cb405a6673d8d92a1f0678e8d9109 -->
# phil/typography-bundle

**Des guillemets typographiques, des tirets, des espaces françaises et des césures conditionnelles partout où une
application Symfony affiche du texte.**

phil/typography-bundle installe `phil/typography` dans une application Symfony. Il construit un typographe par locale
à partir de la configuration (le préréglage de la locale, ou les correcteurs que l'application énumère). Il enregistre
chaque locale configurée comme un service distinct et ajoute les filtres Twig `typography` et `typography_text`. Quand
`phil/ujo-bundle` est installé, il applique aussi le typographe au HTML de chaque document UJO (Universal JSON Output)
que l'application produit, chacun dans la locale de la requête, sauf si un gabarit en nomme une autre.

Il s'adresse à toute application Symfony qui affiche du texte écrit par des personnes : un CMS, un blogue, un forum,
une boutique. Un gabarit écrit `{{ article.body|typography }}` ; les documents UJO rendus par `ujo_html` ou
`Ujo::toHtml()` sortent avec leur typographie corrigée, sans rien à écrire.

C'est un bundle mince par-dessus `symfony/config` et `symfony/dependency-injection`. Twig et
`phil/ujo-bundle` sont facultatifs : les filtres ne sont enregistrés que si Twig est installé, et l'intégration UJO
que si le moteur de rendu de ce bundle est dans le conteneur.

La conception est réduite : `PhilTypographyBundle` lit la configuration, `TypographyServices` enregistre les services,
`TypographyExtension` contient les filtres, `TypographyHtmlFilter` et `UjoFilterPass` décorent le moteur de rendu UJO.

## État

| Partie | État |
|---|---|
| Configuration : locale par défaut, césure et ses langues expérimentales, éléments protégés, correcteurs par locale | Implémenté et testé |
| `Typographers`, `Typographer` et un service par locale configurée, injectable par nom d'argument | Implémenté et testé |
| Twig : `typography` pour le HTML, qui échappe ce qui n'est pas marqué sûr ; `typography_text` pour le texte brut | Implémenté et testé |
| La locale de la requête, par le `LocaleSwitcher` de Symfony, pour les filtres et la passe UJO | Implémenté et testé |
| La typographie sur le HTML des documents UJO, par le moteur de rendu de `phil/ujo-bundle` | Implémenté et testé |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis, installation et activation du bundle. |
| [Configuration](doc/fr/configuration.md) | Chaque option, et les services qu'elle donne. |
| [Twig](doc/fr/twig.md) | Les deux filtres, l'échappement et la locale d'un gabarit. |
| [Documents UJO](doc/fr/ujo.md) | La passe typographique sur le HTML des documents UJO. |
| [Exceptions](doc/fr/exceptions.md) | Ce que lancent le bundle et la bibliothèque. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application implémente, configure, remplace ou utilise. |
| [Tests](doc/fr/testing.md) | Les tests du bundle, et comment tester une application qui l'utilise. |
| [Référence](doc/fr/reference.md) | Chaque classe et chaque service du bundle. |

## Démarrage rapide

```sh
composer require phil/typography-bundle
```

```yaml
# config/packages/phil_typography.yaml
phil_typography:
    default_locale: fr_CA
    locales:
        en_GB: []            # a service with the preset of en_GB
```

```twig
<h1>{{ article.title|typography_text }}</h1>
{{ article.bodyHtml|raw|typography }}
<aside lang="en-GB">{{ article.quote|typography('en_GB') }}</aside>
```

## Prérequis

PHP 8.5 ou plus récent, Symfony 8.2 et `phil/typography`. Twig 3.21, pour les filtres ; `symfony/translation`, pour
la locale de la requête ; `phil/ujo-bundle`, pour l'intégration UJO.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
