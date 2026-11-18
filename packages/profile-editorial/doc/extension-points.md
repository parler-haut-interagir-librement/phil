# Extension points

The profile has no code, so it has no extension points of its own: an application changes the behaviour in the
members, each through its configuration, its ports and its events.

| To | See |
|---|---|
| Add a format, or change a purification level | The configuration `phil_markup` and the readers of `phil/markup` |
| Add a citation style | The contract `CitationStyle` of `phil/citations` |
| Change how notes are numbered or placed | `NoteOptions` of `phil/footnotes-sidenotes` |
| Change the catalogue of warnings, or keep preferences elsewhere | The configuration and the port `WarningPreferencesStore` of `phil/content-warnings-bundle` |
| Add nodes to the graph of a page | `NodeCollector` and the providers of `phil/structured-data-bundle` |
| Leave out a member | Require the others directly: see [Upgrading](upgrading.md) |

The members are at `0.x`: their extension points are stable only from their 1.0 releases.
