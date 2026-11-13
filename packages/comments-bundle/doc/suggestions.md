# Suggestions

A thread can propose a replacement for its passage: a text, or an empty text to propose removing it. The suggestion
waits (`pending`) until someone the policy lets decide accepts or rejects it; either decision resolves the thread.

## Applied by the application

`Comments::applySuggestion($thread, $actor, $document)` replaces the passage, where the thread is anchored, by the
suggestion in a UJO document, accepts the suggestion and returns the document changed, for the application to save:

```php
$document = $comments->applySuggestion($threadId, $editor, $ujo->decode($article->body()));
$article->setBody($ujo->encode($document));
```

The passage must still be at its place, the same characters at the same offsets — re-anchor first if the document
changed —, or `InvalidComment` is thrown and nothing changes.

## Applied in the editor

The controller accepts a suggestion in the block editor itself: it finds the passage where it is now, replaces it in
the block, asks the server to record the acceptance, then hands the block to the editor through its
`phil-block-editor:apply` event; the writer saves the form as usual. The *Accept* button is offered only when the
editor is editable and the passage is found. A block another writer holds through `phil/edit-lock-bundle` is left
alone.

## How the passage is replaced

In a field of inline HTML, the characters of the passage are removed and the replacement, escaped, written where the
first of them was; every tag stays where it was, empty if need be, so that the marks around and inside the passage
remain balanced: replacing `two three` in `one <b>two</b> three` by `2 3` gives `one <b>2 3</b>`, the replacement
written where `t` was, inside the bold, and the closing tag kept. In the source of a code, diagram or formula block, the
characters are replaced as they are. The replacement is always text: a suggestion never brings markup.
