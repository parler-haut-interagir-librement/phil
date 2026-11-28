<!-- phil-dev source-sha256=063d3f604f6471ce37afc1fb5546989a2f26ed866514c3eb2d68979840790925 -->
# Configuration

La configuration du bundle se trouve sous `phil_measurement` :

```yaml
# config/packages/phil_measurement.yaml
phil_measurement:
    units:
        mass:
            units:
                '[gr]': { factor: '0.00006479891', cldr: mass-grain }
        paper-weight:
            standard: g/m2
            units:
                g/m2: { factor: '1' }
                '[lb_av]/[ream]': { factor: '1.48' }
    channels:
        storefront: { mass: kg, length: cm }
        marketplace: { mass: '[lb_av]', length: '[in_i]' }
    doctrine_type: true
```

| Option | Valeur par défaut | Effet |
|---|---|---|
| `units` | `{}` | Familles et unités ajoutées à celles de la bibliothèque, par nom de famille, comme les lit `UnitRegistry::withDefinitions()`. |
| `units.<family>.standard` | `null` | L'unité standard d'une nouvelle famille. |
| `units.<family>.cldr` | `null` | La catégorie CLDR d'une nouvelle famille, pour ses préférences. |
| `units.<family>.difference` | `null` | La famille de différences d'une nouvelle famille affine. |
| `units.<family>.units.<code>.factor` | obligatoire | Le facteur vers l'unité standard, un nombre décimal ou une fraction comme `'5/9'`, sous forme de chaîne. |
| `units.<family>.units.<code>.offset` | `null` | Le décalage d'une échelle de température. |
| `units.<family>.units.<code>.reciprocal` | `false` | Une unité qui varie dans l'autre sens. |
| `units.<family>.units.<code>.ucum` | `null` | Son code d'échange, quand il diffère de son code. |
| `units.<family>.units.<code>.cldr` | `null` | Sa clé CLDR, pour ses motifs et ses préférences. |
| `units.<family>.units.<code>.difference` | `null` | Son unité de différences, dans une famille affine. |
| `channels` | `{}` | L'unité dans laquelle chaque canal de vente affiche une famille, par canal puis par famille. |
| `doctrine_type` | `true` | Enregistre le type de colonne `phil_measurement` auprès de Doctrine DBAL, quand il est installé. |

Les codes d'unités et les noms de familles sont conservés tels qu'écrits : un code comme `[lb_av]` est entre
guillemets en YAML, et un tiret n'est jamais changé en trait de soulignement. Les facteurs sont des chaînes, afin que
YAML ne les lise jamais comme des nombres à virgule flottante.

Les définitions et les canaux sont vérifiés à la construction du conteneur : une unité définie deux fois, une nouvelle
famille sans son unité standard, ou l'unité d'un canal hors de sa famille fait échouer la construction avec la raison,
plutôt qu'une requête.

Le document « Units and families » de `phil/measurement` énumère les unités qu'il déclare et le sens de chaque clé
d'une définition.
