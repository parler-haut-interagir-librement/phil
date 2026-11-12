<!-- phil-dev source-sha256=bb25df20282847ff0955ed2dd5b78a5025cea0dc8984b28ce9835cc3ea76c7fb -->
# phil/email-address

**Acceptez les adresses auxquelles un serveur de courriel peut livrer, refusez les autres, et attrapez la faute de
frappe avant qu'elle ne rebondisse.**

phil/email-address lit une adresse courriel telle que les gens la tapent et la vérifie dans l'ordre : sa syntaxe,
comme la boîte de courriel de la RFC 5321 avec un domaine international et les formes de la RFC 6531 que permet
l'application ; les domaines que l'application refuse, ou les seuls qu'elle accepte ; le domaine de premier niveau,
d'après la liste de la zone racine que tient l'IANA ; les domaines des boîtes de courriel jetables, d'après une liste importée comme données ; et, par un port, si le DNS de son domaine nomme un
serveur qui reçoit le courriel. Quel que soit le résultat, il suggère l'adresse que la personne voulait sans doute
écrire quand le domaine est à une faute de frappe d'un domaine bien connu : `ada@gmial.com`, `ada@gmailcom`,
`ada@hotmail.con`.

Il s'adresse à toute application PHP qui recueille des adresses : un formulaire d'inscription, un abonnement à une
infolettre, un formulaire de contact, un fournisseur d'identité, l'importation d'une liste. Il répond à « le courriel
peut-il atteindre cette adresse ? » avant l'envoi du premier message, pour qu'une faute de frappe ne devienne pas un
rebond et qu'une boîte jetable ne devienne pas un compte.

C'est une simple bibliothèque PHP sur `ext-intl`, `ext-ctype` et `ext-filter`, avec deux composants Symfony : le
Validator, pour sa contrainte `DeliverableEmailAddress`, et le Filesystem, pour écrire ses données. Les domaines passent par IDNA (UTS #46), comme les lisent les navigateurs ; rien n'est filtré par une expression
régulière trouvée sur le Web.

La conception : `EmailAddress` est la valeur (la partie avant le @ telle qu'écrite, le domaine sous ses formes Unicode
et ASCII), analysée sous une `SyntaxPolicy` ; `TopLevelDomains` contient les domaines de premier niveau délégués ; `DomainRules`
et `DisposableDomains` contiennent des domaines et leurs
sous-domaines ; `Contract\MailDomainResolver` est le port vers le DNS, avec `Dns\DnsGetRecordResolver` et
`Dns\StaticResolver` ; `TypoSuggester` trouve l'adresse voulue ; `EmailValidator` exécute les étapes et retourne une
`Validation` ; `Validator\DeliverableEmailAddress` les exécute dans le Validator de Symfony.

## État

| Partie | État |
|---|---|
| Syntaxe de la RFC 5321 : dot-atoms, longueurs de 64, 253 et 254 octets, étiquettes de 63 | Implémenté et testé |
| Domaines internationaux (UTS #46, non transitionnel, STD3, bidi et liants vérifiés) | Implémenté et testé |
| Parties locales non ASCII (RFC 6531), parties locales entre guillemets et littéraux IP, chacun quand il est permis | Implémenté et testé |
| Domaines refusés et domaines seuls permis, avec leurs sous-domaines | Implémenté et testé |
| Domaines jetables : la liste, la fusion, l'importateur et sa commande | Implémenté et testé |
| Les listes fournies dans `data/` | Pas encore importées : les deux importateurs sont prêts, `data/VERSION` indique `none` pour chacune |
| MX, MX nul (RFC 7505) et A/AAAA par un port, adaptateur `dns_get_record()` | Implémenté et testé |
| Suggestions pour les fautes de frappe des domaines bien connus et de `com`, `net` et `org` | Implémenté et testé |
| Domaines de premier niveau connus : la liste, l'importateur de l'IANA et sa commande | Implémenté et testé |
| `DeliverableEmailAddress`, une contrainte du Validator de Symfony | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis, installation, le validateur comme service. |
| [Syntaxe](doc/fr/syntax.md) | Ce qu'`EmailAddress::parse()` accepte et refuse, la politique, les formes d'une adresse. |
| [Validation](doc/fr/validation.md) | Les étapes d'`EmailValidator`, la `Validation`, les problèmes. |
| [Domaines](doc/fr/domains.md) | Domaines de premier niveau, domaines refusés et seuls permis, domaines jetables, les listes fournies et leur importation. |
| [DNS](doc/fr/dns.md) | Le port du résolveur, les enregistrements MX, MX nul et A, les délais d'attente et le cache. |
| [Validator de Symfony](doc/fr/symfony-validator.md) | La contrainte `DeliverableEmailAddress`, ses options, ses messages et ses codes. |
| [Suggestions](doc/fr/suggestions.md) | Comment `TypoSuggester` trouve l'adresse voulue, et comment l'ajuster. |
| [Points d'extension](doc/fr/extension-points.md) | Le port, les arguments et les données qu'une application choisit ou remplace. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque et son seuil de mutation, et comment tester du code qui valide des adresses. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/email-address
```

```php
use Phil\EmailAddress\DisposableDomains;
use Phil\EmailAddress\Dns\DnsGetRecordResolver;
use Phil\EmailAddress\EmailValidator;
use Phil\EmailAddress\TopLevelDomains;

$validator = new EmailValidator(
    disposable: DisposableDomains::bundled(),
    resolver: new DnsGetRecordResolver(),
    topLevelDomains: TopLevelDomains::bundled(),
);

$validation = $validator->validate('ada@gmial.com');

$validation->isValid();                // true when gmial.com receives mail
$validation->suggestion?->toString();  // 'ada@gmail.com': ask "Did you mean…?"

$validator->validate('ada@localhost')->syntaxError?->describe(); // 'the domain has no dot'
```

Dans un formulaire ou une entité Symfony :

```php
use Phil\EmailAddress\Validator\DeliverableEmailAddress;

#[DeliverableEmailAddress(checkMailServer: true)]
public string $email;
```

## Prérequis

PHP 8.5 ou plus récent, `ext-intl`, `ext-ctype` et `ext-filter` ; `symfony/validator` et `symfony/filesystem` 8.2 ou
plus récents.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la [politique de
sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE). La liste des domaines jetables de `data/` est versée au domaine public sous CC0 1.0, et la liste
des domaines de premier niveau est publiée par l'Internet Assigned Numbers Authority ; voir [NOTICE](NOTICE).
