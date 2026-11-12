<!-- phil-dev source-sha256=eb23d137086b754ec142aeddf08a9498b78b7b0faf271400ccc1f277616b923b -->
# Points d'extension

Ce qu'une application configure, étend, remplace ou écoute. Les fiches sont celles de `phil/contact` ; les numéros de
téléphone, les adresses Web et les identifiants de messagerie passent par `phil/phone-bundle`, `phil/url-bundle` et
`phil/instant-messenger-bundle`.

## Les genres et les services propres à l'application

Les adresses Web et les identifiants de messagerie d'une fiche acceptent les enums propres à l'application — qui
implémentent `Phil\Url\UrlType` et `Phil\InstantMessenger\MessengerType` — par les options `url_types` et
`messenger_types` de `ContactCardType`, comme les champs de ces bundles les acceptent.

## Formulaires

| Point | Comment |
|---|---|
| Le pays des numéros de téléphone | L'option `region` de `ContactCardType` ; `phil_phone.default_region` sans elle |
| Les genres d'adresses Web et les services | Ses options `url_types` et `messenger_types` |
| Les parties d'un nom de personne | Son option `structured_name`, et `PersonNameType` seul |
| La culture des noms saisis | L'option `culture` de `ContactCardType` et de `PersonNameType` ; un champ de culture sans elle |
| Les libellés | Le domaine de traduction `phil_contact` : les traductions d'une application remplacent celles qui sont fournies |
| Davantage que ce que fait le formulaire | Une extension de type de formulaire de l'application sur `ContactCardType`, `EmailAddressType`, `PhoneLineType` ou `PersonNameType` |

Voir [Formulaires](forms.md).

## Le thème et le contrôleur Stimulus

`@PhilContact/form/theme.html.twig` est ajouté aux thèmes de formulaire ; un thème de l'application placé après lui
remplace ses blocs. `phil--contact-bundle--collection` répartit `added`, avec l'index de la nouvelle entrée, et
`removed` : un contrôleur de l'application les écoute.

## Stockage, téléchargements et Twig

| Point | Comment |
|---|---|
| L'endroit où une fiche est conservée | Le type de colonne `phil_contact`, du JSON par `ContactCardArray` — voir [Doctrine](doctrine.md) |
| Un téléchargement vCard | `VCardResponse` renvoyé par le contrôleur de l'application, sous le nom de fichier qu'il choisit |
| Une exportation JSContact | `JSContactResponse` renvoyé par le contrôleur de l'application |
| Une importation JSContact | Le service `JSContactReader` dans le contrôleur de l'application ; ses limites et sa région sous `phil_contact.jscontact` ; un autre lecteur, avec les services de messagerie et les genres d'adresses de l'application, construit par l'application |
| Les données structurées et les noms | `contact_vcard`, `contact_json_ld` et `person_name` — voir [vCard, JSContact et Twig](vcard-and-twig.md) |
