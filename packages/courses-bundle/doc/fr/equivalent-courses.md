<!-- phil-dev source-sha256=ff374043b005b9486d068e4c4b0fac62bd0421a72bb7a078663ec186b1b483a3 -->
# Cours équivalents

Une même formation donnée en plusieurs langues est un groupe de cours équivalents : un cours par langue, chacun avec son
propre contenu. Un apprenant suit le cours de sa langue, et terminer un cours du groupe les termine tous. Un cours dont
le contenu est traduit à l'intérieur d'un seul cours est une autre fonctionnalité, que le bundle n'offre pas.

## Les groupes

`Equivalences` écrit et lit les groupes :

```php
use Phil\CoursesBundle\Application\Equivalences;

$group = $equivalences->create('Fire safety');
$equivalences->add($group->id, $english->id, 'en');
$equivalences->add($group->id, $french->id, 'fr-CA');

$equivalences->equivalents($english->id);   // [$french->id]
$equivalences->remove($french->id);         // the course stays, in no group
$equivalences->dissolve($group->id);        // the courses stay, each on its own
```

Une langue est une étiquette de la forme de BCP 47 — une langue principale de 2 à 8 lettres, puis des sous-étiquettes de
1 à 8 lettres ou chiffres —, de 35 caractères au plus, conservée en minuscules avec `-` : `fr_CA`, `fr-CA` et `FR-ca`
désignent une même langue.

| Règle | Refus |
|---|---|
| Un cours appartient à un groupe au plus | `InvalidCourse` : `The course "…" is already in another group of equivalent courses.` |
| Un groupe contient un cours par langue | `InvalidCourse` : `The group already holds a course in "fr".` |
| Un groupe contient 50 cours au plus | `InvalidCourse` |
| Une langue est une étiquette | `InvalidCourse` |
| Le groupe et le cours existent | `UnknownRecord` |

Ajouter un cours déjà dans le groupe change sa langue. Retirer un cours le retire de son groupe.

## Le cours de la langue de l'apprenant

`courseFor(CourseId $course, ?string $language)` donne le cours du groupe dans cette langue : le cours de cette étiquette
exacte, sinon le premier cours de sa langue principale — `fr-BE` trouve `fr`, et `fr` trouve `fr-ca` —, sinon le cours
demandé. Un cours dans aucun groupe, sans langue, ou dont la langue n'est pas une étiquette donne le cours demandé.

Le plan d'un cours liste les autres cours de son groupe, chaque lien portant sa langue dans `hreflang` et `lang`. La
route `phil_courses_language`, `GET /{course}/language` sous le préfixe des pages, redirige l'apprenant vers le cours de
la locale de la requête ; une application y fait un lien partout où elle nomme le cours, et fixe la locale de ses
requêtes comme elle le fait pour ses pages — depuis le profil de l'apprenant, ou depuis `Accept-Language` avec
`framework.set_locale_from_accept_language`.

## Un achèvement reconnu dans tout le groupe

`Completions::completion()` répond, pour un cours, par l'achèvement de l'apprenant lui-même, sinon par le plus ancien
achèvement d'un cours équivalent ; son `course` nomme le cours qu'il a suivi. Ce qui le lit suit :

| Partie | Ce que le groupe change |
|---|---|
| [Parcours d'apprentissage](learning-paths.md) | Une étape est terminée par un cours équivalent, et ouvre les étapes qui l'attendent |
| [Formation obligatoire](mandatory-training.md#cours-équivalents) | Une obligation est remplie par un cours équivalent, terminé avant ou après l'assignation |
| [Crédits](credits-and-transcripts.md#le-relevé) | Un groupe est crédité une fois par cycle ; un cours sans crédits propres accorde ceux de son groupe |
| phil/certificates-bundle | Un cours de certificat d'une langue est décerné à l'achèvement d'un autre |

Un achèvement déjà reconnu n'est pas défait quand un cours quitte son groupe ou que le groupe est dissous.
