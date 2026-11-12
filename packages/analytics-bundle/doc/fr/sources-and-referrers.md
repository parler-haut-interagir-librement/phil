<!-- phil-dev source-sha256=34b8d9e1e1b9eac9d6f6a560eadd02f72695ad3fb4ab274c0e840b7e9e163f44 -->
# Sources et référents

**La source d'une page vue est classée par le code du bundle lui-même** dans l'une de six classes, d'après des listes
d'hôtes. Seule la classe est comptée, jamais le référent. Aucune bibliothèque d'analyse des référents n'est installée,
et seule l'origine du référent est jamais lue : ni son chemin ni sa requête.

État : `HostList`, `ReferrerClassifier` et `ReferralKind` sont implémentés et testés. Le bundle configure
`ReferrerClassifier` à partir de `phil_analytics.hosts`, avec de courtes listes par défaut de moteurs de recherche et de
réseaux sociaux que l'application remplace ([configuration.md](configuration.md#réglages-du-domaine)).

## `ReferralKind`

| Cas | Valeur | Sens | `isVisit()` |
|---|---|---|---|
| `Direct` | `direct` | Aucun référent, ou un référent sans origine web (`android-app://...`) | oui |
| `Search` | `search` | Un moteur de recherche | oui |
| `Social` | `social` | Un réseau social | oui |
| `Website` | `website` | Tout autre site | oui |
| `Internal` | `internal` | Le site lui-même, ou une autre origine que l'application déclare sienne | non |
| `Spam` | `spam` | Sur la liste de pourriel : écarté avant que rien ne soit compté | non |

**Une visite est une page vue dont la source n'est pas le site lui-même** (`isVisit()`). Compter les visites ainsi ne
demande aucun identifiant de visiteur ([counters.md](counters.md#pas-de-visiteurs-uniques--des-visites-à-la-place)).

## `ReferrerClassifier`

```php
$classifier = new ReferrerClassifier(
    HostList::of(['google.{}', 'duckduckgo.com']),   // search
    HostList::of(['facebook.com']),                  // social
    HostList::of(['free-traffic.test']),             // spam
);

$own = [
    Origin::fromString('https://example.com'),
    Origin::fromString('https://shop.example.com'),
    Origin::fromString('https://login.example.net'), // e.g. the return from an identity provider
];

$classifier->classify(null, $own);                                   // Direct
$classifier->classify('android-app://com.example.mail', $own);       // Direct
$classifier->classify('https://example.com/events?x=1', $own);       // Internal
$classifier->classify('https://login.example.net/cb?code=abc', $own);// Internal
$classifier->classify('https://www.google.co.uk/search?q=x', $own);  // Search
$classifier->classify('https://m.facebook.com/', $own);              // Social
$classifier->classify('https://free-traffic.test/', $own);           // Spam
$classifier->classify('https://blog.example.com/', $own);            // Website: an undeclared subdomain is another site
```

`classify(?string $referer, iterable<Origin> $ownOrigins): ReferralKind` suit cet ordre :

1. Aucun référent, un référent vide, ou un référent sans origine http(s) : `Direct`.
2. Une origine égale à l'une des `$ownOrigins` (schéma, hôte et port exacts) : `Internal`.
3. Hôte sur la liste de pourriel : `Spam`.
4. Hôte sur la liste des moteurs de recherche : `Search`.
5. Hôte sur la liste des réseaux sociaux : `Social`.
6. Tout le reste : `Website`.

Passez comme `$ownOrigins` les origines déclarées du site (`CollectionSite::$origins`) **plus toute origine que
l'application traite comme le site lui-même**, comme le retour de son fournisseur d'identité ; sinon, un aller-retour
de connexion compterait comme une nouvelle visite.

Le référent à classer est le référent *de la page vue* — ce que le navigateur du visiteur avait comme référent de la
page —, pas le `Referer` de la requête de collecte, qui est la page mesurée elle-même.

## `HostList`

`Phil\AnalyticsBundle\Domain\ValueObject\HostList` est une liste de noms d'hôtes auxquels on compare l'hôte d'un
référent.

```php
$list = HostList::of(['example.com', 'google.{}']);
$list->matches('www.example.com');   // true: an entry matches itself and every subdomain
$list->matches('badexample.com');    // false: matching is on label boundaries
$list->matches('WWW.Example.com.');  // true: case and a trailing dot are ignored
$list->matches('google.co.uk');      // true
$list->matches('google.attacker.example'); // false
$list->entries;                      // ['example.com', 'google.{}'], normalized and deduplicated
HostList::empty()->matches('example.com'); // false
```

Les entrées sont débarrassées de leurs espaces et mises en minuscules ; les doublons sont retirés. Une entrée qui n'est
pas un nom d'hôte (vide, une URL, un chemin, une étiquette vide, une espace) lance `InvalidValue`.

La correspondance coûte les étiquettes de l'hôte, pas la longueur de la liste : les entrées littérales sont gardées dans
un ensemble haché, et l'hôte y est cherché une fois par suffixe (`www.example.com`, `example.com`, `com`). Les entrées
qui ont une étiquette `{}` sont gardées dans un arbre d'étiquettes lues depuis la droite, où `{}` est une branche de
plus, et l'hôte descend cet arbre une étiquette à la fois. Une liste de milliers d'hôtes de pourriel de référents,
littéraux ou gabarits, vérifiée à chaque page vue, reste quelques recherches, et aucune liste n'est trop longue pour la
correspondance : aucun motif n'est construit à partir des entrées.

### L'étiquette `{}`

**Une étiquette écrite `{}` tient lieu d'un suffixe public, jamais d'un domaine enregistrable** : une étiquette de
premier niveau (`com`, `fr`), ou l'une des étiquettes génériques de second niveau `ac`, `co`, `com`, `edu`, `go`,
`gob`, `gov`, `ne`, `net`, `or`, `org` suivie d'un code de pays de deux lettres (`co.uk`, `com.au`). Ainsi, `google.{}`
correspond à `google.com`, `www.google.co.uk` et `google.fr`, mais ni à `google.attacker.example` ni à
`www.google.evil.co.uk`.

Pourquoi : n'importe qui peut enregistrer `attacker.example`. Si `{}` correspondait à n'importe quel domaine, un
référent forgé serait classé comme moteur de recherche, et une entrée de pourriel écarterait des sites légitimes sous
le même préfixe.

C'est une approximation fixe, pas la Public Suffix List : un suffixe public hors de ce motif (un suffixe de trois
étiquettes, un second niveau propre à un pays absent de la liste) ne correspond pas à `{}` ; listez ces hôtes
explicitement.

## D'où viennent les listes

Les listes du domaine public sont la source prévue : par exemple la liste CC0 des moteurs de recherche et des réseaux
sociaux et la liste CC0 de pourriel de référents publiées par le projet Matomo, lues et converties en entrées de
`HostList` par l'application et données sous `phil_analytics.hosts`. Les valeurs par défaut du bundle sont dix moteurs
de recherche et douze réseaux sociaux, et aucun hôte de pourriel ; il n'installe aucune bibliothèque d'analyse des
référents — en particulier aucune dont le fichier de données est sous une licence réciproque.
