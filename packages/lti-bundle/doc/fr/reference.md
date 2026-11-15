<!-- phil-dev source-sha256=e76dbfb5aa26cddb506f193eb0976e645f86ee6b7404c39dce879be73c5b9a6b -->
# Référence

Chaque type public du bundle, avec son rôle. Un type marqué interne ne fait pas partie de l'API publique.

## Types

| Type | Genre | Rôle |
|---|---|---|
| `Phil\LtiBundle\Application\Contract\KeySealer` | `interface` | Scelle les clés privées que le bundle garde dans sa table, et les ouvre : le port vers un service de gestion de clés. L'identifiant de clé est authentifié avec la clé scellée, de sorte qu'une clé scellée copiée dans une autre ligne ne s'ouvre pas. |
| `Phil\LtiBundle\Application\Contract\LaunchMessages` | `interface` | Ce que la plateforme lance, tel que l'application le décide : l'application l'implémente lorsque le côté plateforme est activé. Le bundle vérifie la requête, l'outil et l'adresse de redirection avant de demander, et signe le message. |
| `Phil\LtiBundle\Application\Contract\LaunchUsers` | `interface` | L'utilisateur qu'un lancement vérifié connecte, côté outil : le compte propre de l'application pour la personne, trouvé ou créé à partir du lancement. Sans lui, le bundle connecte un `LtiUser` qui ne vit que dans la session. |
| `Phil\LtiBundle\Application\Contract\PendingScores` | `interface` | Les notes que l'outil doit encore envoyer, gardées jusqu'à ce que la plateforme les prenne : le message du bus ne porte que leur identifiant. |
| `Phil\LtiBundle\Application\Event\DeepLinkingReturned` | `final class` | Une réponse de Deep Linking que la plateforme a vérifiée : un écouteur place les éléments — liens de ressource, line items — et répond au navigateur, ou le bundle affiche une courte page. |
| `Phil\LtiBundle\Application\Event\LaunchReceived` | `final class` | Un lancement que l'outil a vérifié, son utilisateur connecté : un écouteur y répond — la page du lien de ressource, le sélecteur d'une requête de Deep Linking —, ou le bundle envoie le navigateur à la cible du lancement. |
| `Phil\LtiBundle\Application\Event\LineItemDeleted` | `final readonly class` | Un line item qu'un outil a supprimé sur la plateforme, avec ses notes. |
| `Phil\LtiBundle\Application\Event\LineItemSaved` | `final readonly class` | Un line item qu'un outil a créé ou modifié sur la plateforme. |
| `Phil\LtiBundle\Application\Event\ScorePublished` | `final readonly class` | Une note que l'outil a envoyée, prise par la plateforme. |
| `Phil\LtiBundle\Application\Event\ScoreReceived` | `final readonly class` | Une note qu'un outil a envoyée à la plateforme, gardée comme la dernière de l'apprenant sur le line item : le carnet de notes de l'application écoute. |
| `Phil\LtiBundle\Application\Message\PublishScore` | `final readonly class` | Envoie une note en attente à sa plateforme : le message ne porte que l'identifiant de la note, que le *handler* lit dans le magasin. |
| `Phil\LtiBundle\Application\PendingScore` | `final readonly class` | Une note que l'outil envoie à un line item d'une plateforme, gardée jusqu'à son envoi. |
| `Phil\LtiBundle\Application\ScorePublisher` | `final readonly class` | Envoie les notes de l'outil aux plateformes : une note est gardée comme en attente, puis envoyée par un message du bus qui ne porte que son identifiant — ou tout de suite, sans bus —, et oubliée une fois que la plateforme la prend. Un échec du réseau ou de la plateforme lève une exception, pour que le bus réessaie ; une note qui ne pourra jamais être envoyée — sa plateforme n'est plus enregistrée — est oubliée. |
| `Phil\LtiBundle\Infrastructure\Configuration\Registrations` | `final class` | Construit les enregistrements et les clés de la configuration, lorsque le conteneur fabrique les services. |
| `Phil\LtiBundle\Infrastructure\Console\PurgeCommand` | `final readonly class` | Retire les nonces, les états de connexion et les jetons d'accès expirés, comme une tâche planifiée l'exécute : les états lorsque le côté outil est activé, les jetons lorsque le côté plateforme l'est. |
| `Phil\LtiBundle\Infrastructure\Console\RotateKeysCommand` | `final readonly class` | Renouvelle la clé de signature — tout de suite, ou avec `--if-due` seulement une fois qu'elle est plus vieille que la durée de vie configurée, comme une tâche planifiée l'exécute — et supprime les clés retirées avant le chevauchement. |
| `Phil\LtiBundle\Infrastructure\Console\SchemaCommand` | `final readonly class` | Affiche le SQL qui crée les tables du bundle manquantes dans sa connexion, et l'exécute avec `--force` : pour une application sans l'ORM, ou avant sa première migration. Une table qui est déjà là reste telle quelle. |
| `Phil\LtiBundle\Infrastructure\Controller\AgsController` | `final readonly class` | Les Assignment and Grade Services de la plateforme par HTTP : les line items d'un contexte — listés avec leurs filtres et leurs pages, lus, créés, modifiés, supprimés —, les notes envoyées à un line item, et ses résultats. Chaque appel porte un jeton porteur de la portée dont il a besoin ; un contexte ou un line item qui n'est pas celui de l'outil répond 404. Chaque changement est signalé à l'application par un événement. |
| `Phil\LtiBundle\Infrastructure\Controller\JwksController` | `final readonly class` | Le jeu de clés de l'application, pour que l'autre côté vérifie ce qu'elle signe : public pendant cinq minutes dans les caches. |
| `Phil\LtiBundle\Infrastructure\Controller\NrpsController` | `final readonly class` | Le Names and Role Provisioning Service de la plateforme par HTTP : les membres d'un contexte, une page à la fois, la page suivante dans l'en-tête `Link` ; un contexte qui n'est pas celui de l'outil répond 404. |
| `Phil\LtiBundle\Infrastructure\Controller\PlatformLaunchController` | `final readonly class` | Les routes de navigateur de la plateforme : le point de terminaison d'autorisation, qui répond à la requête d'authentification de l'outil par le lancement signé pour la personne connectée, et le retour d'une réponse de Deep Linking. |
| `Phil\LtiBundle\Infrastructure\Controller\ServiceResponses` | `final class`, interne | Les réponses des services de la plateforme : du JSON de leur type de média, des erreurs avec le `WWW-Authenticate` de la RFC 6750. |
| `Phil\LtiBundle\Infrastructure\Controller\TokenController` | `final readonly class` | Le point de terminaison des jetons de la plateforme, comme la RFC 6749 le fait répondre : le jeton en JSON, jamais mis en cache ; une erreur avec son code, 401 pour un client qui ne s'authentifie pas et 400 sinon. |
| `Phil\LtiBundle\Infrastructure\Controller\ToolController` | `final readonly class` | Les deux routes de l'outil : l'initiation de connexion, qui envoie le navigateur à la plateforme avec une requête d'authentification, et le lancement, que l'authentificateur a vérifié avant : l'application y répond par `LaunchReceived`, ou le navigateur va à la cible du lancement. |
| `Phil\LtiBundle\Infrastructure\Dbal\DbalAccessTokens` | `final readonly class` | Les jetons d'accès que la plateforme a accordés, dans une table, par l'empreinte de leur valeur seulement. |
| `Phil\LtiBundle\Infrastructure\Dbal\DbalKeyStore` | `final class` | Les clés de l'application dans une table, chaque clé privée scellée par le service de gestion de clés comme le JSON de sa JSON Web Key privée : la clé de signature est la plus récente non retirée, une première étant faite lorsque la table n'en contient aucune ; une rotation retire les clés précédentes, publiées pendant le chevauchement, puis élaguées ; les clés ouvertes sont gardées jusqu'à `reset()`. |
| `Phil\LtiBundle\Infrastructure\Dbal\DbalLineItemStore` | `final readonly class` | Les line items de la plateforme, dans une table ; en supprimer un supprime ses notes dans la même transaction. |
| `Phil\LtiBundle\Infrastructure\Dbal\DbalLoginStates` | `final readonly class` | Les états de connexion de l'outil, dans une table, gardés par le SHA-256 de l'état. Un état est pris en supprimant sa ligne : de deux lancements avec le même état, seul celui dont la suppression a retiré la ligne continue. |
| `Phil\LtiBundle\Infrastructure\Dbal\DbalNonces` | `final readonly class` | Les nonces employés une seule fois, dans une table : une valeur est gardée par son SHA-256 sous sa portée, et la clé primaire fait de la vérification et de l'écriture une seule étape. Une ligne expirée est remplacée en une seconde étape, que la clé primaire garde elle aussi atomique. |
| `Phil\LtiBundle\Infrastructure\Dbal\DbalPendingScores` | `final readonly class` | Les notes que l'outil doit encore envoyer, dans une table. |
| `Phil\LtiBundle\Infrastructure\Dbal\DbalScoreStore` | `final readonly class` | La dernière note de chaque apprenant sur chaque line item, dans une table : la note telle qu'AGS l'écrit, relue par la bibliothèque, et son moment à la microseconde pour l'ordre. |
| `Phil\LtiBundle\Infrastructure\Dbal\LtiSchemaListener` | `final readonly class` | Ajoute les tables du bundle au schéma que l'ORM génère pour leur connexion : `doctrine:migrations:diff` écrit la migration qui les crée. Enregistré sur l'événement `postGenerateSchema` de l'ORM lorsque l'ORM est installé. |
| `Phil\LtiBundle\Infrastructure\Dbal\LtiTables` | `final readonly class` | Les sept tables du bundle sous un même préfixe : les nonces employés une seule fois, les états de connexion de l'outil, les jetons d'accès de la plateforme, les clés, les line items et les notes de la plateforme, et les notes que l'outil doit encore envoyer. |
| `Phil\LtiBundle\Infrastructure\Dbal\Row` | `final class`, interne | Des lectures typées d'une ligne, quel que soit le type que le pilote donne : un entier peut venir en texte, un nombre à virgule en texte. |
| `Phil\LtiBundle\Infrastructure\DependencyInjection\DefaultsPass` | `final readonly class` | Ce que l'application donne au bundle, ou sa valeur par défaut : une liste de membres vide lorsqu'elle n'en a pas, de sorte que les services de la plateforme répondent 404 pour chaque contexte ; un arrêt avec un message lorsque le côté plateforme est activé sans `LaunchMessages` ; et les recettes du kit nommées d'après le sous-répertoire des composants du Symfony UX Toolkit, à moins que la configuration n'en nomme un. |
| `Phil\LtiBundle\Infrastructure\DependencyInjection\LtiServices` | `final class` | Les services de la configuration : ce que les deux côtés partagent — les magasins des nonces et des clés, les jeux de clés de l'autre côté, le vérificateur, la route du jeu de clés, la page d'envoi du formulaire —, puis ceux de l'outil et de la plateforme lorsqu'ils sont activés. |
| `Phil\LtiBundle\Infrastructure\Http\FormPostResponder` | `final readonly class` | Répond avec une page dont le formulaire est envoyé par le navigateur à l'autre côté — un `id_token`, une réponse de Deep Linking — : le formulaire se soumet de lui-même par le contrôleur Stimulus du bundle, et affiche son bouton lorsque les scripts ne s'exécutent pas. La page n'est jamais mise en cache, jamais mise en cadre par une autre origine que celle où elle envoie le formulaire, et n'envoie aucun référent. |
| `Phil\LtiBundle\Infrastructure\KeyManagement\KeyManagementSealer` | `final readonly class` | Scelle les clés privées par chiffrement à enveloppe par le composant KeyManagement de Symfony, sous une clé maîtresse du KMS de l'application — une clé de données neuve par clé privée, AES-256-GCM, l'identifiant de clé comme données additionnelles — et garde l'enveloppe en base 64. Le composant est expérimental dans Symfony 8.2 : cet adaptateur est la seule classe qui l'appelle. |
| `Phil\LtiBundle\Infrastructure\Memory\InMemoryPendingScores` | `final class` | Des notes en attente gardées dans un seul processus, pour les tests. |
| `Phil\LtiBundle\Infrastructure\Messenger\PublishScoreHandler` | `final readonly class` | Envoie la note en attente qu'un message nomme. Un refus que la plateforme répétera — la plateforme n'est plus enregistrée, un statut de 400 à 499 autre que 408 et 429 — n'est pas réessayé ; tout autre échec l'est. |
| `Phil\LtiBundle\Infrastructure\Routing\PlatformUrls` | `final readonly class` | Les adresses des services de la plateforme, pour les revendications des lancements que l'application construit : le point de terminaison des Assignment and Grade Services d'un contexte et d'un line item, avec les portées pour lesquelles l'outil est enregistré, et les adhésions du contexte. |
| `Phil\LtiBundle\Infrastructure\Security\LtiLaunchAuthenticator` | `final class` | Transforme un lancement que l'outil vérifie en jeton de sécurité : sur la route du lancement, un POST avec un `id_token` et un `state` est vérifié par `LaunchValidator` ; son utilisateur est celui de l'application, par `LaunchUsers`, ou un `LtiUser` ; le jeton est un `LtiToken` qui porte le lancement. La requête continue ensuite vers le contrôleur du lancement. Un lancement qui échoue à une vérification répond 401 avec la raison, jamais avec le jeton. |
| `Phil\LtiBundle\Infrastructure\Security\LtiToken` | `final class` | Le jeton de sécurité d'un lancement vérifié : son utilisateur, son pare-feu, et le lancement, de sorte que l'application lit le contexte, les rôles et les points de terminaison des services dans `$token->launch`. |
| `Phil\LtiBundle\Infrastructure\Security\LtiUser` | `final readonly class` | L'utilisateur d'un lancement lorsque l'application n'a pas de compte propre pour lui : nommé par l'émetteur, le client et le sujet hachés du lancement, avec `ROLE_LTI` et, selon les rôles du lancement, `ROLE_LTI_INSTRUCTOR` ou `ROLE_LTI_LEARNER`. Il ne vit que dans la session. |
| `Phil\LtiBundle\Infrastructure\Security\LtiUserProvider` | `final readonly class` | Le fournisseur d'utilisateurs des `LtiUser` qu'un lancement connecte : ils ne vivent que dans la session, de sorte qu'un rafraîchissement les garde tels quels et qu'aucun n'est chargé par son identifiant. Un pare-feu de lancements le nomme lorsque l'application n'a pas de `LaunchUsers`, en chaîne avec le sien sinon. |
| `Phil\LtiBundle\Infrastructure\Twig\KitComponents` | `final readonly class` | Les noms sous lesquels les recettes du kit sont installées, dans le sous-répertoire des composants de la configuration `ux_toolkit` de l'application : avec `ui`, `Button` est `ui:Button`. |
| `Phil\LtiBundle\Infrastructure\Twig\LtiExtension` | `final readonly class` | `phil_lti_kit()`, le nom sous lequel une recette du kit est installée, pour les gabarits du bundle : `{% component (phil_lti_kit('Button')) %}`. |
| `Phil\LtiBundle\PhilLtiBundle` | `final class` | `phil/lti` dans Symfony : une application qui est un outil LTI 1.3, une plateforme, ou les deux. |

## Routes

| Route | Méthode | Fichier | Contrôleur |
|---|---|---|---|
| `phil_lti_jwks` | GET | `jwks.php` | `JwksController` |
| `phil_lti_login` | GET, POST | `tool.php` | `ToolController::login()` |
| `phil_lti_launch` | POST | `tool.php` | `ToolController::launch()`, après `LtiLaunchAuthenticator` |
| `phil_lti_platform_authorize` | GET, POST | `platform.php` | `PlatformLaunchController::authorize()` |
| `phil_lti_platform_deep_linking` | POST | `platform.php` | `PlatformLaunchController::deepLinking()` |
| `phil_lti_platform_token` | POST | `platform.php` | `TokenController` |
| `phil_lti_platform_line_items` | GET, POST | `platform.php` | `AgsController::lineItems()` |
| `phil_lti_platform_line_item` | GET, PUT, DELETE | `platform.php` | `AgsController::lineItem()` |
| `phil_lti_platform_scores` | POST | `platform.php` | `AgsController::scores()` |
| `phil_lti_platform_results` | GET | `platform.php` | `AgsController::results()` |
| `phil_lti_platform_memberships` | GET | `platform.php` | `NrpsController` |

## Commandes

| Commande | Ce qu'elle fait |
|---|---|
| `phil:lti:schema [--force]` | Affiche, ou crée, les tables manquantes de la connexion. |
| `phil:lti:rotate-keys [--if-due]` | Fabrique une nouvelle clé de signature, tout de suite ou une fois que la courante atteint sa durée de vie, et supprime les clés retirées avant le chevauchement. |
| `phil:lti:purge` | Retire les nonces, les états de connexion et les jetons d'accès expirés. |

## Gabarits et traductions

| Nom | Ce que c'est |
|---|---|
| `@PhilLti/form_post.html.twig` | La page dont le formulaire envoie un lancement ou une réponse de Deep Linking par POST ; `action` et `fields`. |
| `@PhilLti/deep_linking_returned.html.twig` | La page où un retour de Deep Linking aboutit sans réponse d'un écouteur ; `result`. |
| `phil_lti` | Le domaine de traduction des deux, en anglais et en français. |
| `phil_lti_kit()` | La fonction Twig qui nomme une recette du kit. |
| `@phil/lti-bundle` | L'espace de noms AssetMapper du contrôleur Stimulus `auto-submit`. |

## Constantes

| Constante | Valeur | Sens |
|---|---|---|
| `LtiTables::DEFAULT_PREFIX` | `phil_lti_` | Le préfixe par défaut des tables. |
| `LtiTables::PREFIX_PATTERN` | `/^[a-z_][a-z0-9_]{0,39}$/D` | La forme d'un préfixe. |
| `LtiServices::STORE_DBAL`, `STORE_MEMORY` | `dbal`, `memory` | Les valeurs de `store`. |
| `LtiServices::KEYS_DBAL`, `KEYS_CONFIG` | `dbal`, `config` | Les valeurs de `keys.source`. |
| `LtiServices::SERVICE_HTTP_CLIENT`, `KEY_SET_HTTP_CLIENT` | `phil_lti.service_http_client`, `phil_lti.key_set_http_client` | Les services `SafeHttpClient` des appels aux services et des jeux de clés. |
| `ServiceResponses::MAX_BODY` | `65536` | Le plus grand corps qu'un service lit. |
