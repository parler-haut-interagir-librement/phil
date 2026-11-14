<!-- phil-dev source-sha256=06e70fd5ce595ea0820881415e490093ff8d8629c31e09f22a7881d9bdd01c95 -->
# Points d'extension

## `StatusLists`

Les listes d'état de l'application, `Phil\OpenBadgesBundle\Application\Contract\StatusLists` :

```php
interface StatusLists
{
    public function find(string $list): ?StatusListContent;
}
```

`find()` renvoie le contenu d'une liste — les index dont le bit est activé et l'objet, `revocation` ou `suspension` —,
ou null pour une liste que l'application n'a pas. Elle est appelée pour chaque liste d'état servie et pour chaque
attestation que la page de vérification contrôle contre l'une des listes du site : une implémentation lit un index
conservé avec ce qui a été émis, sans parcourir toutes les attestations. Voir [Publication](publishing.md).

## La mise en page de la page de vérification

`verification.layout` nomme le gabarit Twig qu'étend la page, avec les blocs `title` et `content` ; la page elle-même
se remplace comme n'importe quel gabarit de bundle. Voir [Page de vérification](verification-page.md).

## Clés de confiance

`trusted_keys` associe les méthodes de vérification d'autres émetteurs à leurs clés Ed25519, sous forme de
`publicKeyMultibase` : le vérificateur et la page acceptent leurs attestations quand la méthode est sous
l'identifiant de l'émetteur, comme `https://partner.example/issuer#key-1` pour `https://partner.example/issuer`.
Pour vérifier des émetteurs que l'application ne liste pas, `remote_issuers` lit leurs profils
([Autres émetteurs](remote-issuers.md)).

## Les méthodes de vérification

`Phil\OpenBadges\Contract\VerificationMethods` est le service auquel le vérificateur demande les clés : les clés connues,
`phil_open_badges.known_methods`, ou `RemoteVerificationMethods` autour d'elles quand `remote_issuers` est activé. Les
émetteurs `did:web` y sont déjà lus. Une application qui trouve des clés ailleurs (un registre de ses partenaires, une
autre méthode DID) le décore; un décorateur
interroge d'abord le service interne et lève `InvalidCredential` pour une méthode qu'il ne détient pas. Le client HTTP des
profils, `phil_open_badges.safe_http_client`, est un `SafeHttpClient` au-dessus du `http_client` de l'application quand
elle en a un : un client à portée limitée ou un enregistrement des tests s'y place, et la garde l'enveloppe toujours.

## Les contextes JSON-LD

`contexts` donne à la suite Data Integrity les documents de contexte que fournit l'application, un fichier par URL. Pour
les lire d'ailleurs — un cache, une base de données —, remplacez l'argument du service
`Phil\OpenBadges\DataIntegrity\EddsaRdfc2022` par un `Phil\OpenBadges\Contract\ContextLoader` de l'application; un
chargeur qui récupère des contextes laisse un document choisir ce que ses propres termes veulent dire : il sert donc les
contextes que l'application accepte et refuse les autres.

## La limite sur les vérifications

`verification.rate_limit` nomme n'importe quel limiteur de `phil/rate-limit-bundle`, le sien ou un que l'étiquette
`phil_rate_limit.limiter` désigne dans un autre paquet. Une route de l'application qui sert la page sous un autre nom
garde sa propre valeur par défaut `_rate_limit` : la limite ne s'applique qu'à la route du bundle.

## Les services de la bibliothèque

Chaque service de `phil/open-badges` qu'enregistre le bundle peut être décoré ou remplacé par sa classe : un `Signer`
sur un service de gestion de clés, par exemple, remplace `Phil\OpenBadges\Contract\Signer`.
