<!-- phil-dev source-sha256=16461f1c7da3970f15d158dbf2cc0b793f27be513db6229187df1bd46619ae47 -->
# phil/phone

**Des numéros de téléphone lus comme les gens les saisissent, vérifiés selon le plan de leur pays, puis formatés.**

`phil/phone` lit un numéro dans un `PhoneNumber`, avec ou sans son indicatif de pays, avec des espaces, des points ou
un poste. La bibliothèque vérifie le numéro selon le plan de numérotation de son pays et indique son type : mobile,
fixe, sans frais et autres. Elle écrit le numéro aux formes E.164, internationale, nationale et `tel:` de la
RFC 3966. **Elle ne** devine **pas** d'opérateur, de fuseau horaire ni de lieu, ne lit pas de numéros dans du texte
libre et ne formate pas pendant la saisie.

Elle s'adresse aux applications PHP qui stockent des numéros de contact et doivent les afficher et les composer
correctement d'un pays à l'autre. Les plans de numérotation sont ceux de libphonenumber : 254 territoires importés
sous forme de données PHP par le `MetadataImporter` de la bibliothèque. L'analyse et le formatage sont du code propre à
la bibliothèque. Elle ne dépend d'aucun autre paquet, seulement de `ext-ctype`, `ext-dom` et `ext-mbstring`, et ses
plans suivent les versions de libphonenumber en exécutant de nouveau l'importateur. Les données importées restent sous
licence Apache 2.0 : le paquet est donc sous MIT et Apache-2.0.

Dans une application Symfony, utilisez `phil/phone-bundle` : un champ de formulaire, une contrainte de validation, un
type de colonne Doctrine et des filtres Twig. `phil/instant-messenger` utilise `phil/phone` pour lire les numéros de
téléphone qui identifient les comptes WhatsApp, Signal ou Viber. La conception est un type valeur sur des données
simples : un `PhoneNumber` garde un indicatif de pays, un numéro national et un poste, et la bibliothèque ne lit que
les parties des plans qu'elle utilise.

## État

| Partie | État |
|---|---|
| `PhoneNumber::parse()` : indicatifs de pays, préfixes internationaux et nationaux, postes, `tel:` | Implémenté et testé |
| `region()`, `type()`, `isValid()` selon les plans de numérotation | Implémenté et testé |
| `format()` : E.164, international, national, RFC 3966 | Implémenté et testé |
| `MetadataImporter` : le XML de libphonenumber vers des données PHP, 254 territoires | Implémenté et testé |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis et installation. |
| [Analyse](doc/fr/parsing.md) | Ce que lit `parse()`, les régions, les postes, les refus. |
| [Validation et types](doc/fr/validation.md) | `isValid()`, `region()`, `type()`, et ce que signifie chaque type. |
| [Formatage](doc/fr/formatting.md) | Les quatre formats, les postes, et le stockage d'un numéro. |
| [Métadonnées](doc/fr/metadata.md) | D'où viennent les plans de numérotation, leur licence et leur mise à jour. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports, arguments et données qu'une application choisit ou remplace. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque et son seuil de mutation. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/phone
```

```php
use Phil\Phone\PhoneFormat;
use Phil\Phone\PhoneNumber;

$number = PhoneNumber::parse('06 12 34 56 78', 'FR');
$number->isValid();                          // true
$number->type();                             // PhoneNumberType::Mobile
$number->format(PhoneFormat::International); // "+33 6 12 34 56 78"
(string) $number;                            // "+33612345678"
```

## Prérequis

PHP 8.5 ou plus récent avec `ext-ctype`, `ext-dom` et `ext-mbstring`. `parse()` mesure le texte avec `ext-mbstring` ;
`ext-dom` et `ext-ctype` ne servent qu'à l'importateur, mais Composer les exige avec la bibliothèque.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

Le code est sous MIT, © Philippe Gamache, doing business as Le Gnome Archiviste;
a Parler Haut Interagir Librement project. Voyez [LICENSE](LICENSE). Les plans de numérotation de `data/` viennent de
libphonenumber, sous licence Apache 2.0 : voyez [NOTICE](NOTICE) et [data/LICENSE](data/LICENSE).
