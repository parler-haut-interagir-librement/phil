<!-- phil-dev source-sha256=9c4b67561ace173348bb5cb3ab2e611986e2e585ee2e9ae97fec107dbae71e1f -->
# Tests

## Les tests du paquet

Les tests sont des tests PHPUnit dans `tests/Unit`, exécutés depuis la racine du monodépôt avec `composer test`, ou
seuls :

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

Ils s'exécutent sans noyau, sans base de données et sans réseau : chaque vérification lit ses enregistrements dans
`StaticTxtResolver`. `tests/Support/Keys` construit des clés DKIM octet par octet, d'une taille exacte, et
`tests/Support/CountingResolver` retient les noms qu'on lui a demandés, pour prouver que chaque nom n'est demandé
qu'une fois et que la marche SPF s'arrête à sa limite. Dans le monodépôt, Infection maintient le paquet à 100 % des
mutants couverts.

`tests/Integration/SystemResolverTest` interroge le résolveur du système pour un nom qui ne se résout jamais ; il ne
s'exécute que si `PHIL_TEST_DNS` est définie, car il a besoin d'un réseau.

## Tester une application

`StaticTxtResolver` prend les enregistrements TXT par nom, et les noms dont la requête échoue :

```php
use Phil\MailAuthCheck\Dns\StaticTxtResolver;
use Phil\MailAuthCheck\MailAuthCheck;
use Phil\MailAuthCheck\SenderProfile;

$checker = new MailAuthCheck(new StaticTxtResolver(
    ['example.org' => ['v=spf1 -all'], '_dmarc.example.org' => ['v=DMARC1; p=reject']],
    ['s1._domainkey.example.org'],
));

$report = $checker->check(new SenderProfile('example.org', dkimSelectors: ['s1']));
// dkim.lookup-failed is among $report->failures()
```

Comparez les constats par leur `code`, qui est stable, plutôt que par leur message.

## La cible de fuzzing

`tests/Fuzz/records.php` donne à lire aux analyseurs SPF, DKIM et DMARC un texte que personne n'a écrit, puis analyse
l'enregistrement SPF d'un domaine dont chaque nom répond par ce texte : chaque analyseur retourne ou lance
`InvalidRecord`, rien d'autre ne s'échappe, et la marche ne compte jamais plus de onze requêtes. Ses germes sont dans
`tests/Fuzz/corpus/mail-auth-records`.

```sh
composer fuzz -- mail-auth-records
```
