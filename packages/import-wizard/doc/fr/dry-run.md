<!-- phil-dev source-sha256=6f084bd126225aa2b6d543678074b6b9213e65f9247423032e303f0581856d59 -->
# L'essai à blanc

Rien n'est importé sans essai à blanc : `Importer::commit()` prend un `ImportPlan`, et seul `ImportPlanner::plan()` en
fait un. Le plan est ce que la personne voit avant de confirmer, et ce qui s'écrit ensuite.

## Planifier

```php
$plan = new ImportPlanner()->plan($source, $profile, $target);
```

Le planificateur lit chaque ligne de la source, en sautant les lignes vides, et pour chacune :

1. lit chaque cellule en correspondance avec sa colonne — type, longueur, choix, obligatoire ;
2. refuse une ligne qui a plus de cellules que l'en-tête, sauf si les cellules en trop sont vides, et une ligne mal
   formée ;
3. exécute les règles du profil sur une ligne dont toutes les cellules ont été lues ;
4. refuse une ligne dont la clé naturelle appartient à une ligne antérieure du fichier ;
5. résout ses références, dans le fichier ou par la recherche de la cible ;
6. décide : mettre à jour quand la cible a un enregistrement de cette clé, créer sinon.

Les recherches se font une fois par clé, par tranches d'au plus 500 clés par appel. Le planificateur n'écrit rien et ne
garde aucun état entre deux appels.

## Le plan

| Membre | Contient |
|---|---|
| `rows` | les `PlannedRow` à écrire, dans l'ordre où elles s'écrivent : chaque ligne après les lignes du fichier auxquelles elle se réfère |
| `rejections` | les `RowRejection`, dans l'ordre du fichier : la ligne, les cellules telles qu'écrites, les `CellError` |
| `mapping` | la correspondance utilisée, à garder dans le profil |
| `headers` | les en-têtes du fichier |
| `fingerprint` | un SHA-256 du profil, de la correspondance, des en-têtes, de la ligne, de l'action et des valeurs de chaque ligne, et de chaque refus |
| `creates()`, `updates()`, `refusals()` | les décomptes |
| `preview()` | chaque ligne qui n'est pas vide, dans l'ordre du fichier, sous forme de `PreviewLine` : ligne, action, erreurs |
| `describe($error)` | la phrase anglaise d'une erreur, avec l'en-tête de sa colonne |

Une `PlannedRow` contient sa ligne physique, son action, sa `NaturalKey`, les valeurs des colonnes en correspondance
par nom, la ligne de la ligne du fichier vers laquelle pointe chacune de ses références (`dependsOn`), et ses cellules
telles qu'écrites.

## Les raisons

Une `CellError` nomme sa colonne — null pour la ligne entière —, sa `Reason`, un détail qui n'est jamais une valeur du
fichier, et la ligne de l'autre ligne en cause. La valeur d'une `Reason` est un code stable qu'une application traduit ;
`message()` est la phrase anglaise que porte le fichier des lignes refusées.

| Code | Quand |
|---|---|
| `required` | une cellule vide dans une colonne obligatoire |
| `not-an-integer`, `not-a-decimal`, `not-a-boolean`, `not-a-date`, `not-an-email` | une cellule que son type ne lit pas |
| `too-long` | un texte plus long que ne le permet sa colonne ; le détail donne la longueur |
| `not-allowed` | une valeur hors des choix ; le détail les liste |
| `too-many-cells` | plus de cellules que l'en-tête, non vides |
| `malformed-row` | du texte après un guillemet fermant |
| `duplicate-key` | la clé d'une ligne antérieure, dont la ligne est donnée |
| `self-reference` | une ligne qui se réfère à elle-même |
| `unknown-reference` | une référence introuvable dans le fichier comme par la recherche ; le détail nomme le type |
| `reference-cycle` | une ligne d'un cycle de références, avec la ligne suivante du cycle |
| `reference-rejected` | une référence à une ligne du fichier qui est refusée, dont la ligne est donnée |
| `date-order` | une date de fin antérieure à sa date de début ; le détail nomme la colonne de début |
| `refused-by-target` | une ligne que la cible a refusée, avec les propres mots de la cible |

## Écrire

```php
$result = new Importer(batchSize: 500)->commit($plan, $target, previewed: $fingerprint);
```

L'importeur remet les lignes à la cible par lots d'au plus `batchSize`, dans l'ordre du plan. Un lot ne contient jamais
une ligne et une ligne à laquelle elle se réfère : le lot se ferme avant une telle ligne, afin que la cible ait écrit,
et accepté, l'enregistrement visé. Une ligne que la cible refuse est signalée avec ses erreurs, et une ligne qui s'y
réfère est refusée avec `reference-rejected` sans atteindre la cible.

Entre l'aperçu et la confirmation, les enregistrements que détient la cible peuvent changer. L'application garde
l'empreinte qu'elle a montrée, planifie de nouveau le fichier à la confirmation, et passe cette empreinte comme
`previewed` : un plan qui diffère lance `PlanChanged` et rien n'est écrit, de sorte que la personne revoit l'aperçu. Une
cible d'un autre type lance aussi `PlanChanged`.

`ImportResult` contient le nombre d'enregistrements créés et mis à jour, le nombre de lots, et chaque refus — ceux du
plan, ceux de la cible et ceux qui en ont découlé — dans l'ordre du fichier.

## Le fichier des lignes refusées

```php
$csv = RejectedRowsFile::csv($plan, $result->rejections);
```

Le fichier contient, pour chaque ligne refusée, sa ligne dans le fichier importé (`import_line`), ses erreurs
(`import_errors`, chacune sous la forme `Header: reason`, séparées par ` | `), puis ses cellules sous les en-têtes du
fichier. La personne corrige les cellules et importe de nouveau le fichier avec le même profil : les deux premières
colonnes ne correspondent à aucune colonne et sont ignorées. Sans `$result->rejections`, le fichier contient les refus
du plan.

```csv
import_line,import_errors,Email,First name,Last name
3,Email: is not an email address | First name: is required,nope,,Lee
5,Row: repeats the key of another row (line 2),ana@example.org,Ana,Lee
```

## Renseignements personnels

Une ligne ne contient que ce que contient son fichier. La bibliothèque ne journalise rien ; les messages de ses
exceptions et les détails de ses erreurs nomment une colonne, une ligne, une limite ou un type, jamais la valeur d'une
cellule. L'empreinte est un hachage, gardé à côté de l'aperçu plutôt que du plan. Un fichier de lignes refusées et un
aperçu contiennent les données du fichier : l'application ne les donne qu'à la personne qui l'a importé.
