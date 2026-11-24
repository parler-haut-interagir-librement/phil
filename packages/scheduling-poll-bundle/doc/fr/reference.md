<!-- phil-dev source-sha256=6de124aca7aff335cf8bbc79f830738bdbea5ef2434fe796897439fcf6dea835 -->
# Référence

Chaque type public du paquet, avec son rôle.

| Type | Rôle |
|---|---|
| `PhilSchedulingPollBundle` | Le bundle : sa configuration `phil_scheduling_poll`, la correspondance Doctrine, ses services. |
| `Application\SchedulingPolls` | Crée les sondages, inscrit les répondants, ouvre les liens, enregistre les réponses, révoque, clôt, compte, efface. |
| `Application\SchedulingPollViews` | La feuille du répondant et le tableau de l'organisateur, selon ce que le sondage laisse voir des réponses. |
| `Application\SchedulingPollDraft` | De quoi est fait un nouveau sondage. |
| `Application\ProposedSlot` | Une date qu'un nouveau sondage propose : un début et une fin. |
| `Application\RespondentAccess` | Ce qu'un lien a ouvert : le répondant, le sondage, ses plages, et si les réponses peuvent changer. |
| `Application\AnswerForm` | Lit les réponses et le fuseau horaire que le formulaire soumet. |
| `Application\ChosenDateCalendar` | La date choisie sous forme de fichier iCalendar. |
| `Application\NoRespondentAddresses` | Le `RespondentAddresses` par défaut : aucun compte n'a d'adresse. |
| `Application\Contract\RespondentAddresses` | Port : l'adresse d'un répondant qui s'est joint avec un compte. |
| `Application\View\AnswerSheet` | La page du répondant. |
| `Application\View\ResultsView` | Les réponses de tous, ou une phrase tant qu'elles sont cachées. |
| `Application\View\SlotLine` | Une date telle qu'une page la montre, avec la réponse du lecteur et ses décomptes. |
| `Application\View\ResponseRow` | Les réponses d'un répondant dans le tableau. |
| `Application\Event\DateChosen` | Un sondage a été clôturé sur une date. |
| `Application\Event\ClosedWithoutDate` | Un sondage a atteint son échéance et personne ne pouvait venir. |
| `Application\Message\AnnounceChosenDate` | Message : annoncer la date d'un sondage. |
| `Application\Message\SendChosenDate` | Message : envoyer la date à un répondant. |
| `Application\Message\CloseDuePolls` | Message du planificateur : clôturer les sondages dont l'échéance est passée. |
| `Application\Message\EraseRespondents` | Message du planificateur : effacer les noms et les adresses dont le délai est passé. |
| `Application\Exception\AnswerRefused` | Un lien qui n'ouvre rien, un sondage clôturé ou plein. |
| `Application\Exception\NotFound` | Aucun sondage, ou aucun répondant, de cet identifiant. |
| `Application\Exception\NotOrganizer` | Seul l'organisateur peut le faire. |
| `Domain\Availability` | Oui, au besoin, non. |
| `Domain\ClosedBy` | L'organisateur ou l'échéance. |
| `Domain\Model\SchedulingPoll` | Le sondage, avec sa correspondance Doctrine. |
| `Domain\Model\Slot` | Une date d'un sondage, avec sa correspondance Doctrine. |
| `Domain\Model\Respondent` | Un répondant et ses réponses, avec sa correspondance Doctrine. |
| `Domain\Contract\SchedulingPollRepository` | Port : où sont conservés les sondages et leurs plages. |
| `Domain\Contract\RespondentRepository` | Port : où sont conservés les répondants. |
| `Domain\Tally\AvailabilityCount` | Le décompte sur `phil/poll`, et son bris d'égalité. |
| `Domain\Tally\SlotTally` | Ce qu'une date a rassemblé, et sa place. |
| `Domain\Exception\SchedulingPollException` | Chaque exception du bundle. |
| `Domain\Exception\InvalidSchedulingPoll` | Une valeur hors limites. |
| `Infrastructure\Doctrine\DoctrineSchedulingPollRepository`, `DoctrineRespondentRepository` | Les dépôts sur Doctrine. |
| `Infrastructure\Doctrine\InvitationSchemaListener` | Ajoute les tables des liens au schéma généré. |
| `Infrastructure\Memory\InMemorySchedulingPollRepository`, `InMemoryRespondentRepository` | Les dépôts en mémoire. |
| `Infrastructure\Messenger\AnnounceChosenDateHandler`, `SendChosenDateHandler`, `CloseDuePollsHandler`, `EraseRespondentsHandler` | Les *handlers* des messages. |
| `Infrastructure\Mailer\ChosenDateMail` | Construit le courriel de la date choisie. |
| `Infrastructure\Scheduler\SchedulingPollSchedule` | Le calendrier `phil_scheduling_poll`. |
| `Infrastructure\Privacy\SchedulingPollPrivacy` | L'export et l'effacement par `phil/privacy-bundle`. |
| `Infrastructure\Twig\SchedulingPollTwig` | Les fonctions des gabarits. |
| `Infrastructure\Http\AnswerController` | Les pages d'un répondant. |
| `Infrastructure\DependencyInjection\SchedulingPollServices`, `KitSubdirectoryPass`, `OptionalServicesPass` | Les services, le nom des recettes du kit, les services omis sans leurs collaborateurs. |
