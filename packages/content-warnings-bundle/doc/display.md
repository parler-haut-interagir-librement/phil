# Display

## The template

`@PhilContentWarnings/fold.html.twig` wraps a piece of content in its warnings, as the current reader's preferences
decide:

```twig
{% embed '@PhilContentWarnings/fold.html.twig' with {warnings: book.warnings, id: 'book-' ~ book.id} %}
    {% block content %}{{ book.excerpt }}{% endblock %}
{% endembed %}
```

| Decision | What is rendered |
|---|---|
| No warnings | The content alone |
| Shown | A note naming the warnings with their severity, the notes, then the content; `notice: false` renders the content alone |
| Folded | The same note and notes, then a `details` element whose `summary` reveals the content |
| Hidden | A short note naming the themes that hid the content; `hidden_notice: false` renders nothing |

| Variable | Meaning |
|---|---|
| `warnings` | A `ContentWarnings`, or null |
| `id` | The `id` of the element of the content |
| `notice` | `false` leaves out the warnings of content shown |
| `hidden_notice` | `false` renders nothing for hidden content |

Hidden content is never sent to the browser. The messages are in the `phil_content_warnings` domain.

## Twig functions

| Function | Gives |
|---|---|
| `content_warnings_decision(warnings)` | The `Decision` for the current reader |
| `content_warning_label(theme, locale = null)` | A theme's label, its key for a theme the catalogue does not have |
| `content_warning_description(theme, locale = null)` | A theme's description, empty without one |
| `content_warning_category(theme, locale = null)` | The label of the theme's category, empty for a theme the catalogue does not have |

An application renders its own markup from the decision when the template does not fit.

## The Stimulus controller

`phil--content-warnings-bundle--reveal`, declared in `assets/package.json`, is on the element of folded content. The
`details` element does the folding, with the keyboard and without script. The controller:

| Member | What it does |
|---|---|
| `toggle` (on `details` `toggle`) | Dispatches `revealed` or `concealed` |
| `reveal` | Opens the content, focuses it and scrolls it into view: at once when the reader asks for reduced motion, smoothly otherwise |
| `conceal` | Folds the content again |

A "show all" button of the application calls `reveal` on each controller, or listens to `revealed` to remember a
choice.

## The stylesheet and accessibility

`assets/styles/content-warnings.css`, imported with the controller, borders the warning, switches the summary between
"Show the content" and "Hide the content", gives the focused content a visible outline, and fades it in only for
readers who did not ask for reduced motion (`prefers-reduced-motion: no-preference`).

The warnings are a `role="note"` paragraph read before the content; the `summary` is a native button announced as
expanded or collapsed; the content takes the focus only when `reveal` opens it.
