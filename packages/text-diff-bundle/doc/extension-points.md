# Extension points

The bundle wires the library; what is specific to the application stays in its own services.

## The UJO services

`text_diff_document` reads documents with the `Phil\Ujo\Ujo` service and renders their blocks with the
`Phil\Ujo\Html\HtmlRenderer` service when the container holds them — `phil/ujo-bundle` registers both, with the
application's block types, and other bundles decorate the renderer, as `phil/highlight-bundle` does to colour code
blocks. A block type of the application's own is rendered in a difference as it is on the page.

## The HTML sanitizer

`html_sanitizer` names any service implementing `Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface`: one of
FrameworkBundle's sanitizers, or the application's own. It decides what HTML a comparison by `html` keeps from the
values a template does not mark safe ([Twig](twig.md)).

## The differ

`Phil\TextDiff\TextDiff` is a public service, autowired by its class: a controller or a service compares texts with it
and lists the changes — `Diff::changes()` — to show them in its own way, or compares documents with `documents()`.

## The controller's labels and keys

The `comparison` controller takes its labels from its values and leaves keyboard shortcuts to the page
([JavaScript](javascript.md)); the stylesheet's colours are custom properties ([Styling](styling.md)).
