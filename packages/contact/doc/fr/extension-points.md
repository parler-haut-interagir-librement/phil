<!-- phil-dev source-sha256=ad01218c33c93b393cc8040c17ccc0a59cd4cbee50696d7dc8b29307916f09a0 -->
# Points d'extension

La bibliothèque est du PHP ordinaire : elle ne déclare aucun service ni aucun port, et ses classes sont finales. Ce
qu'une application étend se trouve dans les bibliothèques qui composent une fiche.

| Point | Comment |
|---|---|
| Les sortes d'adresses Web | Les `urls` d'une fiche sont des `TypedUrl` de `phil/url` : une application ajoute une sorte en écrivant une énumération qui implémente `Phil\Url\UrlType` |
| Les services de messagerie | Les `handles` d'une fiche sont des `Handle` de `phil/instant-messenger` : une application ajoute un service en écrivant une énumération qui implémente `Phil\InstantMessenger\MessengerType` |
| Le pays d'un numéro de téléphone | Donné quand `phil/phone` analyse le `PhoneNumber` d'une `PhoneLine` |
| La façon dont une culture écrit et trie les noms | Une `NameConvention` de l'application, donnée aux méthodes de `PersonName` |
| Les services de messagerie et les sortes d'adresses sous lesquels JSContact est lu | Les arguments `messengers` et `urlTypes` de `JSContactReader` |
| Les limites d'une fiche JSContact lue | Les arguments `maxBytes`, `maxDepth` et `maxEntries` de `JSContactReader` |
| Ce que JSContact porte au-delà de la fiche | `JSContactCard::$unmapped`, gardé et réécrit ; `JSContactCard::$notices`, ce qui a été lu sans être gardé |
| L'endroit où une fiche est gardée | `ContactCardArray::from()` donne un tableau pour une seule colonne JSON, `ContactCardArray::to()` le relit |
| La façon de publier une fiche | `VCard::of()` pour un téléchargement en vCard 4.0, `SchemaOrg::of()` pour le JSON-LD, `JSContactWriter` pour JSContact ; un autre format s'écrit à partir des propriétés publiques de la fiche |
| Attraper les erreurs de la bibliothèque | Chaque exception implémente `Phil\Contact\Exception\ContactException` |

Une fiche enregistrée nomme chaque sorte et chaque service par sa classe d'énumération et son cas,
`App\Url\Kind::Forum`. Renommer ou retirer un cas que les données nomment encore fait lever `UnreadableContact` par
`ContactCardArray::to()` : migrez donc un cas dans les données avant qu'il quitte le code.
