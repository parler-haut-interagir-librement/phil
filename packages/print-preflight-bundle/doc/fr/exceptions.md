<!-- phil-dev source-sha256=fe50749bbb578029b6527f65f7f58f26d92032d6281adf79aaa995ac264bd178 -->
# Exceptions

Les exceptions du bundle implémentent `Phil\PrintPreflight\Exception\PrintPreflightException`, comme celles de la
bibliothèque.

| Exception | Levée quand |
|---|---|
| `InvalidMeasurement` (`InvalidArgumentException`) | Un document mesuré qui n'est pas du JSON, un champ absent ou du mauvais type, une unité, un espace colorimétrique ou un style inconnu, une police nommée par famille sans résolveur, un refus du modèle de la bibliothèque ; une longueur ou un format mal écrit dans la configuration ; un média qui n'est pas une image de taille connue |
| `UnknownProfile` (`InvalidArgumentException`) | Un nom de profil que la configuration ne déclare pas, avec dans le message les noms qu'elle déclare |

L'`InvalidProfile` de la bibliothèque sort de la configuration sous forme d'`InvalidConfigurationException` à la
construction du conteneur. Le `Phil\PagedMedia\Exception\InvalidDocument` du moteur traverse `PdfLayout` tel quel — un
dessin avant la première page, une taille de police de 0 —, et le `FontNotFound` de `phil/fonts-bundle` traverse
`CatalogueFontResolver`.
