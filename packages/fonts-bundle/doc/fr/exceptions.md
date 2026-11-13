<!-- phil-dev source-sha256=98892ba7f757b7f9bcb2037bb6cb440a7f45cda57459ebd5280b25456a6230b3 -->
# Exceptions

Les exceptions du bundle implémentent `Phil\Fonts\Exception\FontsException`, comme celles de la bibliothèque.

| Exception | Lancée quand |
|---|---|
| `FontUnreadable` (`RuntimeException`) | Un fichier de police que la bibliothèque ne peut pas lire (un fichier WOFF2 sans `ext-brotli`, un fichier endommagé) ou un registre des licences qui n'est pas du JSON valide ; le message nomme le fichier, l'exception précédente est celle de la bibliothèque |
| `FontNotFound` (`InvalidArgumentException`) | Un chemin où le catalogue n'a aucun fichier ; une famille, une graisse et un style qu'aucun fichier ne contient dans les formats demandés |

Le catalogue ne lance jamais d'exception pour un fichier illisible : il garde la raison dans l'entrée. L'exception
`Phil\PagedMedia\Exception\InvalidFont` du générateur natif traverse `PdfFonts` pour une police dont la licence interdit
l'intégration.

Une configuration que Symfony refuse (un répertoire vide, un `font_display` que CSS ne connaît pas) lance
`InvalidConfigurationException` à la construction du conteneur.
