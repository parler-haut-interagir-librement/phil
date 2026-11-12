<!-- phil-dev source-sha256=16073f0d84b0bf1a08445b7261eb4dc20fc37dfbbc5fd636085137c417404872 -->
# Tests

## Les tests du bundle

`tests/Unit` couvre les clés, les contenus et les brouillons ; `DraftKeys` ; `Drafts` (enregistrements par révision,
conflits et courses, expiration, brouillons qui ne s'ouvrent plus, borne de taille, abandon et purge) ; le même contrat
sur les deux stockages (celui de Doctrine sur SQLite en mémoire, avec sa table créée à partir du mappage, et des lignes
qui ne sont pas des brouillons) ; le sceau de KeyManagement sur le KMS local du composant ; le propriétaire de Symfony Security ; l'extension de
type de formulaire (les valeurs de la balise, les clés par sujet et par utilisateur, les champs marqués, les soumissions
invalides, l'URL du point de terminaison, les formulaires soumis gardés jusqu'à la réponse) ; la version de la
structure ; les libellés ; le témoin des formulaires envoyés ; chaque réponse des points de terminaison et les jetons
CSRF qu'ils acceptent et refusent ; la commande de purge ; et la configuration, les services, les réglages ajoutés en
préfixe et la passe de compilation du bundle.

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
composer fuzz -- draft
node --import ./tests/js/register.mjs --test tests/js     # from packages/forms-autosave
```

Infection impose au code modifié 100 % de mutants tués.

## Tester une application

Réglez `server.store: memory` et `server.encrypt: false` dans l'environnement `test` : les brouillons vivent dans le
processus et sont vidés entre les requêtes. `Drafts` est public dans le conteneur, pour amorcer ou lire des brouillons
depuis un test.
