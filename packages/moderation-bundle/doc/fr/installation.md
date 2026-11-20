<!-- phil-dev source-sha256=3103ae1a803cc1a7fcc0fd5a3adeefbd5e5684ab9285401a565391c6d25a309f -->
# Installation

## Prérequis

PHP 8.5 ou une version plus récente avec `ext-sodium`, Symfony 8.2 ou une version plus récente, Doctrine DBAL 4.5, Twig
3.21 et Symfony UX Twig Component 3.7. La protection CSRF de FrameworkBundle doit être activée. Les pages de la
modération ont besoin de SecurityBundle ; sans lui, le formulaire de signalement et le journal répondent toujours, et
personne n'est modérateur. Le mode d'incident vient avec
`phil/incident-mode-bundle` 0.1.1 ou plus récent ([Mode d'incident](incident-mode.md)).

## Le bundle

```sh
composer require phil/moderation-bundle
```

```php
// config/bundles.php
Phil\ModerationBundle\PhilModerationBundle::class => ['all' => true],
```

La recette du bundle l'enregistre et écrit `config/packages/phil_moderation.yaml` et
`config/routes/phil_moderation.yaml`.

## Les tables

Le bundle ajoute ses cinq tables au schéma que l'ORM génère pour sa connexion ; générez la migration :

```sh
php bin/console doctrine:migrations:diff
```

### Collation des clés

**Les clés se comparent octet par octet**, comme PHP les compare. Les membres et les cibles décident de ce que lit une
requête : les notes sur le membre `Ann` ne sont jamais lues pour `ann`, les énoncés de motifs adressés à `Ann` jamais
listés pour `ann`, et un signalement de la cible `post:Abc` jamais clos avec les signalements de `post:abc`.

| Table | Colonnes en `utf8mb4_bin` sur MySQL et MariaDB |
|---|---|
| `phil_moderation_report` | `id`, `target_type`, `target_id`, `reporter`, `decision_id` |
| `phil_moderation_decision` | `id`, `target_type`, `target_id`, `moderator`, `author` |
| `phil_moderation_note` | `id`, `member`, `author` |
| `phil_moderation_sandbox` | `member` |
| `phil_moderation_statement` | `id`, `decision_id`, `recipient`, `target_type`, `target_id` |

Sur MySQL et MariaDB, ces colonnes prennent le jeu de caractères `utf8mb4` et la collation `utf8mb4_bin`, selon la
plateforme de la connexion : la collation par défaut de MySQL 8, `utf8mb4_0900_ai_ci`, ignore la casse et les accents.
Les collations par défaut de PostgreSQL sont déterministes, égales seulement quand les octets le sont, et SQLite compare
les octets : leurs colonnes gardent la valeur par défaut.

#### Mise à niveau depuis 0.2

Le mode d'incident passe à `phil/incident-mode-bundle` : la clé `incident` de la configuration, les pages des incidents
et `phil_moderation_suspended()` disparaissent ([Mise à niveau depuis 0.2](incident-mode.md#mise-à-niveau-depuis-02)).

#### Mise à niveau depuis 0.1

Les tables d'une installation 0.1 sur MySQL ou MariaDB gardaient la collation de la connexion.
`doctrine:migrations:diff` écrit la migration qui amène les colonnes ci-dessus à `utf8mb4_bin` ; exécutez-la avec
`doctrine:migrations:migrate`. Le changement ne fait que distinguer davantage les valeurs : aucune ligne existante ne
brise une clé. Les lignes qu'une collation insensible à la casse avait déjà fusionnées restent fusionnées ; les valeurs
écrites à partir de la migration sont gardées distinctes. Sur PostgreSQL et SQLite, le diff est vide.

## Les routes

```yaml
# config/routes/phil_moderation.yaml
phil_moderation_admin:
    resource: '@PhilModerationBundle/config/routes/admin.php'
    prefix: /admin/moderation
phil_moderation_report:
    resource: '@PhilModerationBundle/config/routes/report.php'
    prefix: /moderation
phil_moderation_journal:
    resource: '@PhilModerationBundle/config/routes/journal.php'
    prefix: /moderation/journal
phil_moderation_statements:
    resource: '@PhilModerationBundle/config/routes/statement.php'
    prefix: /moderation/statements
```

Gardez `/admin` derrière le pare-feu, et `/moderation/statements` derrière celui qui connaît les membres. Avec
EasyAdmin, les pages sont aussi greffées sur chaque tableau de bord ; le premier import est alors facultatif
([Pages](pages.md)).

## Les recettes du kit

Les gabarits dessinent leurs éléments avec les recettes du kit PHIL UX, installées dans l'application par le Symfony UX
Toolkit ; rien n'est produit à partir de `vendor/` :

```sh
php bin/console ux:install alert --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install badge --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install button --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install field --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install input --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install native-select --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install table --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install textarea --kit=github.com/parler-haut-interagir-librement/ux-kit
```

Les recettes ont les noms, les props et les blocs du kit Shadcn du Toolkit : un site sous Tailwind les installe donc
depuis `github.com/parler-haut-interagir-librement/ux-kit-shadcn`. Elles se trouvent sous le sous-répertoire des
composants de la configuration `ux_toolkit` ; dans un environnement où le Toolkit n'est pas activé, répétez-le dans
l'option `kit_subdirectory`.

## Les membres

Le bundle désigne un membre par un identifiant stable. La classe d'utilisateur de l'application implémente
`ModerationMember` :

```php
use Phil\ModerationBundle\Application\Contract\ModerationMember;

final class User implements UserInterface, ModerationMember
{
    public function moderationIdentifier(): string
    {
        return $this->uuid->toRfc4122();
    }
}
```

**L'identifiant d'utilisateur n'est jamais lu** : c'est souvent une adresse de courriel. Une application dont les
membres se trouvent autrement remplace `CurrentMember` ([Points d'extension](extension-points.md)).

## Les cibles

Chaque paquet qui détient du contenu enregistre sa `ModerationTarget` ; le propre contenu d'une application fait de même,
avec l'autoconfiguration ([La file](queue.md#cibles)).
