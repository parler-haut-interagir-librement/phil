<!-- phil-dev source-sha256=a6213bcd63ce71d05cd9ddd84a9d76b322f4d46371dd80eac93a8d2e43860c20 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension ne sont stables qu'à partir de sa version 1.0. D'ici là, une version
mineure peut les changer, et son CHANGELOG dit comment.

| Pour | Faites |
|---|---|
| Envoyer la date à un répondant qui s'est joint avec un compte | Implémentez `Application\Contract\RespondentAddresses` et nommez le service dans `respondent_addresses` |
| Conserver les sondages et les répondants ailleurs | Faites des alias de `Domain\Contract\SchedulingPollRepository` et de `RespondentRepository` vers vos dépôts ; chaque écriture qui change un état est conditionnelle, comme le disent les interfaces |
| Réagir à la date choisie | Écoutez `Application\Event\DateChosen`, ou `ClosedWithoutDate`, répartis une seule fois par la clôture gagnante |
| Envoyer le courriel autrement | Remplacez le *handler* de `Application\Message\SendChosenDate`, ou décorez `Infrastructure\Mailer\ChosenDateMail` |
| Changer les mots | Remplacez le domaine `phil_scheduling_poll` dans `translations/` |
| Changer une page | Intégrez un gabarit et remplacez ses blocs, ou remplacez-le sous `templates/bundles/PhilSchedulingPollBundle/` |
| Célébrer la date choisie | Remplissez le bloc `celebration`, ou écoutez l'événement DOM `scheduling-poll:chosen` |
| Avoir les pages de l'organisateur | Écrivez-les dans l'application, sur `SchedulingPolls` et `SchedulingPollViews` |
