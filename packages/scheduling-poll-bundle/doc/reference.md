# Reference

Every public type of the package, with its role.

| Type | Role |
|---|---|
| `PhilSchedulingPollBundle` | The bundle: its configuration `phil_scheduling_poll`, the Doctrine mapping, its services. |
| `Application\SchedulingPolls` | Creates polls, enrolls respondents, opens links, records answers, revokes, closes, counts, erases. |
| `Application\SchedulingPollViews` | The respondent's sheet and the organizer's table, as the poll lets each see the answers. |
| `Application\SchedulingPollDraft` | What a new poll is made of. |
| `Application\ProposedSlot` | A date a new poll proposes: a start and an end. |
| `Application\RespondentAccess` | What a link opened: the respondent, the poll, its slots, whether the answers can change. |
| `Application\AnswerForm` | Reads the answers and the time zone the form posts. |
| `Application\ChosenDateCalendar` | The date chosen as an iCalendar file. |
| `Application\NoRespondentAddresses` | The default `RespondentAddresses`: no account has an address. |
| `Application\Contract\RespondentAddresses` | Port: the address of a respondent who joined with an account. |
| `Application\View\AnswerSheet` | The respondent's page. |
| `Application\View\ResultsView` | The answers of all, or a sentence while hidden. |
| `Application\View\SlotLine` | A date as a page shows it, with the viewer's answer and its counts. |
| `Application\View\ResponseRow` | One respondent's answers in the table. |
| `Application\Event\DateChosen` | A poll was closed on a date. |
| `Application\Event\ClosedWithoutDate` | A poll reached its deadline and nobody could come. |
| `Application\Message\AnnounceChosenDate` | Message: announce the date of a poll. |
| `Application\Message\SendChosenDate` | Message: send the date to one respondent. |
| `Application\Message\CloseDuePolls` | Message of the schedule: close the polls past their deadline. |
| `Application\Message\EraseRespondents` | Message of the schedule: erase the names and addresses past their delay. |
| `Application\Exception\AnswerRefused` | A link that opens nothing, a poll closed or full. |
| `Application\Exception\NotFound` | No poll, or no respondent, of this identifier. |
| `Application\Exception\NotOrganizer` | Only the organizer may do this. |
| `Domain\Availability` | Yes, if need be, no. |
| `Domain\ClosedBy` | The organizer or the deadline. |
| `Domain\Model\SchedulingPoll` | The poll, mapped for Doctrine. |
| `Domain\Model\Slot` | A date of a poll, mapped for Doctrine. |
| `Domain\Model\Respondent` | A respondent and their answers, mapped for Doctrine. |
| `Domain\Contract\SchedulingPollRepository` | Port: where the polls and their slots are kept. |
| `Domain\Contract\RespondentRepository` | Port: where the respondents are kept. |
| `Domain\Tally\AvailabilityCount` | The count on `phil/poll`, and its tie-break. |
| `Domain\Tally\SlotTally` | What one date gathered, and its place. |
| `Domain\Exception\SchedulingPollException` | Every exception of the bundle. |
| `Domain\Exception\InvalidSchedulingPoll` | A value out of bounds. |
| `Infrastructure\Doctrine\DoctrineSchedulingPollRepository`, `DoctrineRespondentRepository` | The stores on Doctrine. |
| `Infrastructure\Doctrine\InvitationSchemaListener` | Adds the tables of the links to the generated schema. |
| `Infrastructure\Memory\InMemorySchedulingPollRepository`, `InMemoryRespondentRepository` | The stores in memory. |
| `Infrastructure\Messenger\AnnounceChosenDateHandler`, `SendChosenDateHandler`, `CloseDuePollsHandler`, `EraseRespondentsHandler` | The handlers of the messages. |
| `Infrastructure\Mailer\ChosenDateMail` | Builds the mail of the date chosen. |
| `Infrastructure\Scheduler\SchedulingPollSchedule` | The schedule `phil_scheduling_poll`. |
| `Infrastructure\Privacy\SchedulingPollPrivacy` | The export and the erasure through `phil/privacy-bundle`. |
| `Infrastructure\Twig\SchedulingPollTwig` | The functions of the templates. |
| `Infrastructure\Http\AnswerController` | The pages of a respondent. |
| `Infrastructure\DependencyInjection\SchedulingPollServices`, `KitSubdirectoryPass`, `OptionalServicesPass` | The services, the name of the kit's recipes, the services left out without their collaborators. |
