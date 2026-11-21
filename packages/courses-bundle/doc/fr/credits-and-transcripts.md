<!-- phil-dev source-sha256=aa9f92472207cec70c67d237771ddf7196f76d66bc93df19a992783c55b945c8 -->
# Crédits et relevés

Un cours peut accorder des crédits — des unités de formation continue ou des heures — à son achèvement. Chaque
achèvement entre dans le relevé de l'apprenant, avec les crédits que le cours accorde à ce moment et l'assignation
qu'il a remplie, et le relevé additionne les crédits par unité. Le relevé est une page, du JSON et du CSV, pour
l'apprenant et pour les gestionnaires.

## Les crédits

```php
$transcripts->credit($course->id, Credits::of('1.5', CreditUnit::Ceu));   // CreditUnit::Ceu or CreditUnit::Hours
$transcripts->credit($course->id, null);                                  // none
```

```sh
php bin/console phil:courses:credits <course> 1.5 --unit=ceu
php bin/console phil:courses:credits <course> 0
```

Un montant a deux décimales au plus, de 0,01 à 9 999,99, conservé en centièmes pour que les sommes restent exactes. Un
changement s'applique aux achèvements qui suivent ; ceux qui sont entrés avant gardent leurs crédits.

## Le relevé

`TrainingCompletions` écoute `CourseCompleted` : il termine les obligations de l'apprenant sous le cours, puis inscrit
l'achèvement dans son relevé — le cours, son titre à ce moment, pour que la ligne reste lisible une fois le cours
renommé ou retiré, le moment, les crédits, et l'assignation et le cycle qu'il a remplis. Un cours recertifié et terminé
de nouveau entre de nouveau. `CreditsGranted` est envoyée avec chaque entrée.

Avec les [groupes de cours équivalents](equivalent-courses.md), un groupe est crédité une fois par cycle : un cours qui
n'accorde aucun crédit lui-même accorde ceux du premier cours de son groupe, par langue, qui en accorde ; et un
achèvement qui ne remplit aucune obligation alors que le relevé de l'apprenant contient déjà un cours équivalent entre
dans le relevé sans crédits. Un achèvement qui remplit le cycle suivant d'une recertification est crédité de nouveau.

`Transcripts::of(LearnerId $learner)` donne un `Transcript` : ses entrées, la plus récente d'abord ;
`total(CreditUnit $unit)`, les centièmes de cette unité ; `toArray()` ; et `csv()`.

| Route | Chemin avec la recette | Qui | Quoi |
|---|---|---|---|
| `phil_courses_transcript` | `/courses/transcript` | l'apprenant connecté | Son relevé en page, avec les totaux |
| `phil_courses_transcript` | `/courses/transcript.json` | l'apprenant connecté | Le même en JSON |
| `phil_courses_transcript` | `/courses/transcript.csv` | l'apprenant connecté | Le même en CSV, en pièce jointe |
| `phil_courses_admin_transcript` | `/admin/courses/transcripts/{learner}`, `/json`, `/csv` | un gestionnaire | Le relevé de n'importe quel apprenant |

Le JSON contient l'apprenant, les entrées et les totaux :

```json
{
    "learner": "member-42",
    "entries": [
        {"course": "01J…", "title": "Fire safety", "completed_at": "2026-11-22T10:00:00+00:00", "credits": "1.5", "unit": "ceu", "assignment": "01J…", "cycle": 1}
    ],
    "totals": {"ceu": "1.5"}
}
```

Le CSV suit la RFC 4180, avec les colonnes `course`, `title`, `completed_at`, `credits`, `unit`, `assignment` et `cycle`.
Une cellule qu'un tableur lirait comme une formule — qui commence par `=`, `+`, `-`, `@`, une tabulation ou un retour
chariot — est précédée d'une apostrophe. Chaque réponse est `no-store` ; le CSV est envoyé comme `text/csv` avec
`nosniff`.

## Vie privée

Avec phil/privacy-bundle installé, le module `courses_training` fournit dans une demande d'accès les obligations de
l'apprenant — l'assignation, le cours, le cycle, les moments de l'assignation, l'échéance, l'achèvement et le
renouvellement, et les rappels envoyés — et son relevé avec ses totaux.

Un effacement supprime les obligations et retire le nom de la personne des assignations qu'elle a faites, qui restent
non signées. Le relevé est supprimé aussi, à moins que l'application ne garde les relevés en vertu d'une obligation
légale, comme la formation continue l'exige souvent : avec `phil_courses.training.keep_transcripts: true`, le relevé
survit et le rapport le dit.

| Magasin | Effacement |
|---|---|
| `phil_course_obligation` | `deleted` |
| `phil_course_assignment` | `redacted` : le nom de la personne qui a assigné est retiré |
| `phil_course_transcript` | `deleted`, ou `survives` avec `keep_transcripts` |

L'application déclare les relevés dans son calendrier de conservation, avec la durée que son obligation fixe :

```php
new RetentionRule(
    'courses.transcripts',
    'phil_course_transcript',
    [DataCategory::fromString('education')],
    RetentionPeriod::fixed('P7Y'),
    Mechanism::Manual,
    ErasureEffect::Survives,
    AccessResponse::Returned,
    new Processing('Continuing education records the law asks the organization to keep.'),
);
```

Le relevé nomme l'apprenant par l'identifiant stable sous lequel les cours le connaissent, jamais par un nom ou une
adresse de courriel ; le CSV et le JSON ne portent rien de plus.
