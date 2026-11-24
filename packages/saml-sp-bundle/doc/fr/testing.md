<!-- phil-dev source-sha256=85c00fa797ca5394697d9bb4b987ce49bed9f5551b54fdad7054d8f4b0cffef0 -->
# Tests

Les tests sont des tests PHPUnit, lancés depuis la racine du monodépôt avec `composer test`, ou seuls :

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

- `tests/Unit` teste la configuration et les services qu'elle enregistre, la recette Flex, les fournisseurs d'identité,
  la connexion, les utilisateurs, la déconnexion, l'authentificateur, le fournisseur d'utilisateurs, l'écouteur de
  déconnexion, le contrôleur, la commande de vérification, le choix du magasin de rejeu et la localité des réserves de
  cache, sans noyau.
- `tests/Functional` démarre `tests/Functional/App/TestKernel` : FrameworkBundle, SecurityBundle avec un pare-feu qui
  exécute l'authentificateur et la déconnexion de Symfony, et le bundle avec deux fournisseurs d'identité faits comme
  Microsoft Entra ID — `acme`, ses métadonnées dans un fichier, et `portal`, qui peut envoyer des réponses non
  sollicitées. Un navigateur se connecte de la connexion jusqu'au tableau de bord, se voit refuser une réponse rejouée ou
  portée par un autre navigateur, se déconnecte vers le fournisseur d'identité et en est déconnecté.
  Dans son environnement `doctrine`, qui ajoute une connexion Doctrine DBAL sur SQLite, une assertion rejouée est
  refusée par la table de `DbalReplayGuard` ; dans `test`, par la réserve de cache ; dans `prod`, sa réserve de cache sur
  le système de fichiers empêche la construction du conteneur.

Les réponses et les messages de déconnexion des tests sont écrits par `tests/Fixtures/Saml.php` et signés par le
signataire de la bibliothèque avec les clés d'essai de `tests/Fixtures/keys` : les clés des tests de phil/saml-sp, faites
pour ces tests seulement et documentées dans son guide des tests. La vérification des signatures contre un signataire
indépendant, l'enveloppement de signature et les autres attaques sont testés dans phil/saml-sp.
