<!-- phil-dev source-sha256=29720d2b801b5c9d4875822375c9e5e0f7b0c0d591cb291d539ea86b53914d9e -->
# Les événements

## Deux événements, un contrat

| Événement | Signifie |
|---|---|
| `Phil\Publishing\PagePublished` | La page est passée en ligne, ou l'est de nouveau avec un nouveau contenu : les lecteurs la voient à son adresse canonique |
| `Phil\Publishing\PageRetired` | La page est passée hors ligne — retirée, ou supprimée — : les lecteurs ne la voient plus à cette adresse |

Les deux implémentent `Phil\Publishing\Contract\PageEvent` et sont immuables. Un écouteur qui traite les deux de la même
façon — une purge de cache, un signal — type son argument avec le contrat ; un écouteur qui ajoute ou retire quelque
chose — une entrée de plan du site — écoute chaque classe.

## Les champs

| Champ | Type | Contient | Contrôle |
|---|---|---|---|
| `canonicalUrl` | `Phil\Url\Url` | L'adresse où les lecteurs trouvent la page | Par `Url` lui-même : une adresse `http` ou `https` absolue, normalisée, de 2048 caractères au plus |
| `locale` | `string` | La langue de la page, une étiquette BCP 47 : `fr`, `fr-CA`, `zh-Hant-TW`, `es-419` | Deux ou trois lettres, puis des sous-étiquettes d'une à huit lettres ou chiffres, séparées par des traits d'union ; 35 caractères au plus. `fr_CA`, un identifiant de locale d'ICU, est refusé |
| `indexable` | `bool` | Si les moteurs de recherche peuvent indexer la page. Pour une page retirée, s'ils le pouvaient pendant qu'elle était en ligne | — |
| `ownerId` | `string` | L'identifiant que le paquet propriétaire donne à la page, comme `news:42` | De 1 à 255 octets, sans caractère de contrôle ni espace au début ou à la fin |

Un champ qui échoue à son contrôle lève `Phil\Publishing\Exception\InvalidPageEvent`, une `InvalidArgumentException`,
dont le message cite 40 caractères de la valeur au plus.

La casse d'une étiquette de langue est conservée telle quelle : `fr-CA` et `fr-ca` désignent la même langue, et un
propriétaire répartit une seule graphie.

## Pour le propriétaire d'une page

- **Répartissez une fois le changement stocké.** Après la validation de la transaction, jamais pendant : un écouteur
  agit sur une page que les lecteurs voient déjà, ou ne voient plus, et une publication annulée ne répartit rien.
- **Un événement par page et par langue.** Une page publiée en trois langues donne trois `PagePublished`, chacun avec
  l'adresse de sa langue.
- **Le même `ownerId` pour tous les événements d'une page.** Un écouteur s'en sert, avec la langue, comme clé de ce
  qu'il garde : une entrée de plan du site, un signal en attente. Préfixez-le du genre de page, pour que deux genres
  n'entrent jamais en collision.
- **Une page sans adresse ne répartit rien.** Un événement exige une adresse absolue ; un contenu que les lecteurs ne
  peuvent pas atteindre n'est pas une page.
- **Publier de nouveau répartit `PagePublished` de nouveau.** Un écouteur traite un second événement pour la même page
  comme une mise à jour : le contenu, et peut-être l'adresse ou l'indicateur, a changé.

## Pour un écouteur

- L'événement est le message entier : il porte ce dont un écouteur a besoin pour agir, jamais la page elle-même. Un
  écouteur qui a besoin de plus interroge le propriétaire par `ownerId`.
- Un écouteur peut s'exécuter plus tard, depuis une file d'attente : les champs sont des scalaires et un `Url`, qui se
  sérialise.
- `indexable` à faux sur un `PagePublished` désigne une page que les lecteurs voient et que les moteurs de recherche ne
  doivent pas voir : un plan du site l'omet, un signal aux moteurs de recherche la saute, un cache la purge tout de même.
