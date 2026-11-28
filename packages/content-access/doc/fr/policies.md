<!-- phil-dev source-sha256=23645ee0a3bd42dfd4c7e3c9fa92678ddc643b247d897f72e9c989be26c6b7dc -->
# Politiques et décisions

## Une politique

Une `AccessPolicy` dit ce qu'un contenu demande à son lecteur. Elle est immuable : les méthodes `with…()` retournent une
nouvelle politique.

| Membre | Valeurs | Valeur par défaut |
|---|---|---|
| `audience` | `Audience::Public`, `Audience::Members`, `Audience::Restricted` | — |
| `rule` | une `Phil\Rules\Rule`, pour un public restreint seulement | `null` |
| `publicFrom` | l'instant à partir duquel le contenu est public pour de bon | `null` |
| `preview` | si un lecteur sans le droit lit la partie avant la coupure | `false` |
| `whenDenied` | `WhenDenied::Locked`, une entrée verrouillée, ou `WhenDenied::Hidden`, rien du tout | `Locked` |

```php
AccessPolicy::public();
AccessPolicy::members()->withPreview();
AccessPolicy::restricted($rule)->withPublicFrom(new DateTimeImmutable('2027-01-15T00:00:00+00:00'));
AccessPolicy::restricted($rule)->withWhenDenied(WhenDenied::Hidden);
```

Une contradiction est refusée par `InvalidPolicy` à la construction de la politique : un contenu restreint sans règle,
une règle sur tout autre public, un contenu public avec un accès anticipé, un aperçu ou un refus caché, et un contenu
caché avec un aperçu.

### Accès anticipé

Avec `publicFrom`, le public s'applique jusqu'à cet instant ; à partir de là, tout le monde lit le contenu, et la
politique ne se referme plus jamais. Les supporteurs lisent un épisode une semaine avant les autres : l'épisode est
restreint à `tier:supporter` et public une semaine après sa sortie.

`isPublicAt($instant)` dit si tout le monde lit le contenu à un instant donné.

## Une décision

`AccessDecider::decide($policy, $viewer, $facts = [])` répond à l'instant de l'horloge :

| Cas | `access` | `reason` | `personal` |
|---|---|---|---|
| Un contenu public | `Full` | `Reason::Public` | non |
| Un accès anticipé terminé | `Full` | `Reason::EarlyAccessEnded` | non |
| Pour les membres, un lecteur connecté | `Full` | `Reason::Member` | oui |
| Pour les membres, un visiteur | `Preview`, `Locked` ou `Hidden` | `Reason::Anonymous` | oui |
| Restreint, la règle tient | `Full` | `Reason::RulePassed` | oui |
| Restreint, la règle échoue | `Preview`, `Locked` ou `Hidden` | `Reason::RuleFailed` | oui |

Un lecteur refusé reçoit `Hidden` quand la politique cache le contenu, `Preview` quand elle montre un aperçu, et
`Locked` autrement.

Un contenu public, ou dont l'accès anticipé est passé, est décidé sans lire le lecteur. Un contenu pour les membres
demande seulement si le lecteur est connecté. Un contenu restreint évalue sa règle sur les faits du lecteur — voir les
[conditions](conditions.md) — et les droits ne sont lus à leur source que lorsqu'une condition demande une
fonctionnalité.

`isGranted()` dit si le lecteur lit tout le contenu, et `isVisible()` si le contenu lui apparaît du tout.

### Ce qu'il reste

`explanation` contient les feuilles de la règle qui a décidé, chacune un `Phil\Rules\Evaluation\Step` avec son JSON
Pointer, son code de raison et ses détails. Une entrée verrouillée montre ce qu'il reste à partir d'elles :
`access.feature-not-yet` donne dans `opens_at` l'instant où un contenu s'ouvre, `access.anonymous` demande au lecteur
de se connecter, `access.feature-missing` nomme la fonctionnalité à laquelle s'abonner. Une décision cachée ne porte
aucune explication.

### Contenus cachés

Un contenu caché n'apparaît dans aucune liste, et son adresse reçoit la réponse de l'adresse d'un contenu qui n'existe
pas : même statut, même corps, mêmes en-têtes. Une réponse qui distingue un contenu caché d'un contenu absent dirait à
un visiteur que le contenu existe.

### Cache

- Une décision dont `personal` est faux est la même pour tout le monde : la page peut être stockée dans un cache
  partagé.
- Une décision personnelle n'est jamais stockée dans un cache partagé ; une page construite à partir d'elle varie selon
  le lecteur.
- `changesAt` est la fin d'un accès anticipé à venir : la décision change pour tout le monde à cet instant, et aucun
  cache ne garde la page au-delà.

## La coupure d'un document

Un document UJO (Universal JSON Output) marque la fin de sa partie publique par un bloc d'un type à lui, `access-cut`
par défaut. `PreviewCut` donne les blocs qu'une décision laisse lire au lecteur :

| `access` | Blocs |
|---|---|
| `Full` | tous les blocs sauf les coupures |
| `Preview` | les blocs avant la première coupure |
| `Locked`, `Hidden` | aucun |

```php
$cut = new PreviewCut();                // or new PreviewCut('paywall')

$blocks = $cut->visibleBlocks($decision, $document['blocks']);
$cut->publicPart($document['blocks']);  // the blocks before the first cut
$cut->hasCut($document['blocks']);
```

Un document sans coupure n'a pas de partie publique : son aperçu est vide, jamais le contenu entier. Les blocs réservés
ne sont pas cachés dans la page par du CSS ou du JavaScript ; ils ne sont pas envoyés.

## Données structurées

Les moteurs de recherche lisent un contenu réservé comme le fait un visiteur anonyme.
`StructuredData::of($policy, $instant, '.reserved')` donne les propriétés schema.org à fusionner dans le JSON-LD du
contenu :

```php
['isAccessibleForFree' => false, 'hasPart' => [['@type' => 'WebPageElement', 'isAccessibleForFree' => false, 'cssSelector' => '.reserved']]]
```

ou `['isAccessibleForFree' => true]` quand le contenu est public à cet instant. Les propriétés suivent la politique,
jamais le lecteur courant, pour que le balisage soit le même pour tout le monde. Le sélecteur nomme l'élément qui
contient la partie réservée ; un sélecteur vide, de plus de 255 octets, ou contenant un caractère de contrôle ou un
`<` est refusé.
