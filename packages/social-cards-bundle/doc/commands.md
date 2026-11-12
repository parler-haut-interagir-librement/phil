# Commands

## `phil:social-cards:check`

```bash
bin/console phil:social-cards:check /blog/a-post
bin/console phil:social-cards:check https://example.org/
```

A path is rendered by the application itself, as a request to the router's address — `framework.router.default_uri`
— and its base path; an address is fetched through the guarded fetcher, as a link preview is. The command lists the
`og:`, `article:`, `twitter:` and `fediverse:` tags, then each finding of `TagCheck` as an error or a warning.

| Exit code | When |
|---|---|
| 0 | No error; warnings may remain |
| 1 | An error, or a page that cannot be read: not a `200` answer of HTML, on a private network, too slow |
