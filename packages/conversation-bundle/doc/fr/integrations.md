<!-- phil-dev source-sha256=9ae0fffa8432c4653bc8ffc20d8cdb0d335ff37ca92662193105b533a2a194ef -->
# Notifications, temps réel et vie privée

## Notifications

Avec phil/notifications-bundle enregistré, le bundle déclare le type de notification `phil_conversation.message`, ou
celui que nomme `notifications.type`, avec les canaux `site` et `email`, les notifications non lues d'une même
conversation étant regroupées en une seule. Chaque message conservé est notifié à ses destinataires qui n'ont pas mis la
conversation en sourdine ni bloqué l'auteur, l'auteur étant l'acteur.

La notification porte les identifiants de la conversation et du message, jamais le corps. Quand elle est montrée,
`ConversationSubjects` nomme la conversation — son sujet, ou les noms de ses participants actifs — et renvoie à sa page
quand les routes du bundle sont importées ; une conversation effacée depuis n'a pas de libellé. Les titres et les corps
du type sont les traductions de l'application, `phil_conversation.message.title` et `phil_conversation.message.body`,
comme pour tout type du bundle de notifications.

## Temps réel

Avec MercureBundle et `realtime.enabled: true`, chaque message conservé est publié sur un hub Mercure comme une mise à
jour privée, sur les sujets de son auteur et de chaque destinataire actif — `urn:phil:conversation:<identifiant>`, ou
`topic_prefix` suivi de l'identifiant :

```json
{"conversation": "0192f0c1-5c5e-7a3b-9e1d-0123456789ab", "message": "0192f0c1-6a00-7b3b-9e1d-0123456789ab", "sequence": 42}
```

La mise à jour nomme le message, jamais son corps. La page d'une conversation s'abonne au sujet propre au lecteur, par
la fonction `mercure()` de MercureBundle, qui donne aussi au navigateur le témoin qui l'autorise ; le contrôleur
Stimulus `phil--conversation-bundle--thread` charge alors les derniers messages depuis la page du bundle, qui vérifie de
nouveau que le lecteur participe à la conversation, quand une mise à jour de cette conversation arrive. Une mise à jour
d'une autre conversation est ignorée, et un chargement qui échoue laisse les messages tels qu'ils étaient.

L'application autorise chaque participant à s'abonner à son seul sujet. Un hub injoignable — ou le hub intégré de
FrankenPHP hors d'une requête qu'il sert, dans une commande ou un consommateur Messenger — est journalisé comme un
avertissement et ne fait pas échouer le message : il est conservé, et la page le montre quand elle est rechargée. Un
*worker* qui écrit des messages publie par un hub qui a sa propre URL.

## Vie privée

Avec phil/privacy-bundle installé, `ConversationPrivacy` participe à ses demandes d'accès et d'effacement, par les
interfaces de `Phil\PrivacyBundle\Application\Contract`, la personne concernée étant l'identifiant du participant. Une
personne concernée qui ne peut pas être un participant, une adresse courriel, ne détient rien ici.

L'exportation, dans le module `conversations`, donne :

| Clé | Ce qu'elle contient |
|---|---|
| `conversations` | Chaque conversation à laquelle la personne a participé : son identifiant, son genre et son sujet, son arrivée et son départ, si elle l'a mise en sourdine |
| `messages` | Chaque message qu'elle a écrit, ouvert : sa conversation, son identifiant, son instant et son corps |
| `reception` | Qui elle laisse lui écrire, et qui elle a bloqué |

L'effacement vide le corps et l'auteur de chacun de ses messages — les autres gardent la conversation, le message étant
montré comme effacé —, supprime ses participations, ses réglages et ses blocages, son identifiant dans les blocages des
autres, et l'empreinte de la paire de chacune de ses conversations directes, et supprime les conversations restées sans
personne avec leurs messages. Son rapport dit `Redacted` pour `phil_conversation_message` et `Deleted` pour les
participants, les réglages de réception et les blocages.
