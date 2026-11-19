<!-- phil-dev source-sha256=42be39182c26a0b4560feb090587d61a9d68d20cf0b2611b1556807f27267db5 -->
# Sécurité et vie privée

## Ce que la bibliothèque garde d'un membre

| Donnée | Gardée par | Personnelle |
|---|---|---|
| Une habitude : son membre, son nom, son horaire, son fuseau horaire, son premier jour, sa cible, son dernier jour | `HabitStore` | L'identifiant du membre ; le nom qu'il a donné à l'habitude, qui peut en dire beaucoup sur lui |
| Une validation : son habitude, son jour, son statut, sa valeur et son instant | `CheckInStore` | Ce que le membre a fait, jour après jour |

Un membre est désigné par un identifiant opaque et stable de l'application — l'UUID d'un utilisateur —, jamais par une
adresse courriel : `Habit` refuse un identifiant qui contient une `@`. Les événements portent les identifiants et les
nombres atteints, jamais le nom d'une habitude ni une valeur validée, de sorte qu'une file d'attente ou un journal qui
les garde ne contient rien de plus.

Les habitudes peuvent concerner la santé, la foi ou le rétablissement. Traitez le nom d'une habitude et ses validations
comme sensibles : montrez-les à leur seul membre, sauf s'il en décide autrement.

## Accès et effacement

`Habits::ofMember($member)` liste les habitudes d'un membre, archivées comprises, et `history()` leurs validations :
leur part d'une demande d'accès. `forgetMember($member)` oublie chaque habitude d'un membre et ses validations, et
renvoie le nombre d'habitudes oubliées ; `remove($id)` oublie une habitude.

## Des bornes sur chaque entrée

| Entrée | Borne |
|---|---|
| Un identifiant d'habitude ou de membre | De 1 à 255 caractères ASCII visibles |
| Le nom d'une habitude | De 1 à 200 caractères, sans espaces autour ni caractères de contrôle |
| Une cible | De 2 à 1 000 000, ou aucune |
| La valeur d'une validation | De 0 à 1 000 000 000 |
| Une règle d'horaire | 64 octets ; des nombres de trois chiffres au plus, puis les bornes de chaque sorte |
| Un jour | `YYYY-MM-DD`, de 0001-01-01 à 9999-12-31 |
| Revenir en arrière | De 0 à 366 jours, sept par défaut ; jamais après aujourd'hui |
| Un gel | 366 jours de long, finissant au plus 366 jours plus loin |
| Les jalons | 50 longueurs de série et 50 forces |
| Un nom de table | 63 lettres minuscules, chiffres ou `_` |

Le travail d'une lecture est borné par l'âge de l'habitude : un seul passage sur ses périodes. Les analyseurs de règles
et de jours sont soumis au fuzzing. Les instructions SQL prennent chaque valeur en paramètre ; les noms de tables sont
vérifiés avant d'être écrits dans une instruction.
