<!-- phil-dev source-sha256=9c355500089fd8f238f8d12ee2f7c5181b7ae3831df191155d060467f8bb7965 -->
# Géocodage

## Les ports

| Type | Rôle |
|---|---|
| `Geocoding\Geocoder` | `geocode(string $address, ?string $locale)` et `reverse(Coordinates $at, ?string $locale)` : des lieux, le meilleur en premier |
| `Geocoding\IpLocator` | `locate(string $ip)` : un lieu approximatif (une ville au mieux), ou null |
| `Geocoding\Place` | `coordinates`, `provider`, `label`, `street`, `locality`, `region`, `postalCode`, `countryCode` |
| `Exception\GeocodingFailed` | Un fournisseur qui n'a pas pu répondre |

Une partie d'adresse qu'un fournisseur ne donne pas vaut null ; un pays est représenté par ses deux lettres ISO 3166-1,
en majuscules. Un fournisseur qui répond n'avoir rien trouvé donne une liste vide, jamais un échec. Un fournisseur
injoignable, qui refuse la clé, dépasse son quota ou répond ce qu'il ne devrait pas (un lieu sans coordonnées, des
coordonnées hors bornes, un pays qui ne compte pas deux lettres, autre chose que du JSON) produit un `GeocodingFailed`.
Son message donne le fournisseur et le statut HTTP ou le genre d'échec, jamais la requête. L'URL d'une requête contient
la clé et l'adresse ou l'adresse IP demandée : ni le message ni l'exception du client HTTP ne sont donc gardés, et un
journal ou une page d'erreur qui montre l'échec ne montre aucun secret ni aucun renseignement personnel.

Les ports et les lieux ne nomment rien d'autre que cette bibliothèque : un modèle de domaine peut les contenir.

## Les adaptateurs

Ils prennent le `HttpClientInterface` de Symfony.

| Adaptateur | Prend | Interroge |
|---|---|---|
| `NominatimGeocoder` | l'agent utilisateur, une URL de base (le serveur public par défaut), le nombre maximal de lieux (5) | `/search` et `/reverse`, `jsonv2`, avec le détail de l'adresse |
| `GoogleMapsGeocoder` | la clé, une URL de base | la Geocoding API : `address` ou `latlng` |
| `NrcanGeocoder` | une URL de base (le service de Ressources naturelles Canada), le nombre maximal de lieux (5) | `/{en,fr}/locate?q=` |
| `PhotonGeocoder` | une URL de base (le serveur public), le nombre maximal de lieux (5), les langues que connaît le serveur (`de`, `en`, `fr`) | `/api?q=` et `/reverse?lat=&lon=`, en GeoJSON |
| `OpenCageGeocoder` | la clé, une URL de base, le nombre maximal de lieux (5) | `?q=` une adresse ou `lat,lng`, sans annotations |
| `HereGeocoder` | la clé, les URL de géocodage et de géocodage inverse, le nombre maximal de lieux (5) | `/geocode?q=` et `/revgeocode?at=`, avec `show=countryInfo` |
| `MapboxGeocoder` | le jeton d'accès, une URL de base, le nombre maximal de lieux (5), si les réponses sont permanentes | `/forward?q=` et `/reverse?longitude=&latitude=` de l'API v6 |
| `AzureMapsGeocoder` | la clé d'abonnement, une URL de base, le nombre maximal de lieux (5), la version de l'API (`2026-01-01`) | `/geocode?query=` et `/reverseGeocode?coordinates=` |
| `AdressesQuebecGeocoder` | une URL de base (le service du Gouvernement du Québec), le nombre maximal de lieux (5) | `/findAddressCandidates?SingleLine=` et `/reverseGeocode?location=`, en WGS 84 |
| `IpstackLocator` | la clé d'accès, une URL de base (`https://api.ipstack.com`) | `/{ip}` |

La locale passée devient `accept-language` pour Nominatim et `language` pour Google Maps. La politique d'utilisation de
Nominatim demande un agent utilisateur qui nomme le site et la façon de le joindre, et au plus une requête par seconde
sur son serveur public. ipstack refuse une adresse IP invalide avant d'interroger le service. Il l'interroge en HTTPS ;
un forfait qui ne répond qu'en HTTP simple prend `http://api.ipstack.com` comme URL de base, et chaque recherche envoie
alors en clair l'adresse IP du visiteur (un renseignement personnel selon la Loi 25 du Québec) et la clé d'accès.
`MaxMindLocator` lit une base sur le serveur et n'envoie rien.

Le service de géolocalisation de Ressources naturelles Canada est gratuit et ne demande aucune clé, pour le Canada
seulement : adresses civiques, intersections, codes postaux et noms de la Base de données toponymiques du Canada. Une
locale en français demande des titres en français, toute autre des titres en anglais. Il trouve des lieux à partir de
mots et jamais à partir d'un point : `reverse()` ne trouve donc rien et une chaîne interroge le géocodeur suivant. Ses
lieux portent comme libellé le titre que donne le service, `CA` comme pays, et le code postal quand c'est ce qu'il a
trouvé ; il ne donne aucune autre partie d'adresse. Une réponse qui est un message au lieu de lieux produit un
`GeocodingFailed`.

Photon trouve des lieux dans les données d'OpenStreetMap au fil de la frappe. Son serveur public ne demande aucune clé,
mais une utilisation raisonnable : il limite le débit des gros utilisateurs. Votre propre serveur s'indique par une URL
de base. Un serveur refuse une langue qu'il ne connaît pas : une locale n'est donc envoyée comme `lang` que si sa langue
(`fr` pour `fr_CA`) fait partie de celles données au géocodeur ; sinon, les lieux arrivent sous leurs noms locaux. Un
lieu porte sa rue (numéro et rue), sa ville, son État ou sa province, son code postal et son code de pays, ainsi qu'un
libellé fait de son nom et de ces parties ; un lieu qui est une rue, une ville ou un État donne son nom comme cette
partie.

OpenCage, HERE et Mapbox prennent la locale sous forme d'étiquette IETF (`fr-CA` pour `fr_CA`), Azure Maps sous forme de
son en-tête `Accept-Language`. Leurs conditions diffèrent sur ce qu'une application peut conserver, ce qui importe pour
`CachedGeocoder` :

| Fournisseur | Clé | Ce qui peut être conservé | Un point donne |
|---|---|---|---|
| OpenCage | celle de son tableau de bord | tout, aussi longtemps que vous le voulez | au plus un lieu |
| HERE | celle de la plateforme HERE | trente jours au plus, sauf si le droit de conserver est acheté : une durée de cache sous trente jours | jusqu'au nombre maximal de lieux |
| Mapbox | un jeton d'accès | rien, sauf avec `permanent` (facturé) ; ses réponses temporaires ne s'affichent qu'avec une carte Mapbox | un lieu |
| Azure Maps | celle du compte Azure | ce que disent les Microsoft Product Terms | le lieu le plus précis |
| Adresses Québec | aucune | ses données sont sous CC BY 4.0 : citez le Ministère des Ressources naturelles et des Forêts | l'adresse à cet endroit, ou aucune |

Le pays est le code alpha-2 que donne chacun ; celui de HERE vient de `countryInfo`, que l'adaptateur demande. Adresses
Québec ne donne que des adresses du Québec, tirées du registre d'adresses de la province, sans langue, région ni pays :
ses lieux ont `Québec` et `CA`, et un code postal de la forme `A1A 1A1`. Ses candidats n'arrivent dans aucun ordre : ils
sont donc triés par score ; une rue trouvée sans numéro n'a pas de partie rue. Un point où il ne trouve aucune adresse
répond par une erreur, lue comme aucun lieu ; une erreur sur une adresse produit un `GeocodingFailed`. Le Gouvernement
du Québec remplace le service par un autre basé sur son nouveau registre d'adresses, annoncé pour 2026 : l'URL de base
est configurable.

```php
$geocoder = new CachedGeocoder(
    new ChainGeocoder([
        new NominatimGeocoder($httpClient, 'Example (contact@example.com)'),
        new GoogleMapsGeocoder($httpClient, $key),
    ]),
    $cache,
    604800,
);

$places = $geocoder->geocode('4545 avenue Pierre-De Coubertin, Montréal', 'fr');
$places[0]->coordinates;    // Coordinates
```

## Chaîne et cache

`ChainGeocoder` interroge ses géocodeurs à tour de rôle et donne les lieux du premier qui en trouve. Celui qui échoue
est passé ; quand tous échouent, le dernier échec est lancé ; quand aucun ne trouve rien, il n'y a aucun lieu.

`CachedGeocoder` conserve une réponse pendant sa durée de vie (une semaine par défaut) dans un cache de
`symfony/cache-contracts`, selon l'opération, l'adresse ou le point, et la locale. Un échec n'est pas conservé.

## Bases MaxMind, sur le serveur

`MaxMindLocator` localise une adresse IP dans une base de villes MaxMind conservée sur le serveur : GeoLite2 City,
gratuite avec un compte MaxMind, ou GeoIP2 City. Rien ne quitte le serveur : aucune requête, aucune clé, aucun quota, et
l'adresse du visiteur reste chez le site, ce que favorise une loi sur la vie privée comme la Loi 25 du Québec.

```php
use Phil\Geo\Geocoding\Adapter\MaxMindLocator;
use Phil\Geo\Geocoding\MaxMind\Database;

$locator = new MaxMindLocator(new Database('/var/lib/geoip/GeoLite2-City.mmdb'), 'fr');

$place = $locator->locate('81.2.69.142');
$place?->locality;     // "Londres", or the English name when the database has no French one
$place?->countryCode;  // "GB"
```

- Le lieu porte la ville, la première subdivision comme région, le code postal et le pays ; son libellé est la ville, la
  région et le nom du pays, dans la locale passée.
- Un réseau que la base ne contient pas, ou une fiche sans position (toutes les fiches d'une base de pays), ne donne
  aucun lieu.
- Une adresse invalide lance `InvalidPlace`, dont le message n'en montre que les 64 premiers octets, caractères de
  contrôle échappés ; un fichier absent, illisible ou endommagé, `InvalidDatabase`.

`Database` lit tout fichier MaxMind DB, comme le décrit la spécification de son format (version 2.0) : `find($ip)` donne
la fiche du réseau auquel appartient une adresse, telle que la base la contient, et `metadata()` ce que le fichier dit
de lui-même. Le fichier est ouvert à la première recherche et lu là où mène chaque recherche, sans jamais être chargé en
entier : il est lu par blocs de 4 Ko, et une `Database` en garde jusqu'à 64 (256 Ko) pour les recherches suivantes. Une
adresse IPv4 est trouvée dans une base IPv6 ; une adresse IPv6 dans une base IPv4 ne trouve rien. Un entier de 64 ou 128
bits trop grand pour PHP est donné par ses chiffres hexadécimaux, précédés de `0x`.

La base appartient à l'application : celle-ci la télécharge avec son compte MaxMind, la tient à jour (`geoipupdate`
remplace le fichier, et une nouvelle `Database` lit le nouveau) et affiche l'attribution que demande la licence de
MaxMind.
