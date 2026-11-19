<!-- phil-dev source-sha256=1a2b18e4276ea180894e328b1543bcb39d56c3c18a27c8096d998815f0f76405 -->
# Assiduité

L'assiduité d'un participant est une liste de **séjours**, `Attendance\Interval` : quand il est arrivé et quand il est
reparti, ou null tant qu'il est encore dans la salle. Les séjours viennent des notifications — `ParticipantJoined` en
ouvre un, `ParticipantLeft` le termine — ou de `presences()` interrogée à intervalles : un participant vu pour la
première fois est arrivé à ce moment, un participant qu'on ne voit plus est reparti à ce moment. L'application les
conserve ; phil/virtual-classroom-bundle le fait.

`AttendanceSheet` les compte par rapport à une séance, de son début à sa fin :

```php
use Phil\VirtualClassroom\Attendance\AttendanceSheet;

$seconds = AttendanceSheet::seconds($stays, $start, $end);
$percent = AttendanceSheet::percent($stays, $start, $end);
```

- Un séjour est tronqué à la séance ; un séjour encore ouvert se termine avec elle.
- Les séjours qui se chevauchent comptent une seule fois : un participant sur deux appareils, ou le même séjour
  rapporté par deux sources.
- La part est le nombre de secondes suivies sur le nombre de secondes de la séance, en pourcentages entiers arrondis à
  la baisse, de sorte que 74,9 % ne franchisse jamais un seuil de 75. Une séance de durée nulle est suivie à 0 %.

Les instants des événements sont en UTC. L'instant d'une notification est lu en secondes ou en millisecondes — à partir
de 10^11, des millisecondes —, sous forme de nombre ou de chiffres, ou de date RFC 3339 ; un instant avant 2000 ou
après 2200 n'est pas un instant.
