<!-- phil-dev source-sha256=31a3116fe1597a177e5de235164dce797fd4a76a4d36aa73ab9a5e542699dd3b -->
# Événements

## Ce que contient un événement

| Partie | Type | Borne |
|---|---|---|
| Identifiant | 32 chiffres hexadécimaux en minuscules | aléatoire, 128 bits |
| Instant | `DateTimeImmutable` | tiré de l'horloge de la fabrique |
| Source | `Source` : `http`, `messenger`, `console`, `log`, `browser`, `manual` | |
| Gravité | `Severity` : `warning`, `error`, `fatal` | |
| Exceptions | une liste non vide d'`ExceptionRecord`, celle levée d'abord en premier | 10 par défaut |
| Version, environnement | textes ou `null` | 128 et 64 caractères |
| Requête | `RequestSummary` ou `null` | voir plus bas |
| Contexte | étiquettes de scalaires ou `null` | 50 étiquettes, clés de 64 caractères, textes de 1 024 |

Un `ExceptionRecord` a un type (255 caractères), un message (2 048) et ses cadres, le plus intérieur en premier. Un
`Frame` a un fichier (1 024), une ligne, une fonction (255), et indique s'il appartient à l'application. Chaque texte
est rendu en UTF-8 valide avant d'être coupé.

Un `RequestSummary` a la méthode, l'adresse (2 048), la route, au plus 50 en-têtes par nom en minuscules (1 024
chacun) et l'adresse du client telle qu'un anonymiseur l'a laissée. Il ne contient jamais le corps.
`RequestSummary::anonymized()` prend l'adresse brute et un `IpAnonymizer` de `phil/ip-anonymizer`, et ne garde que ce
que retourne la stratégie :

```php
use Phil\ErrorTracking\RequestSummary;
use Phil\IpAnonymizer\Truncation;

$request = RequestSummary::anonymized('GET', $url, 'order_show', $headers, $clientIp, new Truncation(24, 48));
```

## À partir d'un throwable

`EventFactory::fromThrowable()` lit le throwable, puis chaque throwable précédent, jusqu'à la borne de la chaîne. Pour
chacun, le premier cadre est l'endroit où il a été levé et la fonction dans laquelle il l'a été ; les suivants sont
les appels qui y ont mené, jusqu'à la borne des cadres. Les arguments des appels ne sont jamais lus, quoi que dise
`zend.exception_ignore_args`.

Les noms que PHP donne aux fonctions anonymes et aux classes anonymes portent un chemin, une ligne et, pour une classe
anonyme, un octet NUL : `{closure:App\Job::run():12}` devient `{closure}`, et `class@anonymous` perd tout ce qui suit
son nom.

Un fichier sous le répertoire du projet est gardé relatif à celui-ci ; il appartient à l'application, sauf s'il se
trouve sous `vendor/`. Un fichier hors du projet garde son chemin absolu et n'appartient jamais à l'application.

## À partir d'un message

`EventFactory::fromMessage()` construit un événement d'une seule exception à partir d'un type, d'un message et de
cadres donnés par l'appelant : un enregistrement de journal sans exception, une erreur signalée par un navigateur. Les
cadres sont coupés à la borne.

## Valeurs simples

`toArray()` donne l'événement sous forme de scalaires et de tableaux qu'un magasin peut écrire en JSON ; `fromArray()`
les relit et lève `InvalidEvent` pour une valeur manquante ou d'un autre type. Les clés inconnues sont ignorées, et une
étiquette ou un en-tête qui n'est pas un scalaire est retiré.

## Titre et coupable

`title()` est le type et le message de l'exception levée : `RuntimeException: The export failed`. `culprit()` est le
cadre le plus intérieur de l'application, cherché depuis l'exception levée jusqu'à ses causes.
