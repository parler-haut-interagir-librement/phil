<!-- phil-dev source-sha256=281676a60aa24319a55680d79c3d13b502888e7388d3fecc7733093fe007e104 -->
# Points d'extension

La bibliothèque est du PHP simple et ne déclare aucun service. Ce que choisit une application passe par deux ports et
par les arguments d'`EventWeather` et des adaptateurs.

## `Contract\ForecastProvider`

| Méthode | Contrat |
|---|---|
| `attribution(): Attribution` | La ligne de crédit, la licence et la source qu'exigent ses données |
| `supports(EventLocation): bool` | Si le service fait des prévisions pour le lieu |
| `horizonHours(): int` | Combien d'heures à partir de maintenant ses prévisions couvrent |
| `forecast(EventLocation): Forecast` | Les prévisions du lieu, ses périodes en UTC ; lève `WeatherUnavailable` quand le service échoue |

Un adaptateur porte le nom de son service, ne garde aucun état entre les appels et envoie ses requêtes par le client
HTTP qu'on lui donne, afin que le cache de l'application s'y applique.

## `Contract\AlertSource`

| Méthode | Contrat |
|---|---|
| `attribution(): Attribution` | La mention de source de ses alertes |
| `supports(EventLocation): bool` | Si le service émet des alertes pour le lieu |
| `alerts(EventLocation): list<WeatherAlert>` | Les alertes en vigueur ou annoncées pour le lieu, les terminées exclues ; lève `WeatherUnavailable` quand le service échoue |

`Cap\CapReader` lit les documents CAP d'un service qui les publie.

## Arguments

| Point | Comment |
|---|---|
| Les fournisseurs et leur ordre | La liste donnée à `EventWeather`, le préféré en premier |
| Les sources d'alertes | La liste donnée à `EventWeather` ; la première qui prend en charge le lieu est interrogée |
| Maintenant | L'horloge PSR-20 donnée à `EventWeather` |
| Les avertissements des fournisseurs en échec | Le journaliseur PSR-3 donné à `EventWeather` |
| Le cache | Le client HTTP donné aux adaptateurs, enveloppé dans `CachingHttpClient` |
| L'identification | Le `Contact` donné aux adaptateurs |
| La langue des mots des fournisseurs | `en` ou `fr`, donné aux adaptateurs d'Environnement Canada |
| Les points de terminaison | Le `Url` donné à chaque adaptateur |
| La distance de recherche d'un site de page de ville | Le `$searchKilometers` de `CitypageWeatherProvider` |
| Intercepter les erreurs de la bibliothèque | Chaque exception implémente `Exception\EventWeatherException` |

## Stabilité

Les ports, les objets-valeur et les adaptateurs forment l'API publique. L'espace de noms `Internal\` — `Data`,
`JsonClient`, `Polygon` — n'en fait pas partie : il change sans préavis.
