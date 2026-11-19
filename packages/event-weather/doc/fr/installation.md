<!-- phil-dev source-sha256=c930267c4d6cb866ce17ce9bd5a597bdc5df3d88db490140768ab2a78c22d40e -->
# Installation

## Prérequis

PHP 8.5 ou une version plus récente, avec `ext-dom`, `ext-libxml` et `ext-mbstring` pour le lecteur CAP. La
bibliothèque exige les contrats du client HTTP de Symfony, `psr/clock`, `psr/log`, `phil/geo` et `phil/url` ;
l'application installe un client HTTP, comme `symfony/http-client`, un cache, comme `symfony/cache`, et une horloge,
comme `symfony/clock`.

## Installation

```sh
composer require phil/event-weather symfony/http-client symfony/cache symfony/clock
```

## Le cache

Les conditions de MET Norway interdisent de redemander avant l'`Expires` d'une réponse et demandent
`If-Modified-Since` après. Les réponses d'Environnement Canada portent les mêmes en-têtes. Les fournisseurs envoient
leurs requêtes par le client HTTP qu'on leur donne : enveloppez-le dans le `CachingHttpClient` de Symfony, qui garde
chaque réponse jusqu'à son expiration et la revalide avec le `Last-Modified` qu'elle portait. Donnez-lui un cache à
étiquettes qui dure entre les requêtes — un adaptateur de système de fichiers, de Redis ou de base de données — et
`sharedCache: false`, les réponses étant réservées à l'application.

```php
use Symfony\Component\Cache\Adapter\FilesystemTagAwareAdapter;
use Symfony\Component\HttpClient\CachingHttpClient;
use Symfony\Component\HttpClient\HttpClient;

$client = new CachingHttpClient(HttpClient::create(), new FilesystemTagAwareAdapter('weather'), sharedCache: false);
```

Les coordonnées envoyées à MET Norway sont coupées à quatre décimales : des événements distants de quelques mètres
partagent donc une réponse. Le cache est le choix de l'application : la bibliothèque ne garde rien entre les appels.

## L'identification

Les deux fournisseurs demandent qui envoie les requêtes. Un `Contact` donne le nom de l'application, comme produit du
`User-Agent` avec une version facultative, et un moyen de joindre la personne qui l'exploite — une adresse Web ou une
adresse de courriel :

```php
use Phil\EventWeather\Contact;

$contact = new Contact('EventsApp/1.4', 'https://events.example/contact');
// User-Agent: EventsApp/1.4 https://events.example/contact
```

MET Norway bannit les clients dont l'identification est inventée ou copiée : chaque application donne son propre nom
et son propre contact.

## Comme services Symfony

```php
// config/services.php
use Phil\EventWeather\Contact;
use Phil\EventWeather\EnvironmentCanada\CitypageWeatherProvider;
use Phil\EventWeather\EnvironmentCanada\WeatherAlertsSource;
use Phil\EventWeather\EventWeather;
use Phil\EventWeather\MetNorway\LocationforecastProvider;
use Symfony\Component\HttpClient\CachingHttpClient;

$services->set('weather.http_client', CachingHttpClient::class)
    ->args([service('http_client'), service('cache.weather'), '$sharedCache' => false]);
$services->set(Contact::class)->args(['EventsApp/1.4', 'https://events.example/contact']);
$services->set(CitypageWeatherProvider::class)->args([service('weather.http_client'), service(Contact::class), 'fr']);
$services->set(LocationforecastProvider::class)->args([service('weather.http_client'), service(Contact::class)]);
$services->set(WeatherAlertsSource::class)->args([service('weather.http_client'), service(Contact::class), 'fr']);
$services->set(EventWeather::class)->args([
    [service(CitypageWeatherProvider::class), service(LocationforecastProvider::class)],
    service('clock'),
    [service(WeatherAlertsSource::class)],
    service('logger'),
]);
```

`cache.weather` est un réservoir de cache à étiquettes de l'application. Chaque classe de la bibliothèque est
immuable et ne garde aucun état entre les appels : un seul `EventWeather` sert toutes les requêtes d'un *worker* de
longue durée.
