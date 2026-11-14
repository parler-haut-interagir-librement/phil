<!-- phil-dev source-sha256=10051ce9082844e64ddf8c634cb9ca31b1032f5d964439004a471ad4c6e222f0 -->
# Configuration

```yaml
# config/packages/phil_mentions.yaml
phil_mentions:
    store: doctrine                 # doctrine, or memory for tests
    entity_manager: default
    member_directory: ~             # required: a MemberDirectory service
    pattern: '[\p{L}\p{N}_](?:[\p{L}\p{N}_.\-]{0,62}[\p{L}\p{N}_])?'
    limits:
        per_text: 20                # members a text mentions at most, 1 to 1000
        text_length: 100000         # characters of a text read for mentions, 1 to 10000000
    notification_type: member.mentioned
    preferences: ~                  # a MentionPreferences service
    comments:
        markdown: false             # whether comments are Markdown
        record: true                # whether each comment written, edited or deleted is read for mentions
```

| Option | Valeur par défaut | Effet |
|---|---|---|
| `store` | `doctrine` | L'endroit où les mentions sont conservées : la table `phil_mention`, ou la mémoire, vidée entre deux requêtes |
| `entity_manager` | `default` | Le gestionnaire d'entités de la table |
| `member_directory` | obligatoire | L'identifiant du service `MemberDirectory` de l'application |
| `pattern` | voir plus haut | La forme d'un identifiant après `@` : une expression régulière sans délimiteurs ni ancres, vérifiée à la construction du conteneur — voir [Mentions](mentions.md#le-motif) |
| `limits.per_text` | `20` | Le nombre maximal de membres qu'un texte mentionne ; le lieur résout autant d'identifiants |
| `limits.text_length` | `100000` | Le nombre de caractères d'un texte qui sont lus |
| `notification_type` | `member.mentioned` | Le type de notification, déclaré à `phil/notifications-bundle` avec les canaux `site` et `email`, et `web_push` quand son Web Push est activé |
| `preferences` | aucun | L'identifiant d'un service `MentionPreferences` ; sans lui, le réglage de `phil/settings-bundle` quand ce bundle est activé, sinon chaque membre est notifié |
| `comments.markdown` | `false` | Indique si `CommentMentions` lit les commentaires comme du Markdown |
| `comments.record` | `true` | Indique si `RecordCommentMentions` enregistre les mentions de chaque commentaire que `phil/comments-bundle` écrit, modifie ou supprime ; `false` pour une application qui appelle elle-même `CommentMentions` |

## Ce que le bundle configure

Quand le bundle est activé à côté d'eux, son `prepend` ajoute :

| Bundle | Configuration |
|---|---|
| DoctrineBundle | Le mappage de `Phil\MentionsBundle\Domain\Model` |
| `phil/notifications-bundle` | `types.<notification_type>.channels: [site, email]`, avec `web_push` quand `web_push` est activé, que la configuration du type par l'application remplace |
| `phil/settings-bundle` | Le réglage `notifications.mentions` : un booléen, activé par défaut, dans la section des notifications après `notifications.web_push`, avec son libellé dans le domaine `phil_mentions` |
| `phil/block-editor-bundle` | `mention_search: Phil\MentionsBundle\Infrastructure\BlockEditor\MemberReferenceSearch`, sauf si l'application nomme le sien |
| FrameworkBundle avec AssetMapper | Le contrôleur sous `@phil/mentions-bundle`, la feuille de style sous `phil-mentions` |

## Services

| Service | Enregistrement |
|---|---|
| `Phil\MentionsBundle\Domain\Contract\MentionRepository` | Un alias, public, de `DoctrineMentionRepository` ou de `InMemoryMentionRepository` |
| `Phil\MentionsBundle\Application\Contract\MemberDirectory` | Un alias de `member_directory` |
| `Phil\MentionsBundle\Domain\ValueObject\MentionPattern` | À partir de `pattern` |
| `Phil\MentionsBundle\Application\MentionLimits` | À partir de `limits` |
| `Phil\MentionsBundle\Application\Extraction\TextMentions` | Sur le motif |
| `Phil\MentionsBundle\Application\MentionRecorder` | Public ; sur le stockage, l'annuaire, `clock`, les limites et `event_dispatcher` |
| `Phil\MentionsBundle\Application\Mentions` | Public ; le point d'entrée de l'application |
| `Phil\MentionsBundle\Application\Rendering\MentionLinker` | Public ; sur l'annuaire, le motif et `limits.per_text` |
| `Phil\MentionsBundle\Infrastructure\Notifications\NotifyMentionedMember` | Écouteur de `MemberMentioned`, quand `phil/notifications-bundle` est activé |
| `Phil\MentionsBundle\Application\Contract\MentionPreferences` | Un alias de `preferences`, de `SettingsMentionPreferences` ou de `AlwaysNotify` |
| `Phil\MentionsBundle\Infrastructure\Twig\MentionsExtension` | Avec Twig |
| `Phil\MentionsBundle\Infrastructure\BlockEditor\MemberReferenceSearch` | Avec `phil/block-editor-bundle` |
| `Phil\MentionsBundle\Infrastructure\Comments\CommentMentions` | Public, avec `phil/comments-bundle` |
| `Phil\MentionsBundle\Infrastructure\Comments\MemberAuthorDirectory` | Avec `phil/comments-bundle`, pour son `author_directory` |
| `Phil\MentionsBundle\Infrastructure\Comments\RecordCommentMentions` | Avec `phil/comments-bundle` et `comments.record`, un écouteur de ses `CommentWritten` et `CommentDeleted` |

Aucun service ne garde d'état entre les requêtes : le bundle fonctionne tel quel sous un *worker* qui sert de
nombreuses requêtes.
