<!-- phil-dev source-sha256=390411aa2537160389ebab5bff2b1d7383a1dd96a1a64fbd83488d9574da48fe -->
# Erreurs et budgets

## Les exceptions

Chaque exception implémente `Exception\VirtualClassroomException`.

| Exception | Quand |
|---|---|
| `InvalidValue` | Une valeur hors de ses bornes : un identifiant, un titre, une durée, un secret, une clé. |
| `ProviderUnavailable` | Le serveur n'a pas répondu à temps, n'a pu être joint ou a répondu par une erreur serveur : réessayez plus tard. |
| `ProviderRefused` | Le serveur a répondu par une erreur client ou une redirection, ou a refusé l'appel ; le message nomme sa clé stable. |
| `InvalidResponse` | La réponse est illisible : ce n'est pas le XML d'une réponse, aucun code de retour, plus grande que le budget. |
| `InvalidWebhook` | Une notification refusée : sa signature, son âge, sa forme, une salle qui n'est pas un identifiant. |
| `Unsupported` | Ce que le fournisseur ne sait pas faire. |

Aucun message ne nomme un participant, ne contient un nom d'affichage ni ne porte l'adresse d'un appel — dont la somme
de contrôle dérive du secret : un appel est désigné par son étiquette, `create`, `getMeetingInfo`. Un journal peut
garder les messages tels quels.

## Le budget d'un appel

`Http\HttpBudget` borne chaque appel d'un adaptateur qui appelle son serveur :

| Borne | Valeur par défaut | Limites |
|---|---|---|
| `timeout` | 5 secondes sans un octet | plus de 0 |
| `maxDuration` | 10 secondes au total | le délai d'attente ou plus, 120 au plus |
| `maxBytes` | 1 Mio de réponse | de 1 Kio à 16 Mio |

Un appel ne suit aucune redirection. Sa réponse est lue morceau par morceau et coupée au-delà de `maxBytes`, jamais
mise en mémoire tampon en entier. Un dépassement de délai ou une défaillance du transport est une
`ProviderUnavailable`.

## Les bornes d'une notification

| Adaptateur | Corps | Contenu |
|---|---|---|
| BigBlueButton | 1 Mio | 1000 participants, 200 séjours chacun, JSON sur 32 niveaux |
| Jitsi | 256 Kio | JSON sur 16 niveaux |

Un jeton Web JSON fait 16 Kio au plus, ses parties 16 niveaux de profondeur.
