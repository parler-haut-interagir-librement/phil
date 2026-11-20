<!-- phil-dev source-sha256=1ffd9344e06425e1cf59a401a59d932c5840585be36113f9f4304c8b813c108e -->
# Tests

## Les tests du bundle

`tests/Unit` couvre les clés, les contenus et les brouillons ; `DraftKeys` ; `Drafts` (enregistrements par révision,
conflits et courses, expiration, brouillons qui ne s'ouvrent plus, borne de taille, abandon et purge) ; le même contrat
sur les deux stockages (celui de Doctrine sur SQLite en mémoire, avec sa table créée à partir du mappage, et des lignes
qui ne sont pas des brouillons) ; le sceau de KeyManagement sur le KMS local du composant ; le propriétaire de Symfony Security ; l'extension de
type de formulaire (les valeurs de la balise, les clés par sujet et par utilisateur, les champs marqués, les soumissions
invalides, l'URL du point de terminaison, les formulaires soumis gardés jusqu'à la réponse) ; la version de la
structure ; les libellés ; le témoin des formulaires envoyés ; chaque réponse des points de terminaison et les jetons
CSRF qu'ils acceptent et refusent ; la commande de purge ; `BinaryKeysListener` — la clé primaire en `utf8mb4_bin` dans le
schéma généré pour MySQL et MariaDB, la collation par défaut sur SQLite, où des clés qui ne diffèrent que par la casse
ou l'accent sont quatre brouillons, les options qu'un champ a déjà conservées — ; et la configuration, les services, les
réglages ajoutés en préfixe et la passe de compilation du bundle.

`tests/Integration` exécute `BinaryKeysListener` sur un vrai MySQL 8.4 et un vrai PostgreSQL 17 : des brouillons dont les
clés ne diffèrent que par la casse sont enregistrés, lus et supprimés séparément par `DoctrineDraftStore`, la même clé
enregistrée deux fois est toujours refusée, une clé qui diffère par un accent est une autre ligne, et sur MySQL la clé
primaire est en `utf8mb4_bin` et la table de la version précédente l'atteint par le diff du schéma. Le test de chaque
serveur lit son URL dans `PHIL_TEST_MYSQL_URL` ou `PHIL_TEST_POSTGRESQL_URL` et est ignoré sans elle.

`tests/Functional` démarre un noyau avec FrameworkBundle en français, DoctrineBundle sur SQLite, TwigBundle,
KeyManagementBundle avec un client `sodium://` local et le bundle avec son stockage serveur. Il joue un formulaire de sa
page à sa soumission : la balise et le gabarit d'état, des brouillons enregistrés scellés, lus, refusés d'un utilisateur à l'autre
et sans jeton, une soumission invalide qui garde le brouillon, et une soumission valide qui inscrit le témoin et
supprime la copie du serveur.

`tests/js` exécute le contrôleur et ses modules sous l'exécuteur de tests de Node, sur une petite imitation du DOM : les
champs conservés et exclus, les valeurs lues et réécrites, les éditeurs remontés, le stockage refusé ou plein, les
brouillons expirés ou d'une autre version, le témoin des formulaires envoyés, l'anti-rebond, les jetons CSRF, le client
du serveur, l'état et l'avis, et les enregistrements, restaurations, abandons, conflits et envois au serveur du
contrôleur.

`tests/Fuzz/draft.php` soumet le lecteur des brouillons stockés au fuzzing : tout ce qu'il relit réécrit le même JSON,
ou est refusé avec `InvalidDraft`.

À la racine du monodépôt, puis depuis le bundle :

```sh
vendor/bin/phpunit packages/forms-autosave/tests
PHIL_TEST_MYSQL_URL='mysql://phil:phil@127.0.0.1:13306/phil_test?serverVersion=8.4' \
PHIL_TEST_POSTGRESQL_URL='postgresql://phil:phil@127.0.0.1:15432/phil_test?serverVersion=17' \
vendor/bin/phpunit packages/forms-autosave/tests/Integration
composer fuzz -- draft
node --import ./tests/js/register.mjs --test tests/js     # from packages/forms-autosave
```

Infection impose au code modifié 100 % de mutants tués.

## Tester une application

Réglez `server.store: memory` et `server.encrypt: false` dans l'environnement `test` : les brouillons vivent dans le
processus et sont vidés entre les requêtes. `Drafts` est public dans le conteneur, pour amorcer ou lire des brouillons
depuis un test.
