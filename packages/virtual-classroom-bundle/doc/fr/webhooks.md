<!-- phil-dev source-sha256=5740dea698cd33b2e5775b1a30fc1cf3c50be158ae7e26ad3e36533aac9fb6f4 -->
# Webhooks

Les serveurs rappellent l'application à des adresses du bundle, chacune portant une **preuve** : le HMAC-SHA-256 de son
fournisseur, de sa salle et de son canal sous `webhook_secret`, 32 caractères en base64url.

| Route | Chemin | Méthodes | Usage |
|---|---|---|---|
| `phil_virtual_classroom_room_webhook` | `/webhook/{provider}/{room}/{channel}/{token}` | GET, POST | Les adresses avec lesquelles une salle est ouverte : `ended` et `events`. |
| `phil_virtual_classroom_webhook` | `/webhook/{provider}/{token}` | POST | L'adresse unique d'un compte, JaaS par exemple. |

Chaque canal de chaque salle a sa propre adresse. BigBlueButton montre aux participants les adresses de ses rappels
`meta_` : l'adresse des événements signés, qu'ils peuvent voir, ne peut ni terminer la salle ni atteindre une autre
salle, et ce qui y parvient doit porter la signature propre au serveur. `phil:virtual-classroom:webhook-url <provider>`
écrit l'adresse d'un compte, à régler dans la console du fournisseur.

## Ce qui se passe

`WebhookController` vérifie la preuve — une preuve fausse donne un 404, comme une adresse qui n'existe pas —, refuse un
corps de plus de 1 Mio par un 413, puis remet la notification au fournisseur, qui vérifie sa propre signature et lit ses
événements. `Classrooms::receive()` les applique :

- les événements d'une salle autre que celle de l'adresse sont refusés, comme une notification falsifiée ;
- les événements d'une salle que l'application ne connaît pas, ou d'une salle sur un autre fournisseur, sont ignorés ;
- `ParticipantJoined` ouvre un séjour, `ParticipantLeft` en termine un, `RoomEnded` termine la salle, `RecordingReady`
  conserve l'enregistrement — listé depuis le serveur quand la notification ne le décrit pas.

| Réponse | Quand |
|---|---|
| 204 | Appliquée, ou rien à appliquer. |
| 401 | Le fournisseur a refusé la notification : sa signature, son âge, sa forme. BigBlueButton cesse alors de réessayer. |
| 404 | Une preuve fausse, un fournisseur inconnu, un fournisseur qui ne lit aucune notification, une salle qui n'est pas un identifiant. |
| 413 | Un corps de plus de 1 Mio. |

Une notification livrée deux fois ne change rien : une arrivée dans un séjour connu n'en ouvre aucun, un départ sans
séjour ouvert n'en termine aucun, un enregistrement connu n'est pas nouveau.

## Par fournisseur

| Fournisseur | Canal | Ce qui arrive |
|---|---|---|
| BigBlueButton | `ended` | La fin de la salle, par GET. |
| BigBlueButton | `events` | Un enregistrement prêt, signé en HS256 ; l'assiduité de la séance, avec son jeton porteur. |
| Jitsi, JaaS | `account` | Des participants qui arrivent et repartent, la salle détruite, un enregistrement téléversé, signés dans `X-Jaas-Signature`. |

Le rappel signé d'analyse de l'apprentissage de BigBlueButton — l'assiduité postée avec son jeton porteur — n'est
vérifié que contre des réponses simulées de BigBlueButton : il n'a pas encore été vérifié contre un vrai serveur
BigBlueButton 3.x.

Un serveur Jitsi propre à l'application n'envoie de notifications que par un module qui poste les événements JaaS signés
de la même façon ; réglez son adresse sur l'adresse de compte de son fournisseur.
