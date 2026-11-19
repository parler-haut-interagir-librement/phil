<!-- phil-dev source-sha256=3aab13e703e893c43bb9ab1df50feca498922f6ee35bf82eb4ebf857f1a51074 -->
# Console

Les quatre commandes sont enregistrées quand `symfony/console` est installé.

## phil:api-keys:create

```sh
bin/console phil:api-keys:create alice@example.com "Deploy script" --scope=projects:read --scope=projects:write --expires="+30 days"
```

Crée un jeton pour un propriétaire (l'identifiant par lequel son utilisateur est chargé) et affiche son texte sur une
deuxième ligne à part : copiez-le à ce moment, il ne sera plus montré. `--owner-type` nomme un autre type de propriétaire que `user` :

```sh
bin/console phil:api-keys:create acme "Billing export" --owner-type=organization --scope=billing
```

`--expires` accepte une date (`2027-01-31`) ou
un temps relatif (`+30 days`), lu à partir de l'horloge du bundle; sans cette option, la durée de vie par défaut
s'applique. Une expiration invalide, une portée non déclarée, un nom mal formé ou une expiration au-delà de la durée de
vie maximale, un type de propriétaire non déclaré ou une portée que ce type ne peut pas détenir affiche la raison et
retourne `2`.

## phil:api-keys:list

```sh
bin/console phil:api-keys:list --owner=alice@example.com --limit=20
bin/console phil:api-keys:list --owner=acme --owner-type=organization
```

Un tableau des jetons, du plus récent au plus ancien : identifiant, type de propriétaire, propriétaire, nom, portées, création, expiration,
dernière utilisation et état (`active`, `revoked` ou `expired`). Jamais de secret : le stockage n'en contient aucun.

## phil:api-keys:revoke

```sh
bin/console phil:api-keys:revoke 3kTMd9qXbW1z 8hPq2LmZ0aRt
```

Révoque des jetons par identifiant, sur-le-champ. Un identifiant sans jeton est signalé, les autres sont tout de même
révoqués, et la commande retourne `1`.

```sh
bin/console phil:api-keys:revoke --owner=acme --owner-type=organization
```

Avec `--owner`, révoque plutôt chaque jeton actif de ce propriétaire, et affiche combien; des identifiants avec
`--owner`, ou ni l'un ni l'autre, retournent `2`.

## phil:api-keys:purge-expired

```sh
bin/console phil:api-keys:purge-expired --retention=30
```

Supprime les jetons expirés ou révoqués depuis plus longtemps que la rétention, en jours, `purge_retention` par défaut.
Exécutez-la chaque jour :

```php
use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\Scheduler\RecurringMessage;

RecurringMessage::cron('@daily', new RunCommandMessage('phil:api-keys:purge-expired'));
```
