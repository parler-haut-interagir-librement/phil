<!-- phil-dev source-sha256=cc92ca3162b77c2c136a36ba037cfe2439c72dc7ca1b9de3333015c49e86d180 -->
# Données

Les formats et les subdivisions sont ceux de [commerceguys/addressing](https://github.com/commerceguys/addressing), sous
licence MIT ; cette bibliothèque les a d'abord générés à partir du service Address Data Service de Google et les
entretient. `Data\DataImporter` les écrit sous forme de fichiers PHP dans `data/` : `formats/<country>.php`,
`formats/ZZ.php` pour les pays sans format propre, et `subdivisions/<list>.php`, nommés comme commerceguys/addressing
nomme ses fichiers. La version importée figure dans `data/VERSION`. La bibliothèque ne fait que les lire, à l'exécution,
et ne dépend jamais de commerceguys/addressing.

Les noms de pays ne sont pas importés : symfony/intl les donne, dans toutes les langues qu'il connaît.

## Mise à jour

1. Faites une copie de travail de commerceguys/addressing à une étiquette de version.
2. `php bin/import-data.php path/to/addressing` : ses formats sont lus par sa propre classe de dépôt, chargée depuis
   cette copie de travail seulement. `DataImporter` écrit d'abord chaque fichier dans un dossier de préparation, et
   seulement ensuite le met à la place de `data/formats/` et de `data/subdivisions/` : des données refusées en cours de
   route laissent intactes les données déjà en place.
3. Inscrivez l'étiquette dans `data/VERSION` et dans le CHANGELOG.
4. Lancez les tests : un format modifié peut changer un test, qui suit alors les données.
