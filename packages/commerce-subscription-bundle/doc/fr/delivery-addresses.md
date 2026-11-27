<!-- phil-dev source-sha256=4ffad8e133e8837c140972c4a1b955029bf5d28e51227a0bd623199ade0dd465 -->
# Adresses de livraison

Un abonnement qui expédie quelque chose, un numéro imprimé, un cadeau de bienvenue ou la récompense d'un palier, garde
l'endroit où vont ses envois. Chaque commande de l'abonnement fige une copie de cette adresse. L'une et l'autre sont
effacées quelques jours après le dernier envoi. L'adresse est une `Phil\Address\Address` de `phil/address`, vérifiée et
écrite comme son pays l'écrit.

`Application\DeliveryAddresses` porte le tout. Rien ici ne demande d'adresse : l'application n'en demande une que pour
les paliers qui expédient, et l'omet de tous les autres abonnements.

## L'adresse d'un abonnement

```php
use Phil\Address\Address;

$addresses->change($subscription, Address::of('CA', [
    'givenName' => 'Ada',
    'familyName' => 'Lovelace',
    'addressLine1' => '1 rue Saint-Paul',
    'locality' => 'Montréal',
    'administrativeArea' => 'QC',
    'postalCode' => 'H2Y 1G6',
]), 'post');

$entityManager->flush();
```

- **`change()`** garde l'adresse, le code du mode de livraison qui transporte les envois, et un numéro de téléphone
  quand le transporteur du mode en demande un. Elle retourne la `DeliveryAddress` gardée.
- **`remove()`** l'oublie, avec le moment du dernier envoi.
- **`of()`** la lit, ou retourne `null`.

Vérifiez l'adresse avec l'`AddressValidator` de `phil/address`, ou avec la contrainte `ValidAddress` de
`phil/address-bundle` dans un formulaire, avant de la remettre ici. `change()` ne vérifie que le mode et le numéro de
téléphone.

### Le numéro de téléphone

Un numéro de téléphone n'est gardé que **si le transporteur du mode de livraison en demande un** :

- un mode listé dans `delivery_addresses.phone_required_methods` refuse une adresse sans numéro, avec
  `SubscriptionRefused::phoneRequired()` ;
- tout autre mode ne garde aucun numéro, même quand on en donne un.

Le numéro s'écrit en E.164, comme `+15145550123`. Lisez ce que l'abonné a saisi avec `phil/phone`, et remettez le
numéro normalisé.

```yaml
phil_commerce_subscription:
    delivery_addresses:
        phone_required_methods: [courier]
```

Une application dont les transporteurs en décident autrement, par pays ou par poids par exemple, déclare un service qui
implémente `Application\Contract\PhoneRequirement`, avec un alias sous cette interface.

## La copie figée dans chaque commande

Chaque commande que `Subscriptions` passe, la commande d'une période et la commande d'un changement sur-le-champ, fige
l'adresse de l'abonnement, quand il en a une, dans une rangée de `phil_commerce_subscription_order_address` indexée par
le numéro de la commande. L'abonné qui déménage ne change que l'adresse des commandes suivantes : la commande déjà
passée garde l'adresse avec laquelle elle a été passée.

```php
$address = $addresses->ofOrder('2026-0042');   // the DeliveryAddress frozen, or null
```

- **`freeze()`** est ce que `Subscriptions` appelle. Appelée de nouveau pour la même commande, elle retourne la copie
  déjà figée et ne change rien.
- **`shipped($orderNumber, $at)`** note que l'envoi de la commande est parti, sur la copie et sur son abonnement.
  L'effacement compte à partir de ce moment. Appelez-la quand le transporteur prend le colis, à partir de l'étiquette de
  `phil/commerce-logistics-bundle` par exemple.
- **`copiesOf($subscriber)`** liste les copies d'un abonné, de la plus ancienne à la plus récente.

## Effacement

Une adresse est effacée `delivery_addresses.retention_days` après son dernier envoi, 90 jours par défaut :

| Quoi | Effacée quand |
|---|---|
| L'adresse d'un abonnement | L'abonnement est terminé, et sa fin et son dernier envoi, s'il y en a un, sont tous deux plus vieux que la durée de conservation |
| La copie dans une commande | Son envoi est parti il y a plus que la durée de conservation ; sans envoi noté, elle a été figée il y a plus que la durée de conservation |

Un abonnement actif garde son adresse, quelle que soit la date de son dernier envoi, puisque ses prochains envois en ont
besoin.

`purge($at)` efface ce qui a dépassé la durée de conservation à `$at`, par lots de `delivery_addresses.purge_batch`,
jusqu'à ce qu'un lot revienne court. `erase($subscriber)` efface toutes les adresses et toutes les copies d'un abonné.
Les deux écrivent sur-le-champ, et les deux sont **idempotentes** : relancée, ou lancée par deux travailleurs en même
temps, la deuxième exécution ne trouve plus rien et répond par des zéros.

### La commande et la planification

```sh
php bin/console phil:commerce-subscription:purge-addresses
php bin/console phil:commerce-subscription:purge-addresses --limit=100
```

Avec `symfony/scheduler`, la planification `phil_commerce_subscription_addresses` envoie un message
`PurgeDeliveryAddresses` une fois par jour, que `PurgeDeliveryAddressesHandler` traite. Consommez-le avec :

```sh
php bin/console messenger:consume scheduler_phil_commerce_subscription_addresses
```

Le message ne porte rien : le gestionnaire lit l'heure dans l'horloge `clock` du conteneur et la durée de conservation
dans la configuration.

### Plusieurs travailleurs

Avec `symfony/lock` et le service `lock.factory`, que FrameworkBundle enregistre quand un stockage de verrous est
configuré, la planification s'exécute sous un verrou, si bien que plusieurs travailleurs qui la consomment envoient
chaque purge une seule fois. La commande prend un verrou à elle et laisse finir une purge déjà en cours. Le stockage des
verrous doit être partagé par tous les travailleurs, Redis ou la base de données plutôt que des fichiers sur un seul
serveur.

```yaml
phil_commerce_subscription:
    delivery_addresses:
        lock_factory: my.lock.factory   # another LockFactory; null for none
```

Sans verrou, deux travailleurs peuvent purger en même temps : la purge reste correcte, chaque rangée étant effacée une
seule fois, et seul le travail est fait deux fois.

## Demandes d'accès et d'effacement

Avec `phil/privacy-bundle` enregistré, `Infrastructure\Privacy\DeliveryAddressPrivacy` répond à ses demandes d'accès et
d'effacement sous le module `commerce_subscription.delivery_addresses`, la personne concernée étant l'identifiant de
l'abonné :

- l'export donne l'adresse de chaque abonnement, avec son dernier envoi, et chaque copie, avec les moments où elle a été
  figée et expédiée ;
- l'effacement vide l'adresse de chaque abonnement (`Redacted`, l'abonnement reste) et supprime les copies (`Deleted`).
  Relancé, il rapporte les mêmes effets.

## Stockage

L'abonnement garde son adresse dans les colonnes `delivery_address` (JSON) et `last_shipped_at` de
`phil_commerce_subscription`, qui acceptent `NULL` ; les copies vivent dans `phil_commerce_subscription_order_address`.
Lancez `doctrine:migrations:diff` après la mise à jour : la migration ajoute les deux colonnes nullables, si bien que les
abonnements existants continuent de fonctionner sans adresse, et crée la table des copies.

## Lire l'adresse ailleurs

Un autre paquet lit l'adresse par `DeliveryAddresses::of()` pour un abonnement et `ofOrder()` pour une commande, derrière
un port qui lui est propre : une liste d'envois à préparer, une étiquette, un export pour un transporteur. Il ne lit
jamais les colonnes.
