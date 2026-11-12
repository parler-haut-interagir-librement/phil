<!-- phil-dev source-sha256=77cabbb9e3f61471cafb05d83e7eba489c3ab91a200de40d2a9473fd3de40400 -->
# Traduction

| Type | Genre | Rôle |
|---|---|---|
| `Contract\Translatable` | interface | `translationClass()`, `translatedFields()`, `newTranslation()` |
| `Contract\Translation` | interface | `translatable()`, `locale()` |
| `TranslationEntity` | trait | La colonne `locale`, dans la seule graphie de `phil/locale-dimensions` |
| `TranslationListener` | écouteur : `loadClassMetadata` | Vérifie le mappage, ajoute l'index unique sur l'enregistrement et la locale |
| `Translations` | service | `find()`, `get()`, `translate()`, `locales()`, `preload()`, `values()` |
| `TranslationSet` | valeur | Les traductions que `preload()` a lues, par enregistrement |
| `Exception\InvalidTranslation` | exception | Une entité de traduction mappée autrement, ou une traduction faite pour un autre enregistrement |

Un enregistrement traduit garde ce que toutes les locales partagent — un prix, une date, une image — et son texte vit
dans une entité de traduction qui lui est propre, une ligne par enregistrement et par locale, avec les seules colonnes
traduites. Les locales, leur graphie et les chaînes de repli sont celles de `phil/locale-dimensions`.

```php
#[ORM\Entity]
class Product implements Translatable
{
    public static function translationClass(): string { return ProductTranslation::class; }
    public static function translatedFields(): array { return ['name', 'description']; }

    public function newTranslation(Locale $locale): ProductTranslation
    {
        return new ProductTranslation($this, $locale);
    }
}

#[ORM\Entity]
class ProductTranslation implements Translation
{
    use TranslationEntity;

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    public string $name = '';

    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $description = null;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Product::class)]
        #[ORM\JoinColumn(name: 'product_id', nullable: false, onDelete: 'CASCADE')]
        private Product $translatable,
        Locale $locale,
    ) {
        $this->writtenIn($locale);
    }

    public function translatable(): Product { return $this->translatable; }
}
```

```php
$translations = new Translations($manager);
$chain = $locales->chainFor(Locale::fromString('fr_CA'));   // fr_CA → fr → en

$translation = $translations->find($product, $chain);        // the fr_CA row, or fr, or en, or null
$translations->values($translation);                         // ['name' => '…', 'description' => '…']
```

## Le mappage

L'entité de traduction mappe la relation vers un seul côté `translatable` vers son enregistrement, tenue par une colonne
de jointure, et le champ `locale`, que donne `TranslationEntity`. Au chargement du mappage, `TranslationListener` refuse
une entité de traduction à qui l'un ou l'autre manque, par `InvalidTranslation`, et ajoute à sa table un index unique
sur la colonne de jointure et la locale, nommé `<table>_translatable_locale` : une ligne par enregistrement et par
locale, la seule chose qui tient quand deux requêtes traduisent le même enregistrement en même temps. Un index que
l'entité déclare sur les deux mêmes colonnes, dans un ordre ou dans l'autre, est gardé tel quel. Une sous-classe hérite
des deux de la classe qui les mappe.

L'enregistrement n'a pas de collection de ses traductions : une liste d'enregistrements chargerait chaque locale de
chacun pour en montrer une. `Translations` lit les lignes dont il a besoin.

## Lire

| Méthode | Renvoie |
|---|---|
| `find($record, FallbackChain)` | la ligne de la première locale de la chaîne qui en a une, ou `null` |
| `get($record, Locale)` | la ligne de cette locale exactement, ou `null` |
| `locales($record)` | les locales où l'enregistrement a une ligne, dans l'ordre de leur valeur : ce que listent les variantes `hreflang` |
| `preload(iterable $records, FallbackChain)` | un `TranslationSet` : `of($record)` donne la ligne de chaque enregistrement le long de la chaîne |
| `values(Translation)` | les champs que nomme `translatedFields()`, par nom, dans cet ordre |

**Une ligne est servie entière.** La chaîne choisit une ligne, jamais un champ : une ligne française sans description
est servie sans description, plutôt qu'avec la description anglaise à côté d'un nom français. Une chaîne épuisée ne sert
rien, et l'appelant décide de ce que cela veut dire — une 404, un texte d'attente, une page qui dit que le texte n'est
pas traduit.

**Une liste coûte une requête par classe de traduction.** `preload()` lit les lignes de tous les enregistrements, dans
toutes les locales de la chaîne, en une requête par classe de traduction et par tranche de 500 enregistrements
(`Translations::CHUNK`), puis choisit en mémoire la ligne de chaque enregistrement. Lisez la liste d'abord, préchargez,
puis affichez :

```php
$products = $repository->findBy(['category' => $category]);
$set = $translations->preload($products, $chain);

foreach ($products as $product) {
    $set->of($product)?->name;
}
```

Les lignes persistées et non vidées comptent : un enregistrement traduit dans cette requête est relu avant le vidage ;
les lignes dont la suppression est prévue ne comptent pas. Un enregistrement pas encore enregistré ne lit rien dans la
base.

## Écrire

`translate($record, Locale)` donne la ligne de cette locale exactement, pour l'écrire : celle qui est enregistrée, ou
une nouvelle faite par `newTranslation()` et persistée pour le prochain vidage. **Elle ne recopie jamais un champ d'une
autre locale.** Le texte qu'un lecteur aurait reçu par la chaîne n'est pas le texte de cette locale, et une copie
passerait pour une traduction que personne n'a écrite, puis s'écarterait de sa source sans que personne ne le voie. Un
éditeur qui veut partir d'une autre locale montre cette locale à côté des champs vides.

`newTranslation()` doit faire une traduction de cet enregistrement, de la classe que nomme `translationClass()`, dans la
locale demandée ; tout le reste lève `InvalidTranslation`. L'enregistrement et la locale d'une ligne sont donnés par son
constructeur et ne changent jamais.

## Slugs

Une entité de traduction qui est `Sluggable` prend `ScopedSluggableEntity` et nomme `locale` dans `slugScope()`, pour
que chaque locale ait ses propres slugs : `/fr/crepes` et `/en/crepes` coexistent, et deux lignes françaises au même
titre reçoivent `crepes` et `crepes-2`. `SlugListener` refuse le slug d'une traduction dont la portée ne nomme pas sa
locale, par `InvalidScope`. Déclarez l'index unique sur la portée et le slug :

```php
#[ORM\UniqueConstraint(columns: ['locale', 'slug'])]
class ProductTranslation implements Translation, Sluggable
{
    use TranslationEntity;
    use ScopedSluggableEntity;

    public function slugSource(): string { return $this->name; }
    public function slugScope(): array { return ['locale']; }
}
```

## Versions

Il n'y a ni étape ni copie d'une locale source : une traduction qui change avec le temps est `Versionable`, comme tout
enregistrement, et chaque locale a ses propres versions — figées, créées et restaurées à part par `Versions`. Les
colonnes partagées de l'enregistrement sont versionnées sur l'enregistrement, s'il y a lieu. Voir
[Versions](versions.md).
