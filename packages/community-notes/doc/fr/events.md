<!-- phil-dev source-sha256=f418f42406b2ff201515ea053c36168d86083785aeeba72023ae58ac54da33b3 -->
# Événements et messages

## Les événements

`CommunityNotes` diffuse un événement après chaque action, par le répartiteur PSR-14 qu'on lui donne, quand il y en a un.
Chaque événement transporte l'identifiant de la note, et rien d'autre : ni l'auteur, ni l'évaluateur, ni le verdict.

| Événement | Diffusé quand |
|---|---|
| `NoteWritten` | Une note a été rédigée. |
| `NoteRated` | Une note a été évaluée, ou évaluée de nouveau. |
| `NoteStatusChanged` | Une décision a changé l'état d'une note : publiée, dépubliée, décidée non utile. |
| `NoteWithdrawn` | Son auteur a retiré une note. |
| `NoteErased` | Une note a été effacée avec les données de son auteur. |

Tous sont dans `Phil\CommunityNotes\Event`. Un écouteur qui a besoin de plus lit la note avec `find()` ; celui qui
prévient l'auteur que sa note est publiée écoute `NoteStatusChanged` et lit l'état.

## Décider de nouveau en arrière-plan

Le message `Phil\CommunityNotes\Message\ReconsiderNote` nomme une note à décider de nouveau ; `ReconsiderNoteHandler` la
décide selon la règle du `CommunityNotes` qu'on lui donne, et ignore une note effacée entre-temps. Avec Symfony
Messenger, le *handler* est enregistré sur le bus, et une commande ou un écouteur envoie un message par note après un
changement de la règle ou l'effacement d'un membre :

```php
$bus->dispatch(new ReconsiderNote($noteId));
```

Le message est court et ne contient aucun renseignement personnel, si bien qu'une file d'attente ne garde rien de plus
que l'identifiant.
