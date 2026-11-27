<!-- phil-dev source-sha256=3f5e48d9b4cbb8d20320a5772b4cf485dc56f0149637f2488b3b14e7cacf5f04 -->
# phil/safe-http

**Reading other sites safely: an HTTP client on Symfony HttpClient that refuses private networks, follows redirects it checks itself, and bounds the time, the size and the redirects of what it reads.**

Un site qui lit des adresses données par ses membres ou par d'autres sites — un aperçu de lien, une vérification de
lien, un point de terminaison oEmbed, un flux, une mention envoyée par un autre site — ne doit pas les laisser
atteindre son propre réseau : un point de terminaison de métadonnées d'un nuage, une base de données, une page
d'administration sur la boucle locale. phil/safe-http lit de telles adresses :

- chaque adresse, la première et chaque redirection, est une adresse HTTP ou HTTPS absolue d'un nom d'hôte, lue par
  phil/url : aucune adresse IP, sous aucune de ses écritures, ni nom d'utilisateur ni mot de passe ;
- le `NoPrivateNetworkHttpClient` de Symfony refuse un hôte qui est une adresse de boucle locale, privée, de lien local
  ou réservée, ou qui s'y résout, et se connecte à l'adresse qu'il a vérifiée ;
- les redirections sont suivies une à une, chacune vérifiée, jusqu'à un nombre donné ; une redirection de HTTPS vers
  HTTP est refusée ;
- le silence de l'hôte et la durée totale de la lecture, redirections comprises, sont bornés ;
- les en-têtes d'une réponse sont bornés en octets et en nombre, comptés à mesure qu'ils arrivent ;
- le corps est lu par morceaux, seulement pour les types de média demandés, jusqu'à un nombre d'octets ou une marque
  d'arrêt, puis le transfert est annulé ; un morceau est coupé aux octets qui restent avant de rejoindre le corps ;
- `send()` fait une requête de n'importe quelle méthode, avec des en-têtes et un corps, sous les mêmes gardes : les
  en-têtes qu'on lui donne ne quittent jamais le schéma, l'hôte et le port de la première adresse, et une requête avec
  un corps, ou d'une méthode autre que `GET`, `HEAD` ou `OPTIONS`, ne suit aucune redirection.

Une lecture donne une `SafeResponse` — l'adresse qui a répondu, les redirections, le statut, les en-têtes et le corps
dans la mesure où il a été lu — ou une exception : l'adresse ou une redirection est refusée, les redirections sont
trop nombreuses, la requête est refusée avant d'être envoyée, ou la lecture a échoué. Un statut d'erreur est une réponse, de sorte qu'une vérification de lien distingue
un `404` d'un délai dépassé.

## État

| Partie | État |
|---|---|
| `SafeHttpClient` : `get()`, `head()`, `send()` ; `Limits`, `ReadRule`, `SafeResponse` | Implémentés et testés |
| `AddressGuard` | Implémenté, testé et soumis au fuzzing |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis et l'installation. |
| [Lecture](doc/fr/reading.md) | Le client, ses limites, les règles d'une lecture, l'envoi d'une requête avec des en-têtes et un corps, la réponse et les exceptions. |
| [La garde](doc/fr/guard.md) | Ce qu'une adresse doit être, les redirections, les réseaux privés, et ce que la garde ne couvre pas. |
| [Points d'extension](doc/fr/extension-points.md) | Le transport, les adresses fixes, et ce qui reste fermé. |
| [Tests](doc/fr/testing.md) | Les tests du paquet et sa cible de fuzzing. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```php
use Phil\SafeHttp\Limits;
use Phil\SafeHttp\ReadRule;
use Phil\SafeHttp\SafeHttpClient;
use Phil\SafeHttp\Exception\SafeHttpException;

$client = new SafeHttpClient(limits: new Limits(userAgent: 'ExampleBot/1.0 (+https://example.com/bot)'));

try {
    $page = $client->get($address, ReadRule::htmlHead());
} catch (SafeHttpException) {
    $page = null; // refused, too many redirects, or no answer in time
}

if (null !== $page && 200 === $page->status) {
    // $page->body: the page up to the end of its head, 1 MiB at most
}

// A request of another platform's web service, its credentials kept on that host.
$token = $client->send('POST', 'https://platform.example/oauth/token', ['Authorization' => 'Basic '.$credentials, 'Content-Type' => 'application/x-www-form-urlencoded'], 'grant_type=client_credentials', new ReadRule('application/json'));
```

## Prérequis

PHP 8.5 ou une version plus récente avec les extensions mbstring et URI, `phil/url`, ainsi que HttpClient de Symfony,
ses contrats et HttpFoundation, dont `IpUtils` sert à la vérification des réseaux privés.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
