<!-- phil-dev source-sha256=a3d6809fb959fcfdc92352bef0275a29829774cfceb5866e1d76f718504b68aa -->
# Points de terminaison

Importés de `config/routes.php` sous le préfixe de l'application, ici `/_comments`. Chaque point de terminaison ne
répond qu'à une requête du contrôleur, venant d'un auteur que nomme l'`AuthorProvider` de l'application, une fois que
l'application a configuré son fournisseur d'auteurs et sa politique. Les corps et les réponses sont en JSON.

`CommentsRequest` reconnaît une requête du contrôleur par trois vérifications :

- l'en-tête `X-Phil-Comments: 1`, qu'un formulaire ou un lien d'un autre site ne peut pas ajouter sans que le
  navigateur demande d'abord la permission ;
- aucun `Sec-Fetch-Site: cross-site` venant du navigateur ;
- pour un `POST`, la vérification CSRF sans état de Symfony sur le jeton `phil_comments` ; le `GET` des fils ne change
  rien et ne porte aucun jeton. Pour chaque `POST`, le contrôleur tire un nouveau jeton aléatoire. Il le place dans un
  témoin nommé d'après `csrfCookie` (`csrf-token` par défaut, le `csrf_protection.cookie_name` de FrameworkBundle ;
  préfixé de `__Host-` et `secure` en HTTPS, même site seulement) et l'envoie dans un en-tête du même nom.
  `SameOriginCsrfTokenManager` accepte la requête quand le `Sec-Fetch-Site`, l'`Origin` ou le `Referer` du navigateur
  nomme ce site, ou quand l'en-tête et le témoin portent le même jeton. Il la refuse quand l'un d'eux dit le contraire ;
  FrameworkBundle efface le témoin dans la réponse.

| Route | Méthode et chemin | Corps | Réponse |
|---|---|---|---|
| `phil_comments_threads` | `GET /threads?subject=article:42` | — | `200 {"threads": [thread, …]}` |
| `phil_comments_open` | `POST /threads` | `{"subject", "anchor", "body", "suggestion"}` | `201 {"thread"}` |
| `phil_comments_reply` | `POST /threads/{thread}/reply` | `{"body"}` | `201 {"thread"}` |
| `phil_comments_resolve` | `POST /threads/{thread}/resolve` | — | `200 {"thread"}` |
| `phil_comments_reopen` | `POST /threads/{thread}/reopen` | — | `200 {"thread"}` |
| `phil_comments_accept` | `POST /threads/{thread}/accept` | — | `200 {"thread"}` |
| `phil_comments_reject` | `POST /threads/{thread}/reject` | — | `200 {"thread"}` |
| `phil_comments_edit` | `POST /comments/{comment}/edit` | `{"body"}` | `200 {"thread"}` |
| `phil_comments_delete` | `POST /comments/{comment}/delete` | — | `200 {"thread"}`, `{"thread": null}` quand le fil est parti avec son dernier commentaire |

Les formulaires simples des commentaires sur une page envoient à une route de plus, importée de
`config/page_routes.php` : `phil_comments_page`, `POST /page`, à une adresse signée, avec une réponse `303` qui
renvoie à la page ; voir [la page](page.md).

`anchor` vaut `{"block", "field", "start", "quote", "prefix", "suffix"}` : `block` et `field` sont des textes ou
`null`, `start` un nombre entier, `quote` un texte, `prefix` et `suffix` des textes ou omis. Une `anchor` à `null` ou omise ouvre un fil sur la page entière, qui n'accepte aucune `suggestion`. `suggestion` est
un texte — vide pour proposer un retrait — ou `null`. Les identifiants dans les chemins comptent de 1 à 36 lettres,
chiffres, `_` ou `-`.

## Un fil

```json
{
    "id": "0192f3c4-7d1e-7a40-9c4f-2b6f0e8d1a55",
    "subject": "article:42",
    "anchor": {"block": "a1B2c3", "field": "text", "start": 4, "end": 7, "quote": "cat", "prefix": "the ", "suffix": " sat"},
    "status": "open",
    "detached": false,
    "suggestion": {"text": "dog", "state": "pending"},
    "author": {"ref": "user:ada", "name": "Ada"},
    "createdAt": "2026-11-06T10:00:00+00:00",
    "resolvedBy": null,
    "resolvedAt": null,
    "may": {"resolve": true, "decide": false, "reply": true},
    "comments": [
        {"id": "…", "author": {"ref": "user:ada", "name": "Ada"}, "body": "A dog?", "createdAt": "…", "editedAt": null, "mine": true, "may": true}
    ]
}
```

Un fil sur la page entière a `"anchor": null`, `"detached": false` et `"suggestion": null`. `detached` vaut `true`
pour un fil orphelin, dont le passage n'a été trouvé nulle part à la dernière réancre de la ressource par l'application.

`may` dit ce que la personne qui consulte peut faire, selon la politique : résoudre et rouvrir, décider de la
suggestion, répondre ; sur un commentaire, le modifier et le supprimer.

## Erreurs

| Statut | `error` | Quand |
|---|---|---|
| 400 | `invalid`, avec `message` | Un corps qui n'est pas du JSON, un champ du mauvais type, une référence, une ancre, un commentaire ou une suggestion qui ne peut pas être gardé, une suggestion sans passage, un auteur nommé par une adresse de courriel, une décision sur un fil sans suggestion en attente |
| 401 | `anonymous` | Le fournisseur d'auteurs ne nomme personne |
| 403 | `forbidden` | L'en-tête ou le jeton CSRF manque ou est faux, ou la requête vient d'un autre site |
| 403 | `refused` | La politique ne le permet pas |
| 404 | `missing` | Aucun fil ni commentaire n'a cet identifiant |
| 404 | `disabled` | Le fournisseur d'auteurs ou la politique n'est pas configuré |

## La configuration du contrôleur

`ClientConfig::for($subject, $viewer, $page = true)` donne au contrôleur ce dont il a besoin pour une page : le sujet, la référence
de la personne qui consulte, les URL des points de terminaison, celles d'un fil ou d'un commentaire avec `__id__` à la
place de son identifiant, `csrfCookie`, le nom du témoin et de l'en-tête qui portent son jeton CSRF, `mentions`,
l'adresse de la recherche de membres quand `phil/mentions-bundle` est activé et sa route importée, `null` sinon, et
`page`, qui indique si le panneau offre un formulaire pour commenter la page entière.

```php
return $this->render('article/edit.html.twig', [
    'comments' => $clientConfig->for(ResourceReference::of('article', (string) $article->id()), $currentAuthor),
]);
```
