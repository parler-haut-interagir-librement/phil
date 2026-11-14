<!-- phil-dev source-sha256=c9bdf290d70a879e3030bcd832de0b5713f371b50ed5c9e1ac9bd65488f503c0 -->
# Canaux

Un type nomme les canaux de chaque destinataire ; le destinataire peut désactiver n'importe lequel d'entre eux. Le
répartiteur procède alors ainsi :

| Canal | Livraison |
|---|---|
| `site` | Gardée dans le centre du destinataire, regroupée dans une notification semblable non lue quand le type le dit, et affichée comme message éphémère avec le nouveau nombre de non-lues |
| `email` | Le canal courriel de Symfony Notifier, à l'adresse de l'annuaire ; laissée au résumé quand le destinataire en a choisi un, que le type le permet et que le centre garde la notification |
| `web_push` | Le `WebPushSender` : celui du bundle, sur minishlink/web-push, avec `web_push` activé, ou celui de l'application |
| `sms`, `push`, `chat`, `desktop`… | Le canal de Symfony Notifier, à l'adresse de l'annuaire ; un transport après une barre oblique, `sms/twilio` |

## Canaux de diffusion

`broadcast` nomme les canaux où une notification est publiée une fois, quels que soient ses destinataires : un canal
Slack ou Discord par `chat/slack` ou `chat/discord`, avec les ponts de Symfony Notifier. Son texte est rédigé dans la
locale par défaut : le titre, une ligne vide et le corps, puisque la plupart des ponts publient le texte seul d'un
message — le webhook de Discord laisserait tomber le corps. Le pont de Slack rend aussi le titre et le corps comme deux
blocs, avec ce texte en repli.

## Heures de silence

Pendant les heures de silence d'un destinataire, le message éphémère et chaque canal sauf le centre et le courriel sont
retenus : la notification attend dans le centre. Les heures de silence vont de `start` inclus à `end` exclu, dans le
fuseau horaire du destinataire, au-delà de minuit quand `start` vient après `end`.

## Refus

`type:channel` désactive un canal pour un type, `*:channel` pour chaque type. Le centre lui-même peut être désactivé.

## Web Push

Avec `web_push` activé, le bundle garde les navigateurs de chaque destinataire et leur envoie ; une application qui
nomme son propre `web_push_sender` garde les abonnements, les points de terminaison et le bouton, et envoie comme elle
l'entend.

### L'activer dans un navigateur

`<twig:PhilNotifications:WebPush />` montre au destinataire courant un bouton à bascule, `aria-pressed`, dans un
navigateur qui peut enregistrer un *service worker* et recevoir des messages poussés ; ailleurs, et sans JavaScript, rien
ne s'affiche. Son contrôleur Stimulus enregistre le *service worker* du bundle, demande la permission quand le membre
appuie sur le bouton — jamais avant —, abonne le navigateur avec la clé publique VAPID de l'application et envoie
l'abonnement à `push_routes.php`, avec le jeton CSRF du centre. Quand on appuie de nouveau, il oublie l'abonnement des
deux côtés. Une région d'état dit ce qui s'est passé, une permission refusée comprise.

Les points de terminaison ne répondent qu'au destinataire courant, vérifié comme le sont les changements du centre :

| Point de terminaison | Réponse |
|---|---|
| `POST /push/subscribe` | `201`, l'abonnement gardé ; `422` quand ce n'est pas un abonnement ou que son service de poussée n'est pas autorisé ; `413` au-delà de 4096 octets |
| `POST /push/unsubscribe` | `204`, le point de terminaison oublié ; `404` quand le destinataire ne l'avait pas abonné |

### Ce qui est gardé

Un abonnement est le point de terminaison du navigateur et deux clés, lus par `PushSubscription`. Le point de
terminaison est une adresse HTTPS d'un hôte public — une adresse IP, `localhost`, des identifiants de connexion ou le
port 0 sont refusés — sur l'un des `push_services`, les services de poussée de Chrome, Edge, Firefox et Safari par
défaut : le site ne poste à aucune autre adresse qu'un membre pourrait nommer. Chaque destinataire garde ses dix derniers
navigateurs ; un point de terminaison abonné de nouveau passe au destinataire qui l'a fait, comme quand quelqu'un d'autre
ouvre une session dans le même navigateur. `PushSubscriptions::forgetRecipient()` les oublie tous quand un membre part.

### L'envoi

`MinishlinkWebPushSender` envoie une notification à chaque navigateur de son destinataire avec minishlink/web-push :
chiffrée pour ce navigateur (RFC 8291), signée avec les clés VAPID (RFC 8292), postée avec Symfony HttpClient — réseaux
privés refusés, redirections non suivies, dix secondes pour répondre. Un service de poussée qui répond `404` ou `410` ne
connaît plus l'abonnement, qui est oublié ; toute autre réponse est journalisée avec l'hôte du service de poussée, jamais
le point de terminaison, qui est un secret de son navigateur. Les heures de silence et les refus (`*:web_push`)
s'appliquent comme à n'importe quel canal.

`WebPushMessage::of()` donne ce qui est envoyé pour une notification d'un type :

| Propriété | Ce qu'elle contient |
|---|---|
| `payload` | Le JSON que le *service worker* affiche : `id`, `type`, `title`, `body`, `url`, `count` ; au plus 3993 octets, ce que contient un enregistrement chiffré de 4096 octets, le corps étant d'abord raccourci par une ellipse, puis le titre, puis l'URL omise |
| `urgency` | L'en-tête `Urgency` : `high` pour un type `urgent` ou `high`, `normal` pour `medium`, `low` pour `low` |
| `topic` | L'en-tête `Topic` d'un type qui se regroupe — 32 caractères sûrs pour une URL, tirés d'une empreinte de son groupe —, afin qu'un service de poussée remplace un message plus ancien du même groupe qu'il détient encore ; `null` sinon |
| `ttl` | L'en-tête `TTL` : `web_push.ttl`, un jour par défaut, quatre semaines au plus |

### Le *service worker*

`assets/worker/push-worker.js` affiche chaque message comme une notification marquée de l'identifiant de sa notification,
de sorte qu'un message ultérieur d'une notification regroupée la remplace, et ouvre sa page au clic — une page de ce
site seulement. AssetMapper le sert sous `phil-notifications-worker/`, sa portée étant le dossier d'où il est servi : il
traite les messages poussés et laisse en paix les pages du site. Une application qui a son propre *service worker* donne
à `web_push.worker` l'URL de ce *worker* et y charge le script du bundle avec `importScripts()`, à l'URL qu'AssetMapper
donne à `phil-notifications-worker/push-worker.js` ; ses deux écouteurs, `push` et `notificationclick`, se placent à côté
de ceux du *worker*.

### Un expéditeur propre à l'application

```php
final readonly class BrowserPush implements WebPushSender
{
    public function __construct(private NotificationTypes $types, private PushSubscriptions $subscriptions) {}

    public function send(string $recipient, RenderedNotification $notification): void
    {
        $message = WebPushMessage::of($notification, $this->types->get($notification->notification->type));

        foreach ($this->subscriptions->of($recipient) as $subscription) {
            // Encrypt $message->payload for $subscription and post it with its urgency, topic and time to live.
        }
    }
}
```

## Adresses

Les canaux qui ont besoin d'une adresse la demandent au `RecipientDirectory` au moment de l'envoi de la notification,
jamais avant : un `EmailRecipientInterface` pour le courriel, un `SmsRecipientInterface` pour le SMS. Un destinataire que
l'annuaire ne connaît pas est sauté et journalisé.
