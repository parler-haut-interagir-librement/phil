<!-- phil-dev source-sha256=4251d16d0c527fc89dbb6474d923de79a5bd3336bc6434351980daa0e36df270 -->
# phil/error-tracking

**Les erreurs d'une application, gardées chez elle : groupées, débarrassées des renseignements personnels, et
distinguées comme nouvelles, répétées ou revenues.**

phil/error-tracking transforme une exception en événement d'erreur (sa chaîne d'exceptions, les cadres de chaque
trace, le contexte du code qui l'a attrapée, la version et l'environnement qui l'ont exécutée, et la requête réduite à
ce qui aide) et le classe dans un groupe des événements qui partagent son empreinte. L'empreinte est stable : le type
de chaque exception, son message sans les valeurs qui changent, et le fichier et la fonction des premiers cadres de
l'application, sans leurs lignes. Un groupe est ouvert, résolu, ignoré, ou en régression quand il revient sur une
autre version après avoir été résolu.

Rien n'est écrit avant le passage de l'assainisseur : les en-têtes `Authorization` et `Cookie`, les champs nommés
comme des mots de passe et des jetons, chaque valeur de la chaîne de requête et les adresses courriel de tout texte
sont filtrés, et l'adresse du client n'est jamais gardée qu'au point où `phil/ip-anonymizer` l'a laissée. Un
échantillonneur garde une part des événements et un limiteur borne les événements d'un groupe, pour qu'une erreur levée
dans une boucle coûte un nombre borné d'écritures.

Il s'adresse à toute application PHP qui veut ses erreurs là où vivent déjà ses données, sans les envoyer à un tiers :
un petit site, une boutique, un outil de jeu dans sa première phase. C'est une simple bibliothèque PHP dont les
prérequis sont `psr/clock` et `phil/ip-anonymizer`. L'endroit où sont gardés les groupes est un port,
`ErrorStore`, tout comme le limiteur et les alertes ; `phil/error-tracking-bundle` apporte la capture, un magasin DBAL,
les pages d'administration et les alertes à une application Symfony.

## État

| Partie | État |
|---|---|
| Événements d'erreur : exceptions chaînées, cadres sans arguments, contexte, version, environnement, requête réduite | Implémenté et testé |
| L'empreinte stable et le normaliseur de messages | Implémenté et testé, avec un banc d'essai |
| L'assainisseur des en-têtes, des champs, des valeurs de requête, des adresses courriel, des identifiants de connexion et des jetons | Implémenté et testé, avec un banc d'essai |
| L'échantillonneur, le port du limiteur | Implémenté et testé |
| Les états d'un groupe : ouvert, résolu, ignoré, en régression sur une autre version | Implémenté et testé |
| `ErrorTracker` sur un magasin, un limiteur et un alerteur | Implémenté et testé |
| Cartes de sources des scripts minifiés, et les cadres relus à travers elles | Non commencé, prévu après la première version : un cadre garde l'adresse et la ligne qu'il a reçues |
| Un bundle Symfony : capture, magasin DBAL, pages d'administration, alertes, erreurs du navigateur | Implémenté dans `phil/error-tracking-bundle` |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis, installation, construction du traqueur. |
| [Événements](doc/fr/events.md) | Ce que contient un événement, ses bornes, et comment un throwable en devient un. |
| [Regroupement](doc/fr/grouping.md) | L'empreinte, le normaliseur de messages et les états d'un groupe. |
| [Assainissement](doc/fr/scrubbing.md) | Ce qui est filtré avant que rien ne soit écrit, et comment ajouter des champs. |
| [Échantillonnage et limitation](doc/fr/sampling-and-throttling.md) | L'échantillonneur et le port du limiteur. |
| [Points d'extension](doc/fr/extension-points.md) | Le magasin, le limiteur et l'alerteur de l'application. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque, son seuil de mutation et ses bancs d'essai. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/error-tracking
```

```php
use Phil\ErrorTracking\CaptureContext;
use Phil\ErrorTracking\ErrorTracker;
use Phil\ErrorTracking\EventFactory;
use Phil\ErrorTracking\Source;
use Symfony\Component\Clock\NativeClock;

$tracker = new ErrorTracker(new EventFactory('/srv/app', new NativeClock(), release: '1.4.0', environment: 'prod'), $store);

try {
    $exporter->run($order);
} catch (ExportFailed $failure) {
    $capture = $tracker->captureThrowable($failure, new CaptureContext(Source::Manual, context: ['order' => $order->id]));
    // $capture->occurrence: First, Regression or Repeat; null when sampled out or throttled
}
```

`$store` est l'`ErrorStore` de l'application : voir [Points d'extension](doc/fr/extension-points.md).

## Prérequis

PHP 8.5 ou plus récent avec `mbstring` et `sodium`, `psr/clock` et `phil/ip-anonymizer`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la [politique de
sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
