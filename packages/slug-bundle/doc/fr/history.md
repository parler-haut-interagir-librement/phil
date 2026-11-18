<!-- phil-dev source-sha256=1981b346443fa14a0b0425a2feb4e5cd38ec43f58a19f1ac13976616fc49ab6f -->
# Historique des anciens slugs

Quand un titre change, son slug change souvent avec lui, et chaque lien vers l'ancienne adresse se brise. L'historique
garde les anciens slugs de chaque sujet, chacun pointant vers le slug actuel du sujet, pour qu'une ancienne adresse
reçoive une redirection permanente plutôt qu'une 404. Il est facultatif et désactivé par défaut : il demande
`doctrine/dbal` et une table.

## L'activer

```sh
composer require doctrine/dbal
```

```yaml
# config/packages/phil_slug.yaml
phil_slug:
    history:
        enabled: true
        connection: default   # the DBAL connection of the table
        clock: clock          # the PSR-20 clock of the recorded changes
```

`history: true` abrège `enabled: true` avec les valeurs par défaut. Quand `doctrine/orm` est installé, la table
`phil_slug_history` est ajoutée au schéma que l'ORM génère pour cette connexion : `doctrine:migrations:diff` écrit
alors la migration qui la crée, et `doctrine:schema:validate` ne demande pas de la supprimer. Sans l'ORM, créez-la à
partir de `SlugHistoryTable::configure()`.

L'historique est le service `phil_slug.history`, autoconnecté comme
`Phil\SlugBundle\Application\Contract\SlugHistory`.

### Collation des clés

**Les clés se comparent octet par octet**, comme PHP les compare. Une portée peut mêler les casses et un sujet est un
identifiant quelconque : un changement du sujet `Post-7` ne réécrit jamais l'historique de `post-7`, et un ancien slug
de la portée `Blog` ne mène jamais à un sujet de `blog`. Les slugs sont en minuscules par leur règle ; l'ancien slug
prend la collation binaire avec la portée sous laquelle il est classé.

| Table | Colonnes en `utf8mb4_bin` sur MySQL et MariaDB |
|---|---|
| `phil_slug_history` | `scope`, `former_slug`, `subject` |

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

## Portées et sujets

| Mot | Ce que c'est | Règle |
|---|---|---|
| Portée | L'espace dans lequel un slug est unique : `page`, `page:fr`, `tenant-42:product` | De 1 à 100 octets de lettres ASCII, de chiffres et de `_ . : / -` |
| Sujet | L'identifiant de ce que le slug nomme dans sa portée, tel que l'application l'écrit : `42`, un UUID | De 1 à 191 octets de texte UTF-8 sans caractère de contrôle |

Une portée ou un sujet hors de ces règles est refusé avec `InvalidSlugHistory` avant toute requête, plutôt que coupé
pour tenir dans sa colonne. Une page traduite enregistre ses changements dans une portée par langue, et un site à
plusieurs locataires met le locataire dans la portée.

## Enregistrer un changement

Le code qui change un slug enregistre le changement, dans la même unité de travail :

```php
use Phil\Slug\Slug;
use Phil\SlugBundle\Application\Contract\SlugHistory;

final readonly class RenamePage
{
    public function __construct(private SlugHistory $history) {}

    public function __invoke(Page $page, Slug $newSlug): void
    {
        $this->history->record('page:'.$page->locale, (string) $page->id, $page->slug, $newSlug);
        $page->slug = $newSlug;
    }
}
```

Un changement s'écrit en une transaction de trois instructions, si bien que l'historique ne garde jamais de chaîne :

1. le nouveau slug cesse d'être un ancien slug de la portée, quel que soit le sujet qui l'avait : une adresse de
   nouveau en usage n'est plus redirigée ;
2. chaque ancien slug du sujet pointe désormais vers le nouveau slug ;
3. l'ancien slug est écrit comme ancien slug du sujet, à la place de toute ligne qu'il avait.

| Changements du sujet 7 dans `page` | Anciens slugs ensuite |
|---|---|
| `a` → `b` | `a` → `b` |
| puis `b` → `c` | `a` → `c`, `b` → `c` |
| puis `c` → `a` | `b` → `a`, `c` → `a` |

Enregistrer un slug comme son propre ancien slug ne change rien. L'historique grandit d'au plus une ligne par ancien
slug distinct d'un sujet, quel que soit le nombre de changements.

## Le lire

| Méthode | Retourne |
|---|---|
| `currentSlug(string $scope, Slug $former): ?Slug` | Le slug actuel du sujet qui a eu `$former`, ou `null` |
| `formerSlugs(string $scope): iterable<string, Slug>` | Chaque ancien slug de la portée, en clé, avec le slug actuel vers lequel il pointe, dans l'ordre des anciens slugs |
| `forget(string $scope, string $subject): void` | Retire chaque ancien slug du sujet : il a été supprimé, ou ses anciennes adresses ne doivent plus répondre |

Un contrôleur qui ne trouve aucune page pour un slug peut demander `currentSlug()` et rediriger avec un 301 ; un
fournisseur de redirections lit `formerSlugs()` pour bâtir sa table. Une ligne modifiée à la main en quelque chose qui
n'est pas un slug ne reçoit pas de réponse.

L'historique ne garde rien entre deux appels : il est sûr dans un processus de longue durée.
