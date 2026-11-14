# Rendering

## The filter

```twig
{{ comment.body|mentions }}
{{ proposal.body|markdown|mentions }}
```

`mentions` turns the handles of a text into links:

```html
<a class="mention" href="/members/ada" data-mention="user:1">@ada</a>
```

| Input | Read as |
|---|---|
| A plain string | Escaped first, then linked |
| HTML marked safe, such as the output of a Markdown filter | Linked as it is |

The filter is declared by `#[AsTwigFilter]` on `MentionsExtension::link()`, with `preEscape: 'html'` and its output
marked safe; TwigBundle loads the service only when a template calls the filter.

A member without a profile, or whose address is neither relative nor `http` or `https`, gets
`<span class="mention" data-mention="user:3">@chloé</span>`. A handle nobody has stays as it is.

## What is read

`MentionLinker::link(string $html): string` reads only the text between tags, and never inside `a`, `code`, `pre`,
`script`, `style` or `textarea`: a link already made, and code, stay as they are. The directory is asked once per text,
for its first distinct handles up to `limits.per_text`; the others stay as they are. A text between tags that is not
valid UTF-8 is left as it is.

The handle keeps the case it was typed with; the link and the reference are the member's.

## UJO documents

A UJO document rendered by `phil/ujo` already holds its marks as links: it needs no filter. A handle typed in a
document without choosing it in the editor's menu stays text; the filter links it too when the application applies it
to the rendered HTML.

## The stylesheet

`phil-mentions/mentions.css` styles `.mention`, from the tokens of PHIL UX when they are loaded and the page's own
colours otherwise.
