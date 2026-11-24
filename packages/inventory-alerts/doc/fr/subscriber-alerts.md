<!-- phil-dev source-sha256=3925bb888b11a942776d7b1744f5fd05c8f915d686f8ad21f8e8e1bf934f2fad -->
# Alertes aux abonnés

## S'abonner

Un abonnement ne se fait que sur demande explicite d'un client — un formulaire qu'il remplit avec son adresse —,
jamais à partir d'un compte, d'une commande ou d'une liste d'infolettre.

```php
use Phil\EmailAddress\EmailAddress;
use Phil\InventoryAlerts\Subscription\Price;

$email = EmailAddress::parse($form['email']);

$subscriptions->subscribeBackInStock('MUG-BLUE', $email, $now);
$subscriptions->subscribePriceDrop('MUG-BLUE', $email, new Price(1_999, 'CAD'), $now); // at 19.99 CAD or less
```

Une adresse a au plus un abonnement actif par UGS (unité de gestion de stock) et par type. S'abonner de nouveau renvoie
l'abonnement de retour en stock qu'elle a déjà ; un abonnement de baisse de prix garde sa place et prend le nouveau
seuil. Un `Price` est en unités mineures d'une devise ISO 4217, et un prix n'est au seuil ou en dessous que dans la
même devise.

## Vagues

Quand des unités sont reçues, l'application appelle `SubscriberAlerts::restocked()` avec les unités disponibles. La
vague contient les premiers abonnés actifs dans l'ordre où ils se sont abonnés, autant que la `WavePolicy` en donne
pour ces unités :

```text
wave = min(maximum, max(minimum, units × subscribers per unit))
```

Avec la politique par défaut — 2 abonnés par unité, 10 au moins, 500 au plus —, 3 unités préviennent 10 personnes et
1 000 unités en préviennent 500. L'application rappelle plus tard, une fois que les premiers acheteurs ont eu leur
chance, tant qu'il reste des unités et que la dernière vague était `full` ; ceux déjà prévenus ne le sont pas deux
fois. Aucune unité disponible ne prévient personne.

Quand un prix change, `SubscriberAlerts::priceChanged()` prévient les abonnés dont le seuil est au nouveau prix ou
au-dessus, jusqu'au maximum d'une vague ; l'application rappelle aussitôt tant que la vague était `full`.

```php
$wave = $alerts->restocked('MUG-BLUE', available: 3, at: $now);

if (null !== $wave && $wave->full) {
    // Schedule the next wave in an hour if units remain.
}
```

La bibliothèque vérifie de nouveau ce que renvoie le magasin de données — actif, même UGS et même type, seuil atteint
— et le retrie par date d'abonnement puis par identifiant.

## Après l'alerte

`SubscriberNotifier` envoie la vague ; ses abonnements sont ensuite marqués comme notifiés, et ne sont plus actifs. Si
le notificateur lève une exception, rien n'est marqué et la vague est renvoyée au prochain appel. Un abonnement sert
une seule alerte : une fois celle-ci livrée, `Subscriptions::purgeNotified($before)` le supprime, adresse comprise.

Une vague porte le type, l'UGS, les abonnements et, pour une baisse de prix, le nouveau prix — jamais les unités
restantes. Une alerte dit que l'UGS est de retour ou moins chère ; elle ne presse jamais par une rareté.

## Désabonnement en un clic

Chaque alerte porte un lien de désabonnement avec le jeton de son abonnement :

```php
$token = $subscriptions->unsubscribeToken($subscription);
// https://shop.example/alerts/unsubscribe/{token}, also in the List-Unsubscribe header of the message.

$subscriptions->unsubscribe($token); // deletes the subscription; true for a token this application issued
```

Le jeton est l'identifiant de l'abonnement signé par une empreinte SHA-256 à clé avec le secret remis à
`UnsubscribeTokens` : rien d'autre n'est stocké, et on ne peut pas fabriquer un jeton pour un autre abonnement. Un
lien suivi deux fois ne trouve rien à supprimer et répond quand même vrai. La page qu'ouvre un lien `GET` montre un
bouton qui envoie le jeton par `POST`, pour qu'un analyseur de courriels qui ouvre les liens ne désabonne personne ;
les envois en un clic de la RFC 8058 vont à la même action.
