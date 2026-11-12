<!-- phil-dev source-sha256=435bb709e66828ebcc2d2db243426208c099dd0a101fd5b84480a74d9bd5c2b1 -->
# Stockage

`ContactCardArray::from($card)` écrit une fiche en tableau de textes, `ContactCardArray::to($array)` la relit : c'est ce
que le type de colonne de `phil/contact-bundle` enregistre en JSON.

```json
{"name": "Ada Lovelace", "organization": null, "title": null,
 "emails": ["ada@example.com"],
 "phones": [{"role": "mobile", "number": "+15145550123;ext=12"}],
 "urls": [{"type": "Phil\\Url\\KnownUrlType::Website", "url": "https://example.com/"}],
 "handles": [{"type": "Phil\\InstantMessenger\\KnownMessenger::Telegram", "handle": "adalove"}],
 "structuredName": {"given": "Ada", "family": "Lovelace", "particle": null, "additional": ["Augusta"],
  "secondFamily": null, "honorific": null, "generation": null, "credential": null, "culture": "en-GB", "order": null}}
```

`structuredName` vaut `null` pour une fiche qui n'en a pas. Son `order` est `given-first`, `family-first` ou `null`,
l'ordre de la culture.

Les sortes d'adresses et les services de messagerie s'écrivent `Enum\Class::Case`. Quand une fiche est relue, une clé
absente est vide, et `UnreadableContact` est levée pour tout le reste qui n'est pas ce qu'écrit `from()` : un texte qui
n'est pas un texte, une liste qui n'est pas une liste, un rôle qu'aucun `PhoneRole` n'a, une référence à autre chose
qu'un cas d'une énumération qui implémente `UrlType` ou `MessengerType`, un nom structuré qui n'est pas un objet ou
dont l'ordre est inconnu, ou une valeur que son type refuse.

## Les fiches enregistrées sans nom structuré

Une fiche enregistrée avant d'avoir un nom structuré n'a pas de clé `structuredName`, et elle est relue sans nom
structuré : son `name` reste son seul nom. Aucune migration des données n'est nécessaire. Une application qui veut les
parties des noms qu'elle garde déjà les demande, par le formulaire de `phil/contact-bundle` par exemple, ou les scinde
elle-même et enregistre de nouveau les fiches. Une application qui renomme un cas le renomme aussi dans les fiches
enregistrées.

## Chiffrer les fiches enregistrées

Une fiche est une donnée personnelle de bout en bout : noms, adresses, lignes téléphoniques et identifiants. Quand elle
est enregistrée dans une base de données, chiffrez sa colonne au repos avec `#[Encrypted]` du composant KeyManagement
de Symfony, par son pont Doctrine ORM `symfony/doctrine-orm-key-management`. L'attribut garde le type propre de la
colonne : la fiche est d'abord écrite en JSON par ce type, celui de `phil/contact-bundle` par exemple, puis chiffrée
sous une clé maîtresse du KMS, et enregistrée dans une colonne binaire.

```php
use Doctrine\ORM\Mapping as ORM;
use Phil\Contact\ContactCard;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Attribute\BlindIndexed;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Attribute\Encrypted;

#[ORM\Column(type: 'phil_contact', nullable: true)]
#[Encrypted(key: 'member.contact')]
private ?ContactCard $contact = null;

#[ORM\Column(length: 180)]
#[Encrypted(key: 'member.email')]
private string $email = '';

#[ORM\Column(length: 64)]
#[BlindIndexed('email', 'member-email')]
private string $emailIndex = '';
```

On ne peut pas chercher dans une colonne chiffrée. Une fiche garde plusieurs adresses et lignes téléphoniques : la
valeur par laquelle l'application retrouve les gens va donc dans une colonne à part, chiffrée elle aussi, avec un index
aveugle à côté. `#[BlindIndexed]` remplit cette colonne sœur à chaque flush avec une empreinte à clé de la valeur, et
l'application retrouve la ligne par l'empreinte de ce qu'on lui donne. `Projection\Email` normalise une adresse avant
qu'elle soit indexée ; un numéro de téléphone écrit en E.164 est indexé par `Projection\Verbatim`. Un index aveugle
dit quelles lignes partagent une valeur : n'indexez que ce qui a beaucoup de valeurs distinctes et se cherche par
égalité, rien d'autre.

Les clés maîtresses et la clé de données de l'index se configurent dans `key_management`, avec le KMS de l'application
ou un client `sodium://` local. Elles font leur rotation dans le composant : `key-management:rewrap-data-keys`
enveloppe de nouveau les clés de données enregistrées sans réécrire une ligne quand un magasin de clés de données est
configuré ; sinon l'ancienne clé maîtresse reste dans le KMS tant que des fiches scellées sous elle demeurent. La clé
de données d'un index aveugle ne fait jamais sa rotation. Les deux attributs sont expérimentaux dans Symfony 8.2.
