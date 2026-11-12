<!-- phil-dev source-sha256=20374ed70a6142776764daf6200a6e86eb9c830dafd3b37df0924ad905170a0a -->
# JSContact

JSContact, la RFC 9553, est la forme JSON d'une fiche de contact qu'échangent JMAP Contacts (RFC 9610) et les carnets
d'adresses récents. La bibliothèque écrit une fiche en JSContact 1.0 et en relit une. La forme enregistrée d'une fiche
ne change pas : JSContact est une correspondance pour l'importation et l'exportation.

```php
$document = new JSContactCard('urn:uuid:'.$id, $card, [$address]);

$json = JSContactWriter::toJson($document);       // application/jscontact+json, JSContactCard::MEDIA_TYPE

$read = new JSContactReader()->read($json);
$read->card;        // a ContactCard
$read->addresses;   // the Address values of phil/address
$read->unmapped;    // the properties the library does not map, as they were read
$read->notices;     // what was read but not kept
```

## `JSContactCard`

| Propriété | Contient |
|---|---|
| `uid` | L'identifiant de la fiche, de 1 à 1024 octets, exigé par JSContact |
| `card` | La `ContactCard` |
| `addresses` | Les adresses postales, des valeurs `Address` de `phil/address`, que la fiche ne garde pas |
| `unmapped` | Les propriétés que la bibliothèque ne représente pas — `notes`, `anniversaries`, `photos`, `speakToAs`, un `example.com:property` d'un fournisseur —, des valeurs JSON telles que les donne `json_decode()` sans son indicateur associatif, pour qu'un objet vide le reste |
| `notices` | Un `JSContactNotice` pour chaque partie lue mais non gardée : son pointeur JSON, `/phones/p1/contexts`, et la raison |

Les propriétés non représentées sont réécrites par `JSContactWriter`, après celles qui sont représentées ; elles ne
contiennent jamais une propriété que l'écrivain écrit lui-même, `JSContactCard::MAPPED`, sinon `InvalidContact` est
levée. Une application qui les garde les enregistre à côté de la fiche.

## La correspondance

| JSContact | Fiche | Écrit | Lu |
|---|---|---|---|
| `uid` | `JSContactCard::$uid` | Toujours | Exigé |
| `kind` | Personne ou organisation | `individual`, ou `org` pour une fiche qui ne nomme que son organisation | `org` fait nommer son organisation à la fiche ; toute sorte autre que `individual` et `org` est lue comme celle d'une personne, avec un avis |
| `language` | La culture du nom structuré | Tirée de la culture, sinon de la propriété `language` non représentée | La culture du nom structuré ; gardée non représentée quand aucun nom structuré ne la prend |
| `name` | `name` et `structuredName` | `full`, les composantes dans l'ordre d'affichage, `isOrdered`, `defaultSeparator`, et `sortAs.surname` quand la culture trie le nom autrement qu'il est écrit | Voir plus bas |
| `organizations` | `organization` | Une entrée | Le `name` de la première entrée |
| `titles` | `title` | Une entrée | La première entrée de sorte `title` |
| `emails` | `emails` | Une entrée chacun | Chaque `address` |
| `phones` | `phones` | `number` en adresse `tel:`, et la caractéristique du rôle | Chaque `number`, son rôle tiré de `features` |
| `onlineServices` | `handles`, ou `urls` | `service`, le nom du cas d'énumération, `user`, et `uri` quand le service a un lien | Un identifiant quand `service` nomme un service de messagerie accepté, sinon une adresse Web quand `uri` est d'une sorte acceptée |
| `links` | `urls` | Une entrée chacune | Chaque `uri`, sa sorte détectée |
| `addresses` | `JSContactCard::$addresses` | `components` et `countryCode` | Voir plus bas |

Les entrées sont écrites sous les clés `o1`, `t1`, `e1`, `p1`, `s1`, `l1` et `a1`, numérotées à partir de 1.

### Les noms

Les composantes `title`, `given`, `given2`, `surname`, `surname2`, `generation` et `credential` sont le titre
honorifique, le prénom, les prénoms additionnels, le nom de famille, le second nom de famille, la génération et le
titre de compétence. Une particule s'écrit avec son nom de famille, `de Gaulle`, et `sortAs.surname` est la forme de
tri, `Gaulle` ; à la lecture, la partie du nom de famille qui précède sa forme de tri, et qui se termine par une espace
ou une apostrophe, est la particule. Une particule que la culture garde dans la clé de tri est donc relue comme une
partie du nom de famille, qui écrit et trie le nom de la même façon.

Avec `isOrdered`, un nom de famille avant le prénom se lit `NameOrder::FamilyFirst`, gardé seulement quand ce n'est pas
l'ordre de la culture. Un avis signale une composante d'une autre sorte, une seconde composante d'une sorte gardée une
seule fois, un prénom additionnel au-delà du dixième, un séparateur — les séparateurs suivent la convention du nom —,
un `sortAs` ou un `defaultSeparator` que la culture ne donne pas, une langue que le nom ne peut pas prendre comme
culture, et des composantes qui ne font aucun nom. La fiche d'une organisation garde `full` et signale les composantes.

### Les lignes téléphoniques

| Rôle | Caractéristique écrite | Lue à partir de |
|---|---|---|
| `Mobile` | `mobile` | `mobile`, avec `voice` et `text` sous-entendus |
| `Fax` | `fax` | `fax` |
| `Pager` | `pager` | `pager` |
| `Voice` | `voice` | `voice`, ou aucune caractéristique |
| `Text` | `text` | `text` |

Une ligne prend le rôle de sa première caractéristique dans l'ordre du tableau ; un avis signale chaque autre
caractéristique, dont `video`, `textphone` ou `main-number`. Un numéro sans son indicatif de pays est lu dans la
`region` du lecteur.

### Les adresses

L'écrivain écrit chaque ligne d'adresse en composante `name`, la localité dépendante en `district`, la localité en
`locality`, la zone administrative en `region`, puis le code postal et le code de tri chacun en `postcode`. Les champs
du destinataire d'une adresse sont omis : la fiche nomme son titulaire.

Le lecteur écrit les composantes de rue — `number`, `name`, `direction`, `block`, `building`, `floor`, `apartment`,
`room`, `postOfficeBox` et `landmark` — sur les lignes de l'adresse, dans leur ordre : un `name` après une ligne qui en
a déjà un commence la ligne suivante, et une adresse garde trois lignes. Les premiers `district`, `locality` et
`region` sont gardés, le premier `postcode` est le code postal et le second le code de tri. Un avis signale toute autre
composante, une adresse sans son `countryCode`, et une adresse que `phil/address` refuse.

## Le lecteur

```php
$reader = new JSContactReader(
    maxBytes: 65_536,
    maxDepth: 16,
    maxEntries: 100,
    region: 'CA',
    messengers: [EnumSet::all(KnownMessenger::class), EnumSet::all(MyMessenger::class)],
    urlTypes: [EnumSet::all(KnownUrlType::class)],
);
```

| Argument | Valeur par défaut | Signifie |
|---|---|---|
| `maxBytes` | 65 536 | Le texte le plus long lu |
| `maxDepth` | 16 | L'imbrication la plus profonde de son JSON |
| `maxEntries` | 100 | Le plus grand nombre d'entrées d'une table ou d'une liste |
| `region` | `null` | Le pays dans lequel un numéro sans indicatif de pays est lu |
| `messengers` | Chaque `KnownMessenger` | Les services de messagerie sous lesquels un service en ligne est lu |
| `urlTypes` | Chaque `KnownUrlType` | Les sortes d'adresses Web acceptées |

Le lecteur est strict. `UnreadableJSContact` est levée pour un texte hors des limites, un texte qui n'est pas du JSON,
l'absence d'une `Card` de version `1.0`, l'absence d'un `uid`, une propriété d'un mauvais type JSON, une clé d'entrée
qui n'est pas un `Id` de JSContact, une entrée dont le `@type` n'est pas celui attendu, une entrée sans son texte
exigé, et une fiche que la bibliothèque refuse, un nom de plus de 255 caractères par exemple. Une valeur que son type
refuse — un courriel qui n'en est pas un, un numéro qu'aucun plan ne connaît, une adresse Web d'aucune sorte acceptée —
fait omettre son entrée, avec un avis. Rien n'est perdu en silence : une propriété est représentée, gardée non
représentée, ou signalée.

Le lecteur ne garde aucun état entre deux fiches : une seule instance sert chaque requête d'un *worker*.
