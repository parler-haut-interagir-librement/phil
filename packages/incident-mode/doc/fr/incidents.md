<!-- phil-dev source-sha256=c47ef8669cbf1e507fb84858fad660f6784771660f1e8f4265ff28ca651ded82 -->
# Incidents

## Restrictions

Une `Restriction` est une chose qu'un incident suspend, désignée par un nom : une lettre minuscule, puis jusqu'à 63
lettres minuscules, chiffres et traits d'union. La bibliothèque nomme les plus courantes ; l'application ajoute les
siennes en utilisant un nom.

| Constante | Nom | Ce que l'application arrête |
|---|---|---|
| `Restriction::SIGNUPS` | `signups` | Les nouveaux comptes |
| `Restriction::INVITATIONS` | `invitations` | Les invitations envoyées par des membres |
| `Restriction::NEW_MESSAGES` | `new-messages` | Les messages privés et les nouvelles conversations |
| `Restriction::NEW_POSTS` | `new-posts` | Les nouveaux sujets, articles, annonces |
| `Restriction::NEW_COMMENTS` | `new-comments` | Les commentaires et les réponses |
| `Restriction::REACTIONS` | `reactions` | Les réactions et les votes |
| `Restriction::UPLOADS` | `uploads` | Les fichiers téléversés |
| `Restriction::PROFILE_CHANGES` | `profile-changes` | Les changements d'un profil, d'un avatar, d'un nom affiché |
| `Restriction::READ_ONLY` | `read-only` | Chaque restriction, y compris celles de l'application |

La bibliothèque ne décide rien d'elle-même : l'application vérifie une restriction là où elle s'applique, et décide de
ce qu'affiche une action suspendue.

## Préréglages

Un `Preset` est un nom, un libellé, les restrictions qu'il suspend, la durée qu'une activation propose et la plus longue
qu'il permet. **Un préréglage n'a pas d'activation sans fin** : les deux durées sont obligatoires, la proposée dans les
limites de la plus longue, et une `Duration` va d'une minute à quatre-vingt-dix jours.

```php
use Phil\IncidentMode\Duration;
use Phil\IncidentMode\Preset;

$quiet = Preset::of('quiet-listings', 'Suspend new listings', ['new-listings'], Duration::ofHours(4), Duration::ofDays(2));
```

`Presets` est le catalogue, dans l'ordre où il les offre :

| Nom | Restrictions | Proposée | Maximale |
|---|---|---|---|
| `suspend-signups` | `signups` | 2 heures | 7 jours |
| `suspend-invitations` | `invitations` | 2 heures | 7 jours |
| `suspend-new-messages` | `new-messages` | 1 heure | 3 jours |
| `suspend-contributions` | `signups`, `invitations`, `new-messages`, `new-posts`, `new-comments`, `uploads` | 1 heure | 3 jours |
| `read-only` | `read-only` | 30 minutes | 2 jours |

`Presets::defaults()` contient ces cinq-là. `Presets::withDefaults($presets, $without)` ajoute les préréglages de
l'application après eux ; un préréglage du même nom remplace celui qui est fourni, à sa place, et les noms de `$without`
sont écartés. `new Presets($presets)` ne contient que les préréglages donnés.

Le libellé est en anglais ; une interface le traduit à partir du nom du préréglage.

## Déclencher

```php
$activation = $incidents->start('suspend-signups', Duration::ofHours(2), Actor::of($memberId), 'Spam wave');
```

La durée est obligatoire et le préréglage la borne : une durée plus longue est refusée avec `InvalidIncident`, un
préréglage inconnu avec `UnknownPreset`. La raison est facultative, jusqu'à 500 caractères. L'`Activation` retournée
copie les restrictions du préréglage, de sorte que modifier un préréglage plus tard laisse les incidents en vigueur
tels qu'ils ont été déclenchés.

Un `Actor` est l'identifiant stable que l'application donne à ses membres — un UUID, un `sub` OIDC, un numéro de
membre —, jusqu'à 190 caractères visibles. **Un identifiant qui ressemble à une adresse de courriel est refusé** : une
adresse change et désigne la personne à quiconque lit le journal. `null` représente le système.

## Vérifier

| Méthode | Réponse |
|---|---|
| `isSuspended('signups')` | Si un incident en vigueur nomme la restriction, ou est en lecture seule |
| `suspended()` | Les noms des restrictions suspendues maintenant, chacun une fois, dans l'ordre où les incidents ont commencé |
| `inForceNow()` | Les activations en vigueur maintenant, la plus ancienne d'abord |

Un incident est en vigueur de son début jusqu'à sa fin ou sa levée, selon la première éventualité. Chaque réponse est
une seule question indexée au magasin, au moment que donne l'horloge ; rien n'est conservé entre deux appels.

## Prolonger et lever

`extend($id, $additional, $actor)` repousse la fin d'un incident en vigueur ; l'incident dans son ensemble reste dans
les limites de la plus longue durée de son préréglage, comptée depuis son début, et un incident dont le préréglage a
quitté le catalogue n'est pas prolongé. `lift($id, $actor)` termine maintenant un incident en vigueur. Les deux lèvent
`IncidentNotActive` quand l'incident est inconnu, levé ou échu.

## Le journal

`journal($limit, $offset)` donne chaque activation, le début le plus récent d'abord : préréglage, restrictions, début,
fin, qui l'a déclenchée et pourquoi ; une fois terminée, quand, par qui, et si elle a été levée à la main
(`LiftReason::Manual`) ou est arrivée à échéance (`LiftReason::Expired`, sans acteur). `find($id)` en donne une.

## Événements

Quand un répartiteur d'événements est fourni, `IncidentMode` envoie :

| Événement | Quand | Contient |
|---|---|---|
| `Event\IncidentStarted` | Un incident a été déclenché | L'activation |
| `Event\IncidentExtended` | Un incident en vigueur a reçu une fin plus tardive | L'activation, la fin précédente, l'acteur |
| `Event\IncidentLifted` | Un incident a été levé à la main, ou son échéance a été inscrite | L'activation levée |

Les événements sont les points d'extension : un écouteur avise l'équipe, publie un statut, vide un cache. Chacun est
envoyé une seule fois, même quand deux processus lèvent le même incident en même temps.
