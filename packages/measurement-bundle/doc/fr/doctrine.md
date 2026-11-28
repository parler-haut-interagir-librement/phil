<!-- phil-dev source-sha256=1399c377b569bd0bc10af90f5de967bfaf9e14f44d92423db96bd5cadc0bbfa7 -->
# Doctrine

## Le type de colonne `phil_measurement`

Une `Measurement` dans une seule colonne de chaîne, sous la forme de son texte : son nombre décimal exact et son code
d'unité, `12.5 kg`, jamais un nombre à virgule flottante.

```php
#[ORM\Column(type: 'phil_measurement')]
public Measurement $weight;

#[ORM\Column(type: 'phil_measurement', length: 40, nullable: true)]
public ?Measurement $length = null;
```

La colonne contient 100 caractères à moins que le mappage ne donne sa `length` : un nombre décimal canonique de
18 chiffres significatifs, une espace et un code de 64 octets y tiennent. Le texte est relu par le registre de
l'application, de sorte que ses propres unités sont connues ; une unité que le registre ne contient plus, ou un texte
qui n'est pas une mesure, fait échouer l'hydratation avec `ValueNotConvertible` de Doctrine plutôt que de passer sans
vérification.

Le bundle enregistre le type à son démarrage, construit avec le registre de l'application, sauf si `doctrine_type`
vaut `false` ; il n'est pas déclaré sous `doctrine.dbal.types`, ce qui le construirait sans son registre.

## Deux colonnes pour trier ou additionner en SQL

Une colonne de texte se trie comme du texte. Une mesure à trier, à filtrer ou à additionner en SQL est mappée sur deux
colonnes, un `decimal`, que Doctrine donne sous forme de chaîne, et un code d'unité, dans une unité choisie une fois
pour la colonne :

```php
#[ORM\Column(type: 'decimal', precision: 18, scale: 6)]
private string $weightValue;

#[ORM\Column(length: 64)]
private string $weightUnit;

public function weight(UnitRegistry $units): Measurement
{
    return $units->measurement($this->weightValue, $this->weightUnit);
}

public function setWeight(Measurement $weight, UnitRegistry $units): void
{
    $inKilograms = $weight->convertTo($units->unit('kg'));
    $this->weightValue = $inKilograms->toFixedDecimal(6);
    $this->weightUnit = 'kg';
}
```

La valeur est arrondie à l'échelle de la colonne à son écriture : six chiffres d'un kilogramme, c'est un milligramme.
