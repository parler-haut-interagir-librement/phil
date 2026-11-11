<!-- phil-dev source-sha256=68a649abed0c4871fb3d1370a89acb3334da8fff286133b827ed5e86d56785f5 -->
# Conservation

Une ressource mise à la corbeille est gardée `retention` jours (ou ceux de son type, dans `retention_by_kind`),
comptés à partir du moment de sa mise à la corbeille ; `TrashItem::purgeAfter()` dit jusqu'à quand.

```bash
bin/console phil:trash:purge --limit=500   # every night, from the scheduler
```

`Trash::purge($limit)` prend d'abord les éléments expirés les plus anciens, demande au gestionnaire de chacun d'oublier
ce qu'il a laissé à l'extérieur (des fichiers), puis le supprime. Un élément dont le type n'a plus de gestionnaire est
supprimé quand même.

## Événements

| Événement | Quand | Ce qu'il porte |
|---|---|---|
| `ResourceTrashed` | Une ressource est allée à la corbeille | Le `TrashItem` |
| `ResourceRestored` | Elle est revenue | Le `TrashItem` et la ressource |
| `TrashItemPurged` | Elle est partie pour de bon | Le `TrashItem` |

Ils sont déclenchés par le répartiteur d'événements de l'application, quand il y en a un : une piste d'audit les
écoute.
