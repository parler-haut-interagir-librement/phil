<!-- phil-dev source-sha256=b9a25e05561cabd51e5662dd87fd53adf1c9e0f1b8f50d1f67075a3bc56d9963 -->
# Routes et pages

```yaml
# config/routes/phil_directory.yaml
phil_directory:
    resource: .
    type: phil_directory
```

Le chargeur ajoute sept routes pour chaque genre, nommées `phil_directory_{genre}_{action}` :

| Action | Chemin | Méthodes |
|---|---|---|
| `index` | `podcasts`, `podcasts?page=2` | GET |
| `new` | `podcasts/new` | GET, POST |
| `show` | `podcasts/{slug}` | GET |
| `edit` | `podcasts/{id}/edit` | GET, POST |
| `editors` | `podcasts/{id}/editors` | GET, POST |
| `history` | `podcasts/{id}/history` | GET |
| `publish` | `podcasts/{id}/publish` | POST |

`{id}` est l'UUID de la fiche ; les mots viennent de `words`. La page des rédacteurs porte aussi qui trouve la fiche
([Fiches](listings.md)) ; la page d'une fiche qui n'est pas listée répond avec `X-Robots-Tag: noindex, noarchive`. La
locale est celle de la requête. Une page que personne n'a le droit de voir répond 403, une fiche inconnue 404, un
formulaire refusé 422.

La liste d'un genre montre `page_size` fiches par page, par *slug*, avec des liens vers la page précédente et la
suivante (`rel="prev"`, `rel="next"`) ; `?page=` prend un nombre de 1 à 999999999, et une page au-delà de la dernière,
ou toute autre valeur, répond 404.

## Gabarits

Les pages produisent le rendu de `@PhilDirectory/index`, `show`, `form`, `editors` et `history`, qui étendent chacun
`@PhilDirectory/layout.html.twig`, dont le bloc `meta` de l'en-tête porte la `<meta name="robots">` d'une fiche qui
n'est pas listée. Une application les remplace dans `templates/bundles/PhilDirectoryBundle/`.

| Gabarit | Variables |
|---|---|
| `index` | `genre`, `listings` (la page), `page` (son numéro), `next` (si une page suit) |
| `show` | `genre`, `page`, `listing`, `data`, `related` (une liste de `RelatedListings`), `robots` (les directives aux robots, ou `null`) |
| `form` | `genre`, `form`, `listing`, `id` |
| `editors` | `genre`, `form`, `listing`, `id` |
| `history` | `genre`, `listing`, `id`, `versions` |

## Traductions

Les libellés utilisent le domaine `phil_directory`, en français et en anglais, sous `translations/`.

## Plan du site

`DirectoryUrlProvider` porte l'étiquette `phil_sitemap.url_provider` : une section par genre, nommée par sa clé — une
clé qui se termine par un trait de soulignement et des chiffres, comme `season_2`, prend un trait d'union à la place de
ce trait de soulignement, `season-2` (`Sitemap::sectionNamed()`) —, avec la liste du genre et chaque fiche publiée dans
`sitemap_locale` avec un *slug*, sauf celles que leur référencement masque et celles que leur auteur garde hors des
moteurs de recherche, lues par tranches de 500.
