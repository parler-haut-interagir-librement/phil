<!-- phil-dev source-sha256=3e462ef3530dd448a9803f947918795b094b3116f25dcb04f7c75390fa7ce9b1 -->
# Formulaires et messages

## Le formulaire de réservation

```php
$form = $this->createForm(ReservationType::class, new ReservationDraft(), ['resource' => $room]);
$form->handleRequest($request);

if ($form->isSubmitted() && $form->isValid()) {
    $draft = $form->getData();
    $reservations->hold($room->id, $draft->start, $draft->end, $draft->quantity, $accountId);
}
```

`ReservationType` remplit un `ReservationDraft` : un début et une fin à l'heure de la ressource, et les unités
demandées quand la ressource en contient plus d'une. Une fois soumis, il vérifie la demande comme le fait
`Reservations::check()`, et chaque raison pour laquelle elle serait refusée devient une erreur du formulaire ; une fin
qui ne suit pas le début est une erreur. Le formulaire ne réserve rien : entre la vérification et la retenue, une autre
demande peut prendre les unités ; le contrôleur intercepte donc toujours `ReservationRefused`.

| Option | Valeur par défaut | Sens |
|---|---|---|
| `resource` | obligatoire | La `BookableResource` réservée |
| `replacing` | `null` | L'identifiant de la réservation que le formulaire déplace, écartée des conflits |

Les libellés sont des messages du domaine `phil_reservation` : `form.start`, `form.end`, `form.quantity`.

## Les raisons en mots

`ViolationMessages` écrit les raisons d'une décision dans la langue de la personne qui lit, à partir des messages
`violation.<code>` du domaine `phil_reservation`, au format ICU avec les paramètres de la violation :

```php
$messages->messages($refused->decision);         // ['This time is already booked.']
$messages->message($violation, 'fr');            // 'Ce moment est déjà réservé.'
```

Le bundle est fourni en anglais et en français ; une application ajoute une langue, ou reformule un message, dans ses
propres `translations/phil_reservation+intl-icu.<locale>.yaml`.
