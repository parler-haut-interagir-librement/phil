<!-- phil-dev source-sha256=71e16914d20d1bce0adc4152d2e03f1303a89ccd841422cdc2b82cea3fb4a923 -->
# Assiduité et enregistrements

## Séjours

Un **séjour** est le temps d'un participant dans une salle : quand il est arrivé, et quand il est reparti, ou null
tant qu'il y est. Le magasin garde une ligne par séjour, nommée par la salle, le participant et le moment de son
arrivée.

- Une arrivée alors que le participant est dans la salle, ou à l'intérieur d'un séjour déjà connu — la même arrivée
  rapportée par la notification et par `sync()`, un second appareil —, n'en ouvre aucun.
- Un départ termine le plus récent séjour ouvert du participant qui avait commencé à ce moment ; sans séjour ouvert,
  il n'en termine aucun.
- Quand une salle se termine, chaque séjour ouvert se termine avec elle. Un séjour rapporté après la fin — l'assiduité
  que BigBlueButton poste une fois la séance finie — est conservé, et un séjour encore ouvert se termine à la fin de la
  salle.

`ParticipantArrived` et `ParticipantDeparted` sont distribués pour chaque séjour ouvert ou terminé ; `AttendanceUpdated`
quand la salle se termine, et quand les séjours d'une salle terminée changent.

## Le rapport d'une séance

```php
foreach ($classrooms->attendance($room->id) as $report) {
    $report->participant;   // ParticipantId
    $report->stays;         // list<Interval>
    $report->seconds;       // within the session, overlaps counted once
    $report->percent;       // whole percents, rounded down
}
```

La séance va de l'ouverture de la salle — ou de sa première arrivée, quand la salle ne s'est jamais ouverte par
l'application — à sa fin, ou à maintenant tant qu'elle dure. Le calcul est celui d'`AttendanceSheet`, de
phil/virtual-classroom.

## Enregistrements

Un enregistrement est conservé une fois par identifiant et par format ; un enregistrement connu de nouveau met à jour
son adresse. `recordings()` donne ceux d'une salle ; `RecordingAvailable` est distribué pour chaque nouveau. Un
enregistrement JaaS porte un lien de téléchargement qui fonctionne un jour, `expiresAt` : copiez le fichier dans le
stockage de l'application à la réception de `RecordingAvailable` s'il doit rester.
