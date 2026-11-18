<!-- phil-dev source-sha256=814d508b6aaf690eb9dd755f64f5787e549f7dc22b719b8f63b97497e322dc67 -->
# Installation

## Prérequis

| Prérequis | Version | Pourquoi |
|---|---|---|
| PHP, `ext-mbstring`, `ext-filter` | 8.5 ou plus récent | Les classes `readonly`, les constantes de classe typées, `#[Override]`, `array_find()` et `array_any()` ; une adresse courriel distinguée d'un identifiant de membre. |
| `doctrine/dbal` | `^4.5` | Les deux tables, construites avec les éditeurs de schéma de DBAL 4.5. |
| `psr/clock`, `psr/event-dispatcher` | `^1.0` | L'horloge de chaque moment ; les événements d'une publication et d'une acceptation. |
| `symfony/config`, `dependency-injection`, `http-foundation`, `http-kernel`, `routing` | `^8.2` | La configuration, le câblage, la porte, les pages et leurs routes. |
| `symfony/security-core`, `symfony/security-csrf` | `^8.2` | Le membre connecté, le rôle de l'administration, le jeton des formulaires. |
| `symfony/service-contracts`, `symfony/translation-contracts` | `^3.6` | `kernel.reset` ; les messages de la porte et des formulaires. |
| `twig/twig` | `^3.21` | La page d'acceptation, les pages publiques et l'administration. |
| `symfony/ux-twig-component` | `^3.7` | Les recettes du kit que dessinent les gabarits. |

Paquets facultatifs, chacun activant une partie :

| Paquet | Active |
|---|---|
| `doctrine/doctrine-bundle` | Le service `doctrine.dbal.<name>_connection` et l'étiquette de l'écouteur de schéma. En pratique toujours présent. |
| `doctrine/orm` | Les deux tables ajoutées au schéma que l'ORM génère, pour `doctrine:migrations:diff`. Le bundle lui-même lit et écrit par DBAL seulement. |
| `symfony/translation` | Les pages dans la langue de la requête, anglais et français compris. |
| `symfony/form` | L'option `terms_acceptance` d'un formulaire d'inscription. |
| `symfony/console` | `phil:terms:outstanding`. |
| `symfony/scheduler`, `symfony/messenger` | Les rappels. |
| `phil/notifications-bundle` | Les avis d'une nouvelle version et les rappels. |
| `phil/legal-notices-bundle` | `phil:terms:check-legal-links`, la vérification que les liens légaux existent sur le site. |
| `phil/ip-anonymizer` | Le réseau d'où venait une acceptation. |
| `phil/privacy-bundle` | Les acceptations dans l'exportation et l'effacement des données d'une personne. |
| `phil/text-diff` | Les changements entre deux versions sur les pages publiques. |
| `phil/ujo` | Les textes écrits sous forme de documents UJO (Universal JSON Output). |
| `easycorp/easyadmin-bundle` | Le lien de l'administration dans un menu EasyAdmin. |

## Installer avec Composer

```sh
composer require phil/terms-bundle
```

## Enregistrer le bundle

```php
// config/bundles.php
return [
    Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true],
    Symfony\Bundle\SecurityBundle\SecurityBundle::class => ['all' => true],
    Doctrine\Bundle\DoctrineBundle\DoctrineBundle::class => ['all' => true],
    Symfony\Bundle\TwigBundle\TwigBundle::class => ['all' => true],
    Symfony\UX\TwigComponent\TwigComponentBundle::class => ['all' => true],
    Phil\TermsBundle\PhilTermsBundle::class => ['all' => true],
];
```

## Créer les tables

Avec l'ORM, le bundle ajoute `phil_terms_version` et `phil_terms_acceptance` au schéma que l'ORM génère pour sa
connexion ; la prochaine migration de l'application les crée :

```sh
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

Sans l'ORM, créez-les dans une migration de l'application avec `Infrastructure\Doctrine\TermsTables::configure()`, qui
ajoute les deux tables à un `Schema` de DBAL.

### Collation des clés

**Les clés se comparent octet par octet**, comme PHP les compare. La porte demande à chaque requête si un membre a
accepté la version d'une politique : l'acceptation du membre `Ann` ne laisse jamais passer `ann` ni `ánn`, et l'export
des données d'un membre ne contient jamais les acceptations d'un autre.

| Table | Colonnes en `utf8mb4_bin` sur MySQL et MariaDB |
|---|---|
| `phil_terms_version` | `policy` |
| `phil_terms_acceptance` | `member`, `policy` |

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

## Installer la recette

La recette Flex du bundle l'enregistre, écrit `config/packages/phil_terms.yaml` avec les politiques `terms` et `privacy`,
importe les pages publiques dans `config/routes/phil_terms.yaml`, et copie `App\Terms\Members` et `App\Terms\CurrentMember`
dans `src/Terms/`. Tant que ces deux classes ne lisent pas les comptes de l'application, elles ne listent et ne nomment
personne : la porte ne demande rien à personne, et l'application démarre. La recette se trouve dans le dépôt de recettes
de PHIL, lu une fois que l'application inscrit ce dépôt dans `extra.symfony.endpoint` ; sans cela, enregistrez le bundle
et écrivez la configuration à la main, comme ci-dessous.

## Installer les recettes du kit

Les pages dessinent leurs parties avec des recettes du kit PHIL UX, installées dans l'application par le Symfony UX
Toolkit :

```sh
for recipe in alert badge button checkbox field input native-select table textarea; do
    php bin/console ux:install $recipe --kit=github.com/parler-haut-interagir-librement/ux-kit
done
```

Un site sous Tailwind installe plutôt les mêmes neuf recettes depuis le kit Shadcn du Toolkit (`--kit=shadcn`), avec
`label` et `separator`, que ses recettes appellent, et Symfony UX Icons. `PhilTermsBundle::RECIPES` les énumère. Les
recettes se trouvent dans le sous-répertoire des composants de la configuration du Toolkit, ou dans `kit_subdirectory`
([Configuration](configuration.md)).

## Monter les routes

La porte envoie un membre vers la page d'acceptation, et le problème d'une API renvoie aux versions : les routes des
pages sont toujours montées. L'administration est montée derrière le pare-feu, quand elle est activée.

```yaml
# config/routes/phil_terms.yaml
phil_terms:
    resource: '@PhilTermsBundle/config/routes/pages.php'
    prefix: /terms

phil_terms_admin:
    resource: '@PhilTermsBundle/config/routes/admin.php'
    prefix: /admin/terms
```

La page d'acceptation se trouve à `<prefix>/accept` : aucune politique ne s'appelle donc `accept`.

## Déclarer les politiques

```yaml
# config/packages/phil_terms.yaml
phil_terms:
    locales: [en, fr]
    policies:
        terms: ~
        privacy: { renew_after_days: 365 }
        code_of_conduct: { required: false }
    member_source: App\Security\EveryMember
    member_resolver: App\Security\MemberId
```

## Nommer les membres

`member_source` est requis : le conteneur n'est pas construit sans lui. Il nomme un service qui implémente
`Application\Contract\MemberSource` et donne chaque membre du site, ceux qui n'ont jamais rien accepté compris, pour que
`Outstanding` et les rappels les atteignent tous.

```php
use Phil\TermsBundle\Application\Contract\MemberSource;

final readonly class EveryMember implements MemberSource
{
    public function __construct(private MemberRepository $members) {}

    public function members(): iterable
    {
        yield from $this->members->identifiers();
    }
}
```

Un membre est nommé par un identifiant stable, jamais par un renseignement personnel. `member_resolver` nomme un service
qui implémente `Application\Contract\MemberResolver` et donne cet identifiant ; [Configuration](configuration.md#le-membre)
en montre un. Sans lui, le membre est l'identifiant d'utilisateur du jeton de sécurité, et la construction est refusée à
moins que `stable_identifiers: true` ne déclare que ces identifiants ne sont jamais des adresses courriel ;
`TokenMemberResolver` lance quand même une `LogicException` sur un identifiant qui en a la forme.

Publiez ensuite une première version de chacune, depuis l'administration ou depuis le code : une politique sans
version ne demande rien.
