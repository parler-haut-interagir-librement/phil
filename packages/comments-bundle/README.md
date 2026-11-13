# phil/comments-bundle

**Comments on a page, and on passages of a text that they follow as it changes, with suggested replacements.**

phil/comments-bundle lets readers discuss the words of a document where they stand. A reader selects a passage — in a
UJO (Universal JSON Output) document, inside one field of one block — and opens a thread on it, with a comment and, if
they wish, the text that should replace the passage. Others answer; someone the application allows resolves the thread,
or accepts or rejects the suggestion. The thread is anchored to the block, the field and the character offsets of the
passage, and keeps the passage itself with a few characters on each side: when the text changes, the passage is found
again by its exact text and its context, in its field first, then in its block, then at another occurrence of the same
words; when they occur nowhere, the thread is orphaned, says so on the screen, and is attached again if they come back.
Beside them, readers comment on the whole page, under the same policy, with the same replies and the same states. In
the browser, a Stimulus controller works with the block editor of `phil/block-editor-bundle`: it offers to comment on a
selection, lists the threads beside the editor, highlights their passages as the writer types, applies an accepted
suggestion to the editor, and ends with the comments on the page and their form; on a page without an editor, it shows
the comments on the page alone. Where a page needs no script at all, a Twig component draws the comments on the page on
the server, with the recipes of the PHIL UX kit, and every action is a plain form.

It is for any Symfony application where texts are reviewed: an editorial workflow, a shared draft, a game's rules
being proofread, a contract under negotiation, the minutes of a meeting. The application names its resources and its
users as references of `phil/resource` (`article:42`, `user:7`), says who may read, comment, resolve, decide and
moderate, and tells the bundle when a document has changed; the bundle keeps the threads and finds their passages.
A plain text, outside any block, can be commented on the same way.

It is a Symfony bundle on `phil/resource`, `phil/ujo` and Doctrine ORM: threads and comments live in two tables, or
in memory for tests. The endpoints answer JSON to the controller only; the controller and its stylesheet are served by
AssetMapper with no build step and loaded only on the pages that use them, and highlights use the browser's CSS Custom
Highlight API, which never touches the editor's markup. Behind an option, comments and suggestions take the roles
`comment` and `suggestion` of WAI-ARIA 1.3. Annotation and review tools serve as models of behaviour.

## Status

| Part | State |
|---|---|
| `Anchor`, `Passage`, `InlineHtml`: where a passage is, in which text, as a browser reads it | Implemented and tested |
| `Reanchoring`: a passage found again by its exact text and its context, at another occurrence, or orphaned | Implemented, tested, benchmarked and fuzzed |
| `Thread`, `Comment`: threads on a passage or on the whole page, comments, resolve and reopen, suggestions and decisions | Implemented and tested |
| `Comments`: the application's entry point, with its policy, its events, re-anchoring and suggestions applied to UJO documents | Implemented and tested |
| `UjoPassages`: the texts of a UJO document and their paths, a passage replaced in it | Implemented and tested |
| Doctrine and in-memory stores | Implemented and tested, on SQLite; the store in memory emptied between requests |
| `CommentsController`, routes, `ClientConfig`: the endpoints, comments on the page included, and the controller's configuration | Implemented and tested |
| `PageCommentsComponent`, `PageController`: the comments on a whole page drawn by the server with the kit's recipes, plain forms without JavaScript | Implemented and tested, in an application with the kit |
| The roles `comment` and `suggestion` of WAI-ARIA 1.3, `aria-details` from a passage to its comments | Implemented and tested, behind the option `aria_roles` |
| The `@` autocomplete of `phil/mentions-bundle` on the text areas of the panel and of the page | Implemented and tested |
| The `comments` controller: selection, panel, highlights, accepting into the block editor, the comments on the page | Implemented; pure modules tested under Node, checks in a browser listed in [testing](doc/testing.md) |
| Mentions in comments and their notification | With `phil/mentions-bundle`, which reads the bundle's events |
| Pushed updates | Not part of the bundle: the controller loads threads, and reloads them at an interval when asked |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, enabling the bundle, the tables, the application's services, the routes, the controller and the stylesheet. |
| [Configuration](doc/configuration.md) | Every option, the services registered, and the interfaces the application writes. |
| [Anchoring](doc/anchoring.md) | Anchors, character offsets, the fields of UJO blocks, finding a passage again, following a document. |
| [Threads](doc/threads.md) | Authors and resources, threads on a passage and on the page, the life of a thread, comments, events, the policy, names. |
| [Suggestions](doc/suggestions.md) | Proposed replacements, decisions, and the passage replaced by the application or in the editor. |
| [The page](doc/page.md) | The comments on a whole page drawn by the server, their plain forms, the recipes of the kit. |
| [Endpoints](doc/endpoints.md) | Each route, its body, its answers, the errors and the controller's configuration. |
| [JavaScript](doc/javascript.md) | The controller, its configuration, what it does, its modules and the stylesheet. |
| [Exceptions](doc/exceptions.md) | The bundle's exceptions. |
| [Extension points](doc/extension-points.md) | What an application implements, configures, replaces or relies on. |
| [Testing](doc/testing.md) | The PHP and Node tests, the mutation bar and the checks in a browser. |
| [Reference](doc/reference.md) | Every class and module of the bundle, with its role. |

## Quick start

```sh
composer require phil/comments-bundle
```

```yaml
# config/packages/phil_comments.yaml
phil_comments:
    author_provider: App\Review\CurrentAuthor   # an AuthorProvider
    policy: App\Review\ReviewPolicy             # a CommentPolicy
```

```twig
<div data-controller="phil--comments-bundle--comments"
     data-phil--comments-bundle--comments-config-value="{{ comments|json_encode }}">
    {{ form_row(form.body) }}  {# the block editor of phil/block-editor-bundle #}
    <aside data-phil--comments-bundle--comments-target="panel"></aside>
</div>
```

`comments` is `ClientConfig::for(ResourceReference::of('article', '42'), $author)`, given by the page's controller.
Under a published article, without any script:

```twig
<twig:PhilComments:Page subject="article:42" />
```

After saving the document, the application moves the threads with it:

```php
$comments->reanchorDocument(ResourceReference::of('article', '42'), $document);
```

## Requirements

PHP 8.5 or later, Symfony 8.2, Doctrine ORM 3, `phil/resource` and `phil/ujo`. For the controller, Stimulus 3 and
`phil/block-editor-bundle`. For the comments on the page drawn by the server, Symfony UX Twig Component 3.7 and the
recipes of the PHIL UX kit.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
