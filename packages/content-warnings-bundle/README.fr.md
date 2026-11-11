<!-- phil-dev source-sha256=a6d3cceb2ea360ed916a97b1f6c2d3d9d8d77fa9cf246ec7e028e70a6e864ad9 -->
# phil/content-warnings-bundle

**Des avertissements de contenu dans les applications Symfony : un catalogue tiré de la configuration, des
avertissements sur n'importe quelle entité, et du contenu replié ou masqué selon le choix de chaque lecteur.**

phil/content-warnings-bundle amène `phil/content-warnings` dans Symfony. Le catalogue des thèmes d'avertissement est
construit à partir de la liste de base de la bibliothèque, en six langues, complétée ou remplacée par la configuration
et les fichiers de définitions de l'application ; une définition qui enfreint une règle arrête la construction du
conteneur. Le type de colonne `phil_content_warnings` conserve les avertissements de n'importe quelle entité — un jeu,
un livre, un article, un événement — dans une seule colonne JSON. `ContentWarningsType` les choisit parmi les thèmes,
regroupés par catégorie, et `WarningPreferencesType` permet à un lecteur de choisir un affichage par catégorie et par
thème. Le gabarit `@PhilContentWarnings/fold.html.twig` affiche un contenu avec ses avertissements, le replie derrière
eux dans un élément details accessible que pilote le contrôleur Stimulus `reveal`, ou le laisse de côté. Les libellés et
les messages sont en anglais, en français, en espagnol, en allemand, en italien et en portugais.

Il s'adresse aux applications Symfony qui publient du contenu que certains lecteurs préfèrent aborder avec précaution,
et qui laissent ces lecteurs choisir. Les préférences sont lues par le port `WarningPreferencesStore` : le bundle les
conserve dans la session par défaut, et une application qui a des réglages de membre implémente le port par-dessus
ceux-ci.

Hors de Symfony, ou pour le catalogue, les avertissements et les décisions seuls, utilisez `phil/content-warnings`. Le
bundle est une mince couche au-dessus : une configuration, un type de colonne, des champs de formulaire, des fonctions
Twig, un gabarit et un magasin.

## État

| Partie | État |
|---|---|
| Le catalogue tiré de la configuration et des fichiers de définitions | Implémenté et testé |
| Le type de colonne `phil_content_warnings` | Implémenté et testé |
| `ContentWarningsType`, son thème de formulaire, et `WarningPreferencesType` | Implémenté et testé |
| Les fonctions Twig et `@PhilContentWarnings/fold.html.twig` | Implémenté et testé |
| Le contrôleur Stimulus `reveal` et sa feuille de style | Implémenté et testé |
| `WarningPreferencesStore`, avec les magasins en session et en mémoire | Implémenté et testé |
| Les libellés et les messages en six langues | Implémenté et testé |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis et l'enregistrement. |
| [Configuration](doc/fr/configuration.md) | La liste de base, les fichiers de définitions, l'affichage par défaut, les locales et le magasin. |
| [Doctrine](doc/fr/doctrine.md) | Le type de colonne `phil_content_warnings`. |
| [Formulaires](doc/fr/forms.md) | `ContentWarningsType`, son thème, et `WarningPreferencesType`. |
| [Affichage](doc/fr/display.md) | Le gabarit, les fonctions Twig, le contrôleur Stimulus, la feuille de style et l'accessibilité. |
| [Préférences](doc/fr/preferences.md) | Le port `WarningPreferencesStore` et les magasins fournis. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application configure, implémente, remplace ou utilise. |
| [Tests](doc/fr/testing.md) | La façon dont le bundle est testé. |
| [Référence](doc/fr/reference.md) | Chaque type du bundle. |

## Démarrage rapide

```sh
composer require phil/content-warnings-bundle
```

```php
#[ORM\Column(type: 'phil_content_warnings', nullable: true)]
private ?ContentWarnings $warnings = null;

$builder->add('warnings', ContentWarningsType::class);
```

```twig
{% embed '@PhilContentWarnings/fold.html.twig' with {warnings: book.warnings} %}
    {% block content %}{{ book.excerpt }}{% endblock %}
{% endembed %}
```

## Prérequis

PHP 8.5 ou plus récent avec `ext-intl` ; `phil/content-warnings` 0.1 ; `symfony/config`, `symfony/dependency-injection`,
`symfony/form` et `symfony/options-resolver` 8.2 ou plus récent, `symfony/service-contracts` 3.6 ou plus récent.
Doctrine DBAL, HttpFoundation et Twig sont facultatifs.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la [politique de
sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
