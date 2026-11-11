<!-- phil-dev source-sha256=8c6dc8d001dacf9bff194cf9125e12cb35cb00593af3dc1fddc78bd4dd578333 -->
# Interroger

| Méthode | Donne |
|---|---|
| `isOpenAt($moment)` | Si le lieu est ouvert, en comptant les plages de la veille qui ferment après minuit |
| `nextOpening($moment)` | La prochaine ouverture du lieu après le moment, dans son fuseau horaire ; `null` quand il n'ouvre pas d'ici un an |
| `hoursOn($day)` | Le `DayHours` d'un jour du calendrier : ses heures spéciales, ou celles de son jour de la semaine |
| `specialOn($day)` | Les `SpecialHours` d'un jour, ou `null` |
| `on(DayOfWeek)` | Les heures d'un jour de la semaine |
| `groups()` | La semaine à partir du lundi, en suites de jours consécutifs qui ont les mêmes heures, pour un tableau |
| `isAlwaysClosed()` | Si aucun jour de la semaine n'ouvre |

Un moment est converti dans le fuseau horaire du lieu avant d'être lu : demander à `14:30 UTC` pour une boutique de
Toronto revient à demander pour `09:30` là-bas. Pendant que le lieu est ouvert, `nextOpening()` donne sa prochaine
ouverture, pas l'ouverture en cours.

`nextOpening()` lit chaque période spéciale une seule fois, pour les jours de l'année à venir qu'elle couvre, puis
parcourt les jours sans formater de date pour chacun. Un lieu que la semaine garde fermé n'est examiné que pour ses
jours spéciaux : un lieu toujours fermé sans heures spéciales répond donc `null` tout de suite.
