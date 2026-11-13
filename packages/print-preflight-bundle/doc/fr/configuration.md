<!-- phil-dev source-sha256=f7c763b6ad23453777e427a78fea56d5aa254279f85a1817d80a02f5038f6c41 -->
# Configuration

```yaml
# config/packages/phil_print_preflight.yaml
phil_print_preflight:
    default_profile: booklet
    profiles:
        booklet:
            trim_sizes: [a5]
            bleed: 3mm
            safety_margin: 6mm
            minimum_pages: 8
            maximum_pages: 64
            page_multiple: 4
            severities: { page-count.multiple: warning }
        poster:
            trim_sizes: [a4, 297x210mm]
            bleed: 0.125in
            colour_workflow: rgb
            ignore: [colour.spot]
```

`default_profile` est le profil utilisé quand une vérification n'en nomme aucun ; par défaut, le premier profil. Sans
aucun profil, il en existe un, nommé `default`, avec les valeurs par défaut de la bibliothèque.

| Option d'un profil | Par défaut | Règle |
|---|---|---|
| `trim_sizes` | `[]` | Les formats rognés qu'imprime l'imprimeur, chaque orientation à part ; vide pour tous |
| `uniform_trim_size` | `true` | Chaque page a le format rogné de la première page |
| `bleed` | `3mm` | Le fond perdu que doit porter chaque page |
| `safety_margin` | `5mm` | La distance que garde le contenu à l'intérieur du bord de coupe |
| `minimum_resolution` | `150` | Les pixels par pouce sous lesquels une image est une erreur |
| `target_resolution` | `300` | Les pixels par pouce sous lesquels une image est un avertissement |
| `maximum_resolution` | `null` | Les pixels par pouce au-dessus desquels une image est une remarque |
| `minimum_pages`, `maximum_pages` | `1`, `null` | Le plus petit et le plus grand nombre de pages que relie l'imprimeur |
| `page_multiple` | `1` | 2 pour le recto verso d'une feuille, 4 pour la piqûre à cheval |
| `colour_workflow` | `cmyk` | `cmyk`, ou `rgb` pour les imprimeurs qui convertissent eux-mêmes |
| `allow_spot_colours` | `false` | Si l'imprimeur tire des plaques de tons directs |
| `maximum_ink_coverage` | `300` | La couverture d'encre totale, en pour cent, au-dessus de laquelle une image ou un aplat est une erreur, dans un flux CMJN |
| `minimum_line_width` | `0.25pt` | Le filet le plus fin que l'imprimeur reproduit ; un filet plus fin est un avertissement |
| `allow_overprint` | `true` | Si l'imprimeur respecte la surimpression |
| `allow_transparency` | `true` | Si l'imprimeur accepte la transparence active, comme PDF/X-4 |
| `severities` | `{}` | `error`, `warning` ou `notice` par code de règle, à la place de la gravité par défaut |
| `ignore` | `[]` | Les codes des règles à ne pas signaler |

Les règles et leurs codes sont ceux de `phil/print-preflight` (ses `doc/profiles.md` et `doc/checks.md`).

## Longueurs et formats

Une longueur est un nombre et une unité : `mm`, `in`, `pt` ou `px` (pixels CSS, 96 au pouce) ; `0` n'en demande
aucune. Un format est `a4`, `a5`, `letter`, ou une largeur et une hauteur avec une seule unité, `148x210mm` ou
`8.5 x 11 in`. Les deux sont lus par `Lengths`, sans tenir compte de la casse ni des espaces.

Chaque profil est construit une seule fois, à la construction du conteneur : une longueur que le bundle ne sait pas
lire, un profil que refuse la bibliothèque — une résolution cible sous le minimum, un multiple de pages de 0 — ou un
profil par défaut inconnu lève alors `InvalidConfigurationException`, et non à la première vérification.

## Services

| Service | Public | Fourni |
|---|---|---|
| `Phil\PrintPreflightBundle\Application\PrintProfiles` | oui | Toujours |
| `Phil\PrintPreflightBundle\Application\Preflighter` | oui | Toujours |
| `Phil\PrintPreflightBundle\Application\MeasuredDocument` | oui | Toujours, avec le `FontResolver` quand il y en a un |
| `phil:print-preflight:check` | | Toujours |
| `Phil\PrintPreflightBundle\Application\Port\FontResolver` | non | Un alias de `CatalogueFontResolver` quand `phil/fonts-bundle` est activé |
| `Phil\PrintPreflightBundle\Infrastructure\Media\MediaImages` | oui | Quand `phil/media-bundle` est installé |

`PdfLayout` n'est pas un service : il enveloppe un document du moteur natif, et c'est donc l'application qui le crée.
