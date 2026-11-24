<!-- phil-dev source-sha256=54733e2a2f05207a76ae998414f1368a6e4db89f3735dac009f1675ab97d930a -->
# Pages

`config/routes.php` déclare les pages sous le préfixe que l'application choisit :

| Route | Méthode et chemin | Ce qu'elle fait |
|---|---|---|
| `phil_reviews_page` | `GET /{type}/{id}` | Les avis d'un sujet : ses notes, ses avis par page, le formulaire du membre, les règles. |
| `phil_reviews_write` | `POST /{type}/{id}` | Écrit ou modifie l'avis du membre. |
| `phil_reviews_vote` | `POST /review/{review}/vote` | Vote `helpful` ou `unhelpful`, ou retire le vote. |
| `phil_reviews_respond` | `POST /review/{review}/response` | Publie la réponse, ou la retire quand le texte est vide. |
| `phil_reviews_contest` | `POST /review/{review}/contest` | Conteste le retrait de l'avis du membre. |

Un type non déclaré, un identifiant mal formé ou un avis inconnu reçoit la réponse `404`. Chaque page est servie avec
`Cache-Control: no-store, private` : elle contient les formulaires et les votes du membre.

La page accepte les paramètres de requête `order` — `helpful` (la valeur par défaut), `newest`, `highest` ou
`lowest` — et `page`.

## Des formulaires simples

Chaque formulaire est un formulaire HTML simple, envoyé en `POST` et suivi d'une redirection vers l'avis sur la page
(`303 See Other`), de sorte qu'il fonctionne sans JavaScript et qu'un rechargement ne l'envoie pas de nouveau. Un
formulaire passe la vérification CSRF sans état de Symfony avec le jeton `phil_reviews`, et il est refusé quand le
navigateur dit qu'il a été envoyé depuis un autre site ; il est alors affiché de nouveau, avec `403` et « This form
expired or did not come from this site ». Un avis que les règles refusent est affiché de nouveau, avec `422`, la raison
et ce que le membre avait écrit.

Un visiteur lit les avis et les règles ; un membre connecté obtient le formulaire, ou l'état de son avis quand il a été
retiré, et le formulaire de contestation quand il peut être contesté.

## Accessibilité

- Les étoiles sont la recette `Rating` du kit. Une note affichée est une seule image dont le texte la dit — « Overall
  score 4 out of 5 stars » —, les étoiles elles-mêmes étant cachées des lecteurs d'écran ; une note choisie est un
  groupe de boutons radio avec une légende, que le navigateur donne au clavier. Rien n'est dit par la couleur seule.
- Chaque section a un titre, chaque champ de formulaire une étiquette, et une erreur est annoncée (`role="alert"`) et
  liée à son formulaire par `aria-describedby`.
- Les boutons de vote disent s'ils sont enfoncés (`aria-pressed`), et les décomptes sont une région dynamique.
- La mention d'une compensation est une note (`role="note"`) à côté de l'avis qu'elle concerne.

## Les recettes du kit

Les gabarits dessinent `Rating`, `Button`, `Badge`, `Input`, `Checkbox` et `Textarea`, sous le sous-répertoire des
composants du Toolkit de Symfony UX, ou `kit_subdirectory` :

```yaml
phil_reviews:
    kit_subdirectory: ui   # ui:Rating, ui:Button…
```

## Le contrôleur Stimulus

`phil--reviews-bundle--helpful` envoie un vote en arrière-plan, en demandant du JSON, puis écrit les nouveaux décomptes
et quel bouton est enfoncé. Quand la requête échoue ou est refusée, il envoie le formulaire comme d'habitude, et la page
qui revient dit pourquoi. Avec AssetMapper, le bundle déclare son répertoire de contrôleurs ; avec le
`assets/controllers.json` de Symfony UX, `assets/package.json` le nomme `helpful`.

La route du vote répond à une requête qui préfère le JSON par `{"helpful": 3, "unhelpful": 1, "vote": true}`, ou par
`{"error": "…"}` avec le statut du refus.

## Les gabarits

| Gabarit | Ce qu'il dessine |
|---|---|
| `@PhilReviews/layout.html.twig` | La page autour des avis quand l'application ne nomme aucun `layout`. |
| `@PhilReviews/page.html.twig` | La page d'un sujet. |
| `@PhilReviews/_summary.html.twig` | Les notes d'un sujet : moyenne, critères, superlatifs, la méthode de la moyenne pondérée. |
| `@PhilReviews/_review.html.twig` | Un avis, ses votes et sa réponse. |
| `@PhilReviews/_form.html.twig` | Le formulaire de l'avis du membre. |

Un gabarit se remplace sous `templates/bundles/PhilReviewsBundle/`, et un libellé en traduisant sa clé dans le domaine
`phil_reviews`. Le sommaire peut être dessiné sur les pages propres de l'application :

```twig
{{ include('@PhilReviews/_summary.html.twig', {
    summary: phil_reviews_summary('game', game.id),
    type: phil_reviews_type('game'),
    superlatives: phil_reviews_summary('game', game.id).topSuperlatives(phil_reviews_type('game').superlatives, 3),
}) }}
```

## Les fonctions Twig

| Fonction | Ce qu'elle donne |
|---|---|
| `phil_reviews_kit(recipe)` | Le nom sous lequel une recette du kit est installée. |
| `phil_reviews_name(member)` | Le nom que `ReviewerDirectory` donne à un auteur, ou `null` pour un avis anonymisé. |
| `phil_reviews_summary(type, id)` | Le `RatingSummary` d'un sujet, ou `null` pour un type non déclaré. |
| `phil_reviews_type(type)` | Le `SubjectType` d'un type déclaré, ou `null`. |
| `phil_reviews_aggregate_rating(type, id)` | Le nœud `AggregateRating`, ou `null` ([Notes](ratings.md#les-données-structurées)). |
