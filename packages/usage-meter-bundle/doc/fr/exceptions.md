<!-- phil-dev source-sha256=5964418820c376e2f832777e5a748f4a16f3c11f894be4818f48565d3f5dc780 -->
# Exceptions

Le bundle n'a aucune exception propre.

| Quand | Ce qui est lancé |
|---|---|
| Le conteneur est construit avec un compteur qui n'a aucune période ou en a deux, une limite qui n'est ni `unlimited` ni un nombre entier à partir de zéro, un fuseau horaire inconnu, des compartiments qui ne divisent pas la fenêtre, une ancre qui n'est pas une date, ou un nom qui n'est pas un nom de compteur | `Symfony\Component\Config\Definition\Exception\InvalidConfigurationException`, préfixée `phil_usage_meter.meters:`, avec l'`InvalidUsage` de la bibliothèque comme cause |
| Le conteneur est construit avec un limiteur de débit sur un compteur que la section `meters` ne déclare pas | `InvalidConfigurationException` |
| Le conteneur est construit avec des limiteurs de débit sans que `PhilRateLimitBundle` soit enregistré | `InvalidConfigurationException` |
| Le conteneur est construit avec `store: dbal` sans `doctrine/dbal` | `InvalidConfigurationException` |
| Un limiteur à clé reçoit un en-tête `Idempotency-Key` mal formé | `Symfony\Component\HttpKernel\Exception\BadRequestHttpException`, rendue en 400 |
| Une requête ferait dépasser à son sujet le quota du compteur d'un limiteur | Le `TooManyRequests` de `phil/rate-limit-bundle`, rendu en 429 |

Les exceptions propres à la bibliothèque (`QuotaExceeded`, `UnknownMeter`, `InvalidUsage`) atteignent le code qui
appelle directement `UsageMeter`.
