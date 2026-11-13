<!-- phil-dev source-sha256=b068e3ff0dac553ac03b844324b14eeb17dc51170249ed4e76c7e5899fbb4184 -->
# Exceptions

Les exceptions du bundle implémentent `Phil\Epub\Exception\EpubException`, comme celles de la bibliothèque.

| Exception | Lancée lorsque |
|---|---|
| `UnknownBookSource` (`InvalidArgumentException`) | Un nom de source sous lequel aucun service n'est enregistré ; le message liste les noms connus |
| `StorageFailed` (`RuntimeException`) | Un chemin qui sort du répertoire, un fichier qui ne peut pas être écrit, une collection de médias que la médiathèque n'a pas, un média qu'elle refuse |
| `FontNotEmbeddable` (`InvalidArgumentException`) | Une face dont la licence interdit l'incorporation ou n'autorise que les bitmaps, une famille dont le répertoire de polices ne contient aucun fichier lisible |

Les exceptions `InvalidBook` et `WriteFailed` de la bibliothèque traversent `Epubs` telles quelles ;
`MediaAssets::cover()` lance `InvalidBook` pour un média sans texte de remplacement. Dans un worker, `EpubHandler` les
enveloppe pour Messenger ([Messenger](messenger.md)).

Une configuration que Symfony refuse (un type de stockage inconnu, un dossier vide) lance
`InvalidConfigurationException` à la construction du conteneur.
