<!-- phil-dev source-sha256=88e6a9e26efd8f00af7105587140fcb0fa2c02a98b951160d5e1aad86fdca206 -->
# Géocodage

```yaml
phil_geo:
    geocoding:
        providers: [nrcan, nominatim, google_maps]   # asked in this order
        nominatim:
            user_agent: 'Example (contact@example.com)'
            base_url: 'https://nominatim.openstreetmap.org'
            limit: 5
        google_maps:
            api_key: '%env(GOOGLE_MAPS_KEY)%'
        nrcan:
            limit: 5
        photon:
            base_url: 'https://photon.komoot.io'
            languages: [de, en, fr]
        opencage:
            api_key: '%env(OPENCAGE_KEY)%'
        here:
            api_key: '%env(HERE_KEY)%'
        mapbox:
            access_token: '%env(MAPBOX_TOKEN)%'
            permanent: false        # true: answers that may be kept, billed
        azure_maps:
            subscription_key: '%env(AZURE_MAPS_KEY)%'
        adresses_quebec:
            limit: 5
        cache:
            pool: cache.app
            lifetime: 604800
    ip_locator:
        maxmind:
            database: '%kernel.project_dir%/var/geoip/GeoLite2-City.mmdb'
            locale: fr
            account_id: '%env(MAXMIND_ACCOUNT_ID)%'
            license_key: '%env(MAXMIND_LICENSE_KEY)%'
        ipstack:
            access_key: '%env(IPSTACK_KEY)%'
```

| Option | Valeur par défaut | Rôle |
|---|---|---|
| `geocoding.providers` | aucune | `nominatim`, `google_maps`, `nrcan`, `photon`, `opencage`, `here`, `mapbox`, `azure_maps`, `adresses_quebec` ; sans fournisseur, aucun `Geocoder` n'est enregistré |
| `geocoding.nominatim.user_agent` | | Requis avec Nominatim : le site et la façon de le joindre |
| `geocoding.nominatim.base_url` | le serveur public | Un autre serveur, le vôtre |
| `geocoding.nominatim.limit` | `5` | Le nombre maximal de lieux que donne une adresse, de 1 à 40 |
| `geocoding.google_maps.api_key` | | Requis avec Google Maps |
| `geocoding.google_maps.base_url` | la Geocoding API | |
| `geocoding.nrcan.base_url` | le service de Ressources naturelles Canada | |
| `geocoding.nrcan.limit` | `5` | Le nombre maximal de lieux que donne une adresse, de 1 à 40 |
| `geocoding.photon.base_url` | le serveur public | Votre propre serveur |
| `geocoding.photon.limit` | `5` | Le nombre maximal de lieux que donne une adresse, de 1 à 40 |
| `geocoding.photon.languages` | `[de, en, fr]` | Les langues que connaît le serveur ; une locale dans une autre langue n'est pas envoyée |
| `geocoding.opencage.api_key` | | Requis avec OpenCage |
| `geocoding.opencage.base_url`, `limit` | son API, `5` | Le nombre maximal de lieux que donne une adresse, de 1 à 100 |
| `geocoding.here.api_key` | | Requis avec HERE |
| `geocoding.here.geocode_url`, `reverse_url`, `limit` | son API, `5` | Le nombre maximal de lieux, de 1 à 100 |
| `geocoding.mapbox.access_token` | | Requis avec Mapbox |
| `geocoding.mapbox.base_url`, `limit` | l'API v6, `5` | Le nombre maximal de lieux que donne une adresse, de 1 à 10 |
| `geocoding.mapbox.permanent` | `false` | Des réponses qui peuvent être conservées, que Mapbox facture ; les réponses temporaires ne peuvent pas être mises en cache |
| `geocoding.azure_maps.subscription_key` | | Requis avec Azure Maps |
| `geocoding.azure_maps.base_url`, `limit`, `api_version` | son API, `5`, `2026-01-01` | Le nombre maximal de lieux que donne une adresse, de 1 à 20 |
| `geocoding.adresses_quebec.base_url`, `limit` | le service du Gouvernement du Québec, `5` | Le nombre maximal de lieux que donne une adresse, de 1 à 40 |
| `geocoding.cache.pool` | aucune | Un pool de cache qui conserve les réponses |
| `geocoding.cache.lifetime` | `604800` | Les secondes pendant lesquelles une réponse est conservée, une semaine |
| `ip_locator.maxmind.database` | aucune | Le chemin d'une base de villes MaxMind : l'enregistre comme `IpLocator`, avant ipstack |
| `ip_locator.maxmind.locale` | `en` | La langue des noms, l'anglais quand la base n'en a aucun dans cette langue |
| `ip_locator.maxmind.account_id`, `license_key` | aucune | Le compte MaxMind avec lequel `phil:geo:maxmind:update` télécharge : les deux ou aucun, et seulement avec une `database` ; ensemble, ils enregistrent la commande |
| `ip_locator.maxmind.edition` | `GeoLite2-City` | L'édition téléchargée : `GeoIP2-City` sur un compte payant |
| `ip_locator.maxmind.download_url` | celle de MaxMind | |
| `ip_locator.ipstack.access_key` | aucune | Enregistre le localisateur ipstack |
| `ip_locator.ipstack.base_url` | `https://api.ipstack.com` | `http://api.ipstack.com` pour un forfait qui ne répond qu'en HTTP simple, ce qui envoie en clair l'adresse IP du visiteur et la clé d'accès |

Un seul fournisseur est le `Geocoder` ; plusieurs forment un `ChainGeocoder`, interrogés dans leur ordre ; avec un pool,
l'ensemble est un `CachedGeocoder`. Chaque adaptateur prend le service `http_client` de FrameworkBundle. Un pool
conserve les réponses de chaque fournisseur : la configuration refuse donc un pool quand les fournisseurs comprennent
Mapbox sans `permanent`, puisque ses conditions interdisent de conserver ses réponses temporaires. Gardez une durée de
vie sous trente jours avec HERE ([les conditions de chacun](../../../geo/doc/fr/geocoding.md)).

| Id | |
|---|---|
| `phil_geo.geocoder.{provider}` : `nominatim`, `google_maps`, `nrcan`, `photon`, `opencage`, `here`, `mapbox`, `azure_maps`, `adresses_quebec` | chaque fournisseur listé |
| `phil_geo.geocoder.chain` | avec plusieurs fournisseurs |
| `phil_geo.geocoder.cached` | avec un pool |
| `Phil\Geo\Geocoding\Geocoder` | alias du dernier d'entre eux |
| `phil_geo.ip_locator.ipstack` | avec une clé d'accès ; alias `Phil\Geo\Geocoding\IpLocator` quand aucune base MaxMind n'est nommée |
| `phil_geo.maxmind.database`, alias `Phil\Geo\Geocoding\MaxMind\Database` | avec une base MaxMind |
| `phil_geo.ip_locator.maxmind`, alias `Phil\Geo\Geocoding\IpLocator` | avec une base MaxMind |
| `phil_geo.maxmind.updater`, `phil_geo.maxmind.update_command` | avec une base MaxMind, `account_id` et `license_key` |

La base MaxMind passe en premier : une adresse consultée sur le serveur ne le quitte jamais. Le fichier appartient à
l'application (tenu à jour par `phil:geo:maxmind:update` ou par `geoipupdate`), et le chemin n'a pas besoin d'exister à
la construction du conteneur : il est ouvert à la première recherche. Le lire ne demande aucun client HTTP : sans
`account_id` ni `license_key`, rien de ce qui télécharge n'est enregistré, et l'application peut se passer de
`symfony/http-client`. Le localisateur d'ipstack est enregistré avec sa clé d'accès même à côté d'une base, pour qui le
demande par son id.

```php
public function __construct(private Geocoder $geocoder) {}

$places = $this->geocoder->geocode($address, $request->getLocale());
```

Les lieux, les échecs et les fournisseurs sont ceux de `phil/geo` : voir son document sur le géocodage.

## Tenir la base MaxMind à jour

```sh
bin/console phil:geo:maxmind:update           # downloads only when MaxMind has a newer database
bin/console phil:geo:maxmind:update --force   # downloads it anyway
```

La commande est enregistrée avec une base MaxMind, `account_id`, `license_key` et `symfony/console` ; elle interroge
MaxMind par le service `http_client`. Elle demande à MaxMind le SHA-256 de la plus récente archive de l'édition, avec le
compte et la clé de licence ; quand c'est la somme de contrôle conservée à côté de la base, dans
`GeoLite2-City.mmdb.sha256`, rien n'est téléchargé. Sinon, elle :

1. télécharge l'archive à côté de la base, sous un nom qui lui est propre, à mesure qu'elle arrive ;
2. la vérifie avec cette somme de contrôle ;
3. en extrait `GeoLite2-City.mmdb`, bloc par bloc, sans jamais garder toute l'archive en mémoire ;
4. l'ouvre, pour s'assurer que c'est une base MaxMind de l'édition demandée ;
5. la renomme par-dessus l'ancienne, en une seule étape, et garde sa somme de contrôle.

Tout échec (un compte refusé, une archive qui ne correspond pas, un fichier qui n'est pas de l'édition demandée) laisse
la base en place et retire ses fichiers temporaires, et la commande échoue en donnant la raison. Une seule mise à jour
s'exécute à la fois : elle tient un verrou sur `GeoLite2-City.mmdb.lock`, laissé à côté de la base, et une deuxième
exécution (un cron et une mise à jour forcée, deux hôtes sur un stockage partagé) échoue aussitôt sans toucher aux
fichiers de la première. Exécutez-la chaque jour, puisque MaxMind publie GeoLite2 deux fois par semaine :

```text
0 4 * * * cd /var/www/site && bin/console phil:geo:maxmind:update --no-interaction
```

Une requête Web ouvre la base à sa première recherche : elle lit donc le nouveau fichier tout de suite. Un worker de
longue durée (un consommateur de Messenger, FrankenPHP en mode worker) garde le fichier qu'il a ouvert jusqu'à son
redémarrage.
