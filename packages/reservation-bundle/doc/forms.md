# Forms and messages

## The reservation form

```php
$form = $this->createForm(ReservationType::class, new ReservationDraft(), ['resource' => $room]);
$form->handleRequest($request);

if ($form->isSubmitted() && $form->isValid()) {
    $draft = $form->getData();
    $reservations->hold($room->id, $draft->start, $draft->end, $draft->quantity, $accountId);
}
```

`ReservationType` fills a `ReservationDraft`: a start and an end on the resource's clock, and the units asked for when
the resource holds more than one. Once submitted, it checks the request as `Reservations::check()` does, and each reason
it would be refused becomes an error of the form; an end that does not follow the start is one error. The form books
nothing: between the check and the hold another request may take the units, so the controller still catches
`ReservationRefused`.

| Option | Default | Meaning |
|---|---|---|
| `resource` | required | The `BookableResource` booked |
| `replacing` | `null` | The identifier of the reservation the form moves, left out of the conflicts |

The labels are messages of the `phil_reservation` domain: `form.start`, `form.end`, `form.quantity`.

## The reasons in words

`ViolationMessages` writes the reasons of a decision in the reader's language, from the messages
`violation.<code>` of the `phil_reservation` domain, in ICU format with the violation's parameters:

```php
$messages->messages($refused->decision);         // ['This time is already booked.']
$messages->message($violation, 'fr');            // 'Ce moment est déjà réservé.'
```

The bundle ships English and French; an application adds a language, or rewords a message, in its own
`translations/phil_reservation+intl-icu.<locale>.yaml`.
