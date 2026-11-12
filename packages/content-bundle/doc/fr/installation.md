<!-- phil-dev source-sha256=ece6281123febe7f1f2bd33aa15126c5b70d9472edf8add2b882f093a88deb94 -->
# Installation

## Prérequis

| Prérequis | Contrainte | Pourquoi |
|---|---|---|
| PHP | `>=8.5` | Le code utilise des fonctions de PHP 8.5 (`array_first()`, `array_last()`), `array_any()`, des constantes de classe typées et `new Foo()->bar()` sans parenthèses. |
| `phil/ddd-bundle` | `^0.1@dev` | Les identifiants de substitution des lignes et des routes étendent son `Identifier` (UUIDv7) et sont enregistrés par son `IdentifierType` ; chaque refus du domaine implémente son `DomainError`. |
| `doctrine/orm` | `^3.7` | Les deux classes de base et la route sont mappées par des attributs de l'ORM ; les adaptateurs Doctrine, l'écouteur de mappage et la transaction utilisent le gestionnaire d'entités. |
| `doctrine/dbal` | `^4.2` | Les types DBAL des locales, des étapes, des versions et des identifiants. |
| `symfony/workflow` | `^8.2` | Le workflow éditorial et le workflow de publication sont des `StateMachine` du composant. |
| `phil/locale-dimensions` | `^0.1@dev` | `Locale`, `Version`, `FallbackChain` et `DeclaredLocales` : la locale et la version d'une ligne, et les chaînes déclarées que parcourt une lecture. |
| `phil/markup` | `^0.1@dev` | `MarkupFormat`, les ports `MarkupCompiler` et `HtmlPurifier`, leurs adaptateurs par défaut, et les lecteurs qui convertissent un brouillon en UJO. |
| `phil/ujo` | `^0.1@dev` | L'encodeur JSON de l'UJO qu'enregistre un corps publié. |
| `symfony/routing`, `symfony/http-foundation`, `symfony/http-kernel` | `^8.2` | Le contrôleur, le générateur d'URL et la configuration des routes. |
| `twig/twig` | `^3.21` | Le contrôleur produit le rendu de gabarits ; l'extension et le runtime Twig. |
| `symfony/console` | `^8.2` | Les commandes `phil:content:*`. |
| `symfony/yaml` | `^8.2` | L'option `--file` des commandes lit du YAML. |
| `phil/slug` | `^0.1` | `Slug`, l'adresse d'une ligne localisée, partagée avec les autres bundles qui gardent des slugs. |
| `phil/url` | `^0.1` | `Url`, un lien vers un autre site, une URL canonique, et l'adresse que portent les événements de page. |
| `phil/publishing` | `^0.1@dev` | `PagePublished` et `PageRetired`, les événements que `ContentManager` répartit quand une page passe en ligne ou hors ligne. |
| `phil/sitemap` | `^0.1@dev` | `UrlProvider`, par lequel les contenus publiés donnent leurs pages au plan du site. |
| `symfony/string` | `^8.2` | La clé de ressource par défaut d'une racine est le nom court de sa classe en snake case. |
| `psr/clock` | `^1.0` | La date de publication ; le service `clock` du conteneur, que FrameworkBundle enregistre dès que `symfony/clock` est installé. |
| `symfony/event-dispatcher-contracts` | `^3.6` | Le workflow répartit ses événements par l'`EventDispatcherInterface` des contrats. |
| `symfony/dependency-injection`, `symfony/config` | `^8.2` | `PhilContentBundle` est un `AbstractBundle` avec un arbre de configuration. |

Aucune extension PHP au-delà d'une construction standard n'est déclarée. L'application a besoin du pilote PDO de sa
base de données (`pdo_mysql`, `pdo_pgsql` ou `pdo_sqlite`) ; le bundle est testé sur SQLite, MySQL 8 et PostgreSQL.

### Bundles qu'enregistre l'application

Le bundle exige les bibliothèques ci-dessus, pas les bundles du framework. Ce qu'il enregistre dépend des bundles
qu'a le noyau (lus dans `kernel.bundles`) :

| Le noyau a | Le bundle ajoute |
|---|---|
| rien d'autre | le pipeline, les adaptateurs de balisage, les locales déclarées, et les services de publication, de versions, de routage et de modification ; l'application fournit les cinq ports de persistance ([extension-points.md](extension-points.md)) |
| `DoctrineBundle` (`doctrine/doctrine-bundle`) | ses types DBAL et ses mappages ORM, ajoutés en tête de la configuration `doctrine` ; l'écouteur de mappage ; les adaptateurs Doctrine des cinq ports ; les commandes de console |
| `DoctrineBundle` et `TwigBundle` (`symfony/twig-bundle`) | le contrôleur, le générateur d'URL et les fonctions Twig |
| `SecurityBundle` (`symfony/security-bundle`), avec `raw_html_role` défini | `RawHtmlRole`, qui vérifie sur `security.authorization_checker` le rôle de confiance dont les titulaires écrivent du HTML brut ([configuration.md](configuration.md#raw_html_role)) |
| `DoctrineBundle`, `TwigBundle` et `SecurityBundle` avec la protection CSRF (`symfony/security-csrf`), avec `admin.enabled` | l'administration ([administration.md](administration.md)) ; `symfony/translation` pour ses libellés, `easycorp/easyadmin-bundle` pour son entrée de menu |
| `DoctrineBundle` et `PhilScheduledTransitionsBundle` (`phil/scheduled-transitions-bundle`) | la planification des publications et le résolveur de son gestionnaire de workflow ([publication.md](publication.md#publication-planifiée)) |
| `DoctrineBundle` et `PhilSearchBundle` (`phil/search-bundle`) | l'alimentation de recherche des contenus publiés ([search.md](search.md)) |

Le contrôleur et le runtime Twig utilisent les services `router` et `request_stack`, et les commandes s'exécutent dans
`bin/console` : en pratique, la couche de rendu a aussi besoin de FrameworkBundle. Quand le conteneur a un service
`event_dispatcher`, le workflow répartit ses événements par lui. Le publieur date les publications avec le service
`clock`, que FrameworkBundle enregistre dès que `symfony/clock` est installé.

## Installer

Le bundle est en `0.1.x-dev` (`dev-main` est un alias de `0.1.x-dev`) et n'est pas encore publié sur Packagist, pas
plus que `phil/ddd-bundle`. D'ici là, déclarez un dépôt qui contient les deux paquets : un dépôt `path` vers une copie
de travail locale, ou un dépôt `vcs` par paquet :

```json
{
    "repositories": [
        { "type": "path", "url": "../phil-bundles/packages/*" }
    ]
}
```

Exigez ensuite le bundle, et les bundles du framework que vous voulez qu'il branche :

```bash
composer require phil/content-bundle:^0.1@dev phil/ddd-bundle:^0.1@dev
composer require doctrine/doctrine-bundle symfony/twig-bundle
```

Le `composer.json` propre au bundle déclare `"minimum-stability": "dev"` et `"prefer-stable": true`. Cela ne
s'applique que lorsque le bundle est le paquet racine. Une application qui l'exige a besoin de `@dev` sur les
contraintes, comme ci-dessus, ou des mêmes réglages de stabilité.

## Enregistrer le bundle

Ajoutez-le à `config/bundles.php`, après DoctrineBundle et TwigBundle :

```php
return [
    Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true],
    Doctrine\Bundle\DoctrineBundle\DoctrineBundle::class => ['all' => true],
    Symfony\Bundle\TwigBundle\TwigBundle::class => ['all' => true],
    Phil\DddBundle\PhilDddBundle::class => ['all' => true],
    Phil\ContentBundle\PhilContentBundle::class => ['all' => true],
];
```

Enregistrer `phil/ddd-bundle` est facultatif pour ce bundle : il utilise seulement ses classes, pas ses services.

## Ce qu'il faut écrire dans l'application

1. **Une entité racine et une entité de ligne par genre de contenu** ([storage.md](storage.md)) : la racine étend
   `AbstractContentRoot`, déclare un identifiant dans une colonne nommée `id` et nomme sa classe de ligne ; la ligne
   étend `AbstractDimensionContent` et implémente les capacités dont elle a besoin, avec leurs traits
   ([capabilities.md](capabilities.md)).
2. **La configuration** ([configuration.md](configuration.md)) : les locales servies et leurs chaînes de repli, les
   classes racines et — pour servir des pages — les routes Symfony de chaque genre et le gabarit Twig de chaque clé de
   gabarit.
3. **Les routes** qui montent `ContentController` ([routing.md](routing.md)) et **les gabarits** dont elles
   produisent le rendu ([rendering.md](rendering.md)).

## Schéma de la base de données

Le bundle n'est fourni avec aucune migration. Avec DoctrineBundle, ses mappages font partie des métadonnées de l'ORM,
donc l'outil de migration de l'application les voit :

```bash
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

Le schéma contient une table par classe de ligne (l'application la nomme), avec les colonnes de la dimension, la
colonne de jointure `root_id`, la contrainte d'unicité `(root_id, stage, version, locale_key)` et les colonnes de
chaque capacité que la classe implémente ; la table de routes, `phil_content_route` ; et la table des copies de
travail, `phil_content_working_copy`. Les colonnes sont listées dans
[storage.md](storage.md) et [capabilities.md](capabilities.md).

Les mappages ORM sont ajoutés en tête du gestionnaire d'entités nommé `default` (`doctrine.orm.mappings`) ; voir
[configuration.md](configuration.md) pour `entity_manager`.

## Routes et gabarits

Le bundle n'importe aucun fichier de routes et n'est fourni avec aucun gabarit. L'application monte
`ContentController` sur ses propres routes et les liste sous `phil_content.routes` ; elle écrit les gabarits et les
liste sous `phil_content.templates` ([routing.md](routing.md), [rendering.md](rendering.md)).
