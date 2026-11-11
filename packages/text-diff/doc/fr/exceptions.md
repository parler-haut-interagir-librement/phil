<!-- phil-dev source-sha256=168ecaaa89873cc5b6308a4de5d6cb98841bba02dd77481361d7fecbd93623cb -->
# Exceptions

Chaque exception implémente `Phil\TextDiff\Exception\TextDiffException`, et chacune étend
`InvalidArgumentException`.

| Exception | Levée quand |
|---|---|
| `InvalidText` | un texte donné à `compare()`, `words()`, `lines()`, `paragraphs()` ou `html()` — ou un champ de texte d'un document — n'est pas de l'UTF-8 valide |
| `InvalidConfiguration` | `TextDiff` est construit avec une borne de modifications négative |

Les messages nomment ce qui ne va pas :

```
The text cannot be compared: it is not valid UTF-8.
The number of edits searched for cannot be negative; got -1.
```

`documentToHtml()` laisse passer `Phil\Ujo\Exception\UnknownBlockType` de `phil/ujo` quand le moteur de rendu n'a rien
pour le type d'un bloc. La comparaison de documents ne lève jamais d'exception à cause de leur structure : deux valeurs
`Document` quelconques peuvent être comparées.
