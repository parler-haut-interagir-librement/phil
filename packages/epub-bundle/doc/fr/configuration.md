<!-- phil-dev source-sha256=dd0229ddbbb82e298e8fe81d666d68116093a47f47c07b9d3accc6264dc045be -->
# Configuration

```yaml
# config/packages/phil_epub.yaml
phil_epub:
    temporary_directory: null
    storage:
        type: directory            # or media
        directory: '%kernel.project_dir%/var/epub'
        media_collection: null
    fonts:
        obfuscate: true
        directory: fonts
        stylesheet: css/fonts.css
```

| Option | Valeur par défaut | Ce qu'elle fixe |
|---|---|---|
| `temporary_directory` | `null` | L'endroit où un livre est écrit avant d'être relu, et d'où il est téléversé dans la médiathèque ; le répertoire temporaire du système lorsqu'elle vaut null |
| `storage.type` | `directory` | `directory`, ou `media` pour conserver chaque livre comme un média de `phil/media-bundle` |
| `storage.directory` | `%kernel.project_dir%/var/epub` | Le répertoire de `directory` |
| `storage.media_collection` | `null` | La clé de la collection de médias où vont les livres avec `media` ; aucune lorsqu'elle vaut null |
| `fonts.obfuscate` | `true` | Si `EpubFonts` obscurcit les polices qu'il incorpore, comme l'exigent de nombreuses licences de polices pour les livres numériques |
| `fonts.directory` | `fonts` | Le dossier des polices dans le livre |
| `fonts.stylesheet` | `css/fonts.css` | Le chemin, dans le livre, de la feuille de style qui contient leurs règles `@font-face` |

## Services

| Service | Public | Fourni |
|---|---|---|
| `Phil\EpubBundle\Application\Epubs` | oui | Toujours |
| `Phil\EpubBundle\Application\Port\EpubStorage` | oui, un alias | `DirectoryStorage`, ou `MediaLibraryStorage` avec `type: media` |
| `phil:epub:write` | | Toujours |
| `Phil\EpubBundle\Infrastructure\Messenger\EpubHandler` | non | Lorsque Messenger est installé |
| `Phil\EpubBundle\Infrastructure\Fonts\EpubFonts` | oui | Lorsque `phil/fonts-bundle` est installé et activé |
| `Phil\EpubBundle\Infrastructure\Media\MediaAssets` | oui | Lorsque `phil/media-bundle` est installé et activé |
