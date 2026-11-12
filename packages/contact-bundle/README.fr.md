<!-- phil-dev source-sha256=7731792966f095fc5783ca5a72c1c86671de7ae622e60ef3dc2ccc14d3048e53 -->
# phil/contact-bundle

**Un formulaire de fiche de contact, une colonne JSON, et du vCard et du JSContact pour les applications Symfony.**

phil/contact-bundle amène les fiches de `phil/contact` dans Symfony. `ContactCardType` est un formulaire pour le nom,
l'organisation et le titre d'une fiche, avec des collections de courriels, de lignes téléphoniques, d'adresses Web et
d'identifiants de messagerie. Les utilisateurs ajoutent et retirent des entrées avec les boutons de son thème de
formulaire, pilotés par un contrôleur Stimulus. Les entrées vides sont écartées, et une entrée incorrecte rend son
propre champ invalide. Sur demande, le formulaire demande aussi le nom d'une personne en ses parties, par
`PersonNameType`, qu'une culture ordonne et trie. Le bundle enregistre aussi le type de colonne Doctrine
`phil_contact`, qui conserve une fiche dans une seule colonne JSON. `VCardResponse` envoie une fiche en pièce jointe
`.vcf` et `JSContactResponse` en JSContact, et le lecteur JSContact est un service pour les importations. Les fonctions
Twig `contact_vcard` et `contact_json_ld` écrivent une fiche en vCard ou en JSON-LD schema.org, comme nœud du graphe de
la page lorsque phil/structured-data-bundle en rassemble un, et `person_name` écrit un nom tel qu'il s'affiche ou se
trie. Les libellés et les messages sont en anglais et en français.

Il s'adresse aux applications Symfony où un rédacteur ou un administrateur saisit les coordonnées d'une personne ou
d'une organisation, et où les visiteurs les téléchargent ou les moteurs de recherche les lisent. Les champs de
téléphone, d'adresse Web et d'identifiant de messagerie viennent de `phil/phone-bundle`, `phil/url-bundle` et
`phil/instant-messenger-bundle`. Le formulaire accepte ce que ces paquets acceptent, et ses options limitent les genres
d'adresses Web et les services de messagerie offerts.

Hors de Symfony, ou pour la fiche seule, utilisez `phil/contact`. Le bundle est une mince couche au-dessus : les
transformateurs de formulaire, le type de colonne et l'extension Twig font chacun la traduction entre Symfony et les
types-valeurs de la bibliothèque.

## État

| Partie | État |
|---|---|
| `ContactCardType`, avec `EmailAddressType` et `PhoneLineType` | Implémenté et testé |
| `PersonNameType` et l'option `structured_name` | Implémenté et testé |
| Le thème de formulaire et le contrôleur Stimulus `collection` | Implémenté et testé |
| Le type de colonne `phil_contact` | Implémenté et testé |
| `VCardResponse` ; Twig `contact_vcard`, `contact_json_ld`, `person_name` | Implémenté et testé |
| `JSContactResponse` ; le lecteur JSContact en service, configuré | Implémenté et testé |
| Les libellés et les messages en anglais et en français | Implémenté et testé |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, les bundles apparentés, l'enregistrement, la configuration. |
| [Formulaires](doc/fr/forms.md) | `ContactCardType`, `PersonNameType`, leurs options, le thème et son contrôleur Stimulus. |
| [Doctrine](doc/fr/doctrine.md) | Le type de colonne `phil_contact`, et les noms structurés des fiches enregistrées. |
| [vCard, JSContact et Twig](doc/fr/vcard-and-twig.md) | `VCardResponse`, `JSContactResponse`, le lecteur JSContact, `contact_vcard`, `contact_json_ld`, `person_name`. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application implémente, configure, remplace ou utilise. |
| [Tests](doc/fr/testing.md) | La façon dont le bundle est testé. |
| [Référence](doc/fr/reference.md) | Chaque type du bundle. |

## Démarrage rapide

```sh
composer require phil/contact-bundle
```

```php
#[ORM\Column(type: 'phil_contact', nullable: true)]
private ?ContactCard $contact = null;

$builder->add('contact', ContactCardType::class, ['structured_name' => true]);
```

```twig
{{ contact_json_ld(shop.contact) }}
{{ person_name(shop.contact, 'sort') }}
```

## Prérequis

PHP 8.5 ou plus récent ; `phil/contact`, `phil/phone-bundle`, `phil/url-bundle` et `phil/instant-messenger-bundle` 0.1 ;
`symfony/dependency-injection`, `symfony/form` et `symfony/options-resolver` 8.2 ou plus récent. Doctrine DBAL,
HttpFoundation et Twig sont facultatifs.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la [politique de
sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
