# Continuous integration

phil/link-checker checks the links of the contents a site serves, at run time, from the site itself. The links of a
repository's own files — its README, its documentation, its Markdown pages — are better checked in continuous
integration, by a dedicated tool that needs no PHP: [lychee](https://github.com/lycheeverse/lychee), a fast link
checker distributed as a binary and a GitHub Action.

The library does not call lychee, and does not ship it: each tool checks what it knows.

| Checks | phil/link-checker | lychee in CI |
|---|---|---|
| The links of the site's contents, from the database | yes | no |
| The links of the repository's files | no | yes |
| Anchors of the site's own pages | yes | for Markdown files |
| Refuses private networks | yes, through phil/safe-http | by its own options |

## A GitHub Actions workflow

```yaml
name: Links

on:
  pull_request:
    paths: ['**/*.md']
  schedule:
    - cron: '0 6 * * 1'

permissions:
  contents: read

jobs:
  links:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/cache@v4
        with:
          path: .lycheecache
          key: lychee-${{ github.sha }}
          restore-keys: lychee-
      - uses: lycheeverse/lychee-action@v2
        with:
          args: >-
            --cache --max-cache-age 1d
            --max-concurrency 8
            --accept 200,206,429
            --exclude-private
            --user-agent "ExampleLinks/1.0 (+https://www.example.com/contact)"
            './**/*.md'
          fail: true
```

The options mirror the library's choices: results cached for a day, a bounded pace, 429 accepted rather than counted as
dead, private networks left out, a `User-Agent` that names the site. Pin the action to the release your repository has
reviewed.
