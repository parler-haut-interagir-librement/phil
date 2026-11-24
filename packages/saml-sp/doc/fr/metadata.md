<!-- phil-dev source-sha256=bb10ba8fe60cf6dc761f9ebe5dc2c12468b782354add04ea6bc28d6b13c63677 -->
# Métadonnées

## Lire les métadonnées d'un fournisseur d'identité

`MetadataReader::read($xml, $entityId = null, $trusted = [])` lit un `EntityDescriptor`, ou celui qu'un
`EntitiesDescriptor` désigne par son identifiant d'entité, et en fait des `IdentityProviderMetadata` :

- l'identifiant d'entité ;
- les services de connexion et de déconnexion sur HTTP-Redirect et HTTP-POST, le premier de chaque liaison, les autres
  liaisons étant omises ; chaque adresse est lue par phil/url et doit être en HTTPS ;
- les certificats de signature : ceux des `KeyDescriptor` pour la signature et pour tout usage, sans répétition, dans
  l'ordre — plusieurs pendant un renouvellement de certificat ;
- `WantAuthnRequestsSigned`, et `validUntil` quand il est là.

Quand des certificats sont de confiance, la racine du document doit porter une signature qui se vérifie avec l'un
d'eux, comme Microsoft Entra ID signe ses métadonnées de fédération. Le document est lu par `SafeXml` dans le budget des
métadonnées : 4 Mio, 48 niveaux, 200 000 nœuds. `allowLocalhost: true` accepte `localhost` et les services en HTTP
simple, pour un fournisseur d'identité exécuté sur la machine du développeur.

Chaque refus est une `InvalidMetadata`.

## Les charger à partir de leur adresse

`MetadataLoader` lit des métadonnées à partir du XML, avec `fromXml()`, ou de leur adresse, avec `fromUrl()`, et refuse
des métadonnées au-delà de leur `validUntil`. Le texte récupéré est gardé dans un cache des contrats de cache de
Symfony pour une durée de vie, un jour par défaut (de 60 secondes à 30 jours), de sorte qu'une connexion n'attende
jamais le fournisseur d'identité ; des métadonnées que le lecteur refuse sont retirées du cache aussitôt.
`fromUrl(..., refresh: true)` les récupère de nouveau.

`SafeHttpMetadataFetcher` récupère par phil/safe-http : HTTPS seulement, réseaux privés refusés, redirections vérifiées
une à une et jamais hors de HTTPS, 5 secondes de silence et 15 en tout, 4 Mio au plus. Chaque échec est une
`MetadataUnavailable`.

## Les métadonnées du fournisseur de service

`ServiceProviderMetadata::xml($sp, $wantAssertionsSigned = true, $validUntil = null)` écrit l'`EntityDescriptor` qu'un
fournisseur d'identité importe :

- `AuthnRequestsSigned` et `WantAssertionsSigned` ;
- le certificat de signature, et chaque certificat de chiffrement avec les méthodes de chiffrement que le fournisseur de
  service lit : AES-256-GCM, AES-128-GCM, AES-256-CBC, AES-128-CBC ;
- le service de déconnexion sur HTTP-Redirect et HTTP-POST, quand le fournisseur de service en a un ;
- le format de l'identifiant de nom, quand le fournisseur de service en nomme un ;
- le service de consommation d'assertions sur HTTP-POST.
