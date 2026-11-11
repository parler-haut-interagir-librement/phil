<!-- phil-dev source-sha256=b9f3dbbd6a68a32f9c2a581777c3496243a2c2ecdc760a9f2ebf930d0bf74c98 -->
# Exceptions

| Exception | Lancée quand |
|---|---|
| `Phil\MarkupBundle\Infrastructure\Format\UnavailableFormat` | Un filtre, le convertisseur ou `MarkupFormats::format()` reçoit la demande d'un format inconnu ou absent de `formats` |
| `Phil\Markup\Exception\InvalidSource` | Une source UJO donnée à `markup_html`, à `markup_ujo` ou au convertisseur n'est pas valide |
| `Phil\Ujo\Exception\InvalidDocument` | Le convertisseur ne peut pas écrire un document en JSON |
| `ValueError` | Un niveau donné par sa valeur n'est pas `basic`, `forum` ou `all` |

`UnavailableFormat` étend `InvalidArgumentException` et implémente `Phil\Markup\Exception\MarkupException`, de sorte
qu'attraper les exceptions de la bibliothèque l'attrape aussi. Son message nomme le format et les formats activés :

```text
The format "html" is not one of the enabled formats: markdown, bbcode, text.
```

Une configuration qui nomme un format ou un niveau inconnu, un format par défaut non activé, ou un format ajouté dont le
nom est déjà pris ou n'est pas un nom valide, est refusée à la construction du conteneur, avec
l'`InvalidConfigurationException` de Symfony.
