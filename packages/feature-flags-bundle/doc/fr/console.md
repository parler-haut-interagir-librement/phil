<!-- phil-dev source-sha256=3f45c159e212a8a9305983e66b656db83d9bd348453774b19b293602e222dd14 -->
# Console

## `phil:feature-flags:list`

Liste les indicateurs de fonctionnalité avec leur configuration actuelle (`ListFlagsCommand`, par-dessus `FlagCatalog`
et l'horloge configurée). Toujours enregistrée.

```bash
bin/console phil:feature-flags:list
bin/console phil:feature-flags:list --expired
bin/console phil:feature-flags:list --fail-on-expired
```

| Option | Effet |
|---|---|
| *(aucune)* | Liste chaque indicateur ; avertit, en terminant quand même avec 0, lorsque certains ont dépassé leur date d'expiration. |
| `--expired` | Ne liste que les indicateurs qui ont dépassé leur date d'expiration. |
| `--fail-on-expired` | Affiche une erreur et termine avec 1 (`Command::FAILURE`) lorsqu'un indicateur a dépassé sa date d'expiration. |

Les deux options se combinent.

### Colonnes

| Colonne | Contenu |
|---|---|
| Flag | Le nom. |
| State | `active` ou `inactive`, l'état effectif ; `(stored)` lorsqu'il vient du magasin d'états. |
| Kind | `release`, `experiment`, `ops`, `permission` ou vide. |
| Targeting | `everyone` (aucune règle) ou `N rule(s)` ; pour une expérience, `; A/B by <attribute>: <variant> <weight>%, …`. |
| Owner | Le responsable ou vide. |
| Expires on | `YYYY-MM-DD`, suivi de `(expired)` lorsque la date est dépassée. |
| Source | L'identifiant de service du fournisseur qui déclare l'indicateur. |

Les rangées sont triées par nom. Seuls les fournisseurs **énumérables** contribuent : un fournisseur qui implémente
seulement `FlagProvider` (un service distant qui répond un indicateur à la fois) n'a aucune rangée
([extension-points.md](extension-points.md#enumerableflagprovider)).

### Indicateurs illisibles

Un indicateur dont le fournisseur contient la définition sans pouvoir la lire (`FlagCatalog::unreadable()`) n'a pas de
rangée ; après le tableau, un avertissement nomme chacun avec sa source et sa raison, et la commande sort tout de même
avec 0 :

```text
 [WARNING] 1 flag cannot be read: broken-row (phil_feature_flags.provider.doctrine): The stored definition of
           "broken-row" is not JSON: Syntax error
```

### Message d'expiration

```text
 [WARNING] 1 flag is past its expiry date: forgotten.
 [ERROR] 2 flags are past their expiry date: forgotten, old-banner.
```

La seconde forme est affichée avec `--fail-on-expired`. Un indicateur est expiré à partir de minuit UTC de son jour
d'expiration ([metadata-and-expiry.md](metadata-and-expiry.md#expiration)).

### En intégration continue

```bash
bin/console phil:feature-flags:list --fail-on-expired
```

La construction échoue tant qu'un indicateur a survécu à son objectif, ce qui transforme un indicateur oublié en tâche
plutôt qu'en branche de plus dans le code. La liste lit aussi le magasin en base de données lorsqu'il est activé : en
intégration continue, elle a donc besoin de la même configuration de base de données que l'application, ou ne lit que le
fournisseur de configuration si `doctrine` est désactivé dans l'environnement d'intégration continue.

## Autres commandes

Le bundle ne fournit aucune commande pour basculer, créer ou modifier un indicateur : les changements passent par
l'administration ou par le code de l'application qui appelle `FlagToggler` et `FlagEditor` avec un acteur et une raison.
