<!-- phil-dev source-sha256=d771434d890133e7d0e5117ef6d4b727c948b9a744e5f861803e5453c5c6dd60 -->
# Le magasin en base de données

Lorsque `doctrine` est activé ([configuration.md](configuration.md#doctrine)), deux tables contiennent ce qui change
sans déploiement, sur une seule connexion DBAL.

| Identifiant de service | Classe | Rôle |
|---|---|---|
| `phil_feature_flags.provider.doctrine` | `DoctrineFlagDefinitions` | Un fournisseur (`EnumerableFlagProvider`) placé après la configuration, et le magasin de définitions où écrit l'administration (`WritableFlagDefinitions`). |
| `phil_feature_flags.state_store.doctrine` | `DoctrineFlagStore` | Le magasin des états actifs (`WritableFlagStore`) de tout indicateur, de n'importe quel fournisseur. |
| `Phil\FeatureFlagsBundle\Infrastructure\Doctrine\FlagSchemaListener` | `FlagSchemaListener` | Ajoute les deux tables au schéma ORM de cette connexion. |

## Tables

`phil_feature_flags_definition` (`FlagTables::DEFINITIONS`) : les indicateurs créés à partir de l'administration.

| Colonne | Type DBAL | Null | Remarques |
|---|---|---|---|
| `name` | `string(100)` | non | Clé primaire ; le nom de l'indicateur. |
| `definition` | `json` | non | La définition sous la forme de la configuration (`FlagDefinitionFormat::write()`), `active` compris. |
| `revision` | `integer` | non | 1 à la création, incrémentée à chaque modification. |
| `updated_at` | `datetime_immutable` | non | Tirée de l'horloge configurée. |

`phil_feature_flags_state` (`FlagTables::STATES`) : l'état actif de tout indicateur une fois qu'il a été basculé.

| Colonne | Type DBAL | Null | Remarques |
|---|---|---|---|
| `name` | `string(100)` | non | Clé primaire ; le nom de l'indicateur. |
| `active` | `boolean` | non | L'état actif stocké. |
| `updated_at` | `datetime_immutable` | non | Tirée de l'horloge configurée. |

Le JSON de la définition ressemble à ceci :

```json
{
    "active": false,
    "rules": [{"attribute": "tenant", "members": ["tenant-a"]}],
    "experiment": {"attribute": "tenant", "variants": {"control": 50, "treatment": 50}},
    "description": "The new search.",
    "owner": "search-team",
    "kind": "permission",
    "expires_on": "2099-12-31"
}
```

Les clés qui ne contiennent rien sont omises. Le `active` stocké dans la définition est l'état que l'éditeur a écrit à
la création (toujours `false`) ; l'état qu'applique l'évaluateur est celui de `phil_feature_flags_state`, qui contient
une rangée pour chaque indicateur créé.

## Schéma et migrations

`FlagSchemaListener` écoute l'événement `postGenerateSchema` de l'ORM pour la connexion configurée (et vérifie que la
connexion du gestionnaire d'entités est bien celle-là), puis ajoute les deux tables avec `FlagTables::configure()`, sans
toucher à une table qui existe déjà. C'est le motif des écouteurs de schéma que le pont Doctrine de Symfony fournit pour
ses magasins de session, de verrou, de cache et de Messenger. Conséquences :

- `doctrine:migrations:diff` écrit la migration qui crée les tables ;
- `doctrine:schema:validate` et `doctrine:schema:update` ne proposent pas de les supprimer ;
- l'écouteur exige Doctrine DBAL 4.5 ou plus récent (`Schema::edit()`, `GenerateSchemaEventArgs::setSchema()`).

Sans gestionnaire d'entités ORM sur cette connexion, créez les tables à partir de `FlagTables::configure(new Schema())`
([installation.md](installation.md#le-magasin-en-base-de-données-et-sa-migration)).

## Une lecture par requête

Chaque magasin lit toute sa table en **une seule requête** au premier indicateur demandé dans une requête HTTP, et garde
le résultat pour le reste de la requête. Les deux magasins portent l'étiquette `kernel.reset` : la requête suivante (ou
le message suivant d'un worker) relit donc la table. Une bascule faite par un autre processus est ainsi visible à partir
de la requête suivante, ce qui concorde avec la mémoïsation de l'évaluateur
([flags-and-evaluation.md](flags-and-evaluation.md#une-fois-par-requête-et-par-contexte)).

Chaque écriture vide le cache du magasin : la requête d'administration qui a écrit voit donc son propre changement.

`DoctrineFlagDefinitions::findRevised()` n'utilise pas ce cache : l'éditeur et l'administration lisent directement la
rangée et la révision actuelles.

## Écritures conditionnelles

Chaque écriture est une seule instruction dont la condition porte la concurrence :

| Opération | Instruction | Retourne `false` lorsque |
|---|---|---|
| `DoctrineFlagStore::compareAndSet()` | `UPDATE … SET active = ?, updated_at = ? WHERE name = ? AND active = ?` | L'état n'est plus celui attendu, ou la rangée a disparu. |
| `DoctrineFlagStore::insert()` | `INSERT` | La clé primaire le refuse (`UniqueConstraintViolationException`). |
| `DoctrineFlagDefinitions::create()` | `INSERT` avec la révision 1 | La clé primaire le refuse. |
| `DoctrineFlagDefinitions::replace()` | `UPDATE … SET definition = ?, revision = revision + 1, updated_at = ? WHERE name = ? AND revision = ?` | La révision a changé, ou la rangée a disparu. |

Un changement qui a perdu une course n'écrit rien, et la bascule ou l'éditeur transforme le `false` en conflit
([toggles-and-edits.md](toggles-and-edits.md)).

## Rangées illisibles

**Une rangée qui ne se lit plus comme une définition est illisible, et non absente** (nom invalide, JSON invalide,
règle ou expérience que le format refuse). `DoctrineFlagDefinitions` implémente `DiagnosableFlagProvider`
([extension-points.md](extension-points.md#diagnosableflagprovider)) :

- `find()` lance une `InvalidValue` portant la raison pour cet indicateur, si bien que son évaluation est désactivée,
  raison `ERROR`, code `PARSE_ERROR` (jamais `FLAG_NOT_FOUND`), et que chaque autre indicateur s'évalue encore ;
  l'évaluateur ne lance jamais d'exception ;
- `all()` laisse la rangée de côté ;
- `unreadable()` liste chaque rangée illisible sous forme d'`UnreadableFlag`, par le nom qu'elle stocke (qui peut
  lui-même être le problème) avec la raison, triée par nom.

Le catalogue présente une telle rangée à part, et la liste et la page d'indicateur de l'administration l'affichent
avec sa raison ([admin.md](admin.md#écrans)) ; elle ne peut être ni basculée ni modifiée depuis l'administration, et
son nom ne peut pas être créé de nouveau. `findRevised()` lit elle-même la rangée et lance la même `InvalidValue`.

Le JSON est décodé avec une limite de profondeur de 16. Une colonne de révision retournée sous forme de chaîne de
chiffres (certains pilotes le font) est acceptée ; toute autre révision non entière est refusée.

## Portabilité

Les tables n'utilisent que des types DBAL portables. Les booléens sont lus au moyen du type booléen DBAL de la
plateforme : le magasin se comporte donc de la même façon sur SQLite, MySQL 8 et PostgreSQL, que la suite fonctionnelle
met à l'épreuve ([testing.md](testing.md)).

## Retirer un indicateur

Le bundle ne supprime jamais de rangées : il n'y a aucune opération de suppression dans l'éditeur ni dans
l'administration. Retirer un indicateur créé dans la base de données consiste à supprimer sa rangée dans
`phil_feature_flags_definition` (et, pour éviter qu'un état ne reste pour un futur indicateur du même nom, dans
`phil_feature_flags_state`) avec une migration ou un outil propre à l'application. L'évaluateur ignore une rangée d'état
pour un indicateur qu'aucun fournisseur ne déclare.
