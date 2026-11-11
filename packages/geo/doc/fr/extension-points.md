<!-- phil-dev source-sha256=da12e6fbdd315d368ae4e3765ec113664c420335d207693e36f080a86f8b4eb8 -->
# Points d'extension

La bibliothèque est du PHP simple et ne déclare aucun service. Le géocodage se trouve derrière deux ports ; les types
valeur prennent en arguments ce qu'une application choisit.

## `Geocoder`

`Phil\Geo\Geocoding\Geocoder` : des lieux à partir d'une adresse et à partir d'un point. Voir [Géocodage](geocoding.md).

| Méthode | Contrat |
|---|---|
| `geocode(string $address, ?string $locale = null): list<Place>` | Les lieux trouvés, le meilleur en premier ; une liste vide quand rien n'est trouvé ; `GeocodingFailed` quand le fournisseur ne peut pas répondre, avec un message qui nomme le fournisseur et jamais la requête |
| `reverse(Coordinates $at, ?string $locale = null): list<Place>` | La même chose, à partir d'un point ; un fournisseur qui ne trouve rien à partir de points retourne une liste vide |

Dix adaptateurs sont fournis. `ChainGeocoder` en interroge plusieurs à tour de rôle, et passe au suivant quand l'un ne
trouve rien ou échoue ; `CachedGeocoder` conserve les réponses dans un pool PSR-6 pendant une durée que permettent les
conditions du fournisseur. Un géocodeur propre à l'application (un fournisseur non fourni, sa propre base de lieux)
s'utilise seul ou dans l'un ou l'autre.

## `IpLocator`

`Phil\Geo\Geocoding\IpLocator` : un lieu approximatif à partir d'une adresse IP.

| Méthode | Contrat |
|---|---|
| `locate(string $ip): ?Place` | Une ville au mieux, ou `null` ; `InvalidPlace` pour une chaîne qui n'est pas une adresse IP |

`IpstackLocator` interroge ipstack ; `MaxMindLocator` lit une base de villes MaxMind sur le serveur et n'envoie rien.
Une adresse IP est un renseignement personnel : un localisateur propre à l'application ne l'envoie nulle part où elle ne
l'a pas choisi.

## Types valeur

| Point | Comment |
|---|---|
| Le degré de brouillage d'un lieu publié | Les décimales gardées par `Coordinates::blurred()` |
| L'unité d'une distance | `Distance` en mètres, kilomètres ou milles |
| Les données structurées | `SchemaOrg::of()` donne des `GeoCoordinates` de schema.org |
