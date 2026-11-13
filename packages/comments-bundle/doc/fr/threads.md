<!-- phil-dev source-sha256=07f777cd112639b04d929eccbc85892cd016727f09dab6165c8af7a9aba080c9 -->
# Fils

## Les auteurs et les ressources

Les auteurs et les ressources commentées sont des références opaques de `phil/resource` : `user:42`,
`article:0192f3c4-…`. Le bundle les stocke et les compare, mais ne les résout jamais ; l'application nomme ses
utilisateurs et ses ressources comme elle le souhaite. Un auteur, et la personne qui résout un fil, sont nommés par un
identifiant stable, jamais par une adresse de courriel : une référence dont l'identifiant contient `@` est refusée avec
`InvalidComment`, et les points de terminaison y répondent par un 400.

## Les fils sur un passage, les fils sur la page

Un fil porte sur un passage — quelques mots sélectionnés dans le texte, voyez [l'ancrage](anchoring.md) — ou sur la page
entière. Les deux genres vivent côte à côte sur la même ressource, sous la même politique, avec les mêmes réponses, les
mêmes résolutions et réouvertures, modifications et suppressions. Un fil sur la page n'a pas d'ancre (`anchor()`
retourne `null`, `isOnPage()` est vrai) : il ne bouge jamais quand le texte change, n'est jamais orphelin et ne propose
aucun remplacement.

```php
$comments->open($subject, Anchor::of('a1B2c3', 'text', 4, 'cat'), $author, 'Why a cat?');   // on a passage
$comments->openOnPage($subject, $author, 'A clear article, thank you.');                   // on the page
```

## La vie d'un fil

| Appel | Qui | Ce qui se passe |
|---|---|---|
| `open($subject, $anchor, $author, $body, $suggestion)` | `mayComment` | Un fil sur le passage, avec son premier commentaire ; une suggestion quand `$suggestion` est un texte |
| `openOnPage($subject, $author, $body)` | `mayComment` | Un fil sur la page entière, avec son premier commentaire |
| `reply($thread, $author, $body)` | `mayComment` | Un commentaire ajouté à la fin |
| `edit($comment, $author, $body)` | son auteur, ou `mayModerate` | Le texte du commentaire remplacé ; `editedAt` défini |
| `delete($comment, $author)` | son auteur, ou `mayModerate` | Le commentaire retiré ; un fil laissé sans commentaire est retiré avec lui, et `null` est retourné |
| `resolve($thread, $actor)` | `mayResolve` | `resolved`, avec qui et quand ; résoudre de nouveau ne change rien |
| `reopen($thread, $actor)` | `mayResolve` | `open` de nouveau ; une décision sur sa suggestion tient |
| `accept($thread, $actor)`, `reject($thread, $actor)` | `mayDecide` | La suggestion décidée, le fil résolu ; voyez [les suggestions](suggestions.md) |
| `discussions($subject, $reader)` | `mayRead` | Chaque fil de la ressource avec ses commentaires, du plus ancien au plus récent, sur des passages et sur la page, fils résolus et orphelins compris |

Chaque appel retourne une `Discussion` : le fil et ses commentaires, du plus ancien au plus récent. Un auteur modifie
et supprime ses propres commentaires tant qu'il peut encore commenter la ressource.

Un commentaire est du texte simple, sans espaces aux extrémités, de 1 à 10000 caractères : jamais du HTML, et le
contrôleur l'affiche comme du texte. Les heures sont en UTC, à la seconde.

## Les événements

Quand `Comments` reçoit un répartiteur d'événements — le bundle lui donne le service `event_dispatcher` quand
l'application en a un —, il répartit, après l'enregistrement :

| Événement | Quand | Porte |
|---|---|---|
| `CommentWritten` | Un fil ouvert, sur un passage ou sur la page, une réponse, une modification | `comment`, `thread`, `edited` |
| `CommentDeleted` | Un commentaire supprimé | `comment`, son ancien identifiant, `thread`, `threadRemoved` |

Ils sont répartis dans la requête qui a enregistré le commentaire, pour des écouteurs de la même application — le pont
de `phil/mentions-bundle` lit ainsi les mentions des commentaires ; ce qui doit s'exécuter plus tard, c'est à
l'écouteur de l'envoyer sur un bus de messages, par identifiants.

## La politique

```php
use Phil\CommentsBundle\Application\CommentPolicy;

final readonly class ReviewPolicy implements CommentPolicy
{
    public function mayRead(ResourceReference $reader, ResourceReference $subject): bool { /* … */ }
    public function mayComment(ResourceReference $author, ResourceReference $subject): bool { /* … */ }
    public function mayResolve(ResourceReference $actor, Thread $thread): bool { /* … */ }
    public function mayDecide(ResourceReference $actor, Thread $thread): bool { /* … */ }
    public function mayModerate(ResourceReference $actor, Comment $comment): bool { /* … */ }
}
```

`DefaultCommentPolicy`, que `Comments` utilise quand l'application n'en configure aucune, permet à tous de lire et de
commenter, à l'auteur d'un fil de le résoudre, et à personne de décider des suggestions ni de modérer. Les points de
terminaison ne sont jamais servis avec elle.

## Les noms

Les points de terminaison affichent un auteur sous le nom que donne l'`AuthorDirectory` de l'application, ou sous sa
référence quand l'annuaire n'en donne aucun ou qu'aucun annuaire n'est configuré.
