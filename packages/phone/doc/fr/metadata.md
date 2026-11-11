<!-- phil-dev source-sha256=342c07401bc99657226aa7864def2eda1059436951054c3f1d2d76100d8bc8c8 -->
# Métadonnées

Les plans de numérotation sont ceux de [libphonenumber](https://github.com/google/libphonenumber),
`resources/PhoneNumberMetadata.xml`, sous licence Apache 2.0. `Metadata\MetadataImporter` transforme le XML en
fichiers PHP dans `data/` : un par pays, `001-<code>.php` pour les numéros d'un indicatif qui n'appartiennent à aucun
pays, et `country-codes.php`, les pays de chaque indicatif avec le pays principal en premier. La version importée est
indiquée dans `data/VERSION`.

Au passage, les motifs perdent les espaces qui servent à leur mise en forme, chaque format reçoit sa règle de préfixe
national avec `$NP` et `$FG` remplacés, et les longueurs possibles sont développées ; les longueurs de la description
générale sont l'union de celles des types. La bibliothèque ne lit que ce qu'elle utilise : ni codes d'opérateur, ni
numéros courts, ni numéros d'exemple.

## Mise à jour

1. Téléchargez `resources/PhoneNumberMetadata.xml` à une étiquette de version de libphonenumber.
2. `php bin/import-metadata.php path/to/PhoneNumberMetadata.xml` : `data/` est remplacé. Chaque territoire est lu
   avant que quoi que ce soit ne soit écrit, chaque fichier est écrit à côté de son nom puis renommé par-dessus, et
   les plans que les nouvelles métadonnées n'ont plus sont retirés en dernier. Des métadonnées qui ne sont pas celles
   de libphonenumber, ou un fichier qui ne peut pas être écrit, laissent les données intactes, et l'importation
   échoue avec une `InvalidMetadata`.
3. Écrivez l'étiquette dans `data/VERSION`, et dans le CHANGELOG.
4. Exécutez les tests : un plan modifié peut changer un numéro des tests, qui suivent alors le plan.
