<!-- phil-dev source-sha256=3d93713baa68cccf8e171de4341ae0a8636d4757f603dffa3ae87fe4342259cb -->
# Formats

## Le format de synonymes de Solr

`SolrSynonymsReader::read($text, 'fr')` lit le format texte que Solr et de nombreux moteurs chargent ;
`SolrSynonymsWriter` le réécrit.

```text
# Synonyms of the games
jdr, jeu de rôle
donjon => donjon, dungeon
tic\, tac\, toe, morpion
```

| Ligne | Lue comme |
|---|---|
| Des expressions séparées par des virgules | Une règle d'équivalence |
| Des expressions, `=>`, des expressions | Une règle à sens unique |
| Commençant par `#` | Un commentaire |
| Vide | Rien |

Une barre oblique inverse rend le caractère suivant ordinaire : `\,` une virgule, `\=>` une flèche, `\\` une barre
oblique inverse, `\#` un `#` au début d'une ligne ; tout autre caractère échappé reste lui-même. Les espaces autour
d'une expression ne comptent pas. Une marque d'ordre des octets UTF-8 et les fins de ligne de tout système sont lues.

Un fichier fait 1 Mio au plus et une ligne 4096 octets ; un fichier qui n'est pas de l'UTF-8 valide est refusé, et une
ligne qui n'est pas une règle — une expression vide, deux flèches, une barre oblique inverse à sa fin, une règle hors
des [limites](rules.md#phrases) — est refusée avec `InvalidSynonyms`, dont `fileLine` contient le numéro, à partir
de 1.

L'écrivain commence par un commentaire qui nomme la langue, écrit une règle par ligne et échappe ce que le lecteur lirait
autrement : ce qu'il écrit est relu comme les mêmes règles.

## JSON

`JsonSynonyms` lit et écrit un document adapté à une exportation, à une importation ou à une administration qui modifie
les règles dans un formulaire :

```json
{
    "language": "fr",
    "rules": [
        {"synonyms": ["jdr", "jeu de rôle"]},
        {"from": ["donjon"], "to": ["donjon", "dungeon"]}
    ]
}
```

Une règle est des expressions équivalentes sous `synonyms`, ou une règle à sens unique avec `from` et `to`. Un document
fait 1 Mio au plus et quatre niveaux de profondeur ; un document qui n'a pas cette forme est refusé avec
`InvalidSynonyms`, qui nomme la position de sa règle à partir de 1. L'écrivain met en forme avec indentation, et laisse
les barres obliques et les accents tels quels.
