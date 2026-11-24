# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| To | Do |
|---|---|
| Send the date to a respondent who joined with an account | Implement `Application\Contract\RespondentAddresses` and name the service in `respondent_addresses` |
| Keep polls and respondents elsewhere | Alias `Domain\Contract\SchedulingPollRepository` and `RespondentRepository` to your stores; each write that changes a state is conditional, as the interfaces say |
| React to the date chosen | Listen to `Application\Event\DateChosen`, or `ClosedWithoutDate`, dispatched once by the closing that won |
| Send the mail otherwise | Replace the handler of `Application\Message\SendChosenDate`, or decorate `Infrastructure\Mailer\ChosenDateMail` |
| Change the words | Override the domain `phil_scheduling_poll` in `translations/` |
| Change a page | Embed a template and replace its blocks, or override it under `templates/bundles/PhilSchedulingPollBundle/` |
| Celebrate the date chosen | Fill the `celebration` block, or listen to the DOM event `scheduling-poll:chosen` |
| Have the organizer's pages | Write them in the application, on `SchedulingPolls` and `SchedulingPollViews` |
