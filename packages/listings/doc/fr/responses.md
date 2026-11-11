<!-- phil-dev source-sha256=988195f988eca5601be3629c4961c61197435febef6e08429c053187a1587a52 -->
# Réponses

Un `Response` est la réponse d'un membre à une annonce. Elle ne porte aucun message : l'envoyer est le consentement du
répondant à être mis en contact avec l'auteur, l'accepter est celui de l'auteur, et seule une réponse acceptée
(`permitsContact()`) permet à l'application d'ouvrir une conversation entre eux, dans sa propre messagerie. D'ici là,
aucun des deux n'apprend rien de l'autre au-delà de ce que montrent l'annonce et le profil public du membre, et le lieu
exact de l'annonce reste caché ([Annonces](listings.md#titres-et-lieux)).

| Statut | Ouverte | Permet le contact | Quitté par |
|---|---|---|---|
| `pending` | oui | non | `accept()`, `decline()`, `withdraw()` |
| `accepted` | oui | oui | `withdraw()` |
| `declined` | non | non | rien |
| `withdrawn` | non | non | rien |

Une réponse ouverte compte dans les réponses ouvertes de son annonce. Le répondant peut la retirer, en attente ou
acceptée : son consentement prend fin avec elle, et l'application ferme la conversation qu'elle a ouverte.

## Le répondeur

`Responder` contient les règles qui touchent une annonce et ses réponses, à partir des politiques de genre, des
[ports](extension-points.md) et d'une horloge.

`refusal($listing, $respondent)` indique pourquoi le membre ne peut pas répondre, ou null. Les règles sont consultées de
la moins coûteuse à la plus coûteuse, et la première qui refuse donne la réponse :

| Refus | Quand |
|---|---|
| `not_open` | L'annonce n'est pas visible : non publiée, ou expirée. |
| `responses_closed` | Son genre n'accepte aucune réponse. |
| `own_listing` | Le membre l'a écrite. |
| `blocked` | L'auteur ou le membre a bloqué l'autre. |
| `age_unknown` | L'annonce a un âge minimal et l'âge du membre est inconnu. |
| `too_young` | Le membre est plus jeune que l'âge minimal de l'annonce. |
| `already_responded` | Le membre y a une réponse ouverte. |
| `full` | Elle a autant de réponses ouvertes que son genre le permet. |

L'âge n'est demandé que pour une annonce avec un âge minimal. Une application montre le refus avant qu'un membre essaie
(un bouton désactivé avec sa raison), et `respond()` le vérifie de nouveau.

- `respond($listing, $id, $respondent)` : lance `ResponseRefused`, avec son `refusal`, ou enregistre et retourne une
  réponse en attente.
- `accept($listing, $response)` : l'auteur accepte une réponse en attente à son annonce ; refusée avec
  `other_listing` quand la réponse n'appartient pas à cette annonce, avec `not_open` quand l'annonce n'est plus
  visible (masquée, retirée, fermée, de retour en modération, rejetée ou expirée), avec `blocked` quand l'un des deux a
  bloqué l'autre depuis la réponse, et avec `age_unknown` ou `too_young` quand le répondant ne remplit plus l'âge
  minimal de l'annonce, qu'une modification ou le genre a relevé depuis. Une réponse refusée reste en attente ;
  l'application décline celles dont elle ne veut plus.
- `decline($listing, $response)` : l'auteur décline une réponse en attente à son annonce.
- `withdraw($response)` : le répondant reprend sa réponse.

Une réponse qui n'est pas en attente ne peut être ni acceptée ni déclinée, et une réponse qui n'est pas ouverte ne peut
pas être retirée : `ForbiddenTransition`. Comme pour les annonces, qui peut appeler quoi relève de l'autorisation de
l'application : l'auteur décide, le répondant retire.

## Sans le répondeur

`Response::pending()`, `accept()`, `decline()`, `withdraw()` et `restore()` sont pures, comme les transitions de
l'annonce ; une application qui vérifie ses propres règles peut les utiliser directement. Le répondeur est l'endroit où
les règles de consentement, de blocage et d'âge sont réunies, et l'endroit d'où les appeler.
