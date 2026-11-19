<!-- phil-dev source-sha256=475f57dba29d640b959e3b0d7c769796e3b1f506f301ba575885f0774338e1d5 -->
# Tests

## Les tests de la bibliothèque

La suite `tests/Unit` n'a besoin d'aucun réseau. Les adaptateurs sont testés avec le `MockHttpClient` de Symfony, avec
des réponses écrites pour les tests dans `tests/Fixtures`, d'après les formats que documentent les fournisseurs : une
réponse `complete` de Locationforecast, une collection `citypageweather-realtime` de deux sites, une collection
`weather-alerts` avec des polygones et des alertes terminées ou en double, et une alerte CAP 1.2 en anglais et en
français. Les tests vérifient les requêtes envoyées — adresse, requête, `User-Agent`, bornes —, chaque champ lu, le
choix du fournisseur et sa disponibilité avec une `MockClock`, la mise en cache par `CachingHttpClient` et chaque refus
du lecteur CAP.

Depuis la racine du monodépôt :

```sh
vendor/bin/phpunit packages/event-weather/tests
```

Infection maintient la bibliothèque à 100 % de score de mutation couvert.

## Le fuzzing

La cible de fuzzing `cap` donne au lecteur CAP des documents que personne n'a écrits, à partir du germe de
`tests/Fuzz/corpus/cap` : quoi qu'il lise, il retourne des alertes ou lève `InvalidCapDocument`.

```sh
composer fuzz -- cap
```

## Tester le code qui affiche des prévisions

`EventWeather` prend ses fournisseurs et son horloge : donnez-lui un fournisseur propre au test et une `MockClock`,
puis comparez l'`EventForecast`.

```php
use Symfony\Component\Clock\MockClock;

$weather = new EventWeather([new FixedForecast($periods)], new MockClock('2026-11-13 12:00:00 UTC'));
$forecast = $weather->forecastFor($location, $start, $end);

self::assertSame(Availability::Available, $forecast->availability);
```

Pour tester le câblage d'un adaptateur, donnez-lui un `MockHttpClient` et une réponse enregistrée auprès du
fournisseur.
