<!-- phil-dev source-sha256=eec89c5282525655828a9b131f5b726cfb2b0dff13394193a0021a02749cb290 -->
# Points d'extension

Le profil ne contient aucun code, il n'a donc aucun point d'extension propre : une application change le comportement
dans les membres, chacun par sa configuration, ses ports et ses événements.

| Pour | Voir |
|---|---|
| Ajouter un format, ou changer un niveau de purification | La configuration `phil_markup` et les lecteurs de `phil/markup` |
| Ajouter un style de citation | Le contrat `CitationStyle` de `phil/citations` |
| Changer la numérotation ou le placement des notes | `NoteOptions` de `phil/footnotes-sidenotes` |
| Changer le catalogue d'avertissements, ou conserver les préférences ailleurs | La configuration et le port `WarningPreferencesStore` de `phil/content-warnings-bundle` |
| Ajouter des nœuds au graphe d'une page | `NodeCollector` et les fournisseurs de `phil/structured-data-bundle` |
| Se passer d'un membre | Exiger directement les autres : voir [Mise à niveau](upgrading.md) |

Les membres sont en `0.x` : leurs points d'extension ne sont stables qu'à partir de leurs versions 1.0.
