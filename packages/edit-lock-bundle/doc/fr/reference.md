<!-- phil-dev source-sha256=76c5b909025693cab36d9a92515b75808cde71077846e24d8061884a382e22c2 -->
# Référence

## PHP

| Classe | Rôle |
|---|---|
| `PhilEditLockBundle` | Le bundle : sa configuration, les tables associées pour Doctrine, son jeton CSRF parmi les jetons sans état, les ressources associées pour AssetMapper, Mercure activé avec MercureBundle |
| `Domain\ValueObject\LeaseKey` | Une ressource, et au besoin l'un de ses blocs ; `overlaps()`, `covers()`, `equals()` |
| `Domain\ValueObject\Holder` | Qui détient un bail : un identifiant et un nom |
| `Domain\Model\Lease` | Le droit d'un détenteur d'écrire une clé jusqu'à un moment ; `grant()`, `restore()`, `renewed()`, `passedTo()`, la table `phil_edit_lease` |
| `Domain\Model\LeasedResource` | Une ressource ayant fait l'objet d'un bail, la ligne de `phil_edit_lease_resource` que verrouille chaque revendication sur elle |
| `Domain\Repository\LeaseRepository` | L'endroit où les baux sont conservés, par comparaison et échange, les revendications sur une ressource exécutées une à la fois par `exclusively()` |
| `Domain\Exception\EditLockException`, `InvalidLease` | Les exceptions du bundle, et un bail impossible à créer |
| `Application\EditLocks` | Prendre, pulser, libérer, quitter, reprendre, détenir, présence, purge |
| `Application\EditPolicy`, `DefaultEditPolicy` | Qui peut modifier et reprendre ; la valeur par défaut pour le propre code de l'application |
| `Application\BlockSaves` | Des blocs vérifiés, écrits par leur détenteur, conservés et communiqués |
| `Application\BlockStore` | L'endroit où l'application conserve les blocs enregistrés |
| `Application\Broadcaster`, `NullBroadcaster` | Prévenir les autres rédacteurs ; ne prévenir personne |
| `Application\HolderProvider` | Au nom de qui la requête courante écrit |
| `Application\Exception\LeaseHeld`, `LeaseLost`, `EditRefused`, `InvalidBlock` | Ce qui tourne mal avec les baux et les blocs |
| `Infrastructure\Doctrine\DoctrineLeaseRepository` | Les baux dans `phil_edit_lease`, par des requêtes simples, chaque revendication dans une transaction qui verrouille la ligne de la ressource |
| `Infrastructure\Memory\InMemoryLeaseRepository` | Les baux en mémoire, pour les tests |
| `Infrastructure\Mercure\MercureBroadcaster` | La présence et les blocs publiés vers un hub Mercure |
| `Infrastructure\Mercure\TurboStreams` | Les Turbo Streams de la présence, des blocs enregistrés et retirés |
| `Infrastructure\Http\EditLockController` | Les points de terminaison |
| `Infrastructure\Http\EditLockRequest` | Si une requête vient du contrôleur de ce site : son en-tête, le `Sec-Fetch-Site` du navigateur et le jeton CSRF sans état `phil_edit_lock`, sauf si la méthode est sûre ; le nom du témoin du jeton |
| `Infrastructure\Http\Routes` | Les noms des routes, indexés comme le contrôleur les nomme |
| `Infrastructure\Http\ClientConfig` | La configuration du contrôleur pour une ressource et un détenteur |
| `Infrastructure\DependencyInjection\EditLockServices` | Les services du bundle |
| `Infrastructure\DependencyInjection\CsrfProtectionPass` | Arrête le conteneur avec un message qui dit quoi changer lorsque `framework.csrf_protection` est faux |

## Fichiers

| Fichier | Rôle |
|---|---|
| `config/routes.php` | Les routes, importées par l'application sous son préfixe |
| `assets/src/collaboration_controller.js` | Le contrôleur Stimulus |
| `assets/src/lib/leases.js`, `csrf.js`, `snapshot.js`, `presence.js`, `streams.js`, `i18n.js` | Ses modules, décrits dans [JavaScript](javascript.md#modules) |
| `assets/styles/edit-lock.css` | La feuille de style de la liste des rédacteurs |
| `assets/package.json` | Le contrôleur déclaré pour Symfony UX |
