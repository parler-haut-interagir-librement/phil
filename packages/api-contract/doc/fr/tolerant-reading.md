<!-- phil-dev source-sha256=b02a669f0412889c8b035b1b26dba580952bc2ff64a26b37798223f8b8a694b6 -->
# Lecture tolérante

**État :** `TolerantEnum` et `UnknownAuthorityValue` sont implémentés et testés.

## La règle

Un contrat qui grandit par ajouts oblige chaque client à lire de façon tolérante :

1. **Ignorez les champs inconnus.** Un nouveau champ n'est pas un changement qui brise le contrat. Cette moitié
   concerne le désérialiseur, pas la bibliothèque : configurez votre sérialiseur pour ignorer les attributs en trop, et
   **ne validez jamais une réponse contre un schéma fermé**.
2. **Associez les valeurs d'énumération inconnues à une valeur par défaut.** Une nouvelle valeur d'énumération ne brise
   pas non plus le contrat : un client rencontre donc des valeurs qu'il ne connaît pas. La bibliothèque porte cette
   moitié, avec deux règles choisies par champ :
   - un **champ ordinaire** prend la valeur par défaut que le contrat fixe pour lui;
   - **un champ d'autorité refuse** : un niveau d'assurance inconnu ne satisfait aucune exigence, un état d'octroi
     inconnu n'octroie rien, un type d'événement de sécurité inconnu n'est pas traité.

## `TolerantEnum`

`Phil\ApiContract\TolerantReading\TolerantEnum` offre des méthodes statiques sur n'importe quel `BackedEnum` :

```php
/** @template T of BackedEnum  @param class-string<T> $enum  @param T $default  @return T */
public static function withDefault(string $enum, mixed $value, BackedEnum $default): BackedEnum

/** @template T of BackedEnum  @param class-string<T> $enum  @return T  @throws UnknownAuthorityValue */
public static function authoritative(string $enum, mixed $value): BackedEnum
```

Les deux cherchent la valeur parmi `$enum::cases()` avec une **comparaison stricte et sans conversion** : une valeur
JSON du mauvais type est aussi inconnue qu'une chaîne que l'énumération n'énumère pas. `"1"` n'est pas `1`,
`"STRONG"` n'est pas `"strong"`, `null` et les tableaux sont inconnus.

```php
use Phil\ApiContract\TolerantReading\TolerantEnum;

enum Priority: int { case Normal = 1; case High = 2; }
enum AssuranceLevel: string { case Basic = 'basic'; case Strong = 'strong'; }

$priority = TolerantEnum::withDefault(Priority::class, $data['priority'] ?? null, Priority::Normal);
// 2 -> Priority::High; '2', 7, null -> Priority::Normal

$level = TolerantEnum::authoritative(AssuranceLevel::class, $data['acr'] ?? null);
// 'strong' -> AssuranceLevel::Strong; 'phishing-resistant' -> UnknownAuthorityValue
```

Attrapez `UnknownAuthorityValue` là où l'exigence est vérifiée, et traitez-la comme « non satisfaite ».

## `UnknownAuthorityValue`

`Phil\ApiContract\Exception\UnknownAuthorityValue` est une `UnexpectedValueException` qui implémente
`ApiContractException`. Son message nomme la valeur et l'énumération :

```text
"phishing-resistant" is not a known value of App\AssuranceLevel; an unknown authority value is refused.
```

La valeur vient d'une réponse et n'est pas fiable : le message la reprend donc **bornée et échappée** :

- un entier est montré tel quel (`7 is not a known value of ...`);
- une chaîne est mise entre guillemets, coupée à ses 64 premiers octets suivis de `...` quand elle est plus longue, et
  ses caractères de contrôle, guillemets, barres obliques inverses et octets au-dessus de `0x7E` sont échappés (aucun
  saut de ligne brut n'atteint une ligne de journal);
- tout autre type est montré par le nom de son type (`array is not a known value of ...`, `null is not ...`).

## Hors de la bibliothèque

La configuration du sérialiseur, et toute liste des champs de votre contrat qui font autorité : cette liste appartient
à l'application.
