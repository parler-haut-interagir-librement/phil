<!-- phil-dev source-sha256=34ce3df04074033b36f2d8f7e1e507a86784d7e77ba9a1f3a167079b69831d54 -->
# Terminaux connectés

Un terminal de carte connecté est piloté par le serveur, jamais par la caisse. La caisse demande un débit au serveur ;
le serveur crée le paiement du noyau et demande au processeur du terminal, par un adaptateur, de l'afficher sur
l'appareil ; la notification signée du processeur règle le paiement, et la session le compte. La caisse n'exécute aucun
script du processeur, et la clé secrète du processeur reste sur le serveur.

## Enregistrer un terminal

Un terminal est posé à une caisse et nomme l'adaptateur qui parle à son processeur ainsi que l'identifiant que le
processeur donne à l'appareil :

```php
$registers->addTerminal('t1', 'front', adapter: 'fake', device: 'tmr_FRONT', label: 'Counter reader');
$registers->addTerminal('t2', 'phone-ada', adapter: 'fake', device: 'tmr_TAP', label: 'Tap reader', pinEntry: false);
```

Un terminal prend par défaut les paiements qui demandent un NIP, et seule la caisse principale en a un : le terminal
d'une caisse mobile est enregistré avec `pinEntry: false`, et une caisse principale dotée d'un terminal à NIP ne peut
pas devenir mobile ([devices.md](devices.md)). `Terminal::retire()` en retire un du service ; il garde ses paiements.

## Prendre un débit

```php
$payment = $terminalPayments->charge($order, $session, 't1', $clock->now());          // all that is left to pay
$payment = $terminalPayments->charge($order, $session, 't1', $clock->now(), $amount); // or part of it, split
```

`charge()` vérifie que la session est ouverte et que le terminal est à sa caisse, lance un paiement du noyau sous la
méthode des terminaux — `card_present`, l'option `terminals.method` —, et demande à l'adaptateur de le présenter. Le
débit porte le montant du paiement lui-même, jamais un montant tiré de la requête de la caisse. Si l'adaptateur échoue,
le paiement est abandonné sur-le-champ, pour qu'il ne retienne plus sa part de la commande, et l'échec est levé.

La caisse montre ensuite ce que dit le serveur : elle interroge l'état du paiement, ou l'écoute par Mercure. Un débit
encore sur l'appareil est retiré par `cancel($payment)`, qui demande à l'adaptateur de l'annuler et abandonne le
paiement ; une notification qui arrive plus tard s'applique quand même, comme celles du noyau le font toujours.

## Notifications

Le processeur envoie ses événements à `TerminalNotificationController` ([installation.md](installation.md#routage)).
`TerminalPayments::notify()` les remet à `Payments::notify()` du noyau, par `TerminalGateway`, qui demande à chaque
adaptateur à tour de rôle s'il reconnaît la requête — par son en-tête de signature — et laisse le premier qui la
reconnaît la vérifier et la lire. Une fois le paiement capturé, la session le compte, une seule fois, avec la référence
du processeur comme autorisation ; un paiement échoué ou annulé n'est pas compté.

| Réponse | Sens |
|---|---|
| `200` | Appliquée, déjà appliquée, refusée par l'état du paiement, ou pour un paiement que personne n'a présenté |
| `400` | Aucun adaptateur ne reconnaît la notification, ou sa signature ne se vérifie pas |
| `413` | Un corps de plus de 64 Kio |

## Remboursements

`Payments::refund()` du noyau interroge `TerminalGateway`, qui demande à l'adaptateur du terminal du paiement de le
rembourser sous la référence du processeur, avec la clé d'idempotence du remboursement — la même à chaque essai. Comme
avec tout processeur, le remboursement est enregistré quand la notification dit qu'il a eu lieu.

## Écrire un adaptateur

Un adaptateur par processeur, dans l'application ou dans un paquet à lui, nommé d'après le service du processeur plutôt
que d'un nom de paquet qui lui serait propre. Il implémente `Application\Contract\PaymentTerminal`, étiqueté
`phil_commerce_pos.payment_terminal` par l'autoconfiguration :

| Méthode | Contrat |
|---|---|
| `name(): string` | Le nom que donnent les terminaux, jusqu'à 32 lettres minuscules, chiffres ou `_` |
| `present(TerminalCharge $charge): string` | Demande au processeur d'afficher le débit sur `$charge->device`, en portant `$charge->payment` pour que ses notifications le nomment ; retourne la référence que le processeur donne à la demande, de 1 à 255 caractères ASCII visibles |
| `cancel(string $device, string $processorReference): void` | Retire une demande que l'appareil affiche encore |
| `refund(string $processorReference, Money $amount, string $idempotencyKey): void` | Demande un remboursement, en donnant la clé au processeur comme clé d'idempotence pour qu'un remboursement redemandé soit fait une fois ; la notification l'enregistre |
| `recognises(array $headers): bool` | Si une requête est de ce processeur, par un en-tête qu'il envoie toujours |
| `read(string $payload, array $headers): PaymentNotification` | Vérifie la signature — avec `hash_equals()` ou la bibliothèque du processeur — et lit l'événement ; sa référence externe est l'identifiant du paiement que le débit portait. Lève `InvalidNotification` autrement |

Un adaptateur ne garde aucun état entre deux requêtes : le client du processeur et son secret sont ses seules
propriétés.

## Le terminal factice

`Infrastructure\Terminal\FakePaymentTerminal`, nommé `fake`, fait semblant : il se souvient de ce qu'on lui a demandé et
signe les notifications qu'un test lui demande avec un HMAC-SHA256 du corps sous le secret du noyau, dans l'en-tête
`x-fake-terminal-signature`. Il n'est enregistré que lorsque `terminals.fake` vaut `true` — pour les tests, les
démonstrations et le développement d'une caisse —, et le noyau le remet à zéro entre deux requêtes.

```php
[$payload, $headers] = $fake->notification('evt-1', $payment->id, PaymentEvent::Captured);
```
