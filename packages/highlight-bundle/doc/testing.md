# Testing

## The bundle's tests

`tests/Unit` checks the configuration and the services it gives, the mapping of the stylesheet, the languages and
aliases added by the compiler pass, the decoration of the UJO renderer, and the filter. `tests/Functional` boots a
small application — FrameworkBundle with AssetMapper, TwigBundle, `phil/ujo-bundle`, a language of its own and this
bundle — renders a template with the filter and a UJO document, and finds the stylesheet through AssetMapper.

From the monorepo root:

```sh
vendor/bin/phpunit packages/highlight-bundle/tests
```

The bundle is held to 100 % covered mutation score by Infection, like the library.

## Testing an application

A functional test asserts on the rendered HTML. The spans are those of `phil/highlight`, stable from one run to the
next; a test that checks a block is coloured, rather than how, looks for `class="hl-` inside its `code`. To compare
the text alone, assert on the code with its spans removed and its characters unescaped.
