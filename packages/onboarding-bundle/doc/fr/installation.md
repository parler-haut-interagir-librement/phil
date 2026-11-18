<!-- phil-dev source-sha256=1f08748d387108b5541ed84bc7cb93fac6409211c43b9f11f8fe1f98e1028e23 -->
# Installation

## Prérequis

| Paquet | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent, avec `mbstring` | Les titres sont comptés en caractères. |
| `doctrine/orm`, `doctrine/dbal` | `^3.7`, `^4.5` | La table de la progression. |
| `symfony/config`, `symfony/dependency-injection` | `^8.2` | Le bundle, sa configuration et les déclarations. |
| `symfony/http-foundation`, `symfony/security-csrf` | `^8.2` | Les points de terminaison et leur jeton CSRF sans état. |
| `symfony/service-contracts` | `^3.6` | Le localisateur des vérificateurs. |
| `psr/clock`, `psr/event-dispatcher` | `^1.0` | L'heure de chaque étape ; les événements. |

Facultatifs : Twig, Symfony Routing et Symfony UX Twig Component pour la liste de contrôle et la visite, avec la recette
`Button` du kit ; le bundle Stimulus et AssetMapper pour le contrôleur de la visite ; Symfony Security pour l'utilisateur
courant ; `phil/settings-bundle` pour la préférence qui désactive les visites ; `phil/privacy-bundle` pour inclure la progression d'un utilisateur dans l'exportation et l'effacement de ses
données.

## Activer le bundle

```sh
composer require phil/onboarding-bundle
```

```php
// config/bundles.php
return [
    // …
    Phil\OnboardingBundle\PhilOnboardingBundle::class => ['all' => true],
];
```

Le bundle a besoin d'un service `clock`, que FrameworkBundle enregistre, et de la protection CSRF de FrameworkBundle,
active par défaut : le conteneur s'arrête avec un message quand `framework.csrf_protection` vaut false. Le jeton
`phil_onboarding` est sans état : les points de terminaison n'ont donc besoin d'aucune session.

## La recette Flex

La recette Flex du bundle l'enregistre dans `config/bundles.php`, écrit `config/packages/phil_onboarding.yaml` sans aucun
parcours, et `config/routes/phil_onboarding.yaml`, qui importe les points d'accès ci-dessous. La recette se trouve dans le
dépôt de recettes de PHIL, lu une fois que l'application inscrit ce dépôt dans `extra.symfony.endpoint` ; sans cela,
enregistrez le bundle et importez les routes à la main.

## La table

Le bundle ajoute son mapping en tête de la configuration de DoctrineBundle : `phil_onboarding_progress`, une ligne par
utilisateur et par parcours. Générez la migration comme pour les entités de l'application :

```sh
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

| Colonne | Type | |
|---|---|---|
| `user_identifier` | chaîne (320) | Partie de la clé primaire : l'identifiant par lequel l'application nomme l'utilisateur. |
| `flow` | chaîne (64) | Partie de la clé primaire. |
| `steps` | JSON | Les étapes accomplies ou passées, chacune avec son état et son heure. |
| `started_at`, `updated_at` | datetime | Quand le parcours a été commencé, ou recommencé, et modifié pour la dernière fois. |
| `finished_at`, `dismissed_at` | datetime, nullable | Quand toutes les étapes ont été faites pour la première fois ; quand l'utilisateur a rangé le parcours. |

### Collation des clés

**Les clés se comparent octet par octet**, comme PHP les compare. La progression d'un utilisateur dans un parcours se
lit par l'identifiant de l'utilisateur : celle de `Ann` n'est jamais lue, reprise ni écartée pour `ann` ni `ánn`.
`BinaryKeysListener` donne à ces champs la collation binaire quand l'ORM charge leurs métadonnées sur MySQL ou MariaDB ;
le mappage lui-même reste le même sur toutes les plateformes.

| Table | Colonnes en `utf8mb4_bin` sur MySQL et MariaDB |
|---|---|
| `phil_onboarding_progress` | `user_identifier`, `flow` |

Sur MySQL et MariaDB, ces colonnes prennent le jeu de caractères `utf8mb4` et la collation `utf8mb4_bin`, selon la
plateforme de la connexion : la collation par défaut de MySQL 8, `utf8mb4_0900_ai_ci`, ignore la casse et les accents.
Les collations par défaut de PostgreSQL sont déterministes, égales seulement quand les octets le sont, et SQLite compare
les octets : leurs colonnes gardent la valeur par défaut.

#### Mise à niveau depuis 0.1

Les tables d'une installation 0.1 sur MySQL ou MariaDB gardaient la collation de la connexion.
`doctrine:migrations:diff` écrit la migration qui amène les colonnes ci-dessus à `utf8mb4_bin` ; exécutez-la avec
`doctrine:migrations:migrate`. Le changement ne fait que distinguer davantage les valeurs : aucune ligne existante ne
brise une clé. Les lignes qu'une collation insensible à la casse avait déjà fusionnées restent fusionnées ; les valeurs
écrites à partir de la migration sont gardées distinctes. Sur PostgreSQL et SQLite, le diff est vide.

## Les routes

```yaml
# config/routes/phil_onboarding.yaml
phil_onboarding:
    resource: '@PhilOnboardingBundle/config/routes.php'
    prefix: /onboarding
```

| Route | Chemin | |
|---|---|---|
| `phil_onboarding_done` | `POST /{flow}/{step}/done` | Coche une étape faite à la main. |
| `phil_onboarding_answer` | `POST /{flow}/{step}/answer` | Répond à la question d'une étape, avec les choix dans `choices[]`. |
| `phil_onboarding_skip` | `POST /{flow}/{step}/skip` | Passe une étape qui peut être passée. |
| `phil_onboarding_dismiss` | `POST /{flow}/dismiss` | Range le parcours. |
| `phil_onboarding_reset` | `POST /{flow}/reset` | Recommence le parcours. |

Gardez-les derrière le pare-feu des membres : un visiteur reçoit une `403`.

## La recette du kit

La liste de contrôle et la visite dessinent leurs boutons avec la recette `Button` du kit PHIL UX, installée dans
l'application par le Symfony UX Toolkit et rien de rendu depuis `vendor/` :

```sh
php bin/console ux:install button --kit=github.com/parler-haut-interagir-librement/ux-kit
```

Le `Button` du kit Shadcn accepte les mêmes props. La recette se trouve sous le sous-répertoire des composants de la
configuration `ux_toolkit` ; dans un environnement où le Toolkit n'est pas activé, comme l'est souvent la production,
répétez-le dans l'option `kit_subdirectory`.

## Les ressources

Avec AssetMapper, le bundle sert son contrôleur Stimulus sous `@phil/onboarding-bundle` et sa feuille de style sous
`phil-onboarding`. Activez le contrôleur dans `assets/controllers.json`, comme pour tout paquet Symfony UX :

```json
{
    "controllers": {
        "@phil/onboarding-bundle": {
            "tour": { "enabled": true, "fetch": "eager" }
        }
    }
}
```

```twig
<link rel="stylesheet" href="{{ asset('phil-onboarding/onboarding.css') }}">
```
