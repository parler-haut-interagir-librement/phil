<!-- phil-dev source-sha256=7ac8a0cd956ea97ef3c3eeffa064307a52b7936dfca4a5c7399d0654302c5e41 -->
# Référence

| Type | Nature | Rôle |
|---|---|---|
| `Phil\UserSecretsBundle\PhilUserSecretsBundle` | bundle | La configuration `phil_user_secrets`, le mappage Doctrine, le module de vie privée |
| `Phil\UserSecretsBundle\Domain\ValueObject\Owner` | `final readonly class` | La personne à qui appartient un secret |
| `Phil\UserSecretsBundle\Domain\ValueObject\SecretName` | `final readonly class` | Le nom d'un secret parmi ceux de son propriétaire |
| `Phil\UserSecretsBundle\Domain\ValueObject\ServiceName` | `final readonly class` | Le service qu'ouvre un secret |
| `Phil\UserSecretsBundle\Domain\ValueObject\SecretValue` | `final readonly class` | Une chaîne secrète qui ne se montre pas |
| `Phil\UserSecretsBundle\Domain\ValueObject\SealedSecret` | `final readonly class` | Un identifiant de clé et un texte chiffré, dont le premier octet nomme le format |
| `Phil\UserSecretsBundle\Domain\Encryption\SealedFormat` | enum | La version d'une valeur scellée : `Algorithm` (3), écrite ; `KeyManagement` (2), lue |
| `Phil\UserSecretsBundle\Domain\Encryption\SealedAlgorithm` | enum | L'algorithme d'une valeur scellée, son deuxième octet : `KeyManagementAes256Gcm` (1) |
| `Phil\UserSecretsBundle\Domain\Contract\Credential` | interface | Une clé d'API ou un jeton OAuth |
| `Phil\UserSecretsBundle\Domain\Credential\CredentialKind` | enum | `api_key`, `oauth_token` ; un identifiant de connexion tiré de sa charge utile |
| `Phil\UserSecretsBundle\Domain\Credential\ApiKey` | `final readonly class` | Une clé envoyée telle quelle |
| `Phil\UserSecretsBundle\Domain\Credential\OAuthToken` | `final readonly class` | Un jeton d'accès, un jeton d'actualisation, une expiration et des portées |
| `Phil\UserSecretsBundle\Domain\Model\StoredSecret` | entité | La ligne d'un secret |
| `Phil\UserSecretsBundle\Domain\Contract\StoredSecretRepository` | interface | Le stockage des secrets |
| `Phil\UserSecretsBundle\Domain\Exception\UserSecretsException` | interface | Chaque exception du bundle |
| `Phil\UserSecretsBundle\Domain\Exception\InvalidSecret` | `final class` | Une valeur mal formée |
| `Phil\UserSecretsBundle\Domain\Exception\RevokedSecret` | `final class` | Un secret révoqué lu |
| `Phil\UserSecretsBundle\Application\UserSecrets` | `final readonly class` | Stocker, lister, lire, révoquer, supprimer, effacer |
| `Phil\UserSecretsBundle\Application\KeyRotation` | `final readonly class` | Sceller de nouveau sous la clé maîtresse courante |
| `Phil\UserSecretsBundle\Application\RotationReport` | `final readonly class` | Ce qu'a fait une rotation des clés : scellés de nouveau, sautés, illisibles |
| `Phil\UserSecretsBundle\Application\SecretAccess` | `final readonly class` | Une tâche exécutée pour un propriétaire, avec son but |
| `Phil\UserSecretsBundle\Application\SecretSummary` | `final readonly class` | Un secret sans sa valeur |
| `Phil\UserSecretsBundle\Application\Contract\EnvelopeCipher` | interface | Le chiffrement par enveloppe sous une clé maîtresse nommée : le port vers le composant KeyManagement |
| `Phil\UserSecretsBundle\Infrastructure\KeyManagement\KeyManagementCipher` | `final readonly class` | Le chiffrement par enveloppe par un client du composant de Symfony, ses échecs convertis en exceptions du bundle |
| `Phil\UserSecretsBundle\Application\Encryption\Envelope` | `final readonly class` | Un identifiant de connexion scellé avec sa ligne derrière un en-tête de format et d'algorithme, ouvert selon cet en-tête |
| `Phil\UserSecretsBundle\Application\Audit\AuditTrail` | `final readonly class` | Les événements transmis à chaque auditeur |
| `Phil\UserSecretsBundle\Application\Audit\SecretEvent` | `final readonly class` | Un événement d'un secret |
| `Phil\UserSecretsBundle\Application\Audit\SecretEventKind` | enum | Ce qui s'est passé |
| `Phil\UserSecretsBundle\Application\Contract\TokenRefresher` | interface | Renouvelle un jeton OAuth expiré |
| `Phil\UserSecretsBundle\Application\Contract\TokenRevoker` | interface | Révoque un jeton OAuth auprès de son service |
| `Phil\UserSecretsBundle\Application\Contract\RefreshLock` | interface | Un seul processus à la fois actualise un jeton |
| `Phil\UserSecretsBundle\Application\Contract\SecretAuditor` | interface | Un crochet d'audit |
| `Phil\UserSecretsBundle\Application\Exception\SecretNotFound` | `final class` | Aucun secret de ce nom |
| `Phil\UserSecretsBundle\Application\Exception\WrongCredentialKind` | `final class` | Un secret lu comme une autre nature |
| `Phil\UserSecretsBundle\Application\Exception\ExpiredToken` | `final class` | Un jeton qui ne peut pas être actualisé |
| `Phil\UserSecretsBundle\Application\Exception\RefreshConflict` | `final class` | Un jeton actualisé qui n'a pas pu être enregistré par-dessus les changements d'autres processus |
| `Phil\UserSecretsBundle\Application\Exception\UnreadableSecret` | `final class` | Un texte chiffré qui ne s'ouvre pas, ou que le KMS n'a pas ouvert |
| `Phil\UserSecretsBundle\Application\Exception\SealingFailed` | `final class` | Une valeur que le KMS n'a pas scellée |
| `Phil\UserSecretsBundle\Application\Exception\UnknownKey` | `final class` | Une clé courante qui manque au KMS |
| `Phil\UserSecretsBundle\Infrastructure\Doctrine\DoctrineStoredSecretRepository` | `final readonly class` | Les secrets dans Doctrine |
| `Phil\UserSecretsBundle\Infrastructure\Memory\InMemoryStoredSecretRepository` | `final class` | Les secrets dans un tableau |
| `Phil\UserSecretsBundle\Infrastructure\Lock\SymfonyRefreshLock` | `final readonly class` | Le verrou d'actualisation sur le composant Lock de Symfony |
| `Phil\UserSecretsBundle\Infrastructure\Privacy\UserSecretsPrivacy` | `final readonly class` | Le module `user_secrets` de `phil/privacy-bundle` |
| `Phil\UserSecretsBundle\Infrastructure\Audit\LoggerAuditor` | `final readonly class` | Les événements vers un journaliseur PSR-3 |
| `Phil\UserSecretsBundle\Infrastructure\Console\GenerateKeyCommand` | `final class` | `phil:user-secrets:generate-key` |
| `Phil\UserSecretsBundle\Infrastructure\Console\RotateKeysCommand` | `final class` | `phil:user-secrets:rotate` |
| `Phil\UserSecretsBundle\Infrastructure\DependencyInjection\UserSecretsServices` | `final class` | Les services de la configuration |
| `Phil\UserSecretsBundle\Infrastructure\DependencyInjection\KmsPass` | `final readonly class` | Arrête le conteneur quand le KMS n'est pas défini |
| `Phil\UserSecretsBundle\Infrastructure\DependencyInjection\RefreshLockPass` | `final readonly class` | Donne à `UserSecrets` le verrou d'actualisation quand une fabrique de verrous est définie |
