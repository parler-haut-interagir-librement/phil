<!-- phil-dev source-sha256=afce271ce6cc18e46254b72ddac699b6556c72ae68c1f32397dc877757501361 -->
# Configuration

```yaml
# config/packages/phil_fonts.yaml
phil_fonts:
    directory: '%kernel.project_dir%/public/fonts'
    public_path: /fonts
    font_display: swap
    licence_register: '%kernel.project_dir%/config/fonts/licences.json'
```

| Option | Valeur par défaut | Ce qu'elle règle |
|---|---|---|
| `directory` | `%kernel.project_dir%/public/fonts` | Le répertoire des fichiers de police, lu avec ses dossiers |
| `public_path` | `/fonts` | Le chemin d'URL où le répertoire est servi, vers lequel pointent les règles `@font-face` |
| `font_display` | `swap` | Le `font-display` des règles : `auto`, `block`, `swap`, `fallback` ou `optional` |
| `licence_register` | `%kernel.project_dir%/config/fonts/licences.json` | Le fichier JSON qui inscrit chaque fichier de police une fois sa licence vérifiée |

Le répertoire n'a pas besoin d'être public : quand les polices sont servies par un CDN ou copiées par AssetMapper,
`public_path` est l'URL où elles se retrouvent. Le registre a sa place dans le gestionnaire de versions, à côté du code
qui livre les polices.

## Services

| Service | Public | Fourni |
|---|---|---|
| `Phil\FontsBundle\Application\FontFiles` | oui | Toujours, avec `ext-brotli` quand elle est chargée |
| `Phil\FontsBundle\Application\FontCatalogue` | oui | Toujours |
| `Phil\FontsBundle\Application\FontFaces` | oui | Toujours |
| `Phil\FontsBundle\Application\Licences` | oui | Toujours |
| `phil:fonts:list`, `phil:fonts:licences`, `phil:fonts:css` | | Toujours |
| `Phil\FontsBundle\Infrastructure\Twig\FontsExtension` | non | Quand Twig est installé |
| `Phil\FontsBundle\Infrastructure\PagedMedia\PdfFonts` | oui | Quand `phil/paged-media` est installé |
| `Phil\FontsBundle\Infrastructure\Media\MediaFonts` | oui | Quand `phil/media-bundle` est installé et activé |
