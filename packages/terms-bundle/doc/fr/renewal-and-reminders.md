<!-- phil-dev source-sha256=07c3019a68f328698cfd90bc31180d0250bb965f4e3b4c30f7d266b64944e69d -->
# Renouvellement et rappels

## Renouvellement

Une politique dotée de `renew_after_days` redemande son acceptation une fois ce nombre de jours écoulé depuis la
dernière acceptation, par le membre, de la version exigée ou d'une version plus récente. La porte envoie alors le
membre vers la page d'acceptation comme pour une nouvelle version ; la nouvelle acceptation s'ajoute aux précédentes.

## Les avis d'une nouvelle version

Quand `notifications` est activé, `phil/notifications-bundle` transmet les avis. Le bundle déclare les deux types, avec
les canaux `site` et `email`, et fournit leur titre et leur corps dans le domaine `phil_notifications`, en anglais et
en français. `published_type` et `reminder_type` les renomment ; sous un autre nom, l'application écrit les clés de ce
type. L'application modifie un type comme n'importe quel autre :

```yaml
phil_notifications:
    types:
        phil_terms.reminder: { channels: [email] }
```

La publication d'une version importante envoie `phil_terms.published` à l'audience `phil_terms:<policy>`.
`OutstandingAudience` la traduit en membres qui n'ont pas encore accepté la dernière version importante, au moment où
l'avis est livré plutôt qu'au moment où il est envoyé. Chaque avis porte sur le sujet `phil_terms_policy:<policy>` et
porte le numéro de version comme donnée `version` : des identifiants seulement, que les gabarits de l'application
transforment en mots et en lien.

## Rappels

Quand `reminders` est activé, l'horaire `phil_terms` répartit `SendTermsReminders` à la fréquence
`reminders.frequency`, exécuté par `messenger:consume scheduler_phil_terms`. Pour chaque politique requise, le
*handler* rappelle aux membres qui ne seront pas en règle dans `reminders.days_ahead` jours — ils n'auront pas accepté
la version exigée à ce moment, une version entrée en vigueur d'ici là, ou leur acceptation expirera d'ici là — la
version alors en vigueur, 500 membres à la fois.

Les rappels et les avis passent par un `TermsNotifier` : celui de `notifications`, ou le service que nomme `notifier`,
qui peut les envoyer de toute autre façon.

## Qui est un membre

Les avis, les rappels et `phil:terms:outstanding` lisent les membres dans la `MemberSource` que nomme la configuration.
Sans elle, les membres sont ceux qui ont accepté une version de n'importe quelle politique : un membre qui n'a jamais
rien accepté n'en fait pas partie. Une application qui doit joindre chaque membre nomme donc sa propre source.
