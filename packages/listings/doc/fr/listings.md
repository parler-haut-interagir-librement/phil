<!-- phil-dev source-sha256=f2c0d12aa552cc0e0ddee2029eda176af8bcd4212c89b4fc9f3f899cb94b7562 -->
# Annonces

Un `Listing` est immuable. Chaque transition prend le `KindPolicy` du genre de l'annonce (la politique d'un autre genre
lance `InvalidListing`) et, quand le temps compte, un instant. Elle retourne une nouvelle annonce, ou lance
`ForbiddenTransition` quand le statut de l'annonce ne le permet pas. Aucune transition ne lit d'horloge ni ne stocke
quoi que ce soit : l'application charge une annonce, appelle une transition et enregistre le résultat.

Qui peut appeler une transition relève de l'autorisation de l'application : la bibliothèque ne sait pas qui est
l'auteur, un modérateur ou le planificateur. Seule `report()` prend le membre qui signale, pour compter les signaleurs
et refuser l'auteur.

## Le contenu d'une annonce

| Champ | Type | |
|---|---|---|
| `id` | `ListingId` | Choisi par l'application. |
| `kind` | `ListingKind` | Le genre dont la politique s'applique. |
| `author` | `MemberId` | Qui l'a écrite. |
| `title` | `Title` | UTF-8 valide, espaces blancs repliés, aucun caractère de contrôle ou de format, de 1 à 120 caractères. |
| `criteria` | `Criteria` | Voir [Critères](criteria.md). |
| `location` | `Location` | En ligne, ou une zone avec un lieu exact facultatif. |
| `minimumAge` | `?int` | Le plus élevé entre celui de l'annonce et celui du genre ; null pour aucun. |
| `status` | `ListingStatus` | Voir plus bas. |
| `createdAt`, `publishedAt`, `expiresAt`, `remindedAt` | instants | `publishedAt` est la première publication. |
| `extensions` | `int` | Le nombre de fois qu'elle a été prolongée. |
| `reporters` | `list<MemberId>` | Les membres distincts qui l'ont signalée depuis sa dernière publication ou restauration. |
| `moderationReason` | `?ModerationReason` | Pourquoi elle a été rejetée ou retirée. |

Le corps d'une annonce (sa description, ses images, un prix) appartient à l'application : une petite annonce, une offre
d'emploi et une recherche de partenaires ne se décrivent pas de la même façon. La bibliothèque contient ce que tous les
genres partagent et ce dont ses règles ont besoin.

## Titres et lieux

`Title::of()` refuse une entrée de plus de 2048 octets ou en UTF-8 invalide avant tout motif. Il replie chaque suite
d'espaces blancs, espaces insécables et séparateurs de ligne compris, en une seule espace, retire les espaces de début
et de fin, puis refuse tout caractère de contrôle ou de format restant, comme une espace sans chasse ou un trait d'union
conditionnel, et tout titre vide ou de plus de 120 caractères.

`Location::online()` est une annonce sans lieu. `Location::area($area, $exact)` a une zone montrée à tous (une ville, un
quartier) d'au plus 120 caractères, et un lieu exact facultatif d'au plus 255, replié de la même façon. `approximate()`
retire le lieu exact, et `Listing::locationFor($viewer, $acceptedRespondent)` donne l'emplacement complet à l'auteur et
à un répondant que l'auteur a accepté, et la zone seule à toute autre personne.

## L'âge minimal

`Listing::draft()` et `edit()` prennent l'âge minimal de l'annonce, de 0 à 120 ou null ; l'annonce garde le plus élevé
entre celui-ci et celui du genre. Un genre ouvert aux membres dès 13 ans permet à une annonce de demander 18 ans ; une
annonce ne peut jamais descendre sous son genre. `admitsAge($age)` admet tous les âges quand il n'y a pas de minimum, et
jamais un âge inconnu quand il y en a un.

## Statuts

| Statut | Visible | Quitté par |
|---|---|---|
| `draft` | non | `submit()`, `edit()`, `close()` |
| `pending_review` | non | `approve()`, `reject()`, `remove()`, `edit()`, `close()` |
| `published` | jusqu'à `expiresAt` | `report()`, `edit()`, `extend()`, `close()`, `remove()`, `at()` |
| `rejected` | non | `edit()`, `submit()`, `close()` |
| `hidden` | non | `approve()` (la restaure), `remove()`, `close()` |
| `expired` | non | `extend()`, `edit()`, `remove()`, `close()` |
| `closed` | non | rien |
| `removed` | non | rien |

Une annonce est visible (`isVisibleAt($at)`) tant qu'elle est publiée et que `$at` précède son expiration. L'expiration
elle-même ne demande aucune transition : une annonce publiée qui l'a dépassée est déjà invisible. `at($at)` la change en
annonce expirée, pour un stockage qui tient ses statuts à jour ou un planificateur qui les balaie.

## Transitions

- `Listing::draft($id, $policy, $author, $title, $criteria, $location, $minimumAge, $at)` : un nouveau brouillon.
- `submit($policy, $at)` : un brouillon ou une annonce rejetée passe à `pending_review` quand le genre est modéré avant
  publication, à `published` sinon.
- `approve($policy, $at)` : une annonce en attente est publiée ; une annonce masquée est restaurée et ses signalements
  effacés. Une annonce déjà publiée garde son expiration tant que celle-ci est à venir ; sinon, elle en reçoit une
  nouvelle à partir de `$at`, et son rappel redevient dû.
- `reject($policy, $reason)` : une annonce en attente est rejetée, avec son motif.
- `remove($policy, $reason)` : une annonce en attente, publiée, masquée ou expirée est retirée pour de bon.
- `report($policy, $reporter, $at)` : voir [Modération et expiration](moderation-and-expiry.md).
- `edit($policy, $title, $criteria, $location, $minimumAge)` : l'auteur réécrit un brouillon ou une annonce en attente,
  rejetée, publiée ou expirée. Une annonce publiée d'un genre modéré avant publication retourne à `pending_review` en
  gardant son expiration ; toute autre garde son statut.
- `extend($policy, $at)` et `markReminded($at)` : voir [Modération et expiration](moderation-and-expiry.md).
- `close($policy)` : l'auteur ferme toute annonce qui n'est pas déjà fermée ou retirée.

## Stocker une annonce

`Listing::restore()` reconstruit une annonce stockée à partir de ses champs ; il refuse un nombre de prolongations
négatif et plus de signaleurs qu'aucune politique ne pourrait en compter. Chaque champ est une valeur simple ou un
objet-valeur avec une `value`, de sorte qu'une table, un document ou une colonne JSON le contient tel quel.
