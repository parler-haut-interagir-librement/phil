<!-- phil-dev source-sha256=979ae30160a38bb0caceb798a83ed72440360cd02e7ff63cd344e8e7f6ed6be2 -->
# Clôture et date choisie

## Par l'organisateur

```php
$closed = $polls->close($poll->id, $organizer, $slotId);   // true when this call closed it
```
L'organisateur choisit n'importe quelle date du sondage, avant ou après l'échéance ; une date d'un autre sondage lance
`InvalidSchedulingPoll`. Un sondage déjà clôturé, à la main ou à son échéance, n'est pas clôturé de nouveau : `close()`
retourne false.

## À l'échéance

Le planificateur `phil_scheduling_poll` envoie `CloseDuePolls` à la fréquence de `closing.frequency` ; son *handler*
appelle `closeDue()`, qui clôt les sondages dont l'échéance est passée, les plus en retard d'abord, `closing.batch` à la
fois, chacun sur sa meilleure date :

1. le plus de réponses oui ;
2. puis le plus de réponses au besoin ;
3. puis le début le plus hâtif.

Les dates qui ont les mêmes réponses oui et au besoin partagent leur place dans le décompte ; le début le plus hâtif les
ordonne et désigne la date. Quand personne ne peut venir à aucune date — ni oui, ni au besoin —, le sondage est clôturé
sans date et rien n'est envoyé.

Le décompte s'appuie sur le moteur de vote de `phil/poll` : les dates sont les options d'un scrutin à choix multiples
qui accepte un bulletin blanc, chaque répondant dépose le bulletin de ses réponses oui et le bulletin de ses réponses au
besoin, et le décompte des choix compte chacun. `SchedulingPolls::tally()` donne le décompte, un `SlotTally` par date,
la meilleure d'abord.

Un sondage dont l'échéance est passée et que le planificateur n'a pas encore clôturé n'accepte aucune réponse ni aucun
répondant ; sa page dit que sa date est sur le point d'être choisie.

## Une clôture, une annonce

La clôture est un seul `UPDATE` conditionnel, fait seulement tant que le sondage n'est pas clôturé. De deux clôtures au
même moment — celle de l'organisateur et celle du planificateur, ou celles de deux *workers* du planificateur —, l'une
clôt le sondage et l'autre apprend qu'elle ne l'a pas fait. La clôture gagnante, et elle seule :

- met en file `AnnounceChosenDate`, qui ne porte que l'identifiant du sondage ;
- répartit l'événement `DateChosen` — sondage, date, qui l'a clôturé —, ou `ClosedWithoutDate`.

Le planificateur a un état, dans le groupe de cache de `schedule.cache`, afin qu'un *worker* redémarré n'exécute pas
deux fois les messages manqués, et il tient un verrou de `schedule.lock_factory` ; les *handlers* sont tout de même
idempotents.

## Le courriel et son fichier iCalendar

Le *handler* d'`AnnounceChosenDate` met en file un `SendChosenDate` — les identifiants du sondage et du répondant, rien
d'autre — par répondant qui a répondu et n'a pas été révoqué. Le *handler* de `SendChosenDate`, dans le *worker* :

1. lit l'adresse : celle que le répondant a donnée, sinon celle que `RespondentAddresses` trouve pour son compte ;
2. réclame l'avis par un seul `UPDATE` conditionnel, de sorte que deux *workers*, ou un message traité deux fois, ne
   l'envoient qu'une fois ;
3. construit le courriel et l'envoie.

Un répondant sans adresse, révoqué ou qui n'a jamais répondu ne reçoit rien. Un courriel qui échoue rend l'avis avant
que l'exception se propage, afin que Messenger réessaie le message. Aucune adresse, aucun nom ni aucun jeton ne
traverse la file d'attente.

Le courriel est dans la langue du répondant, la date écrite par ICU dans son fuseau horaire, et porte `event.ics` : un
événement dans un calendrier de la RFC 5545, écrit par l'écrivain de `phil/calendar-sync`, son début et sa fin en UTC,
le titre, la description et le lieu du sondage, et l'identifiant `phil-scheduling-poll-<poll>`, le même pour chaque
répondant, de sorte qu'un calendrier qui l'importe deux fois ne garde qu'un événement. Ses mots viennent du domaine de
traduction `phil_scheduling_poll`.
