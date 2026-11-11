<!-- phil-dev source-sha256=2e5ffde0a2bbaa668445cbbb247bdda5de93830d450174659eaa485428191fcc -->
# Tests

## Les tests du bundle

`tests/Unit` couvre les clés, les détenteurs et les baux ainsi que leur association ; `EditLocks` — prise,
renouvellement, attente, expiration, l'ensemble et ses parties, pulsations, reprise, politique, départ, purge, courses,
et revendications abandonnées par le magasin, réessayées puis abandonnées — ; `BlockSaves` ; le même contrat sur les
deux dépôts (celui de Doctrine sur SQLite en mémoire, ses tables créées à partir de l'association) ; les revendications
Doctrine dans leur transaction, annulées lorsqu'elles échouent ; deux revendications concurrentes sur un fichier SQLite
ouvert deux fois — l'ensemble et une partie revendiqués pendant que l'autre est au milieu de sa revendication, qui
attend, abandonne, puis trouve le premier bail — ; les Turbo Streams et le diffuseur sur un hub d'enregistrement et sur
un hub injoignable ; chaque réponse des points de terminaison et les jetons CSRF qu'ils acceptent et refusent ;
`ClientConfig` ; ainsi que la configuration, les services et les réglages ajoutés au préalable du bundle.

`tests/Integration` rejoue les mêmes revendications concurrentes sur MySQL et PostgreSQL, chacune ignorée sauf si
`PHIL_TEST_MYSQL_URL` ou `PHIL_TEST_POSTGRESQL_URL` pointe vers un serveur ; `php vendor/bin/phil-dev services up`
démarre les deux, et `php vendor/bin/phil-dev services test` exécute la suite sur eux.

`tests/Functional` démarre un noyau avec FrameworkBundle, DoctrineBundle sur SQLite, MercureBundle, `phil/ujo-bundle` et
le bundle, et fait jouer deux rédacteurs sur une page par les routes : baux, un enregistrement, une pulsation, la
présence, une reprise refusée puis autorisée, un retrait, le départ, chaque requête avec son jeton et son témoin CSRF,
les requêtes sans eux refusées ; ce que le hub d'enregistrement a reçu est vérifié flux par flux. `MercureHubTest`
publie par un vrai hub et relit la mise à jour en tant qu'abonné ; il est ignoré sauf si un hub répond à `MERCURE_URL`,
par défaut `http://127.0.0.1:3999/.well-known/mercure` :

```sh
docker run -d -p 3999:80 -e SERVER_NAME=':80' \
    -e MERCURE_PUBLISHER_JWT_KEY='!ChangeThisMercureHubJWTSecretKey!' \
    -e MERCURE_SUBSCRIBER_JWT_KEY='!ChangeThisMercureHubJWTSecretKey!' dunglas/mercure
```

Lorsque le bundle est installé seul, où le répartiteur d'événements est un prérequis de développement, la suite
fonctionnelle est ignorée.

`tests/js` exécute les modules purs avec l'exécuteur de tests de Node : les requêtes de bail et leur jeton CSRF, les
instantanés et leurs modifications, la présence et la liste des rédacteurs, les Turbo Streams, les libellés.

Depuis la racine du monodépôt, puis depuis le bundle :

```sh
vendor/bin/phpunit packages/edit-lock-bundle/tests
node --import ./tests/js/register.mjs --test tests/js     # from packages/edit-lock-bundle
```

Chaque ligne de `src/` est soumise au seuil de mutation du monodépôt : chaque mutant est tué.

## Vérifications dans un navigateur

Le contrôleur est vérifié à la main avec deux rédacteurs — deux profils de navigateur, ou deux noms d'hôte du même site,
afin que chacun garde sa propre session — sur une page avec Stimulus, l'éditeur de blocs, le contrôleur, les routes, un
hub et un magasin de blocs. Chaque vérification indique quoi faire et ce qui doit se produire.

| Domaine | Action | Résultat attendu |
|---|---|---|
| Bail | A tape dans un paragraphe | B voit A dans la liste des rédacteurs, le paragraphe encadré avec le nom de A et fermé, le texte de A apparaissant à mesure que A tape |
| Reprise | B, qui y est autorisé, clique sur *Take over* | B détient le bloc ; la copie de A est fermée avec le nom de B, et A est prévenu que le bloc a été repris |
| Nouveau bloc | A appuie sur Entrée à la fin d'un paragraphe et tape | B voit le nouveau paragraphe apparaître juste après, détenu par A |
| Rédacteur tardif | Un troisième rédacteur ouvre la page | La page montre chaque bloc enregistré, tiré du magasin de blocs |
| Départ | B ferme la page | B quitte la liste des rédacteurs, et le bloc de B s'ouvre pour A |
| Sans hub | Désactivez Mercure et rechargez | Les verrous s'affichent encore, à la pulsation suivante ; les enregistrements atteignent les autres lorsqu'ils rechargent |

## Tester une application qui l'utilise

Configurez `store: memory` et `mercure.enabled: false` dans l'environnement de test, ou gardez Doctrine sur la base de
données de test et donnez à `mercure.hub` un hub qui enregistre, comme le fait le noyau fonctionnel du bundle. Dans les
tests unitaires, construisez les pièces directement :

```php
$locks = new EditLocks(new InMemoryLeaseRepository(), new MockClock(), 30, new WritersPolicy());
$locks->acquire(LeaseKey::of('article:42', 'aB3x'), Holder::of('7', 'Ada'));
```
