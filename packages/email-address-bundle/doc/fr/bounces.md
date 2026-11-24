<!-- phil-dev source-sha256=e85275a9394954558673e6235fa417735f95e64ba9f65d4cb0193bda540c6d39 -->
# Rebonds

Les règles de rebond décident ce qu'un rebond ou une plainte fait à son adresse, quelle que soit son arrivée :
un événement du webhook d'un fournisseur de courriel, un message brut que le serveur de courriel envoie à une commande,
ou un message de la boîte des rebonds. Chaque entrée transforme ce qu'elle reçoit en avis — un par adresse —, et
`BounceRules` décide chacun.

## Les règles

| Avis | Décision |
|---|---|
| Un rebond définitif : la boîte n'existe pas | `Suspend` |
| Un rebond temporaire sous le seuil dans la fenêtre | `Count` |
| Le rebond temporaire qui atteint le seuil dans la fenêtre | `Suspend` |
| Une plainte ou une demande de départ, d'un expéditeur authentifié | `Unsubscribe` |
| Une plainte ou une demande de départ dont aucun authentificateur ne répond | `Review` |
| Un rapport non compris, ou qui ne nomme ni adresse ni destinataire | `Review` |
| Le même avis reçu de nouveau dans la fenêtre | `Duplicate`, et rien d'autre |

La fenêtre glisse : avec un seuil de 3 et une fenêtre de `P30D`, le troisième rebond temporaire en trente jours
suspend l'adresse. Une suspension ou un désabonnement oublie les rebonds temporaires comptés ; une révision les garde.

Un avis est désigné par son rapport et son adresse : le même rapport reçu de nouveau — un fournisseur qui réessaie son
webhook, un message envoyé deux fois à la commande — est un `Duplicate`. Un rapport est désigné par l'identifiant
d'événement du fournisseur, ou par l'empreinte du message brut.

Une plainte ne désabonne que si l'expéditeur est authentifié, parce que n'importe qui peut écrire un rapport d'abus
dans la boîte des rebonds. Un rapport d'abus est authentifié quand l'authentificateur configuré en répond — par défaut
`AuthenticationResults` de `phil/bounce-parser`, qui lit le résultat DKIM que votre propre serveur de courriel a
consigné. Un événement du webhook d'un fournisseur est authentifié : le composant Webhook a vérifié la signature du
fournisseur avant que le bundle ne le voie.

## L'événement

Chaque décision, sauf `Duplicate`, est répartie comme un événement `BounceDecided`. Une infolettre, une liste de
contacts ou l'application l'écoute et applique la décision à ses propres enregistrements :

```php
use Phil\EmailAddressBundle\Domain\BounceDecision;
use Phil\EmailAddressBundle\Domain\Event\BounceDecided;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final readonly class ApplyBounceDecisions
{
    public function __construct(
        private Subscribers $subscribers,
    ) {
    }

    public function __invoke(BounceDecided $event): void
    {
        $subscriber = null !== $event->notice->recipientId
            ? $this->subscribers->get($event->notice->recipientId)
            : $this->subscribers->byFingerprint($event->notice->fingerprint);

        match ($event->decision) {
            BounceDecision::Suspend => $subscriber?->suspend(),
            BounceDecision::Unsubscribe => $subscriber?->unsubscribe(),
            BounceDecision::Review => $this->subscribers->flagForReview($subscriber),
            default => null,
        };
    }
}
```

L'événement porte l'avis : son genre, sa source, l'adresse telle que le rapport la donne, l'empreinte de sa forme
normalisée, l'identifiant du destinataire quand l'application l'a signé, et les rebonds temporaires comptés. Il reste
dans le processus.

## Le message

Avec `bounces.message_bus`, chaque décision est aussi transmise sous forme de `BounceDecidedMessage`, pour un handler
dans un worker. Le message porte des identifiants seulement — la décision, le genre, l'empreinte, l'identifiant du
destinataire et la clé de l'avis —, jamais l'adresse. Le handler retrouve l'adresse par son empreinte,
`EmailAddresses::fingerprint()` des adresses qu'il garde, ou par l'identifiant du destinataire.

```yaml
framework:
    messenger:
        routing:
            Phil\EmailAddressBundle\Application\Message\BounceDecidedMessage: async
```

## Le webhook d'un fournisseur

Le composant Webhook de Symfony reçoit la requête du fournisseur, vérifie sa signature et l'analyse par le pont Mailer
du fournisseur en un `MailerDeliveryEvent` ou un `MailerEngagementEvent`. Nommez le webhook dans
`bounces.remote_event_consumers` et le `MailerEventConsumer` du bundle consomme ses événements :

```yaml
framework:
    webhook:
        routing:
            mailer_provider:
                service: 'mailer.webhook.request_parser.<provider>'
                secret: '%env(MAILER_WEBHOOK_SECRET)%'

phil_email_address:
    bounces:
        remote_event_consumers: [mailer_provider]
        recipient_metadata: recipient
```

| Événement | Avis |
|---|---|
| `bounce`, `dropped` | définitif |
| `deferred` | temporaire |
| `spam` | plainte, authentifiée |
| `unsubscribe` | demande de départ, authentifiée |
| `received`, `delivered`, `open`, `click` | aucun |

Avec `recipient_metadata`, l'identifiant du destinataire est lu dans les métadonnées que l'application a posées sur le
message.

## Un message brut

Le serveur de courriel envoie chaque message de l'adresse des rebonds à la commande, qui lit l'entrée standard — ou un
fichier —, l'analyse avec `phil/bounce-parser` et affiche une décision par ligne, jamais une adresse :

```text
# /etc/postfix/master.cf
bounces  unix  -  n  n  -  -  pipe
  user=app argv=/srv/app/bin/console phil:email-address:read-bounce
```

```sh
bin/console phil:email-address:read-bounce var/bounces/2026-11-20.eml
```

La commande lit au plus un octet au-delà de la limite de l'analyseur, qui lit alors ce qu'il peut. Elle se termine par
un succès pour tout message que l'analyseur a lu, afin que le serveur de courriel ne renvoie pas le rebond du rebond ;
elle échoue seulement quand le fichier ne peut pas être lu.

## La boîte des rebonds

`BounceMailbox` est le port de la boîte où reviennent les rebonds : un adaptateur va chercher les messages pas encore
traités et acquitte chacun une fois ses avis décidés. Un message dont la décision lève une exception n'est pas acquitté
et est relu par la relève suivante.

```sh
bin/console phil:email-address:poll-mailbox --limit=100
```

Le bundle fournit `MaildirBounceMailbox`, pour un serveur de courriel qui livre l'adresse des rebonds dans un Maildir :
il lit `new/`, le plus ancien d'abord, et déplace un message acquitté dans `cur/` avec l'indicateur « vu ».

```yaml
phil_email_address:
    bounces:
        maildir: '/var/mail/bounces'
```

Le bundle ne lit lui-même aucune boîte IMAP. Une boîte gardée sur un autre serveur parvient au Maildir par les outils
qui récupèrent déjà le courrier : fetchmail ou getmail l'interroge en IMAP et dépose chaque message dans le Maildir, et
un serveur Postfix lui livre l'adresse de retour par sa livraison locale (`home_mailbox = Maildir/`). Le port
`BounceMailbox` est la place d'un adaptateur IMAP dans un paquet distinct : il se brancherait par `bounces.mailbox`,
sans changer les commandes ni les règles.

## Le chemin de retour

Avec `bounces.verp`, le bundle lit l'étiquette du chemin de retour avec lequel chaque message a été envoyé,
`bounces+TAG@example.net`. Une étiquette signée avec la clé — `Verp::encodeSigned()` de `phil/bounce-parser` — désigne
le destinataire : l'avis porte son identifiant, et les règles comptent les rebonds temporaires du destinataire même
quand le rapport ne nomme aucune adresse. Une étiquette qui n'est pas signée avec la clé est ignorée.
