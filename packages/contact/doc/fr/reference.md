<!-- phil-dev source-sha256=e6cc9e9e74499ca6c0a64f931d2818b7d12119cba8efc74fcfa1232532ae2d76 -->
# Référence

| Type | Sorte | Rôle |
|---|---|---|
| `ContactCard` | `final readonly class` | Une personne ou une organisation, et la façon de la joindre |
| `PersonName` | `final readonly class` | Le nom d'une personne en ses parties, écrit et trié comme le fait sa culture |
| `NameOrder` | énumération adossée à une chaîne | Le prénom d'abord ou le nom de famille d'abord |
| `NameConvention` | `final readonly class` | La façon dont une culture écrit et trie les noms |
| `NameConventions` | `final class` | Les conventions de la bibliothèque, par culture |
| `Email` | `final readonly class` | Une adresse de courriel |
| `PhoneLine` | `final readonly class` | Un numéro de téléphone avec son rôle |
| `PhoneRole` | énumération adossée à une chaîne | L'usage d'une ligne téléphonique, et son type vCard |
| `VCard` | `final class` | Une fiche en vCard 4.0 |
| `SchemaOrg` | `final class` | Une fiche en JSON-LD de schema.org |
| `ContactCardArray` | `final class` | Une fiche vers un tableau et depuis un tableau, pour le stockage |
| `JSContactCard` | `final readonly class` | Une fiche telle que l'échange JSContact : uid, adresses, propriétés non représentées, avis |
| `JSContactWriter` | `final class` | Une fiche en JSContact 1.0 |
| `JSContactReader` | `final readonly class` | Une fiche JSContact lue, de façon stricte et bornée |
| `JSContactNotice` | `final readonly class` | Une partie d'une fiche JSContact lue mais non gardée |
| `Exception\ContactException` | interface | Chaque exception de la bibliothèque |
| `Exception\InvalidContact` | `final class`, `InvalidArgumentException` | Un courriel qui n'en est pas un, un texte trop long, un nom de personne ou une fiche JSContact qui ne peut pas être faite |
| `Exception\UnreadableContact` | `final class`, `UnexpectedValueException` | Une fiche enregistrée qui ne peut pas être relue |
| `Exception\UnreadableJSContact` | `final class`, `UnexpectedValueException` | Une fiche JSContact qui ne peut pas être lue |

Les classes de `Phil\Contact\JSContact\` sont internes : ce sont les parties d'une lecture d'une fiche JSContact.
