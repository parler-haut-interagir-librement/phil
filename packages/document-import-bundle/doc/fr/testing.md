<!-- phil-dev source-sha256=88e9a69b86a0c75c251db1695fbe6c38c4ae5fcd6239de7938bee05f3181a817 -->
# Tests

## Les tests du bundle

`tests/Unit` vérifie la configuration — chaque option, chaque refus — et les services qu'elle donne avec et sans la
médiathèque, l'analyseur et Messenger, l'acheminement d'`ImportDocument`, construit la politique SVG à partir de la
configuration, exécute `DocumentImports` sur les formats qu'il accepte et refuse, la taille des téléversements,
l'analyse préalable, le titre du rapport et l'événement, exécute la file et son *handler* sur des importations en attente
conservées en mémoire, le magasin de répertoire sur un répertoire temporaire, l'analyse préalable sur l'analyseur factice
de `phil/upload-scan`, le fragment du rapport sur Twig, et `MediaLibraryStore` sur la médiathèque de `phil/media-bundle`,
avec des enregistrements et des fichiers conservés en mémoire. `tests/Functional` compile le conteneur que donne le
bundle, importe un collage HTML dont l'image SVG porte un script, importe un fichier RTF avec la table des styles
configurée et un écouteur de `DocumentImported`, et exporte un document que l'importateur relit ; il démarre ensuite une
application avec FrameworkBundle, Messenger, TwigBundle et `phil/upload-scan-bundle`, refuse avant de le lire un fichier
qui contient la chaîne de test EICAR, met en file un fichier DOCX dont le message ne porte que sa référence, l'importe
sur le message du transport en mémoire, signale un fichier refusé par sa référence, et produit le fragment du rapport.

Depuis la racine du monodépôt :

```sh
vendor/bin/phpunit packages/document-import-bundle/tests
```

Infection maintient le bundle à un score de mutation couverte de 100 %, comme les bibliothèques.

## Tester une application

Définissez `media.store: folder` dans l'environnement de test pour garder les images hors de la médiathèque, et vérifiez
les blocs et les médias de `Imported` : les adresses sont le préfixe suivi des noms de fichiers, identiques d'une
exécution à l'autre. Un écouteur de `DocumentImported` se teste en répartissant l'événement avec un `Imported` que
construit le test. Avec `async`, acheminez `ImportDocument` vers un transport `in-memory://` dans l'environnement de test,
vérifiez les messages qu'il contient, et répartissez-les de nouveau avec une `ReceivedStamp` pour exécuter le *handler*.
