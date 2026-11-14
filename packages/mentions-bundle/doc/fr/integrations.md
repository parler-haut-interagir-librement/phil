<!-- phil-dev source-sha256=48cffc4c23ee4036cf362d3e70d777697eec8a502e76dc68a0300c20edf52d7b -->
# Intégrations

## L'éditeur par blocs

Avec `phil/block-editor-bundle`, le bundle enregistre `MemberReferenceSearch` et le nomme comme `mention_search` de
l'éditeur, sauf si l'application nomme le sien. Le menu `@` de l'éditeur suggère alors les membres de l'annuaire :

| Suggestion | Tirée de |
|---|---|
| `reference` | `Member::$reference`, `user:42` |
| `label` | `Member::$name` |
| `url` | `Member::$url` |
| `hint` | `@` et `Member::$handle` |

Un `@` au début de la requête est retiré. Un membre dont l'éditeur ne peut pas écrire la référence — une sorte de plus
de 32 caractères, un identifiant avec d'autres caractères que des lettres, des chiffres, `_`, `.`, `/` et `-` — est
ignoré. La recherche répond au point de terminaison de l'éditeur, `phil_block_editor_mentions`, que lit aussi le
contrôleur `mention` : importez les routes de l'éditeur derrière le pare-feu.

## Commentaires

Avec `phil/comments-bundle` installé à côté, le bundle enregistre `RecordCommentMentions`, un écouteur des
`CommentWritten` et `CommentDeleted` de ce bundle : chaque commentaire écrit ou modifié — sur un passage ou sur la page
entière — voit ses mentions enregistrées, et les membres qu'il mentionne pour la première fois sont notifiés par
`phil/notifications-bundle` ; chaque commentaire supprimé voit ses mentions oubliées. Rien n'est enregistré quand l'un
des deux bundles manque, et rien dans `phil/comments-bundle` ne dépend de celui-ci.

Chaque commentaire est le sujet `comment:<id>`, son auteur est celui du commentaire, son contexte est la ressource de
son fil, de sorte que seuls les membres qui peuvent voir cette ressource sont notifiés. Le commentaire est enregistré
avant d'être lu : un commentaire qui mentionne plus de membres que `limits.per_text` reste tel qu'il a été écrit et ne
notifie personne.

`comments.record: false` désactive l'écouteur, pour une application qui enregistre elle-même les mentions avec
`CommentMentions` :

```php
$discussion = $comments->reply($thread, $author, $body);
$commentMentions->discussion($discussion);   // every comment; only new mentions notify

$commentMentions->comment($comment, $thread);
$commentMentions->forget($comment->id);       // after deleting it
```

`comments.markdown: true` lit les commentaires comme du Markdown. `MemberAuthorDirectory` nomme les auteurs des
commentaires à partir de l'annuaire :

```yaml
phil_comments:
    author_directory: Phil\MentionsBundle\Infrastructure\Comments\MemberAuthorDirectory
```

## Réglages

Avec `phil/settings-bundle`, le bundle déclare le réglage `notifications.mentions` — un booléen, activé par défaut,
dans la section des notifications, après `notifications.web_push` — et le lit avant de notifier. Son libellé est
`notifications.mentions.label` dans le domaine `phil_mentions`, fourni en anglais et en français.

## Markdown

`phil/markup-bundle` produit le rendu de Markdown ; `mentions` vient après lui, sur le HTML :
`{{ body|markdown|mentions }}`. À l'enregistrement, `inText(..., markdown: true)` écarte le même code et les mêmes
adresses que le rendu affiche comme du code et des liens.
