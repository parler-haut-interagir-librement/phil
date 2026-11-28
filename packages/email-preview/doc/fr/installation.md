<!-- phil-dev source-sha256=12460763c82efac736e64674542095088d2f2586739f1f9accc144042ac0b3aa -->
# Installation

```sh
composer require phil/email-preview
```

Le paquet exige PHP 8.5 ou une version plus récente, avec les extensions `dom`, `intl`, `libxml` et `mbstring`. Il
requiert phil/email-templates-bundle, dont il vérifie les aperçus, et symfony/mime, dont il lit aussi les courriels.

C'est une bibliothèque simple : rien à enregistrer. Une application qui veut un seul vérificateur pour toutes les requêtes
le construit une fois — comme service, dans une application Symfony —, puisque le vérificateur et ses règles ne gardent
aucun état entre deux courriels :

```yaml
# config/services.yaml
services:
    Phil\EmailPreview\EmailChecker: ~
    Phil\EmailPreview\Preview\PreviewChecks:
        arguments:
            $renderer: '@Phil\EmailTemplatesBundle\Application\Contract\PreviewRenderer'
```

## Vérifier un courriel

Un courriel est donné aux règles sous la forme d'un `CheckedEmail` : son objet, son HTML, sa partie en texte brut et un
`Sending`, qui dit ce que le rendu ne dit pas — s'il part en masse, les en-têtes que l'expéditeur ajoute et les valeurs
qui identifient le destinataire.

```php
use Phil\EmailPreview\CheckedEmail;
use Phil\EmailPreview\EmailChecker;
use Phil\EmailPreview\Sending;

$sending = Sending::bulk(
    ['List-Unsubscribe' => '<https://example.com/leave/abc>', 'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click'],
    ['ana@example.com', 'subscriber-4242'],
);

$report = new EmailChecker()->check(CheckedEmail::fromRendered($renderedEmail, $sending));
```

`CheckedEmail::fromMime($email, bulk: true)` lit un courriel construit pour Symfony Mailer : ses en-têtes sont ceux de
l'envoi, et les adresses de ses destinataires sont les valeurs qui les identifient.

Un HTML plus grand que `CheckedEmail::MAX_HTML_BYTES` (2 Mio) est refusé avec `EmailTooLarge` avant d'être analysé.

## Afficher le rapport

`Report::toText()` écrit un constat par ligne ; `Report::toHtml()` écrit un fragment HTML, chaque valeur échappée, à
placer à côté de l'aperçu ; `json_encode($report)` le donne à un script. `Report::passes()` est vrai sans erreur, et
`passes(strict: true)` sans aucun constat.
