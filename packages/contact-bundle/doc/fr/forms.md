<!-- phil-dev source-sha256=6739b61010ebfb2998d59a7ddba8afac4600d0d27989ed1e517d6851a4374640 -->
# Formulaires

## `ContactCardType`

Le nom, l'organisation et le titre de la fiche, et quatre collections : les courriels (`EmailAddressType`), les lignes
téléphoniques (`PhoneLineType` : un rôle et le champ de `phil/phone-bundle`), les adresses Web (le champ de
`phil/url-bundle`) et les identifiants de messagerie (le champ de `phil/instant-messenger-bundle`). Les entrées vides
sont écartées, une fiche vide vaut `null` ; une entrée incorrecte rend son propre champ invalide.

```php
$builder->add('contact', ContactCardType::class, [
    'region' => 'FR',
    'url_types' => [KnownUrlType::Website, KnownUrlType::Facebook],
    'messenger_types' => [KnownMessenger::Signal, KnownMessenger::WhatsApp],
]);
```

| Option | Valeur par défaut | Signification |
|---|---|---|
| `region` | `null` : `phil_phone.default_region` | Le pays dans lequel sont lus les numéros de téléphone sans indicatif de pays |
| `url_types` | `null` : tous les genres internationaux | Les genres d'adresses Web acceptés |
| `messenger_types` | `null` : tous les services internationaux | Les services de messagerie offerts |
| `structured_name` | `false` | Si le formulaire demande les parties d'un nom de personne, un `PersonNameType` |
| `culture` | `null` | La culture des noms saisis, donnée à `PersonNameType` |

Avec `structured_name`, le nom de la fiche est donné par ses parties quand le champ du nom est laissé vide, et il les
suit quand elles changent : une fiche dont le nom est la forme d'affichage de son nom structuré montre un champ de nom
vide.

## `PersonNameType`

Un nom de personne en ses parties, vers un `PersonName` de `phil/contact` et depuis un `PersonName` : titre
honorifique, prénom, prénoms additionnels dans un seul champ séparés par des espaces, particule, nom de famille, second
nom de famille, génération, titre de compétence, l'ordre dans lequel le nom s'écrit quand ce n'est pas celui de sa
culture, et sa culture, une étiquette de langue BCP 47. Les champs portent les jetons `autocomplete` dont les
navigateurs les remplissent : `honorific-prefix`, `given-name`, `additional-name`, `family-name` et `honorific-suffix`
pour la génération. Un nom sans prénom ni nom de famille vaut `null` ; un nom que `phil/contact` refuse rend le champ
invalide, `This name is not valid.`.

```php
$builder->add('name', PersonNameType::class, ['culture' => 'fr-CA']);
```

| Option | Valeur par défaut | Signification |
|---|---|---|
| `culture` | `null` : un champ du formulaire | L'étiquette de langue BCP 47 que prend chaque nom saisi ; le formulaire n'a alors pas de champ de culture |

Voir les noms de personnes de `phil/contact` pour la façon dont une culture ordonne et trie un nom.

Les libellés sont dans le domaine de traduction `phil_contact`, en anglais et en français.

## Le thème et le contrôleur Stimulus

Avec TwigBundle, le bundle ajoute `@PhilContact/form/theme.html.twig` aux thèmes de formulaire : chaque collection
reçoit un bouton *Add* et chaque entrée un bouton *Remove*, pilotés par le contrôleur Stimulus
`phil--contact-bundle--collection`. Une nouvelle entrée est le prototype de la collection avec un index qu'aucune entrée
n'a eu : le thème donne au contrôleur l'index qui suit la plus haute clé de la page, puisque les entrées conservées
d'une soumission qui a échoué peuvent sauter des numéros. Le contrôleur répartit `added`, avec l'index, et `removed`.
