<!-- phil-dev source-sha256=24c644105e9279e700d03212bc41b50bc7bd5ea5a7a035fb6b640fc9b4eabfba -->
# Points d'extension

La bibliothèque en est à ses versions `0.x` : les ports, les objets-valeurs et les exceptions peuvent changer dans une
version mineure, ce que dit le journal des changements. Ils sont destinés à être stables à partir de la version `1.0`.

## Ports

| Port | Implémenté par | Rôle |
|---|---|---|
| `Contract\ImportTarget` | l'application ou le bundle qui possède les enregistrements | écrit les lots d'un type, répond aux recherches |
| `Contract\KeyLookup` | les mêmes, à travers `ImportTarget` | dit quelles clés naturelles d'un type existent |
| `Contract\RowSource` | `CsvRowSource`, ou un adaptateur | donne l'en-tête et les lignes d'un fichier, avec leurs lignes physiques |
| `Contract\RowRule` | `DateOrder`, ou l'application | vérifie ensemble les cellules d'une ligne |

## La cible

Une cible écrit un type importable. `existing()` retourne, parmi les clés qu'on lui donne, celles des enregistrements
d'un type qui existent — son propre type pour les créations et les mises à jour, les types des références pour elles.
`write()` crée et met à jour les lignes d'un lot dans leur ordre, et retourne les erreurs des lignes qu'elle a
refusées, par ligne ; leurs mots ne répètent jamais une valeur de la ligne.

```php
use Phil\ImportWizard\Contract\ImportTarget;
use Phil\ImportWizard\Import\Batch;
use Phil\ImportWizard\Plan\CellError;
use Phil\ImportWizard\Plan\NaturalKey;
use Phil\ImportWizard\Plan\RowAction;

final readonly class UnitTarget implements ImportTarget
{
    public function __construct(private UnitRepository $units)
    {
    }

    public function type(): string
    {
        return 'organisational-unit';
    }

    public function existing(string $type, array $keys): array
    {
        $codes = array_map(static fn (NaturalKey $key): string => $key->parts[0], $keys);
        $found = $this->units->existingCodes($codes); // one query for the chunk

        return array_values(array_filter($keys, static fn (NaturalKey $key): bool => \in_array($key->parts[0], $found, true)));
    }

    public function write(Batch $batch): array
    {
        $errors = [];

        foreach ($batch->rows as $row) {
            if ($this->units->isArchived($row->key->parts[0])) {
                $errors[$row->line] = [CellError::refusedByTarget('code', 'the unit is archived')];

                continue;
            }

            RowAction::Create === $row->action
                ? $this->units->create($row->values)
                : $this->units->update($row->key->parts[0], $row->values); // only the columns $row->has()
        }

        $this->units->flush();

        return $errors;
    }
}
```

Une cible écrit un lot dans une seule transaction quand elle le peut. L'importeur l'appelle depuis la requête ou le
*worker* qui confirme l'importation ; une longue importation est confiée à un *worker* par l'application, avec
l'identifiant du fichier stocké et celui de l'empreinte confirmée, jamais avec les lignes.

## Les cibles à écrire

Ces cibles appartiennent aux bundles qui possèdent les enregistrements, et s'écrivent là :

- **Cours** — les personnes (`LmsProfiles::people()`) et les inscriptions (`LmsProfiles::enrolments()`) : `existing()`
  répond aux clés `person` et `course` ; `write()` crée les comptes des personnes et leurs inscriptions, avec le rôle
  et les dates.
- **Organisations** — les unités (`LmsProfiles::organisationalUnits()`) : `existing()` répond aux clés
  `organisational-unit` ; `write()` crée et déplace les unités, un parent toujours écrit dans un lot antérieur à celui
  de ses enfants ; le `unit_code` d'une personne reçoit sa réponse des mêmes clés.

- **Questions** — les questions d'une évaluation et d'un sondage : leurs profils et leurs cibles sont écrits dans les
  paquets qui les possèdent, comme le dit [Fichiers de questions](question-files.md).

## Sources

`CsvRowSource` lit le CSV. Un adaptateur lit tout autre format — une feuille d'un classeur, des lignes venues d'une
API — en implémentant `RowSource` : `headers()` retourne les cellules d'en-tête sans espaces de bordure, `rows()` les
`SourceRow` qui suivent, chacune avec la ligne physique que la personne cherchera, lignes vides comprises, dans
l'ordre. Le planificateur lit les lignes une seule fois.

## Règles

Une règle implémente `RowRule` : `check()` reçoit les valeurs d'une ligne par colonne — null pour une cellule vide — et
retourne ses `CellError` ; `name()`, `toArray()` et `fromArray()` la sérialisent avec le profil. Un profil relu avec
`ImportProfile::fromArray($data, ruleTypes: [MyRule::class])` la connaît.

## Options

- `Column` : type, obligatoire, en-tête, alias, longueur, choix, référence.
- `ImportProfile` : type, colonnes, clé naturelle, règles, correspondance.
- `CsvDialect` : délimiteur, guillemet, encodage, marque d'ordre des octets, protection des formules.
- `SourceLimits` : octets, lignes, cellules par ligne.
- `Importer` : la taille d'un lot.
