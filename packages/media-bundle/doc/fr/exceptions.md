<!-- phil-dev source-sha256=eaf3b34cc6c368e18be50f6f48adab5ca55d465dd9f31ebe74f1d1d491bea7fe -->
# Exceptions

Tout ce que lance le bundle implémente `Phil\MediaBundle\Domain\Exception\MediaException`, qui étend `Throwable` : une
application attrape les exceptions du bundle à un seul endroit.

| Exception | Étend | Lancée quand |
|---|---|---|
| `MediaInUse` | `RuntimeException` | `MediaLibrary::delete()` rencontre un enregistrement qui montre le média dans une colonne qui restreint ; `places` liste chaque colonne et son compte, `App\Entity\Post.cover (3)` |
| `InvalidMedia` | `InvalidArgumentException` | Une valeur que le bundle refuse (un nom de fichier, un type de média, une licence, un point focal, une clé de collection, un format, une transformation, une clé de stockage, un chemin cible avec un enveloppeur de flux) ; un original absent ou qui ne peut pas être copié ; un fichier qui ne peut pas être lu ou décodé ; une image plus grande que `max_pixels` ; un format, une largeur ou un type dans lequel une image n'est pas rendue ; un stockage ou un encodeur qui échoue |

`MediaInUse::by(string $id, list<string> $places)` nomme le média et les colonnes qui le montrent encore ;
l'explorateur de fichiers le signale à côté du média. `InvalidMedia::because(string $reason, ?Throwable $previous =
null)` porte la raison comme message, et l'échec de la couche inférieure (la `FilesystemException` de Flysystem) comme
exception précédente. Les messages sont écrits pour les développeurs et les journaux, pas pour les visiteurs :
`MediaController` change en `404` une `InvalidMedia` rencontrée pendant le rendu.
