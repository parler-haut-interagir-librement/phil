<!-- phil-dev source-sha256=eadccb187207521b3c652014ca79c89c5155eda38e57bf7a259bd7bfa5d1624b -->
# Genres et actions

## Genres

| `BounceKind` | Signifie |
|---|---|
| `Hard` | Un échec permanent qui vise l'adresse : la boîte n'existe pas, ou n'acceptera jamais le message |
| `Soft` | Un échec qui peut passer : un délai, une boîte pleine, un message trop long, un refus par un filtre ou une politique |
| `Complaint` | Le destinataire, ou le fournisseur de sa boîte en son nom, a signalé le message comme non désiré |
| `Feedback` | Un rapport de plainte qui n'est pas une plainte : `not-spam`, `auth-failure` |
| `AutoReply` | Une réponse automatique du destinataire |
| `Delivered` | Une notification de succès : remis, relayé, étendu |
| `Unknown` | Rien que l'analyseur ou les règles reconnaissent |

Un destinataire d'une notification d'état de remise est :

- `Soft` quand son action est `delayed` ;
- `Delivered` quand son action est `delivered`, `relayed` ou `expanded`, ou quand il n'a aucune action et un état
  `2.x.x` ;
- sinon, un échec : `Hard` pour un état `5.x.x`, sauf si l'état parle du message plutôt que de la boîte — `x.2.2` boîte
  pleine, `x.2.3` et `x.3.4` message trop long, `x.7.*` refusé par politique —, ce qui donne `Soft` ; `Soft` pour un
  état `4.x.x` ; sans état, `Soft` pour un code SMTP inférieur à 500 et `Hard` autrement, puisque l'action `failed` dit
  qu'aucune tentative ne suit.

Un refus par politique est temporaire parce qu'il ne dit rien contre la boîte : la même adresse accepte le message
suivant une fois le serveur d'envoi retiré d'une liste de blocage. Un serveur qui écrit `5.7.1` pour une boîte
inexistante se corrige par une règle de portée `Always` sur son diagnostic.

## Actions

| `BounceAction` | L'application |
|---|---|
| `Ignore` | Ne fait rien |
| `CountSoft` | Compte un rebond temporaire contre l'adresse |
| `CountHard` | Compte un rebond dur contre l'adresse |
| `Suppress` | Cesse sur-le-champ d'envoyer à l'adresse, quoi que disent les compteurs |
| `Unsubscribe` | Désabonne l'adresse de ce qui lui a été envoyé |
| `Review` | Garde le message pour une personne |

`BounceAction::for(BounceKind)` donne l'action de l'analyseur : `CountHard`, `CountSoft`, `Ignore` pour les réponses
automatiques et les succès, `Unsubscribe` pour les plaintes, `Review` pour les rapports de rétroaction et les messages
inconnus. Deux exceptions : une plainte dont aucun authentificateur ne se porte garant va à `Review`, et un rapport
`not-spam` vaut `Ignore`. Une règle fixe sa propre action.

La bibliothèque ne compte rien : les seuils de rebonds consécutifs, la remise à zéro d'un compteur par une remise
réussie et la liste de suppression appartiennent à l'application.
