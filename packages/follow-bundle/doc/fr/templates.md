<!-- phil-dev source-sha256=390cc5723ccac8b9dc703791c175514a2a746101834423ec24bc9fa525fe267e -->
# Gabarits

Les gabarits fonctionnent sans JavaScript : chaque formulaire envoie ses données à une adresse signée des routes du
bundle avec le jeton CSRF sans état `phil_follow`, fait son changement pour le membre courant, et revient à la page avec
un `303`. Un visiteur ne voit aucun des formulaires. Le balisage est du HTML simple — `details`, `button`, `fieldset` —
que les styles de PHIL UX habillent.

| Gabarit | Contexte | Montre |
|---|---|---|
| `@PhilFollow/follow_button.html.twig` | `subject`, `type:id` | Le bouton de suivi, le menu des niveaux, et un niveau pour chaque sorte d'événement de `events` |
| `@PhilFollow/requests.html.twig` | — | Les demandes que le membre courant doit trancher, chacune acceptée ou rejetée |
| `@PhilFollow/followers.html.twig` | `subject`, `limit` | Les abonnés d'un sujet et leur nombre, ou rien quand ils sont cachés au lecteur |
| `@PhilFollow/visibility.html.twig` | — | Le choix du membre courant de montrer ou de cacher ses abonnés |
| `@PhilFollow/mutual.html.twig` | `member` | La relation mutuelle avec un autre membre : demander, attendre, y mettre fin |
| `@PhilFollow/stopped.html.twig` | `message`, `back` | Ce qui a arrêté un changement, avec le chemin du retour |

```twig
{{ include('@PhilFollow/follow_button.html.twig', {subject: 'thread:' ~ thread.id}) }}
{{ include('@PhilFollow/followers.html.twig', {subject: 'member:' ~ member.id}) }}
```

Une application habille un gabarit en le remplaçant sous `templates/bundles/PhilFollowBundle/`.

## Les fonctions Twig

| Fonction | Retourne, pour le membre courant |
|---|---|
| `phil_follow_button(subject)` | `subject`, `level`, `inherited`, `pending`, `levels`, `events`, `action`, `token` ; null pour un visiteur |
| `phil_follow_requests(limit = 50)` | `requests` — chacune avec `id`, `kind`, `requester`, `name`, `subject`, `requested_at` —, `action`, `token` ; null pour un visiteur |
| `phil_follow_followers(subject, limit = 50)` | `hidden`, `count`, `followers` — chacun avec `id` et `name` |
| `phil_follow_visibility()` | `hidden`, `action`, `token` ; null pour un visiteur |
| `phil_follow_mutual(member)` | `mutual`, `pending`, `action`, `token` ; null pour un visiteur, pour soi-même, ou quand les relations sont désactivées |

Les noms viennent du `RecipientDirectory` de phil/notifications-bundle, sinon de l'identifiant.

## Les formulaires

| Route | Champs | Changement |
|---|---|---|
| `phil_follow_level` | `level` — `watching`, `mentions`, `ignoring`, `custom` ou `none` —, `events[<event>]` | Suivre, changer de niveau, ou cesser de suivre |
| `phil_follow_request` | `request`, `decision` — `accept`, `reject` ou `withdraw` | Répondre à une demande ou la retirer |
| `phil_follow_visibility` | `hidden`, `1` ou `0` | Cacher ou montrer ses abonnés |
| `phil_follow_mutual` | `action`, `request` ou `end` | Demander une relation mutuelle, ou y mettre fin |

| Statut | Quand |
|---|---|
| `303` | Le changement est fait ; retour à la page |
| `403` | L'adresse n'est pas signée, le jeton ne passe pas, il n'y a aucun membre, ou le changement est refusé |
| `404` | La demande n'attend pas le membre |
| `422` | Une valeur du formulaire est inconnue |

## La recette Flex

Le bundle ne fournit pas encore de recette : les routes et la configuration s'écrivent à la main, comme le montre
[Installation](installation.md).
