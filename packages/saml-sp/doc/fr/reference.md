<!-- phil-dev source-sha256=7f44c816fd97d4b3d634125bbe5e1ba50e75b38880dcaf338fb31c5f342ddd62 -->
# Référence

## `Phil\SamlSp\Provider`

| Type | Rôle |
|---|---|
| `ServiceProvider` | L'application : identifiant d'entité, services de consommation d'assertions et de déconnexion, paires de clés, format de l'identifiant de nom. |
| `IdentityProvider` | Un fournisseur d'identité tel qu'on lui fait confiance : métadonnées, table des attributs, ce que ses réponses doivent respecter. |
| `SecurityPolicy` | Décalage d'horloge, âge d'une réponse, politique de signature, budget d'un message. |

## `Phil\SamlSp\Message`

| Type | Rôle |
|---|---|
| `MessageFactory` | Écrit l'`AuthnRequest`, la `LogoutRequest` et la `LogoutResponse` ; `newId()`. |
| `OutgoingMessage` | Un message à envoyer : ID, document, destination, liaison, paramètre, signé ou non. |
| `ReceivedMessage` | Un message décodé d'une liaison, et si sa liaison en a vérifié la signature. |
| `ResponseProcessor` | Lit, vérifie et contrôle une réponse ; retourne une `SamlIdentity`. |
| `LogoutProcessor` | Lit une requête ou une réponse de déconnexion du fournisseur d'identité. |
| `LogoutRequest` | Une requête de déconnexion vérifiée : ID, émetteur, identifiant de nom, index de session. |
| `PendingRequest` | Une requête qui attend sa réponse ; `toArray()`, `fromArray()`. |
| `TimeWindow` | Interne : l'instant d'un contrôle élargi du décalage d'horloge. |

## `Phil\SamlSp\Binding`

| Type | Rôle |
|---|---|
| `Binding` | `Redirect` ou `Post`, avec l'URI de la liaison. |
| `HttpRedirect` | Le message compressé dans une requête signée ; `url()`, `receive()`. |
| `HttpPost` | Le message dans un champ de formulaire ; `form()`, `receive()`, `decode()`. |
| `MessageSender` | Place un `OutgoingMessage` sur sa liaison, signé quand il doit l'être. |
| `Outbound` | Ce que le navigateur porte : une adresse où rediriger, ou un formulaire. |

## `Phil\SamlSp\Attribute`

| Type | Rôle |
|---|---|
| `AttributeMap` | Les attributs de chaque champ ; `entraId()`, `okta()`, `generic()`, `with()`, `map()`. |
| `UserField` | `Identifier`, `Email`, `GivenName`, `FamilyName`, `DisplayName`, `Groups`, `Roles`. |
| `SamlIdentity` | La personne qu'une assertion connecte ; `subject()`, `value()`, `values()`. |

## `Phil\SamlSp\Metadata`

| Type | Rôle |
|---|---|
| `IdentityProviderMetadata` | Identifiant d'entité, services par liaison, certificats de signature, `validUntil` ; `singleSignOn()`, `singleLogout()`. |
| `MetadataReader` | Lit les métadonnées d'un fournisseur d'identité, leur signature vérifiée quand des certificats sont de confiance. |
| `MetadataLoader` | Charge des métadonnées à partir du XML ou de leur adresse, en cache, les métadonnées expirées refusées. |
| `ServiceProviderMetadata` | Écrit les métadonnées du fournisseur de service. |

## `Phil\SamlSp\Signature`, `Phil\SamlSp\Encryption`, `Phil\SamlSp\Key`

| Type | Rôle |
|---|---|
| `SignatureVerifier` | Vérifie une signature enveloppée sur un élément. |
| `Signer` | Signe un élément, la signature après son `Issuer`. |
| `SignaturePolicy` | SHA-1 permis ou non, la plus petite clé RSA. |
| `SignatureAlgorithm`, `DigestAlgorithm` | Les signatures RSA et les empreintes lues et écrites. |
| `Decrypter` | Déchiffre une `EncryptedAssertion`. |
| `DataEncryption` | Les chiffrements AES d'une assertion chiffrée. |
| `Certificate` | Un certificat X.509 ; `fromPem()`, `fromBase64()`, `notAfter()`, `fingerprint()`. |
| `PrivateKey` | Une clé privée RSA, cachée des affichages de débogage. |
| `KeyPair` | Une clé privée et son certificat, dont on vérifie la correspondance. |

## `Phil\SamlSp\Contract`, `Phil\SamlSp\Store`, `Phil\SamlSp\Http`

| Type | Rôle |
|---|---|
| `Contract\ReplayGuard` | Retient les assertions acceptées jusqu'à leur expiration. |
| `Contract\PendingRequests` | Garde les requêtes qui attendent leur réponse. |
| `Contract\MetadataFetcher` | Récupère des métadonnées à partir de leur adresse. |
| `Store\DbalReplayGuard` | La garde de rejeu sur une table d'une connexion Doctrine DBAL, atomique ; `purge()`, `configureSchema()`, `key()`. |
| `Store\CacheReplayGuard`, `Store\InMemoryReplayGuard` | Gardes de rejeu sur une réserve PSR-6 et en mémoire. |
| `Store\CachePendingRequests`, `Store\InMemoryPendingRequests` | Requêtes en attente sur une réserve PSR-6 et en mémoire. |
| `Http\SafeHttpMetadataFetcher` | Récupère des métadonnées par phil/safe-http. |

## `Phil\SamlSp\Xml`

| Type | Rôle |
|---|---|
| `SafeXml` | Lit un document venu de l'extérieur, en refusant avant l'analyse ce dont SAML n'a jamais besoin. |
| `XmlBudget` | Les octets, la profondeur et les nœuds d'un document ; `message()`, `metadata()`. |
| `Ns` | Les espaces de noms et les identifiants de SAML, de XML Signature et de XML Encryption. |
| `Elements`, `IdMap`, `Instant`, `Base64`, `XmlWriter` | Interne : enfants directs, ID, instants, base64 en temps constant, écriture. |

## `Phil\SamlSp\Exception`

| Type | Rôle |
|---|---|
| `SamlException` | L'interface de chaque exception de la bibliothèque. |
| `InvalidXml` | Un document refusé avant d'être lu. |
| `InvalidSignature` | Une signature absente, mal formée, refusée ou qui ne se vérifie pas. |
| `InvalidMessage` | Un message refusé par un contrôle du profil. |
| `ReplayedAssertion` | Une assertion déjà acceptée. |
| `IdentityProviderRefused` | Un statut autre que le succès ; `status`, `subStatus`. |
| `InvalidMetadata`, `MetadataUnavailable` | Des métadonnées refusées, ou hors d'atteinte. |
| `InvalidKey` | Un certificat ou une clé qui ne peut être lu, ne correspond pas, ou est trop court. |
| `DecryptionFailed` | Une assertion chiffrée qui ne peut être déchiffrée. |
