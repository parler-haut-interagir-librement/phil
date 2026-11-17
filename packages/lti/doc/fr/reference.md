<!-- phil-dev source-sha256=722d6b73039ff7835cf464385f69fb7e4eddda2e079270e456de7527877a0742 -->
# Référence

Chaque type public du paquet, avec son rôle. Un type marqué interne ne fait pas partie de l'API publique.

## Types

| Type | Genre | Rôle |
|---|---|---|
| `Phil\Lti\Contract\AccessTokens` | `interface` | Les jetons d'accès que la plateforme a accordés. |
| `Phil\Lti\Contract\ContentItem` | `interface` | Un élément de contenu de Deep Linking 2.0, choisi dans l'outil et renvoyé à la plateforme. |
| `Phil\Lti\Contract\KeySetSource` | `interface` | D'où l'on lit le jeu de clés de l'autre côté, par son adresse. |
| `Phil\Lti\Contract\KeyStore` | `interface` | Les clés de ce côté : la courante, qui signe, et le jeu de clés qu'il publie — la clé courante avec les clés des rotations précédentes encore dans leur chevauchement, de sorte qu'un jeton signé juste avant une rotation se vérifie. |
| `Phil\Lti\Contract\LineItemStore` | `interface` | Les line items que la plateforme garde, chacun appartenant à l'outil qui l'a créé ou pour qui il a été créé. |
| `Phil\Lti\Contract\LoginStates` | `interface` | Les états de connexion de l'outil. Un état est pris une seule fois : le second lancement avec le même état ne trouve rien. |
| `Phil\Lti\Contract\Nonces` | `interface` | Les valeurs qui ne peuvent être employées qu'une fois : le nonce d'un `id_token`, d'une réponse de Deep Linking, le `jti` d'une assertion de client. Chacune est retenue jusqu'à son expiration ; une valeur revue avant est un rejeu. |
| `Phil\Lti\Contract\PlatformRegistrations` | `interface` | Les plateformes enregistrées auprès de l'outil, et leurs déploiements. |
| `Phil\Lti\Contract\Roster` | `interface` | Les contextes de la plateforme et leurs membres, tels que l'application les garde : les services de la plateforme les lisent pour un outil, et seulement dans les contextes où l'outil est placé. |
| `Phil\Lti\Contract\ScoreStore` | `interface` | La dernière note de chaque apprenant sur chaque line item ; `record()` compare et écrit en une seule étape atomique et répond s'il a gardé la note. |
| `Phil\Lti\Contract\ServiceClient` | `interface` | Envoie des requêtes aux services de l'autre côté — son point de terminaison des jetons, ses notes, sa liste de membres —, qui exigent un corps et un en-tête `Authorization`. |
| `Phil\Lti\Contract\ToolRegistrations` | `interface` | Les outils enregistrés auprès de la plateforme, et leurs déploiements. |
| `Phil\Lti\DeepLinking\ContentItems` | `final class` | Lit la revendication des éléments de contenu d'une réponse de Deep Linking en éléments typés. Un élément d'un type que ce paquet ne connaît pas est ignoré, comme la spécification le permet à une plateforme. |
| `Phil\Lti\DeepLinking\FileItem` | `final readonly class` | Un fichier que la plateforme doit télécharger et garder, avant l'expiration de son adresse. |
| `Phil\Lti\DeepLinking\HtmlItem` | `final readonly class` | Un fragment de HTML que la plateforme doit intégrer. La plateforme décide de ce qu'elle en garde : une plateforme qui en lit un l'assainit avant de l'afficher. |
| `Phil\Lti\DeepLinking\Image` | `final readonly class` | Une icône ou une vignette d'un élément de contenu. |
| `Phil\Lti\DeepLinking\ImageItem` | `final readonly class` | Une image que la plateforme doit afficher. |
| `Phil\Lti\DeepLinking\LineItemRequest` | `final readonly class` | Le line item qu'un lien de ressource demande à la plateforme de créer avec lui, lorsque la plateforme accepte les line items. |
| `Phil\Lti\DeepLinking\LinkItem` | `final readonly class` | Un simple lien vers une page, ouverte par la plateforme hors de LTI. |
| `Phil\Lti\DeepLinking\ResourceLinkItem` | `final readonly class` | Un lien de ressource de l'outil que la plateforme doit placer : lancé plus tard comme un lancement de lien de ressource, à son adresse ou à celle par défaut de l'outil, avec ses paramètres personnalisés et, lorsqu'il est demandé, un line item. |
| `Phil\Lti\Exception\AccessDenied` | `final class` | Un appel à un service de la plateforme sans jeton d'accès valide, ou avec un jeton qui n'a pas la portée dont l'appel a besoin. |
| `Phil\Lti\Exception\InvalidData` | `final class` | Un document d'un service — un line item, une note, un résultat, une page de membres, un élément de contenu — dont la forme ou les valeurs ne sont pas permises par la spécification. |
| `Phil\Lti\Exception\InvalidRequest` | `final class` | Une initiation de connexion, une requête d'authentification ou un lancement dont les paramètres manquent, sont mal formés ou ne correspondent pas à ce qui a été enregistré ou retenu. |
| `Phil\Lti\Exception\InvalidToken` | `final class` | Un JSON Web Token, ou l'une de ses revendications, mal formé, non signé par la clé attendue, expiré, rejoué ou adressé à quelqu'un d'autre. Le message dit quelle vérification a échoué ; il ne répète jamais le jeton. |
| `Phil\Lti\Exception\LtiException` | `interface` | Toutes les exceptions du paquet : attrapez-la pour traiter n'importe quel échec d'un lancement, d'un message ou d'un appel de service. |
| `Phil\Lti\Exception\ServiceFailed` | `final class` | Un appel à un service de l'autre côté — son jeu de clés, son point de terminaison des jetons, ses notes ou sa liste de membres — qui n'a pas pu être fait, ou qui a répondu par une erreur. |
| `Phil\Lti\Exception\TokenRequestRefused` | `final class` | Une requête au point de terminaison des jetons de la plateforme qui est refusée, avec le code d'erreur de la RFC 6749, section 5.2 : `invalid_request`, `invalid_client`, `invalid_grant`, `unauthorized_client`, `unsupported_grant_type` ou `invalid_scope`. |
| `Phil\Lti\Exception\UnknownRegistration` | `final class` | Une plateforme, un outil ou un déploiement qui n'est pas enregistré. |
| `Phil\Lti\Http\FormPost` | `final readonly class` | Un formulaire que le navigateur envoie par POST à l'autre côté, comme LTI envoie un `id_token` ou une réponse de Deep Linking : l'adresse et les champs, rendus par l'application en un formulaire qui se soumet de lui-même. |
| `Phil\Lti\Http\LinkHeader` | `final class` | Lit l'en-tête `Link` de la RFC 8288, comme les services paginent leurs listes : l'adresse d'une relation — `next`, `differences`, `first`, `last` —, résolue par rapport à l'adresse de la requête lorsqu'elle est relative. Un lien dont l'adresse n'en est pas une est passé. |
| `Phil\Lti\Http\SafeHttpServiceClient` | `final readonly class` | Envoie les requêtes des services — le point de terminaison des jetons, les Assignment and Grade Services, les Names and Role Provisioning Services — par `SafeHttpClient::send()` de `phil/safe-http` : HTTPS seulement, chaque hôte vérifié contre les réseaux privés et réservés, aucun mandataire de l'environnement, les en-têtes gardés sur l'origine de l'adresse, le silence de l'hôte, la durée totale et les octets de la réponse bornés. Par défaut, aucune redirection n'est suivie et une réponse est lue jusqu'à 4 Mio ; un client d'autres limites se donne au constructeur. |
| `Phil\Lti\Http\ServiceRequest` | `final readonly class` | Une requête à un service de l'autre côté. |
| `Phil\Lti\Http\ServiceResponse` | `final readonly class` | La réponse d'un service : son statut, ses en-têtes par nom en minuscules, et son corps. |
| `Phil\Lti\Http\UrlPath` | `final class`, interne | Ajoute un segment au chemin d'une adresse en gardant sa requête : les notes d'un line item sont à `{lineitem}/scores`, quelle que soit la requête que porte l'adresse du line item. |
| `Phil\Lti\Http\UrlQuery` | `final class`, interne | Ajoute des paramètres à la requête d'une adresse, en gardant ceux qu'elle a, sans son fragment. |
| `Phil\Lti\Jwt\Base64Url` | `final class` | Base 64 sûre pour les URL, sans remplissage, comme la RFC 7515 écrit les parties d'un jeton ; stricte à la lecture. |
| `Phil\Lti\Jwt\Jwt` | `final readonly class` | Une JSON Web Signature compacte lue mais pas encore vérifiée : son en-tête, ses revendications, les octets qu'elle signe et sa signature. Seule la forme est vérifiée ici : trois parties, chacune en base 64 sûre pour les URL, les deux premières des objets JSON de taille et de profondeur bornées, un algorithme nommé dans l'en-tête. |
| `Phil\Lti\Jwt\JwtSigner` | `final class` | Signe des revendications en JSON Web Signature compacte avec `RS256` par `web-token/jwt-library`, `typ`, `alg` et l'identifiant de clé dans l'en-tête protégé. |
| `Phil\Lti\Jwt\JwtVerifier` | `final readonly class` | Vérifie un jeton signé par l'autre côté : sa forme est d'abord lue dans des limites par `Jwt::parse()` ; l'algorithme est `RS256`, quoi que dise le jeton ; la clé est celle que son `kid` nomme dans le jeu de clés de l'autre côté, relu une fois lorsque la copie en cache ne la contient pas ; la signature se vérifie par `web-token/jwt-library` ; et les heures sont valides, selon l'horloge et une tolérance de quelques secondes pour les horloges qui dérivent : `exp` est dans le futur, `iat` n'est ni dans le futur ni plus vieux que l'âge maximal, `nbf`, lorsqu'il est présent, est passé. |
| `Phil\Lti\Key\CachedKeySetSource` | `final readonly class` | Garde dans un cache les jeux de clés qu'une autre source lit, une heure par défaut. Un rafraîchissement relit un jeu, une fois par minute au plus par adresse par défaut, de sorte que des jetons qui nomment des clés inconnues ne peuvent pas obliger le côté à lire le jeu à chaque requête. |
| `Phil\Lti\Key\InMemoryKeyStore` | `final readonly class` | Des clés gardées en mémoire : une clé courante et, publiées à côté d'elle, les clés publiques des rotations précédentes. Pour les tests, et pour une application qui lit ses clés dans sa configuration. |
| `Phil\Lti\Key\KeySet` | `final readonly class` | Un JSON Web Key Set de clés de signature RSA, tel qu'un côté le publie. En lire un garde les clés RSA pour `RS256` et ignore les autres — clés de chiffrement, d'un autre type ou d'un autre algorithme —, de sorte qu'un jeu partagé avec d'autres usages serve quand même. |
| `Phil\Lti\Key\RsaPrivateKey` | `final readonly class` | Une clé privée RSA de 2048 à 8192 bits qui signe avec `RS256`, sous un identifiant de clé publié avec sa moitié publique dans le jeu de clés de son côté : une JSON Web Key privée de `web-token/jwt-library`. Un vidage de l'objet montre son identifiant et sa moitié publique, jamais les membres privés. |
| `Phil\Lti\Key\RsaPublicKey` | `final readonly class` | Une clé publique RSA de 2048 à 8192 bits qui vérifie les signatures `RS256`, l'algorithme de LTI 1.3, avec son identifiant de clé : une JSON Web Key de `web-token/jwt-library`, lue depuis une JSON Web Key ou depuis du PEM une fois son type, sa taille, son exposant, son algorithme et son usage vérifiés, réécrite en JSON Web Key pour un jeu de clés. |
| `Phil\Lti\Key\SafeHttpKeySetSource` | `final readonly class` | Lit les jeux de clés par `phil/safe-http` : HTTPS seulement, réseaux privés refusés, redirections vérifiées, temps et octets bornés. Chaque appel relit le jeu : enveloppez-la dans `CachedKeySetSource`. |
| `Phil\Lti\Message\AgsEndpoint` | `final readonly class` | La revendication des Assignment and Grade Services dans un lancement : les portées que l'outil peut demander, les line items du contexte et, lorsque le lien de ressource en a un, son propre line item. |
| `Phil\Lti\Message\Claim` | `final class` | Les noms des revendications de LTI 1.3 et de ses services. |
| `Phil\Lti\Message\Context` | `final readonly class` | Le contexte d'un lancement — un cours, une section, un groupe — sur la plateforme. |
| `Phil\Lti\Message\ContextRole` | `enum` | Les rôles principaux d'une personne dans un contexte — un cours, un groupe —, selon le vocabulaire de LIS que LTI 1.3 emploie. |
| `Phil\Lti\Message\DeepLinkingSettings` | `final readonly class` | Ce qu'une requête de Deep Linking accepte en retour : où revenir, les types d'éléments de contenu, les présentations, si plusieurs éléments et des line items sont acceptés, et les données opaques que l'outil doit renvoyer telles quelles. |
| `Phil\Lti\Message\DocumentTarget` | `enum` | Où la plateforme affiche l'outil. |
| `Phil\Lti\Message\InstitutionRole` | `enum` | Les rôles d'une personne dans l'établissement de la plateforme, selon le vocabulaire de LIS. |
| `Phil\Lti\Message\Launch` | `final readonly class` | Un lancement que l'outil a vérifié : la plateforme qui l'a envoyé, par son émetteur et le client qu'elle a donné à l'outil, et le message. `claims` garde chaque revendication du jeton, celles d'autres spécifications comprises, pour ce que ce paquet ne type pas. |
| `Phil\Lti\Message\LaunchMessage` | `final readonly class` | Les revendications de LTI qu'un lancement porte, typées : ce que la plateforme construit et signe, ce que l'outil lit une fois le jeton vérifié. Un lancement de lien de ressource nomme son lien de ressource ; un lancement de Deep Linking porte ses paramètres. |
| `Phil\Lti\Message\LaunchPresentation` | `final readonly class` | Comment la plateforme présente l'outil : où, à quelle taille, dans quelle langue, et où renvoyer la personne. |
| `Phil\Lti\Message\MessageType` | `enum` | Les messages de LTI 1.3 Core et de Deep Linking 2.0. |
| `Phil\Lti\Message\NrpsEndpoint` | `final readonly class` | La revendication du Names and Role Provisioning Service dans un lancement : où les membres du contexte sont listés. |
| `Phil\Lti\Message\Person` | `final readonly class` | La personne visée par un lancement : le sujet, l'identifiant stable que la plateforme lui donne pour cet outil, et ce que la plateforme a choisi de partager de son nom, de son courriel et de sa photo. Le sujet est null dans un lancement anonyme. |
| `Phil\Lti\Message\ResourceLink` | `final readonly class` | Le lien de ressource qu'un lancement ouvre : le placement de l'outil dans un contexte de la plateforme, stable d'un lancement à l'autre. |
| `Phil\Lti\Message\Roles` | `final readonly class` | Les rôles de la personne visée par un lancement, sous forme d'URI complets. Un rôle de contexte envoyé par son nom court, `Instructor`, est lu comme l'URI complet de son vocabulaire, comme LTI 1.3 le permet encore ; un rôle d'un autre vocabulaire est gardé tel quel. |
| `Phil\Lti\Message\SystemRole` | `enum` | Les rôles d'une personne sur la plateforme elle-même, selon le vocabulaire de LIS. |
| `Phil\Lti\Message\ToolPlatform` | `final readonly class` | L'instance de la plateforme d'où vient un lancement, telle qu'elle se décrit. |
| `Phil\Lti\Platform\Ags\AgsService` | `final readonly class` | Les Assignment and Grade Services de la plateforme, pour un outil dont le jeton d'accès accorde la portée de chaque opération, dans un contexte où l'outil est placé, sur les line items qui sont les siens. Une note plus ancienne que la dernière de l'apprenant sur le line item n'est pas gardée, comme la spécification le demande ; les résultats ramènent la dernière note à l'échelle du maximum du line item. `lineItems()` répond une page avec son `limit` et le `nextOffset` de la page suivante. |
| `Phil\Lti\Platform\Ags\InMemoryLineItemStore` | `final class` | Des line items gardés dans un seul processus, pour les tests. |
| `Phil\Lti\Platform\Ags\InMemoryScoreStore` | `final class` | Des notes gardées dans un seul processus, pour les tests : un processus exécute une écriture à la fois, donc la comparaison et l'écriture sont une seule étape. |
| `Phil\Lti\Platform\Ags\StoredLineItem` | `final readonly class` | Un line item que la plateforme garde : son identifiant, l'outil auquel il appartient, son contexte et ses champs. Son adresse est fabriquée par l'application à partir de son identifiant. |
| `Phil\Lti\Platform\AuthenticationRequest` | `final readonly class` | La requête d'authentification OpenID Connect avec laquelle un outil renvoie le navigateur au point de terminaison d'autorisation de la plateforme : ce que LTI fixe — `scope=openid`, `response_type=id_token`, `response_mode=form_post`, `prompt=none` — et ce qu'elle porte — le client, l'adresse de redirection, les indices, le `state` et le `nonce`. |
| `Phil\Lti\Platform\DeepLinkingResult` | `final readonly class` | Une réponse de Deep Linking que la plateforme a vérifiée : l'outil et le déploiement d'où elle vient, les éléments choisis, les données que la plateforme a mises dans la requête, et les messages pour la personne et pour les journaux ; `key` la nomme pour de bon, `claims` contient les revendications avec lesquelles elle a été vérifiée, `fromClaims()` les relit sans le jeton, `key()` donne la clé d'un client et d'un `nonce`. |
| `Phil\Lti\Platform\DeepLinkingReturn` | `final readonly class` | Lit la réponse de Deep Linking qu'un outil renvoie à la plateforme, dans son champ `JWT` : `iss` un client enregistré, `aud` la plateforme, signée par la clé de l'outil, `exp` et `iat` valides, le `nonce` employé une seule fois, le déploiement celui de l'outil, le type et la version du message ceux d'une réponse ; puis les éléments de contenu. |
| `Phil\Lti\Platform\LaunchIssuer` | `final readonly class` | La seconde étape d'un lancement sur la plateforme : répond à la requête d'authentification de l'outil par l'`id_token` — le message signé avec la clé de la plateforme, `iss` la plateforme, `aud` et `azp` le client, `sub` la personne, le `nonce` de la requête, cinq minutes de vie — envoyé par le navigateur à l'adresse de redirection avec le `state`. |
| `Phil\Lti\Platform\LaunchStarter` | `final readonly class` | La première étape d'un lancement sur la plateforme : l'adresse de l'initiation de connexion de l'outil où le navigateur est envoyé, avec l'émetteur, l'indice de la personne, la cible du lancement, l'indice propre de la plateforme, le client et le déploiement. |
| `Phil\Lti\Platform\Nrps\InMemoryRoster` | `final class` | Des contextes et des membres gardés en mémoire, pour les tests et les démonstrations. |
| `Phil\Lti\Platform\Nrps\NrpsService` | `final readonly class` | Le Names and Role Provisioning Service de la plateforme : une page des membres d'un contexte pour un outil à qui la portée est accordée, dans un contexte où il est placé. La page contient `limit` membres au plus, 100 par défaut et 1 000 au plus ; `nextOffset` est l'endroit où la page suivante commence, null sur la dernière. |
| `Phil\Lti\Platform\Token\AccessToken` | `final readonly class` | Un jeton d'accès que la plateforme a accordé à un outil : gardé par le SHA-256 de sa valeur, jamais par la valeur elle-même, avec le client, les portées et le moment de son expiration. |
| `Phil\Lti\Platform\Token\InMemoryAccessTokens` | `final class` | Des jetons d'accès gardés dans un seul processus, pour les tests. |
| `Phil\Lti\Platform\Token\IssuedToken` | `final readonly class` | La réponse du point de terminaison des jetons : la valeur du jeton, donnée une fois, sa durée de vie et ses portées. |
| `Phil\Lti\Platform\Token\ServiceGuard` | `final readonly class` | Vérifie le jeton porteur d'un appel à un service de la plateforme, et la portée dont l'appel a besoin. |
| `Phil\Lti\Platform\Token\TokenEndpoint` | `final readonly class` | Le point de terminaison des jetons de la plateforme : le type d'autorisation `client_credentials`, l'outil s'authentifiant par une assertion JWT signée avec sa clé (RFC 7523) — `iss` et `sub` son client, `aud` l'adresse du point de terminaison, `exp` et `iat` valides, un `jti` employé une seule fois —, les portées accordées étant celles qu'il demande qui sont connues et enregistrées pour lui. Le jeton est une valeur aléatoire donnée une fois et gardée par son empreinte, pendant une heure par défaut. |
| `Phil\Lti\Registration\InMemoryPlatformRegistrations` | `final class` | Des plateformes enregistrées en mémoire, depuis la configuration de l'application ou dans les tests. |
| `Phil\Lti\Registration\InMemoryToolRegistrations` | `final class` | Des outils enregistrés en mémoire, depuis la configuration de l'application ou dans les tests. |
| `Phil\Lti\Registration\PlatformRegistration` | `final readonly class` | Une plateforme enregistrée auprès de l'outil : son émetteur, le client qu'elle a donné à l'outil, et ses points de terminaison — où l'outil envoie la personne s'authentifier, où il demande des jetons d'accès, et où ses clés sont publiées. |
| `Phil\Lti\Registration\ToolRegistration` | `final readonly class` | Un outil enregistré auprès de la plateforme : le client que la plateforme lui a donné, où est son initiation de connexion, les adresses où la plateforme peut envoyer un lancement par POST, où ses clés sont publiées, et les portées des services qu'il peut demander. |
| `Phil\Lti\Security\InMemoryLoginStates` | `final class` | Des états de connexion gardés dans un seul processus, pour les tests. |
| `Phil\Lti\Security\InMemoryNonces` | `final class` | Des nonces retenus dans un seul processus, pour les tests. |
| `Phil\Lti\Security\LoginState` | `final readonly class` | Ce que l'outil retient entre l'initiation de connexion et le lancement : le `state` qu'il a envoyé, le `nonce` que l'`id_token` doit porter, la plateforme qu'il a sollicitée, et la cible du lancement. |
| `Phil\Lti\Security\Random` | `final class`, interne | Des valeurs imprévisibles pour les états, les nonces, les identifiants de jeton et les jetons d'accès : 32 octets aléatoires en base 64 sûre pour les URL. |
| `Phil\Lti\Service\AccessTokenProvider` | `final readonly class` | Les jetons d'accès de l'outil pour les services d'une plateforme : le type d'autorisation `client_credentials`, l'outil s'authentifiant par un JWT signé avec sa clé (RFC 7523) — `iss` et `sub` le client, `aud` le point de terminaison des jetons, un `jti` neuf, cinq minutes de vie —, le jeton gardé dans un cache par plateforme et par ensemble de portées jusqu'à peu avant son expiration. |
| `Phil\Lti\Service\Ags\ActivityProgress` | `enum` | Où en est l'apprenant dans l'activité sur laquelle porte une note. |
| `Phil\Lti\Service\Ags\AgsClient` | `final readonly class` | Les Assignment and Grade Services d'une plateforme, appelés par l'outil avec la revendication du point de terminaison d'un lancement : les line items du contexte, lus, créés, modifiés et supprimés ; les notes envoyées ; les résultats lus. Chaque appel demande la portée dont il a besoin, lorsque la revendication l'accorde. |
| `Phil\Lti\Service\Ags\GradingProgress` | `enum` | Où en est la correction d'une note : seule une note `FullyGraded` est finale pour le carnet de notes de la plateforme. |
| `Phil\Lti\Service\Ags\LineItem` | `final readonly class` | Une colonne du carnet de notes d'un contexte : sur combien il est noté, son étiquette, et ce à quoi il appartient — un lien de ressource de la plateforme, une ressource de l'outil, une étiquette. Son `id` est son adresse, donnée par la plateforme. |
| `Phil\Lti\Service\Ags\LineItemPage` | `final readonly class` | Une page des line items d'un contexte, et l'adresse de la page suivante lorsqu'il y en a une. |
| `Phil\Lti\Service\Ags\MediaType` | `final class` | Les types de médias des Assignment and Grade Services. |
| `Phil\Lti\Service\Ags\Result` | `final readonly class` | Le résultat d'un apprenant sur un line item, tel que le carnet de notes de la plateforme le garde. |
| `Phil\Lti\Service\Ags\Score` | `final readonly class` | Une note que l'outil envoie pour un apprenant sur un line item : la note donnée sur un maximum, lorsqu'il y en a déjà une, où en sont l'apprenant et la correction, et le moment où la note a été faite, par lequel la plateforme garde la plus récente. |
| `Phil\Lti\Service\Nrps\MediaType` | `final class` | Le type de média du Names and Role Provisioning Service. |
| `Phil\Lti\Service\Nrps\Member` | `final readonly class` | Un membre d'un contexte : l'identifiant que la plateforme lui donne pour l'outil — le `sub` de ses lancements —, ses rôles et son statut, et ce que la plateforme a choisi de partager de son nom, de son courriel et de sa photo. |
| `Phil\Lti\Service\Nrps\MemberStatus` | `enum` | Le statut d'un membre d'un contexte. |
| `Phil\Lti\Service\Nrps\MembershipPage` | `final readonly class` | Une page des membres d'un contexte, l'adresse de la page suivante lorsqu'il y en a une, et l'adresse des changements depuis cette lecture, lorsque la plateforme l'offre. |
| `Phil\Lti\Service\Nrps\NrpsClient` | `final readonly class` | Le Names and Role Provisioning Service d'une plateforme, appelé par l'outil avec la revendication d'un lancement : les membres du contexte, une page à la fois, la page suivante trouvée dans l'en-tête `Link`. |
| `Phil\Lti\Service\Scope` | `final class` | Les portées de LTI Advantage qu'un outil demande au point de terminaison des jetons. |
| `Phil\Lti\Service\ServiceCall` | `final readonly class`, interne | Un appel de l'outil à un service d'une plateforme avec un jeton d'accès de la portée dont il a besoin, son JSON lu. |
| `Phil\Lti\Support\Fields` | `final readonly class`, interne | Des lectures typées des membres d'un objet JSON — les revendications d'un jeton, un document d'un service —, chacune bornée : une chaîne de 4 096 octets au plus par défaut, une liste de 256 entrées au plus. Un membre du mauvais type échoue avec `InvalidToken` dans un jeton et `InvalidData` dans un document, en nommant le membre. |
| `Phil\Lti\Tool\DeepLinkingResponder` | `final readonly class` | La réponse de l'outil à une requête de Deep Linking : les éléments de contenu choisis, signés avec la clé de l'outil en `LtiDeepLinkingResponse` pour la plateforme, avec les données opaques de la requête renvoyées telles quelles, envoyés par POST par le navigateur à l'adresse de retour de la requête. Les éléments doivent être des types que la requête accepte, un au plus à moins qu'elle n'en accepte plusieurs ; une liste vide dit que rien n'a été choisi. |
| `Phil\Lti\Tool\LaunchValidator` | `final readonly class` | La seconde étape d'un lancement sur l'outil : l'`id_token` et le `state` que la plateforme renvoie par POST. |
| `Phil\Lti\Tool\LoginInitiator` | `final readonly class` | La première étape d'un lancement sur l'outil : répond à l'initiation de connexion de la plateforme par une requête d'authentification OpenID Connect — `scope=openid`, `response_type=id_token`, `response_mode=form_post`, `prompt=none` —, un `state` et un `nonce` neufs retenus pour le lancement, pendant quelques minutes. |
| `Phil\Lti\Tool\LoginRedirect` | `final readonly class` | Où l'outil envoie le navigateur après une initiation de connexion — le point de terminaison d'autorisation de la plateforme avec la requête d'authentification dans sa requête —, et l'état qu'il a retenu. |
| `Phil\Lti\Tool\LoginRequest` | `final readonly class` | La connexion initiée par un tiers qu'une plateforme envoie à l'outil, par GET ou POST : l'émetteur, l'indice de la personne, la cible du lancement, et, lorsque la plateforme les donne, son propre indice, le client et le déploiement. |

## Constantes

| Constante | Valeur | Sens |
|---|---|---|
| `Jwt::MAX_LENGTH` | `65536` | Le plus long jeton lu, en octets. |
| `Jwt::MAX_DEPTH` | `16` | L'imbrication la plus profonde du JSON d'une partie. |
| `JwtVerifier::DEFAULT_LEEWAY` | `5` | Les secondes de tolérance pour les horloges qui dérivent. |
| `JwtVerifier::DEFAULT_MAX_AGE` | `600` | Le plus vieux jeton accepté, en secondes après son `iat`. |
| `RsaPublicKey::MIN_BITS`, `MAX_BITS` | `2048`, `8192` | Les bornes d'une clé. |
| `RsaPrivateKey::DEFAULT_BITS` | `2048` | Les bits d'une clé générée. |
| `RsaPrivateKey::MAX_JSON_BYTES`, `MAX_JSON_DEPTH` | `65536`, `4` | Les bornes du JSON d'une clé privée lue par `fromJson()`. |
| `KeySet::MAX_KEYS`, `MAX_BYTES` | `32`, `262144` | Les bornes d'un jeu de clés lu. |
| `CachedKeySetSource::DEFAULT_LIFETIME` | `3600` | Les secondes pendant lesquelles un jeu de clés est gardé. |
| `CachedKeySetSource::DEFAULT_REFRESH_INTERVAL` | `60` | Le moins de secondes entre deux lectures d'un même jeu de clés. |
| `LoginInitiator::DEFAULT_STATE_LIFETIME` | `600` | Les secondes entre une connexion et son lancement. |
| `LaunchIssuer::LIFETIME`, `DeepLinkingResponder::LIFETIME` | `300` | Les secondes de vie d'un `id_token` ou d'une réponse de Deep Linking. |
| `TokenEndpoint::DEFAULT_LIFETIME` | `3600` | Les secondes de vie d'un jeton d'accès. |
| `AccessTokenProvider::MARGIN` | `30` | Les secondes avant son expiration où un jeton d'accès en cache est abandonné. |
| `AccessTokenProvider::ASSERTION_TYPE` | `urn:ietf:params:oauth:client-assertion-type:jwt-bearer` | Le type d'assertion de client de la RFC 7523. |
| `LaunchMessage::MAX_CUSTOM` | `100` | Le plus de paramètres personnalisés lus. |
| `ContentItems::MAX_ITEMS` | `100` | Le plus d'éléments de contenu lus dans une réponse. |
| `HtmlItem::MAX_BYTES` | `65536` | Le plus grand HTML d'un élément de contenu. |
| `NrpsClient::MAX_PAGES`, `MAX_MEMBERS_PER_PAGE` | `100`, `1000` | Les bornes d'une lecture des membres. |
| `AgsService::DEFAULT_LIMIT`, `MAX_LIMIT` ; `NrpsService::DEFAULT_LIMIT`, `MAX_LIMIT` | `100`, `1000` | La taille d'une page que la plateforme répond. |
| `SafeHttpServiceClient::DEFAULT_MAX_BYTES` | `4194304` | La plus grande réponse d'un service lue par le client par défaut. |
| `SafeHttpServiceClient::USER_AGENT` | `PhilLti/0.1` | Le `User-Agent` du client par défaut. |
| `LinkHeader::MAX_LENGTH` | `8192` | Le plus long en-tête `Link` lu. |
| `Claim::LTI_VERSION` | `1.3.0` | La version que porte chaque message. |

## Exceptions

| Exception | Levée quand |
|---|---|
| `LtiException` | L'interface de toutes les exceptions du paquet. |
| `InvalidToken` | Un jeton, ou une revendication, est mal formé, non signé par la clé attendue, expiré, rejoué ou destiné à quelqu'un d'autre. |
| `InvalidRequest` | Il manque un paramètre à une initiation de connexion, une requête d'authentification ou un lancement, ou il ne correspond pas. |
| `InvalidData` | Un document d'un service — un line item, une note, une liste de membres — n'est pas ce que la spécification permet. |
| `UnknownRegistration` | Une plateforme, un outil ou un déploiement n'est pas enregistré. |
| `ServiceFailed` | Un jeu de clés ou un service est inaccessible, ou répond une erreur ; `status()` donne le statut HTTP. |
| `TokenRequestRefused` | Le point de terminaison des jetons refuse une requête ; `error` est le code de la RFC 6749. |
| `AccessDenied` | Un appel à un service de la plateforme n'a pas de jeton valide, ou n'en a pas la portée. |
