<!-- phil-dev source-sha256=ec18371e742b2cf4a153f310fe274c239d65f63c41581d20bf2388cc294034b3 -->
# Fiches

```php
$card = ContactCard::of(
    name: 'Ada Lovelace',
    organization: 'Analytical Engines',
    title: 'Countess',
    emails: [Email::of('ada@example.com')],
    phones: [new PhoneLine(PhoneRole::Voice, PhoneNumber::parse('+1 514 555 0123')), new PhoneLine(PhoneRole::Fax, PhoneNumber::parse('+1 514 555 0124'))],
    urls: [TypedUrl::of(KnownUrlType::Website, 'https://example.com')],
    handles: [Handle::of(KnownMessenger::Signal, '+15145550123')],
);
```

Une fiche nomme une **personne** quand elle a un `name` — qui travaille pour son `organization` si elle en a une —, et
une **organisation** quand elle n'a qu'un `organization`. Les textes sont rognés, un texte vide n'en est pas un, et
chacun compte 255 caractères ou moins, sinon `InvalidContact` est levée.

Le nom d'une personne peut aussi être donné en ses parties, `structuredName`, un `PersonName` qui écrit et trie le nom
comme le fait sa culture ; sans `name`, la fiche prend la forme d'affichage de son nom structuré. Voir
[Noms de personnes](names.md).

| Méthode | Donne |
|---|---|
| `displayName()` | Le nom, ou celui de l'organisation |
| `isPerson()` | Si la fiche nomme une personne |
| `isEmpty()` | Si la fiche ne contient rien |
| `phonesFor(PhoneRole)` | Les lignes téléphoniques d'un rôle, dans leur ordre |

## Courriels

`Email::of()` rogne l'adresse et met son domaine en minuscules ; la partie locale reste telle qu'écrite. Un domaine dans
une autre écriture est vérifié sous sa forme ASCII et gardé tel qu'écrit, `ada@exämple.com`. `link()` est l'adresse
`mailto:`.

## Lignes téléphoniques

Une `PhoneLine` est un `PhoneNumber` avec son `PhoneRole` : `Voice`, `Mobile`, `Fax`, `Text` pour les textos seulement,
ou `Pager`. Le plan de numérotation dit si un numéro est sans frais, `$line->number->type()` ; le rôle dit comment le
titulaire de la fiche s'en sert.
