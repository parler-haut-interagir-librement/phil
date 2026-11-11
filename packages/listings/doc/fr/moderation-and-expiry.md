<!-- phil-dev source-sha256=eb60d181434d9605b157738d87ff2c809cf627367027f117b4a732317e26c3bd -->
# Modération et expiration

## Modération avant ou après publication

Le `moderation` d'un genre vaut `Moderation::BeforePublication` ou `Moderation::AfterPublication`.

- **Avant publication** : `submit()` envoie une annonce à `pending_review`, et personne d'autre ne la voit jusqu'à ce
  qu'un modérateur appelle `approve()`, qui la publie, ou `reject()`, qui la renvoie à son auteur avec un
  `ModerationReason`. Une annonce publiée que son auteur modifie retourne à `pending_review`, en gardant son expiration.
- **Après publication** : `submit()` publie une annonce tout de suite. Les membres signalent ce qui ne devrait pas s'y
  trouver.

Un modérateur peut retirer pour de bon, avec `remove()`, une annonce en attente, publiée, masquée ou expirée, avec son
motif. Les motifs sont des clés (`spam`, `off_topic`, `unsafe`) que l'application traduit et liste.

## Signalements

`report($policy, $reporter, $at)` compte le signalement d'un membre sur une annonce visible ; l'auteur ne peut pas
signaler la sienne, et une annonce qui n'est pas visible ne peut pas être signalée. Le second signalement d'un même
membre ne change rien. Le signalement qui porte le nombre de signaleurs distincts au `reportsToHide` du genre rend
l'annonce `hidden`, quel que soit le genre de modération : elle quitte l'affichage jusqu'à ce qu'un modérateur la
restaure avec `approve()`, qui efface les signalements et garde son expiration tant que celle-ci est à venir, ou la
retire avec `remove()`.

Les signalements eux-mêmes (leur motif, leur texte, la file qu'un modérateur consulte) appartiennent à l'application, ou
à un bundle de modération : l'annonce compte les signaleurs dont elle a besoin pour décider quand se masquer.

## Expiration

Une annonce est publiée pendant les `lifetimeDays` du genre à partir de sa publication ; une fois `expiresAt` dépassé,
`isVisibleAt()` est faux. `at($at)` change une annonce publiée dont l'expiration est passée en annonce expirée, pour un
planificateur qui tient les statuts à jour ; une annonce expirée garde tout le reste.

## Rappels

`needsReminderAt($policy, $at)` est vrai quand l'auteur devrait recevoir un rappel pour prolonger : l'annonce est
visible, elle peut encore être prolongée, `$at` se trouve dans les `reminderDays` du genre avant son expiration, et
aucun rappel n'a été envoyé depuis sa dernière prolongation. L'application envoie le rappel, une notification qui porte
l'identifiant de l'annonce, puis enregistre `markReminded($at)`. Un genre avec `reminderDays: 0` n'envoie jamais de
rappel. Dans cette esquisse, `$store` est le stockage des annonces de l'application et `RemindToExtend` son message :

```php
foreach ($store->publishedExpiringBefore($now->modify('+7 days')) as $listing) {
    if ($listing->needsReminderAt($policies->get($listing->kind), $now)) {
        $bus->dispatch(new RemindToExtend($listing->id->value));
        $store->save($listing->markReminded($now));
    }
}
```

## Prolongations

`extend($policy, $at)` ajoute les `extensionDays` du genre à une annonce publiée ou expirée, comptés à partir de son
expiration ou de `$at`, selon la date la plus tardive : une prolongation hâtive ne perd aucun jour, une prolongation
tardive part d'aujourd'hui. Une annonce expirée est publiée de nouveau. Chaque prolongation compte dans le
`maxExtensions` du genre ; au-delà, `extend()` lance `ForbiddenTransition`, et l'auteur rédige une nouvelle annonce. Le
rappel redevient dû avant la nouvelle expiration.
