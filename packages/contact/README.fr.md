<!-- phil-dev source-sha256=2b0636797f4d77ebf8a7b13d1294682b0e23fed8b707b76ed766a3b6d815aae8 -->
# phil/contact

**Une fiche de contact pour une personne ou une organisation, prête à enregistrer en vCard ou en JSON-LD.**

phil/contact garde une fiche de contact dans un objet-valeur immuable. Une fiche nomme une personne, qui peut
travailler pour une organisation, ou une organisation seule. Elle porte un titre, des adresses de courriel, des lignes
téléphoniques, chacune avec son rôle (voix, cellulaire, télécopieur, texto ou téléavertisseur), des adresses Web et des
identifiants de messagerie. Le nom d'une personne peut être gardé en ses parties — prénom, particule, nom de famille
et le reste — et il est alors écrit et trié comme le fait sa culture : `Charles de Gaulle` se trie sous *Gaulle* en
français, `Bartók Béla` s'écrit avec le nom de famille en premier en hongrois. La fiche s'écrit en fichier vCard 4.0,
tel que le décrit la RFC 6350, en JSON-LD de schema.org, un `Person` ou un `Organization`, et en JSContact (RFC 9553),
qu'elle lit aussi, de façon stricte et dans des limites. Elle se transforme aussi en tableau de textes pour une seule
colonne JSON. Elle ne garde pas les adresses postales, qui relèvent de `phil/address` et voyagent à côté de la fiche
dans JSContact, ni les heures d'ouverture, qui relèvent de `phil/opening-hours`, ni de formulaire de contact, que
fournit `phil/contact-form-bundle`.

Elle s'adresse à toute application qui montre comment joindre quelqu'un : une entrée d'annuaire, la page d'une
boutique, le profil d'un organisateur. Les numéros de téléphone, les adresses Web et les identifiants ne sont pas de
simples chaînes. Ce sont les types-valeurs de `phil/phone`, `phil/url` et `phil/instant-messenger` : une fiche ne garde
donc que ce que ces paquets acceptent.

C'est une bibliothèque PHP ordinaire, utilisable dans tout code PHP. Dans une application Symfony, prenez plutôt
`phil/contact-bundle`, qui ajoute le formulaire, le type de colonne Doctrine, le téléchargement en vCard et des
fonctions Twig.

La conception sépare la fiche de ses formats. `ContactCard` et ses parties ne gardent que des valeurs vérifiées, et
`VCard`, `SchemaOrg`, `JSContactWriter` et `ContactCardArray` écrivent chacun la fiche dans un format. Les conventions
de nommage des cultures sont des valeurs typées, `NameConvention`, qu'une application peut remplacer. Relire une fiche
enregistrée lève `UnreadableContact` pour tout ce que `ContactCardArray` n'écrit pas ; la lecture de JSContact garde ce
que la fiche ne représente pas et signale ce qu'elle lit sans le garder, sans jamais rien perdre en silence.

## État

| Partie | État |
|---|---|
| `ContactCard`, `Email`, `PhoneLine` et `PhoneRole` | Implémenté et testé |
| `PersonName`, `NameConvention`, `NameConventions` : noms écrits et triés selon la culture | Implémenté et testé |
| `VCard` : vCard 4.0, échappé et replié | Implémenté et testé |
| `SchemaOrg` : JSON-LD `Person` ou `Organization` | Implémenté et testé |
| `ContactCardArray` : vers un tableau et depuis un tableau, pour le stockage | Implémenté et testé |
| `JSContactWriter`, `JSContactReader` : JSContact 1.0, borné et soumis au fuzzing | Implémenté et testé |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis et l'installation. |
| [Fiches](doc/fr/cards.md) | `ContactCard`, les courriels, les lignes téléphoniques et leurs rôles. |
| [Noms de personnes](doc/fr/names.md) | `PersonName`, ses parties et les conventions des cultures. |
| [vCard et schema.org](doc/fr/vcard-and-schema-org.md) | Les exportations vCard et JSON-LD. |
| [JSContact](doc/fr/jscontact.md) | L'écriture et la lecture de JSContact, la correspondance, les avis et les limites. |
| [Stockage](doc/fr/storage.md) | `ContactCardArray`, ce qui est vérifié quand une fiche est relue, et le [chiffrement des fiches enregistrées](doc/fr/storage.md#chiffrer-les-fiches-enregistrées) avec `#[Encrypted]` et `#[BlindIndexed]`. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports, les arguments et les données qu'une application choisit ou remplace. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque et son seuil de mutation. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/contact
```

```php
use Phil\Contact\ContactCard;
use Phil\Contact\Email;
use Phil\Contact\PersonName;
use Phil\Contact\PhoneLine;
use Phil\Contact\PhoneRole;
use Phil\Contact\VCard;
use Phil\Phone\PhoneNumber;

$card = ContactCard::of(
    organization: 'Analytical Engines',
    emails: [Email::of('ada@example.com')],
    phones: [new PhoneLine(PhoneRole::Mobile, PhoneNumber::parse('+1 514 555 0123'))],
    structuredName: PersonName::of(given: 'Ada', family: 'Lovelace', culture: 'en-GB'),
);

$card->name;                          // "Ada Lovelace"
$card->structuredName->sortKey();     // "Lovelace, Ada"
VCard::of($card);                     // "BEGIN:VCARD\r\nVERSION:4.0\r\nKIND:individual\r\nFN:Ada Lovelace\r\n…"
```

## Prérequis

PHP 8.5 ou plus récent avec `ext-filter`, `ext-intl` et `ext-mbstring` ; `phil/phone`, `phil/url`,
`phil/instant-messenger` et `phil/address` 0.1, et `phil/enums` 0.3.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
