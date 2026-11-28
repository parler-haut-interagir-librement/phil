<!-- phil-dev source-sha256=377117b2f8f01d46c64d6413ef57d0732af974e142f0ae4d8eba697b568a0b76 -->
# Sources

Une source est l'endroit d'où viennent les événements. La bibliothèque la lit par un port, `EventSource`, et fournit
un adaptateur, `IcsUrlSource`, pour un flux iCalendar lu par son adresse.

## Un calendrier à importer

`CalendarSource::of($id, $address, $zone)` nomme un calendrier que l'application importe :

- `$id` — son identifiant dans l'application, de 1 à 255 octets sur une seule ligne ; les états, les surcharges et
  les brouillons sont gardés sous cet identifiant ;
- `$address` — son adresse. `webcal://` et `webcals://` sont lues comme `https://`. L'adresse est ensuite lue par
  `phil/url` : `http` ou `https`, un nom d'hôte public, ni nom d'utilisateur ni mot de passe. Toute autre adresse
  lance `InvalidSource` ;
- `$zone` — le fuseau des heures que le flux écrit sans fuseau, quand il n'en nomme aucun lui-même
  (`X-WR-TIMEZONE`) ; UTC par défaut. Un fuseau que PHP ne connaît pas lance `InvalidSource`.

```php
use Phil\CalendarImport\Source\CalendarSource;

$source = CalendarSource::of('library-events', 'webcal://calendar.example.org/public.ics', 'America/Toronto');
```

## Le port

```php
interface EventSource
{
    public function fetch(CalendarSource $source, ?FetchValidators $validators = null): Fetched;
}
```

Une source répond par un `Fetched` : soit un calendrier lu (`$fetched->result`, un `ReadResult`) avec les validateurs
de la réponse, soit le mot que rien n'a changé depuis les validateurs envoyés (`isNotModified()`). Une source qui ne
donne aucun calendrier cette fois-ci lance `SourceUnavailable`, dont le `kind` dit pourquoi :

| `FailureKind` | Quand |
|---|---|
| `Unreachable` | aucune réponse à temps, une connexion rompue, un nom qui ne se résout pas |
| `Status` | un statut d'erreur ; `$exception->status` le contient |
| `Refused` | l'adresse, ou une redirection, mène à un réseau privé ; une redirection non permise ; trop de redirections |
| `TooLarge` | plus d'octets que permis |
| `Invalid` | une réponse que le lecteur refuse en entier |

## Le flux iCalendar

`IcsUrlSource` lit un flux par `phil/safe-http`.

```php
use Phil\CalendarImport\Source\IcsUrlSource;

$feeds = IcsUrlSource::create();                       // its own client
$feeds = IcsUrlSource::create($httpClient, $limits, redirects: 2);
$feeds = new IcsUrlSource($safeHttpClient, $reader);   // a client and a reader set up by the application
```

`create()` construit un `SafeHttpClient` avec 10 secondes pour se connecter et attendre, 30 secondes pour la lecture
entière, autant d'octets que les `ReaderLimits` du lecteur en permettent, et aucune redirection par défaut : un flux
qui a déménagé échoue avec `Refused`, afin que l'application le voie et mette son adresse à jour, plutôt que de le
suivre indéfiniment.

Chaque lecture :

1. envoie `GET` avec `Accept: text/calendar, text/plain;q=0.5, */*;q=0.1` et, quand des validateurs sont fournis,
   `If-None-Match` et `If-Modified-Since` ;
2. sur `304 Not Modified`, donne `Fetched::notModified()` avec les validateurs de la réponse, ou ceux envoyés quand la
   réponse n'en contient aucun : rien ne change ;
3. sur tout autre statut hors de 2xx, échoue avec `Status` ;
4. sur un corps coupé à la limite d'octets, échoue avec `TooLarge` ;
5. lit le corps avec `ICalendarReader`, dans le fuseau de la source ; un texte refusé en entier échoue avec `Invalid`,
   ou avec `TooLarge` quand la borne dépassée est celle des octets.

### Les lectures conditionnelles

`FetchValidators` contient l'`ETag` et le `Last-Modified` de la dernière réponse. Une valeur vide, de plus de 1 024
octets ou contenant un caractère de contrôle n'est pas gardée, et donc jamais renvoyée. L'importateur garde les
validateurs dans l'état de chaque source, et les envoie à la lecture suivante.

## Sécurité

- **Aucun réseau privé.** `phil/safe-http` résout chaque hôte et refuse une adresse qui est un réseau privé, de
  bouclage, de lien local ou réservé, ou qui s'y résout — à la première requête et à chaque redirection. Une adresse
  de flux saisie par un éditeur ne peut jamais faire lire son propre réseau au serveur.
- **Aucune redirection surprise.** Aucune n'est suivie par défaut ; quand certaines sont permises, chacune est
  vérifiée comme la première adresse.
- **Temps et taille bornés.** La lecture s'arrête à son échéance et à sa limite d'octets ; le lecteur borne ensuite le
  travail fait sur ce qui a été lu (voir [Lire de l'iCalendar](reading.md#les-bornes)).
- **Aucun identifiant de connexion dans les adresses.** `phil/url` refuse une adresse avec un nom d'utilisateur ou un
  mot de passe.
- **Du texte seulement.** Chaque texte d'un flux sort en texte brut, à échapper là où il est affiché.
- **Les événements privés restent dehors.** Un événement marqué `CLASS:PRIVATE` ou `CLASS:CONFIDENTIAL` est refusé.
