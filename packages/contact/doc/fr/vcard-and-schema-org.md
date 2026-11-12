<!-- phil-dev source-sha256=66707e0d266503a3466298c8ef92f6a0b2d6a5682f736b27b446e7773ac33823 -->
# vCard et schema.org

## vCard 4.0

`VCard::of($card)` écrit la fiche comme le décrit la RFC 6350, pour un fichier `.vcf` (`VCard::MEDIA_TYPE`,
`text/vcard`) :

| Propriété | Tirée de |
|---|---|
| `KIND` | `org` pour une organisation, `individual` sinon |
| `FN`, `ORG`, `TITLE` | Le nom affiché, l'organisation, le titre |
| `N` | Le nom structuré : nom de famille avec sa particule et son second nom de famille, prénom, noms additionnels, titre honorifique, génération et titre de compétence ; puis le second nom de famille et la génération, comme les ajoute la RFC 9554, quand le nom les a |
| `N;LANGUAGE=…` | La culture du nom structuré |
| `N;SORT-AS="…"` | Le nom de famille trié et le prénom, quand la culture trie le nom autrement qu'il est écrit : `N;LANGUAGE=fr;SORT-AS="Gaulle,Charles":de Gaulle;Charles;;;` |
| `EMAIL` | Chaque courriel |
| `TEL;VALUE=uri;TYPE=…` | Chaque ligne téléphonique, en adresse `tel:` avec son poste, de type `voice`, `cell`, `fax`, `text` ou `pager` |
| `URL` | Chaque adresse Web |
| `IMPP` | Chaque identifiant dont le service a un lien : `xmpp:…`, `https://t.me/…` |

Les textes échappent `\`, `,`, `;` et les sauts de ligne (CRLF, LF ou un CR seul) ; `SORT-AS` échappe `^` et `"`
comme le fait la RFC 6868, et écrit une virgule d'un nom comme une espace. Les lignes de plus de 75 octets sont
repliées, jamais au milieu d'un caractère ; les lignes se terminent par CRLF.

## schema.org

`SchemaOrg::of($card)` donne le JSON-LD sous forme de tableau :

```php
[
    '@context' => 'https://schema.org',
    '@type' => 'Person',
    'name' => 'Ada Lovelace',
    'jobTitle' => 'Countess',
    'worksFor' => ['@type' => 'Organization', 'name' => 'Analytical Engines'],
    'email' => ['ada@example.com'],
    'telephone' => ['+15145550123'],
    'faxNumber' => ['+15145550124'],
    'sameAs' => ['https://example.com/'],
]
```

Une personne avec un nom structuré a aussi `honorificPrefix`, `givenName`, `additionalName`, `familyName` — avec sa
particule et son second nom de famille — et `honorificSuffix`, la génération et le titre de compétence séparés par une
virgule. Une organisation est un `Organization`, sans `jobTitle`. Les propriétés vides sont omises ; les autres sont
des listes. Les numéros de téléphone sont en E.164, sans leur poste.

## JSContact

Voir [JSContact](jscontact.md).
