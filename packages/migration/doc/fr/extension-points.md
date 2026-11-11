<!-- phil-dev source-sha256=b0caf6f209651f1ff1504ccc6ca58efbb7718b81b0cb419b099277cd7e7146a5 -->
# Points d'extension

La bibliothèque ne déclare aucun service : une application branche ses deux décorateurs dans Doctrine Migrations (voir
[Intégration](integration.md)). Ce qu'elle implémente, c'est la destination des messages et le choix des migrations qui
annoncent.

## `Publisher`

`Phil\Migration\Publisher` envoie un message d'une annonce. `ChatterPublisher` l'envoie par le chatter de
`symfony/notifier`.

| Méthode | Contrat |
|---|---|
| `publish(string $transport, string $message): void` | Envoie le message, déjà coupé à la longueur du canal, sur le transport nommé ; lance une exception quand il ne le peut pas : l'annonceur la journalise et abandonne le reste de ce canal, sans faire échouer l'exécution |

Un éditeur propre à l'application envoie n'importe où ailleurs : un webhook, une liste de diffusion, un journal.

## `Announcing`

`Phil\Migration\Announcing` est une migration qui annonce. `AnnouncingMigrationFactory` lui donne les annonces de
l'exécution.

| Méthode | Contrat |
|---|---|
| `announceWith(Announcements $announcements): void` | Garde les annonces, pour que `note()` et `news()` les remplissent |

`AnnouncingMigration` est la classe de base fournie ; une classe de base propre à l'application étend
`AbstractMigration`, implémente `Announcing` et utilise le trait `Announces`.

## Branchement

| Point | Comment |
|---|---|
| Le `MigrationFactory` ou le `Migrator` propre à l'application | Enveloppé par `AnnouncingMigrationFactory` et `AnnouncingMigrator` au lieu de ceux de la fabrique de dépendances |
| Où et comment une version est annoncée | Les `Channel` donnés à `Announcer` : transport, locale, notes ou nouvelles, titres et message le plus long |
