<!-- phil-dev source-sha256=3e7ee1e91283b237a7fdc45f9709c189e441df6cb887e1ff8450466f55b731fe -->
# Appareils et pays

Deux dimensions tirées de la requête à l'ingestion, chacune limitée à une liste fermée, et chacune tirée de quelque
chose qui est ensuite oublié.

| Partie | État |
|---|---|
| `DeviceClass` | Implémenté et testé |
| `CountryCode`, `GeoDatabaseFreshness` | Implémenté et testé |
| `CountryResolver` (la recherche) | Port ; l'adaptateur par défaut `UnknownCountryResolver` compte tous les pays comme inconnus |

## Appareils : `DeviceClass`

**L'appareil est gardé en trois classes, et rien de plus fin** : ordinateur, tablette, mobile. Une classe plus fine
(un modèle, une version de navigateur) n'ajoute rien dont un agrégat a besoin, et beaucoup de ce dont une empreinte
numérique est faite. La classe vient d'une petite règle sur le `User-Agent`, pas d'une bibliothèque de détection des
appareils.

```php
DeviceClass::fromUserAgent($request->headers->get('User-Agent', ''))->value; // 'desktop' | 'tablet' | 'mobile'
```

La règle, sans égard à la casse :

1. **Tablette** si l'agent utilisateur contient `iPad`, `Tablet` (mais pas `Tablet PC`), `PlayBook`, `Kindle` ou
   `Silk/` ; ou contient `Android` sans `Mobile`.
2. **Mobile** s'il contient `Mobi`, `iPhone`, `iPod`, `Android`, `Windows Phone`, `BlackBerry`, `BB10` ou
   `Opera Mini`.
3. **Ordinateur** autrement, y compris pour un agent utilisateur vide.

La tablette passe en premier parce que les agents utilisateurs des tablettes portent aussi des marques mobiles.
Conséquences connues :

- Un iPad qui se présente comme un Mac est compté comme ordinateur : l'agent utilisateur est tout ce que lit la règle.
- Un ordinateur Windows dont l'agent utilisateur porte `Tablet PC` (prise en charge du stylet) est un ordinateur ; un
  agent utilisateur Windows Phone qui porte `Tablet PC` est mobile.

## Pays : `CountryCode`

**Le pays est tiré de l'adresse du client en mémoire, puis l'adresse est oubliée.** Le pays est la seule chose de
l'adresse qu'un compteur contient jamais.

```php
$country = CountryCode::fromString('ca');
$country->code;        // 'CA'
$country->isKnown();   // true
$country->toString();  // 'CA'

$unknown = CountryCode::unknown();
$unknown->code;        // null
$unknown->toString();  // '(unknown)' — CounterKey::UNKNOWN
$country->equals($unknown); // false
```

`fromString()` accepte exactement deux lettres ASCII (un code ISO 3166-1 alpha-2) et les met en majuscules ; tout le
reste, y compris un saut de ligne final, lance `InvalidValue`. Le bundle ne vérifie pas que le code est attribué.

Le pays est inconnu quand la recherche ne peut pas répondre, ou quand sa base est trop vieille pour servir.

### La recherche : `CountryResolver`

```php
interface CountryResolver
{
    public function countryOf(#[SensitiveParameter] string $ip): CountryCode;
}
```

L'adaptateur ne journalise ni ne stocke l'adresse, et répond `CountryCode::unknown()` quand il ne peut pas savoir — y
compris quand sa base dépasse l'âge que permet sa licence. Celui du bundle, `UnknownCountryResolver`, répond « inconnu »
pour toute adresse : une application qui a une base de géolocalisation nomme son adaptateur sous
`collection.country_resolver`. Voir [extension-points.md](extension-points.md#countryresolver) pour un adaptateur sur
une base locale au format GeoLite2.

## Fraîcheur de la base : `GeoDatabaseFreshness`

Certaines licences de bases de géolocalisation bornent la durée d'utilisation d'une version. Passé cette borne, **le
pays est compté inconnu plutôt que cherché dans une base que la licence ne couvre plus** : une base périmée répond
« inconnu », jamais une supposition.

```php
$freshness = GeoDatabaseFreshness::maxAgeDays(30);
$freshness->maxAgeDays;                       // 30
$freshness->isUsable($builtAt, $now);         // bool
```

| Cas | `isUsable()` |
|---|---|
| `$now` dans les `maxAgeDays` jours après `$builtAt` (inclus) | `true` |
| `$now` une seconde plus tard | `false` |
| `$builtAt` postérieur à `$now` (une horloge ou un fichier dont on ne peut pas se fier) | `false` |

`maxAgeDays()` refuse moins d'un jour. L'âge se mesure depuis la date de construction que passe l'adaptateur ; la
borne que fixe réellement la licence (par exemple un nombre de jours après la *prochaine* version plutôt qu'après la
construction) est à traduire par l'application en `maxAgeDays` et `$builtAt`.
