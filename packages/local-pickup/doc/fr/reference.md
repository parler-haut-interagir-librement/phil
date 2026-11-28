<!-- phil-dev source-sha256=6eea2a8e30ddb27d0c9822bef3c523e86625718dcf8395130f4efd3d15f3cf71 -->
# Référence

Chaque type public du paquet, avec son rôle.

## Services

| Type | Rôle |
|---|---|
| `PickupSlots` | Les plages d'une période que le client peut choisir maintenant ; la décision sur une plage. |
| `Pickups` | Passe un ramassage, le marque prêt, envoie un nouveau code, enregistre une arrivée. |
| `PickupCodes` | Émet un code avec son empreinte à clé ; vérifie un code donné ; normalise ce qui a été saisi. |
| `Counter` | Remet une commande prête contre son code, compte les mauvaises tentatives, journalise la remise. |
| `Expiry` | Termine les ramassages en retard par lots bornés et appelle le port configuré. |
| `PickupPersonalData` | Exporte et efface les noms des personnes qui ramassent pour un client. |
| `Message\NotifyPickupReady` | Le message d'un avis de disponibilité : le numéro de commande seulement. |
| `Message\NotifyPickupReadyHandler` | Relit le ramassage, émet le code et envoie l'avis, une seule fois. |

## Valeurs

| Type | Rôle |
|---|---|
| `PickupPoint` | Le comptoir d'un magasin ou un kiosque de congrès : emplacement de stock, plages, temps de préparation, délai de garde. |
| `PickupPointKind` | `Store` ou `ConventionBooth`. |
| `AgreedPickupPoint` | La copie du point que garde une commande dès qu'elle est passée. |
| `Pickup` | Le ramassage d'une commande : point, plage, état, garde, empreinte du code, tentatives, arrivée, personne qui ramasse. |
| `PickupState` | `Preparing`, `Ready`, `PickedUp`, `Expired` ; `holdsSlot()`. |
| `ArrivalSource` | `Automatic` ou `Manual`. |
| `ExpiryOutcome` | `CancelAndRefund` ou `Restock`. |
| `IssuedCode` | Un code qui vient d'être émis : son affichage, sa charge utile QR, son code QR et son SVG, son empreinte. |
| `ScannedCode` | Le numéro de commande et le code lus dans une charge utile numérisée. |
| `HandOverRequest` | Le code donné, le poste, le personnel, le moment et la personne qui ramasse. |
| `HandOver` | Une entrée du journal : la commande, le client, le point, le poste, le personnel, le moment et les noms. |
| `ReadyNotice` | Ce que dit l'avis de disponibilité : le point convenu, la plage, la fin de la garde, le code. |

## Ports

| Type | Rôle |
|---|---|
| `Contract\PickupRepository` | Conserve les ramassages. |
| `Contract\HandOverLog` | Journalise les remises. |
| `Contract\ReadyNoticeDispatcher` | Envoie à un *worker* le numéro de commande d'un avis de disponibilité. |
| `Contract\ReadyNotifier` | Envoie l'avis de disponibilité. |
| `Contract\ArrivalNotifier` | Avise le personnel d'une arrivée. |
| `Contract\ExpiredPickupCanceller` | Annule et rembourse une commande expirée. |
| `Contract\ExpiredPickupRestocker` | Remet en stock les marchandises d'une commande expirée. |
| `Contract\PickupDesk` | Remet une commande : le port des comptoirs et des caisses. |
| `Memory\InMemoryPickups` | Un stockage de ramassages dans un tableau. |
| `Memory\InMemoryHandOverLog` | Un journal dans un tableau. |

## Exceptions

| Type | Levée quand |
|---|---|
| `Exception\LocalPickupException` | L'interface de chacune des exceptions ci-dessous. |
| `Exception\InvalidPickup` | Un identifiant, un réglage, une ligne stockée ou une charge utile est mal formé. |
| `Exception\UnknownPickup` | Aucun ramassage n'est conservé pour la commande. |
| `Exception\PickupRefused` | Un ramassage ne peut pas être passé, marqué prêt, remis ou expiré ; `reason` est une clé stable, `violations` les raisons de la plage. |
| `Exception\CodeRefused` | Un mauvais code ; `attemptsLeft`. |
| `Exception\CodeLocked` | Le code a subi trop de mauvaises tentatives. |
