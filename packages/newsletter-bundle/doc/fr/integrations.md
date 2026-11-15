<!-- phil-dev source-sha256=e6cb0bebfa7c0a8f93b4fdcfd2d84414a3242b75d861f228a9f12eb3e5b7bbea -->
# Intégrations

## Les automatisations

Avec `phil/automations` installé, le bundle enregistre, avec les étiquettes `phil.automations.trigger` et
`phil.automations.action` :

| Déclencheur | Démarre sur | Options |
|---|---|---|
| `newsletter.subscribed` | Un abonnement, confirmé ou non | `list`, une clé de liste |
| `newsletter.confirmed` | Un abonnement confirmé, ou une liste rejointe | `list` |
| `newsletter.unsubscribed` | Une liste quittée, ou toutes les listes | `list` |
| `newsletter.paused`, `newsletter.resumed` | Une pause, sa fin | — |
| `newsletter.link_clicked` | Un clic sur une campagne qui compte les clics par destinataire | `campaign` |

L'exécution porte sur l'abonné ; sa charge utile contient l'identifiant de l'abonné, la clé de la liste, le site et
l'identifiant externe.

| Action | Fait | Défaite par |
|---|---|---|
| `newsletter.send` | Met en file un gabarit, par sa clé, pour l'abonné ; une fois par étape d'une exécution | — un message ne peut pas être retiré |
| `newsletter.subscribe` | Ajoute l'abonné, déjà confirmé, à une liste de son site | Son retrait, quand l'étape l'a ajouté |
| `newsletter.unsubscribe` | Retire l'abonné d'une liste | Son rajout, quand l'étape l'a retiré |

Un message mis en file par une automatisation est vérifié à son envoi : un abonné qui a quitté ou s'est mis en pause
entre-temps ne reçoit rien. Voir les séquences de courriel de `phil/automations`.

## Les demandes de vie privée

Avec `phil/privacy-bundle` installé, `NewsletterPrivacy` répond à ses demandes d'accès et d'effacement pour le module
`newsletter`, la personne concernée étant l'identifiant externe que l'application a donné à ses abonnés, sur chaque
site. L'exportation liste chaque abonnement — adresse, état, dates, listes —, les messages envoyés et les clics comptés
par destinataire ; l'effacement les supprime tous, et laisse une adresse supprimée sous forme de son empreinte à clé.

## Les webhooks d'audience

Avec `phil/standard-webhooks-bundle` installé et `webhooks` configuré, chaque `AudienceChanged` est envoyé, par
Messenger, aux récepteurs qui ont demandé son type, signé par les clés de ce bundle :

```json
{"type": "newsletter.subscriber.confirmed", "timestamp": "2026-11-13T10:00:00+00:00",
 "data": {"subscriber": "0192…", "external_id": "user-42", "site": "default", "list": "news"}}
```

Les types d'événement sont `subscribed`, `confirmed`, `unsubscribed`, `paused`, `resumed`, `bounced`, `complained` et
`erased`. Le corps ne contient jamais l'adresse. Chaque tentative d'un événement vers un récepteur porte le même
`webhook-id` ; un échec qui vaut une nouvelle tentative est laissé aux nouvelles tentatives de Messenger, un autre est
journalisé et abandonné.

## Les segments

Les destinataires d'une campagne viennent du service `SegmentResolver`. Celui du bundle, `ListRecipients`, envoie à
chaque abonné de la liste qui peut recevoir, et refuse une campagne qui nomme un segment. Un paquet d'audience le
remplace par un résolveur qui connaît les segments.
