<!-- phil-dev source-sha256=565f3c06a5781de6fa0c7ea4e7a1698f5dc94bacdfdb0b7cfdd7f9596eaa2b43 -->
# Référence

| Type | Genre | Rôle |
|---|---|---|
| `Phil\Slug\Slug` | `final readonly class` | Un segment d'URL, vérifié |
| `Phil\Slug\InvalidSlug` | `InvalidArgumentException` | Un texte qui n'est pas un slug, ou dont on ne peut faire aucun slug |
| `Phil\Slug\Slugger` | `interface` | Transforme un texte en `Slug` |
| `Phil\Slug\TransliteratingSlugger` | `final readonly class`, `Slugger` | Translittère en ASCII, coupe à un mot |
| `Phil\Slug\LeadingWordsRemoved` | `final readonly class`, `Slugger` | Décorateur : retire un mot de tête d'une liste |
| `Phil\Slug\Sluggers` | `final readonly class` | Les générateurs nommés, et celui par défaut |
| `Phil\Slug\UnknownSlugger` | `OutOfBoundsException` | Aucun générateur ne porte ce nom |

## `Slug`

| Règle | Valeur |
|---|---|
| Caractères | lettres ASCII minuscules et chiffres |
| Mots | joints par des traits d'union simples ; aucun en tête ni en fin |
| Longueur | de 1 à 255 caractères (`Slug::MAX_LENGTH`) |

`Slug::fromString()` vérifie et **ne corrige jamais** : `My-Article` à côté de `my-article` ferait deux adresses pour
une même page. Un texte qui n'est pas déjà un slug est donc refusé avec `InvalidSlug`. `equals()` compare la graphie ;
`toString()` et `(string)` la redonnent.

## `Slugger`

```php
interface Slugger
{
    /** @throws InvalidSlug when the text holds no letter or digit a slug can be made of */
    public function slug(string $text): Slug;
}
```

### `TransliteratingSlugger`

```php
new TransliteratingSlugger(?string $locale = null, int $maxLength = Slug::MAX_LENGTH);
```

| Texte | Slug |
|---|---|
| `Jeux de rôle` | `jeux-de-role` |
| `L'Ordre du Corbeau` | `l-ordre-du-corbeau` |
| `Ça va? Œuvre — été` | `ca-va-oeuvre-ete` |
| `Straße über`, locale `de` | `strasse-ueber` |

- La translittération est celle du `AsciiSlugger` de `symfony/string`, dans la locale quand on en donne une.
- Un slug plus long que `$maxLength` est **coupé au dernier trait d'union qui tient**, pour qu'aucun mot ne soit coupé
  en deux ; un premier mot plus long que le maximum est coupé là où tombe le maximum.
- `$maxLength` va de 1 à 255 ; toute autre valeur est refusée avec `InvalidSlug`.
- Un texte sans lettre ni chiffre (`' ?! '`) est refusé avec `InvalidSlug`.

### Décorer un générateur

On change un générateur en le décorant : une classe implémente `Slugger`, reçoit le générateur qu'elle enveloppe,
l'appelle, et change le texte avant ou le slug après.

`LeadingWordsRemoved` en est un :

```php
new LeadingWordsRemoved(Slugger $slugger, list<string> $words);
```

- Il retire **un** mot de tête, **une fois**, quand ce mot est dans la liste et que d'autres mots le suivent : avec
  `['le', 'la', 'les', 'l', 'the']`, `Les Gardiens` donne `gardiens`, `The Guild` donne `guild`, et `Les` reste
  `les`.
- Les mots s'écrivent comme des mots de slug — `L'Ordre` donne `l-ordre`, dont le premier mot est `l` — ; un mot qui
  n'est pas une seule suite de lettres ASCII minuscules et de chiffres est refusé avec `InvalidSlug`.
  `LeadingWordsRemoved::checkWord($word)` applique la même règle sans construire de générateur, pour qu'une
  configuration vérifie ses mots tôt.
- Il n'est jamais appliqué par défaut : une application choisit les générateurs qui retirent des mots, et lesquels.

## `Sluggers`

```php
new Sluggers(array<string, Slugger> $sluggers, string $default);

$sluggers->get(?string $name = null): Slugger;          // the default one without a name
$sluggers->slug(string $text, ?string $name = null): Slug;
$sluggers->names(): list<string>;
```

Un nom inconnu — donné à `get()` ou à `slug()`, ou comme valeur par défaut — est refusé avec `UnknownSlugger`, dont le
message liste les noms qui existent.
