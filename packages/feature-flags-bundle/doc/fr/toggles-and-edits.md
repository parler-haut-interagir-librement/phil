<!-- phil-dev source-sha256=07bfec9447ef8f01331967b99a02433229d259a83d38841e155497a490eeb926 -->
# Bascules et modifications

Deux services changent les indicateurs à l'exécution, tous deux activés par `toggle`
([configuration.md](configuration.md#toggle)) :

- `FlagToggler` rend un indicateur actif ou inactif, dans le magasin d'états ;
- `FlagEditor` crée un indicateur, ou change ses règles, son expérience et ses métadonnées, dans le magasin de
  définitions (enregistré seulement lorsqu'un `definition_store` existe).

Les deux suivent le même ordre, **autoriser, lire, auditer, écrire**, au moyen des deux mêmes adaptateurs de
l'application, `ToggleAuthorizer` et `ToggleAuditor`, et chaque changement porte une raison obligatoire et un acteur.

## La raison et l'acteur

`ToggleReason::of(string $value)` :

- refuse une valeur qui n'est pas de l'UTF-8 valide ;
- retire tous les blancs Unicode aux deux extrémités (les espaces et les séparateurs d'espace, `\s` et `\p{Z}` en mode
  Unicode, y compris U+00A0 NO-BREAK SPACE et U+3000 IDEOGRAPHIC SPACE) et refuse une raison qui reste vide ;
- refuse une raison de plus de 1 000 caractères (`ToggleReason::MAX_LENGTH`).

L'acteur est l'identifiant opaque que l'application donne à l'opérateur (un identifiant d'utilisateur, un compte de
service). `ToggleRequest` et `EditRequest` refusent un acteur qui n'est pas de l'UTF-8 valide, ou qui est vide ou blanc
au même sens Unicode : un changement anonyme ne peut pas être audité. Tous les refus sont des exceptions `InvalidValue`.

## Basculer

```php
use Phil\FeatureFlagsBundle\Application\Toggle\FlagToggler;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagName;
use Phil\FeatureFlagsBundle\Domain\Toggle\ToggleReason;
use Phil\FeatureFlagsBundle\Domain\Toggle\ToggleRequest;

$changed = $toggler->toggle(new ToggleRequest(
    FlagName::of('new-checkout'),
    false,                                         // the state asked for
    'user:42',                                     // the actor
    ToggleReason::of('Checkout errors spiking after release 3.4.'),
));
```

`toggle()` retourne si l'indicateur a changé, et exécute :

1. **Autoriser** : `ToggleAuthorizer::authorize($request)` ; un refus lance `ToggleRefused`, et rien n'est lu, écrit ni
   audité.
2. **Lire** : l'état actuel du magasin ; lorsque le magasin ne contient pas encore l'indicateur, l'état que déclare son
   fournisseur (au moyen de `FlagCatalog::find()`). Un indicateur qu'aucun fournisseur ne déclare lance `UnknownFlag` :
   **une bascule ne crée jamais d'indicateur**.
3. **Aucune opération** : demander la valeur que l'indicateur a déjà retourne `false`, n'écrit rien et n'audite rien ;
   la piste ne contient donc que des changements.
4. **Auditer** : `ToggleAuditor::record(ToggleRecord)`, avec l'indicateur, l'acteur, la valeur avant et après, la raison
   et l'heure de l'horloge. **La ligne d'audit précède l'écriture** : un auditeur qui lance une exception arrête la
   bascule avant que quoi que ce soit soit écrit, et aucune bascule n'existe donc sans sa trace.
5. **Écrire** : une comparaison-échange sur la valeur lue (`WritableFlagStore::compareAndSet($flag, $before, $active)`)
   ou, pour un indicateur que le magasin ne contenait pas, une insertion atomique de son premier état
   (`WritableFlagStore::insert()`). Une écriture qui retourne `false` a perdu une course et lance `ToggleConflict` :
   deux bascules concurrentes ne peuvent pas s'appliquer toutes les deux, et le « avant » de la ligne d'audit est la
   valeur que l'écriture a remplacée.

### Première bascule d'un indicateur déclaré

Un indicateur déclaré dans la configuration n'a aucune rangée dans le magasin d'états jusqu'à sa première bascule. Cette
bascule part de l'état déclaré et stocke le nouvel état avec une insertion que le magasin refuse si un autre processus a
inséré en premier ; à partir de là, l'état stocké remplace l'état déclaré
([flags-and-evaluation.md](flags-and-evaluation.md#le-magasin-détats)). Les règles déclarées restent en vigueur.

### Transactions

Lorsque l'écriture échoue après l'écriture de la ligne d'audit (un conflit, une erreur de base de données), la ligne
reste, à moins que l'application exécute `toggle()` dans une seule transaction partagée par le magasin et l'auditeur,
que l'exception annule alors :

```php
$connection->transactional(fn () => $toggler->toggle($request));
```

Sans cette transaction, la piste peut contenir une bascule qui ne s'est pas appliquée, mais ne manque jamais une bascule
qui s'est appliquée. C'est à l'application de décider si le magasin et l'auditeur partagent une connexion.

## Créer un indicateur

```php
use Phil\FeatureFlagsBundle\Application\Edit\FlagEditor;
use Phil\FeatureFlagsBundle\Domain\Edit\EditRequest;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagDefinition;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagName;
use Phil\FeatureFlagsBundle\Domain\Flag\Percentage;
use Phil\FeatureFlagsBundle\Domain\Flag\Rule\PercentageRollout;
use Phil\FeatureFlagsBundle\Domain\Flag\TargetingAttribute;

$editor->create(new EditRequest(
    new FlagDefinition(FlagName::of('beta-search'), false, [
        new PercentageRollout(TargetingAttribute::Tenant, Percentage::of(10)),
    ]),
    'user:42',
    ToggleReason::of('Opening the search beta.'),
));                                                // no revision: a creation
```

`create()` exécute :

1. `ToggleAuthorizer::authorizeEdit($request)` (`ToggleRefused` en cas de refus).
2. Une demande qui nomme une révision est refusée (`InvalidValue`) : une création n'a rien pu lire.
3. Un nom que **n'importe quel fournisseur** déclare déjà lance `EditConflict::alreadyDeclared()` : un autre fournisseur
   masquerait le nouvel indicateur. Un nom qu'un fournisseur contient sans pouvoir le lire est déclaré lui aussi
   (`FlagCatalog::lookup()`).
4. La définition est forcée **inactive**, quoi que dise la demande.
5. `ToggleAuditor::recordEdit(EditRecord)` avec `before = null`.
6. `WritableFlagDefinitions::create()` ; `false` (le nom a été inséré entre-temps) lance `EditConflict`.
7. `WritableFlagStore::insert($flag, false)` stocke l'état initial, inactif.

Activer le nouvel indicateur est une **bascule**, sur sa propre ligne d'audit.

**Une exception à « commence inactif » :** si le magasin d'états contient encore un état pour ce nom, laissé par un
ancien indicateur du même nom dont la définition a disparu, l'insertion est refusée et cet état est conservé, comme le
dernier état fixé par un opérateur. Si cet état était `true`, l'indicateur recréé est actif dès qu'il existe. Effacez la
rangée d'état au retrait d'un indicateur si cela importe.

## Modifier un indicateur

```php
$current = $definitions->findRevised(FlagName::of('beta-search'));     // RevisedDefinition: definition + revision

$editor->edit(new EditRequest(
    new FlagDefinition(FlagName::of('beta-search'), true, [
        new PercentageRollout(TargetingAttribute::Tenant, Percentage::of(50)),
    ]),
    'user:42',
    ToggleReason::of('Widening the beta.'),
    $current->revision,                                                // the revision read
));
```

`edit()` exécute :

1. `ToggleAuthorizer::authorizeEdit($request)`.
2. Une demande sans révision est refusée (`InvalidValue`).
3. `WritableFlagDefinitions::findRevised()` ; un indicateur que le magasin de définitions ne contient pas lance
   `UnknownFlag`.
4. Une révision autre que celle stockée lance `EditConflict::staleRevision()` : l'opérateur relit l'indicateur.
5. **L'état actif est conservé** : le `active` de la demande est ignoré et l'état de la définition stockée est utilisé.
   L'activation est une bascule.
6. `ToggleAuditor::recordEdit(EditRecord)` avec les définitions avant et après.
7. `WritableFlagDefinitions::replace($definition, $revision)` n'écrit que si la révision est toujours celle qui a été
   lue, et l'incrémente ; `false` lance `EditConflict`.

Faire passer un déploiement progressif de 20 % à 100 % change autant qu'une bascule : il est donc tracé comme tel.

## Enregistrements

| Enregistrement | Champs |
|---|---|
| `ToggleRecord` | `flag` (`FlagName`), `actor` (string), `before` (bool), `after` (bool), `reason` (`ToggleReason`), `at` (`DateTimeImmutable`). |
| `EditRecord` | `flag`, `actor`, `before` (`?FlagDefinition`, `null` pour une création), `after` (`FlagDefinition`), `reason`, `at`. |

`FlagDefinitionFormat::write()` transforme une définition en tableau de la forme de la configuration, qu'un auditeur
peut encoder en JSON.

## Exceptions

Toutes implémentent `Phil\DddBundle\Domain\Exception\DomainError`, sauf `InvalidValue`, qui est l'exception de
validation du bundle DDD.

| Exception | Lancée lorsque |
|---|---|
| `InvalidValue` | Une raison ou un acteur vide, une création avec une révision, une modification sans révision, une définition mal formée. |
| `ToggleRefused` | L'autorisateur a refusé (`ToggleRefused::forFlag($flag, $why)`). |
| `UnknownFlag` | La bascule d'un indicateur qu'aucun fournisseur ne déclare ; la modification d'un indicateur que le magasin de définitions ne contient pas. |
| `ToggleConflict` | La comparaison-échange ou la première insertion a perdu une course. |
| `EditConflict` | Une révision périmée, un remplacement perdu, ou la création d'un nom déjà déclaré. |

L'administration les traduit en statuts HTTP ([admin.md](admin.md)).

## Autoriser fermement

L'autorisateur décide de la permission dont chaque changement a besoin, et des indicateurs qui en exigent davantage :
forcer l'ouverture d'un circuit (`circuit-open.*`), désactiver un indicateur de genre `ops`, ou tout indicateur listé
par l'application peut exiger une confirmation plus forte (un second facteur, une clé matérielle) en plus de la
permission. Le bundle passe la demande entière : l'autorisateur peut donc examiner le nom de l'indicateur, l'état visé
et l'acteur ([extension-points.md](extension-points.md#toggleauthorizer)).
