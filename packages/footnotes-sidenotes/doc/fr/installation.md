<!-- phil-dev source-sha256=856608040807d1f5b35943bc2c07eddab72e95077c5653bc9c88cf941a244ccb -->
# Installation

| Prérequis | Version | Raison |
|---|---|---|
| PHP | 8.5 ou plus récent | Le plancher que la bibliothèque déclare et sur lequel elle est testée |
| `ext-hash` | toute | Le préfixe par défaut des identifiants |
| `ext-mbstring` | toute | Les symboles mesurés en caractères |
| `phil/ujo` | 0.1 | Les documents, leur assainisseur et leur moteur de rendu |

```sh
composer require phil/footnotes-sidenotes
```

La bibliothèque ne déclare aucun service et ne lit aucune configuration. Dans une application Symfony, enregistrez
`NoteResolver` et `NotesRenderer` comme services si vous le souhaitez : ils ne conservent rien entre deux appels, donc
une seule instance sert toutes les requêtes d'un worker.

## Les feuilles de style

Le paquet fournit ses feuilles dans `resources/css`. `Stylesheet::Screen->path()` et `Stylesheet::Print->path()` donnent
leurs chemins absolus, pour les copier ou les déclarer dans AssetMapper, et `contents()` donne leur texte, pour
l'intégrer à la page. Aucune n'a besoin de l'autre, ni de phil-ux : chaque valeur lit un jeton `--phil-*` et se rabat
sur une valeur simple. `Stylesheet::PrintPerPage` vient après la feuille d'impression ([Impression](print.md)).

## Paquets facultatifs

| Paquet | Pour |
|---|---|
| `phil/typography` | La typographie d'une locale sur le texte des notes, par les filtres du `HtmlRenderer` de l'appelant |
| `phil/paged-media` | La sortie d'impression transformée en PDF |
