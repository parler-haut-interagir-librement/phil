<!-- phil-dev source-sha256=309cb6d5baabd8bfca9bb6364f12c54e037c3fdd57c4cb66180175306ef2d54f -->
# Déclarer les données personnelles

**État : implémenté et testé.** Du PHP pur, sans conteneur, sans entrée ni sortie.

## L'attribut

`Phil\PrivacyBundle\Domain\Attribute\PersonalData` marque une propriété comme renseignement personnel. Il vise les
propriétés seulement (`Attribute::TARGET_PROPERTY`), propriétés promues du constructeur comprises.

```php
use Phil\PrivacyBundle\Domain\Attribute\PersonalData;

final class Member
{
    public function __construct(
        #[PersonalData('email', retention: 'R-002')]
        public string $email,
        #[PersonalData('person_name')]   // no retention rule cited yet
        public string $displayName,
    ) {
    }
}
```

| Argument | Type | Règle |
|---|---|---|
| `category` | `string` | Devient une `DataCategory` : un jeton `snake_case` en minuscules (`^[a-z][a-z0-9_]*$`) d'au plus 64 caractères. `'E-mail'` ou `'email address'` est refusé. |
| `retention` | `?string`, `null` par défaut | L'identifiant d'une règle de votre calendrier de conservation. S'il est donné, il doit correspondre à `RetentionRule::ID_PATTERN` : une lettre ou un chiffre, puis des lettres, des chiffres, `.`, `_` ou `-`, au plus 64 caractères (`R-002`, `RET-042`, `users.email`). |

Propriétés publiques après la construction : `$category` (`DataCategory`) et `$retention` (`?string`). Un argument mal
formé lève `InvalidPrivacyValue` quand l'attribut est instancié — c'est-à-dire quand quelque chose le lit par
`ReflectionAttribute::newInstance()`, et non au chargement de la classe.

### Pourquoi un attribut, et pourquoi si peu

- **Déclaré là où vit le champ.** La déclaration suit la propriété ; un inventaire tenu dans un fichier à part s'écarte
  du modèle.
- **Des métadonnées et rien d'autre**, pour qu'un modèle du domaine les porte comme il porte les attributs de mappage.
  Ce qui les lit — un test d'inventaire, un export, un nettoyeur — vit hors du modèle.
- **Le vocabulaire est le vôtre.** Le bundle vérifie la forme d'une catégorie, pas une liste : une classification en
  texte libre ne peut être ni regroupée, ni comptée, ni rapprochée d'une règle de conservation.
- **Aucune règle n'est devinée.** Un champ déclaré sans `retention` est signalé comme non couvert, et ne reçoit pas de
  règle par défaut.

## Lire les déclarations

`Phil\PrivacyBundle\Domain\Declaration\DeclaredPersonalFields` a deux méthodes statiques.

### `of(class-string $class): list<PersonalField>`

Chaque champ déclaré de la classe **et de ses parents**. Les parents sont parcourus explicitement parce que la réflexion
ne rend pas les propriétés privées d'un parent depuis l'enfant, et un champ déclaré en privé sur un modèle de base reste
un champ personnel de chaque sous-classe.

L'ordre est stable : les parents d'abord, puis l'ordre de déclaration dans chaque classe. Chaque propriété est signalée
une fois, par la classe qui la déclare.

### `uncoveredIn(class-string $class, ?RetentionSchedule $schedule = null): list<PersonalField>`

Les champs qu'aucune règle de conservation ne couvre :

- sans calendrier, les champs qui ne citent aucune règle ;
- avec un calendrier, aussi les champs dont la règle citée n'est pas définie par le calendrier — un identifiant mal
  orthographié ou retiré ne couvre rien.

## `PersonalField`

Une valeur en lecture seule : `$class` (la classe qui déclare), `$property`, `$category` (`DataCategory`) et
`$retention` (`?string`).

| Méthode | Rend `true` quand |
|---|---|
| `isCoveredByRetention(): bool` | le champ cite une règle, qu'elle existe ou non |
| `isCoveredBy(RetentionSchedule $schedule): bool` | le champ cite une règle que le calendrier définit |

## Le test d'inventaire

L'usage prévu est un test de l'application qui échoue quand un champ personnel n'a pas de règle de conservation :

```php
use Phil\PrivacyBundle\Domain\Declaration\DeclaredPersonalFields;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PersonalDataInventoryTest extends TestCase
{
    /** @return iterable<array{class-string}> */
    public static function models(): iterable
    {
        yield [Member::class];
        yield [Order::class];
    }

    #[DataProvider('models')]
    public function testEveryPersonalFieldHasARetentionRule(string $class): void
    {
        $schedule = (new MyScheduleSource())->load(); // your RetentionScheduleSource

        $uncovered = array_map(
            static fn ($field): string => $field->class.'::$'.$field->property,
            DeclaredPersonalFields::uncoveredIn($class, $schedule),
        );

        self::assertSame([], $uncovered);
    }
}
```

Le bundle ne découvre pas vos modèles : les classes que parcourt le test sont la liste de l'application.
