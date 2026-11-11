<!-- phil-dev source-sha256=228131018fa4848fb9e960d5719cac010296d122ae0e8e63a03b93644251e570 -->
# Configuration

```yaml
phil_console:
    timestamps:
        enabled: false        # true: every command writes the time without --timestamps
        format: iso           # iso, the moment to the millisecond, or relative, the seconds since the start
    summary: verbose          # always, verbose (with -v) or never
    debug:
        enabled: true         # false removes --debug-phil
        channels: []          # the channels --debug-phil prints when it names none; empty for every channel
```

| Option | Valeur par défaut | Effet |
|---|---|---|
| `timestamps.enabled` | `false` | Écrit l'heure devant chaque ligne de chaque commande, comme si `--timestamps` était fourni |
| `timestamps.format` | `iso` | Le format de l'heure lorsque `--timestamps` n'en nomme aucun |
| `summary` | `verbose` | Le moment où le sommaire de la durée et de la mémoire est écrit |
| `debug.enabled` | `true` | Indique si `--debug-phil` existe ; l'option exige Monolog et au moins un canal |
| `debug.channels` | `[]` | Les canaux de `--debug-phil` sans valeur |

Un format ou un mode de sommaire inconnu, ou un nom de canal vide, fait échouer la construction du conteneur.

L'environnement du noyau auquel les gardes se comparent est `%kernel.environment%`.
