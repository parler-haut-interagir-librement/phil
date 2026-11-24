<!-- phil-dev source-sha256=728a5c6028230ff3980009c4e8af57b7d9eaf74fa20b08e66955bdebbabc2529 -->
<!-- phil-dev source-sha256=x -->
# Intégration continue

phil/link-checker vérifie les liens des contenus qu'un site sert, à l'exécution, depuis le site lui-même. Les liens des
fichiers d'un dépôt — son README, sa documentation, ses pages Markdown — se vérifient mieux en intégration continue, par
un outil dédié qui n'a pas besoin de PHP : [lychee](https://github.com/lycheeverse/lychee), un vérificateur de liens
rapide distribué sous forme de binaire et d'action GitHub.

La bibliothèque n'appelle pas lychee et ne le fournit pas : chaque outil vérifie ce qu'il connaît.

| Vérifie | phil/link-checker | lychee en intégration continue |
|---|---|---|
| Les liens des contenus du site, depuis la base de données | oui | non |
| Les liens des fichiers du dépôt | non | oui |
| Les ancres des pages du site | oui | pour les fichiers Markdown |
| Refuse les réseaux privés | oui, par phil/safe-http | par ses propres options |

## Un workflow GitHub Actions

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

Les options reprennent les choix de la bibliothèque : résultats gardés un jour, un rythme borné, le code 429 accepté
plutôt que compté comme mort, les réseaux privés exclus, un `User-Agent` qui nomme le site. Fixez l'action sur la
version que votre dépôt a examinée.
