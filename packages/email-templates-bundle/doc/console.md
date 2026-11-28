# Console

The commands are registered when `symfony/console` is installed.

## phil:email-templates:list

```sh
bin/console phil:email-templates:list
bin/console phil:email-templates:list --site=main
bin/console phil:email-templates:list --site=""      # the shared scope
```

Every template the site sends or keeps a draft of, by key then locale: the origin of what it sends — `site`, `shared` or
`default`, empty when another locale of the chain is sent —, the version sent, where the site's own draft stands —
`unpublished`, `draft` or `published` — and when it was saved.

## phil:email-templates:check

```sh
bin/console phil:email-templates:check -v
```

Checks every default file as a save would: it is read, its sources compile in the sandbox and read only what its schema
declares, and every variant renders with the sample values. Each failure is listed with its reasons; the command fails
when one does, for a deployment to stop on. `-v` lists each template checked.

## phil:email-templates:render

```sh
bin/console phil:email-templates:render ticket --locale=fr --segment=vip --part=text
bin/console phil:email-templates:render ticket --site=main --template-version=2 --part=html > ticket.html
bin/console phil:email-templates:render ticket --site=main --draft --part=subject
```

Renders a template with its sample values, through the `PreviewRenderer`, and writes one part: `subject`, `preheader`,
`html` — the default — or `text`. `--site` is the shared scope by default; `--template-version` a version the site
published, and `--draft` the draft it keeps in the locale, rather than what it sends.
