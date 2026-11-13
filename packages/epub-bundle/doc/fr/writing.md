<!-- phil-dev source-sha256=44d3c92cd157765a1e12df099eb9b1b9c4c6118636b35a801f097b9045eaa922 -->
# Écrire des livres

## `Epubs`

| Méthode | Retourne |
|---|---|
| `write(Book $book)` | Les octets du fichier EPUB |
| `store(Book $book, string $path)` | L'endroit où le stockage le conserve, après l'avoir écrit sous un chemin relatif comme `stories/42.epub` |
| `build(string $source, string $id)` | Le livre qu'une source construit à partir d'un identifiant |
| `sources()` | Les `BookSources`, pour lister leurs noms |

Un contrôleur qui offre un téléchargement écrit les octets dans une réponse avec le type de média
`application/epub+zip`. La bibliothèque donne les mêmes octets pour le même livre : la réponse peut donc être mise en
cache selon la date de modification du livre.

## Sources de livres

Une classe qui implémente `BookSource`, marquée `#[AsBookSource('name')]` et configurée automatiquement, construit un
livre de l'application à partir d'un identifiant :

```php
#[AsBookSource('rulebook')]
final readonly class RulebookBooks implements BookSource
{
    public function build(string $id): Book { /* read the records, return the Book */ }
}
```

Les services portent l'étiquette `phil_epub.source` avec leur nom ; `BookSources::get()` lance `UnknownBookSource` pour
un nom sous lequel aucun n'est enregistré, avec les noms qu'il connaît dans le message.

## Le stockage

`DirectoryStorage` conserve les livres sous leur chemin relatif dans `storage.directory` et retourne le chemin complet.
Un chemin vide, absolu, ou qui contient `..` ou un octet nul lance `StorageFailed`, tout comme un fichier qui ne peut
pas être écrit.

`MediaLibraryStorage` téléverse chaque livre dans la médiathèque de `phil/media-bundle` comme un nouveau média nommé
d'après le dernier segment du chemin, dans la collection `storage.media_collection` lorsqu'elle est définie, et retourne
l'identifiant du média. Une collection que la médiathèque n'a pas, ou un média qu'elle refuse, lance `StorageFailed`.

## La commande

```sh
bin/console phil:epub:write <source> <id> [--output=FILE]
```

Sans `--output`, le livre est conservé dans le stockage sous `<source>/<id>.epub` et la commande affiche où ; avec cette
option, le fichier est écrit à l'endroit indiqué. Une source inconnue, un livre que la bibliothèque refuse, un stockage
ou un fichier qui ne peut pas être écrit affichent la raison et terminent avec le code 1.
