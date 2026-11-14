<!-- phil-dev source-sha256=6251ff92a85e1eef3867ba2b4013f822acf54169019c700066b2455b5567fda1 -->
# La porte de la sérialisation

**État : port seulement, à dessein.** `Phil\AuditBundle\Domain\Contract\CanonicalSerializer` est défini; le bundle ne
fournit **aucune implémentation**.

## Une porte à sens unique

L'empreinte d'une ligne est calculée sur une sérialisation canonique de ses champs. L'ordre des champs et l'encodage
doivent être fixés : un changement ultérieur brise sans bruit la vérification de chaque ligne écrite avant lui, et cet
échec ne peut pas être distingué d'une falsification.

**La porte se ferme à la première ligne ancrée.** Avant, la sérialisation peut encore changer sans coût; après, chaque
ancre s'engage sur des octets produits par cette sérialisation exacte.

Dans un bundle partagé, la porte se ferme **pour chaque application qui l'installe, à sa propre première ligne
ancrée**. Changer ensuite l'implémentation du bundle transformerait chaque ancre de chacune de ces applications en
discontinuité permanente. Voilà pourquoi :

- **le bundle ne fournit pas encore de sérialisation.** Il fournit le port seul. Une application qui ancre avant que le
  bundle en fournisse une écrit sa propre implémentation, la fige et la garde;
- **quand une implémentation entrera dans le bundle, elle viendra avec un identifiant de version écrit dans chaque
  ligne**, et toute sérialisation ultérieure sera une autre implémentation à côté d'elle, jamais une modification de la
  première.

## Versionnée, jamais remplacée

Chaque ligne consigne la version de la sérialisation sous laquelle son empreinte a été calculée, et l'empreinte de la
ligne couvre cette version. La vérification calcule l'empreinte de la charge utile telle qu'elle a été stockée et ne
sérialise jamais de nouveau : les lignes déjà écrites restent donc vérifiables, quel que soit le sérialiseur que
l'application fournira plus tard. La version existe pour le lecteur d'une charge utile, qui doit savoir dans quel
encodage elle se trouve, et pour le registre de sérialiseurs de l'application.

## Le port

```php
interface CanonicalSerializer
{
    /** @return non-empty-string stable for the life of the serializer: changing it is a new version */
    public function version(): string;

    /**
     * @param array<string, scalar|null> $fields the non-personal fields and the commitments, by name
     * @return string stable bytes: same fields, same bytes, always
     */
    public function serialize(array $fields): string;
}
```

Les champs personnels entrent seulement par les octets de leur `Commitment`, jamais en clair.

## Les décisions qu'une implémentation doit figer

- **L'ordre des champs** : triés par nom, ou une liste fixe; jamais l'ordre dans lequel le tableau arrive par hasard.
- **L'ensemble des champs** : ce qui arrive à un champ manquant, à un champ en trop, à un `null`.
- **L'encodage des scalaires** : entiers, nombres à virgule flottante (évitez-les, ou fixez leur forme textuelle),
  booléens, `null`, chaînes.
- **Les valeurs binaires** : les engagements sont des chaînes brutes de 32 octets; encodez-les sans ambiguïté (préfixe
  de longueur, hexadécimal ou base64), jamais comme du texte qu'un encodeur JSON pourrait rejeter ou rééchapper.
- **L'encodage du texte** : UTF-8, et si les chaînes sont normalisées (NFC) avant la sérialisation.
- **La délimitation** : des préfixes de longueur ou un schéma d'échappement, pour que deux ensembles de champs
  différents ne puissent jamais produire les mêmes octets.
- **La chaîne de version** elle-même.

Ne vous fiez pas aux valeurs par défaut de `json_encode()`, à `serialize()` ni à `var_export()` : leur sortie dépend
des indicateurs, des versions de PHP et de l'ordre d'insertion dans le tableau.

## Un exemple d'implémentation

Ceci illustre les propriétés ci-dessus; ce n'est pas un format recommandé.

```php
use Phil\AuditBundle\Domain\Contract\CanonicalSerializer;

final readonly class LengthPrefixedSerializerV1 implements CanonicalSerializer
{
    public function version(): string
    {
        return 'lp-v1';
    }

    public function serialize(array $fields): string
    {
        ksort($fields, SORT_STRING);
        $out = '';

        foreach ($fields as $name => $value) {
            $encoded = match (true) {
                null === $value => 'n',
                is_bool($value) => $value ? 'b1' : 'b0',
                is_int($value) => 'i'.$value,
                is_string($value) => 's'.bin2hex($value),
                default => throw new InvalidArgumentException(sprintf('Field "%s": floats are not serialized.', $name)),
            };
            $out .= strlen($name).':'.$name.strlen($encoded).':'.$encoded;
        }

        return $out;
    }
}
```

Testez-la avec des vecteurs de référence (entrée fixe, octets attendus fixes) validés à côté d'elle : une refonte qui
change un octet fait alors échouer un test avant d'atteindre une ligne ([testing.md](testing.md)).
