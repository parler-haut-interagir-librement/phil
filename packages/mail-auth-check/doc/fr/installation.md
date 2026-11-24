<!-- phil-dev source-sha256=d95b90e874b5ffa9251907002f16d35285aba3ce62c41114b64db001a307cd03 -->
# Installation

```sh
composer require phil/mail-auth-check
```

Le paquet exige PHP 8.5 ou une version plus récente, `ext-filter`, qui lit les adresses des enregistrements SPF, et
`ext-sodium`, qui lit les clés DKIM. `ext-intl`, suggérée, convertit un nom de domaine internationalisé tel que
`bücher.example` en sa forme ASCII ; sans elle, un profil qui en nomme un est refusé avec `InvalidDomain`, et la forme
ASCII (`xn--bcher-kva.example`) fonctionne.

## Une première vérification

```php
use Phil\MailAuthCheck\DmarcPolicy;
use Phil\MailAuthCheck\MailAuthCheck;
use Phil\MailAuthCheck\SenderProfile;

$profile = new SenderProfile(
    sendingDomain: 'example.org',          // the domain of the From address
    returnPathDomain: 'bounce.example.org', // the envelope sender; the sending domain when left out
    dkimSelectors: ['s1'],                  // without "._domainkey"
    spfIncludes: ['_spf.provider.example'], // what the providers ask you to include
    minimumPolicy: DmarcPolicy::Quarantine, // "none" by default
);

$report = new MailAuthCheck()->check($profile);

$report->canSend();       // true when no finding failed
$report->failures();      // the findings that block sending
$report->warnings();      // the findings worth fixing
$report->forCheck('spf'); // the findings of one check: spf, dkim, dmarc or return-path
```

Chaque `Finding` porte le nom de sa vérification, un `Status` (`Pass`, `Warning` ou `Fail`), un code stable tel que
`spf.too-many-lookups`, qu'une application peut traduire ou relier à son aide, et un message en anglais. La
[référence](reference.md) liste chaque code.

Vérifiez un profil d'expéditeur à sa création, puis de nouveau selon un horaire : les enregistrements DNS changent en
dehors de l'application, et un fournisseur qui ajoute une inclusion peut pousser un enregistrement SPF au-delà de ses
dix requêtes.

## Le résolveur

`MailAuthCheck` interroge par défaut le résolveur du système, par `dns_get_record()`, qui attend aussi longtemps que
la configuration du résolveur le permet (`timeout` et `attempts` de resolv.conf). Une requête qui échoue donne un
constat d'échec : un profil dont les enregistrements n'ont pas pu être lus n'est pas déclaré prêt.

```php
use Phil\MailAuthCheck\Dns\StaticTxtResolver;
use Phil\MailAuthCheck\MailAuthCheck;

// Preview the records a domain owner is about to publish.
$preview = new MailAuthCheck(new StaticTxtResolver([
    'example.org' => ['v=spf1 include:_spf.provider.example -all'],
    '_dmarc.example.org' => ['v=DMARC1; p=quarantine; rua=mailto:dmarc@example.org'],
]));
```
