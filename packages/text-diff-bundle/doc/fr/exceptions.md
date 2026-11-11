<!-- phil-dev source-sha256=42bdcb5304b437140d792b6411793825ddc1e8b7351520810d1c767611db1084 -->
# Exceptions

Le bundle n'a aucune exception propre.

| Quand | Ce qui est levé |
|---|---|
| Le conteneur est construit avec un `max_edits` négatif ou un `tokenizer` inconnu | `Symfony\Component\Config\Definition\Exception\InvalidConfigurationException` |
| `text_diff` reçoit un nom de découpeur autre que `words`, `lines`, `paragraphs` ou `html` | `InvalidArgumentException` |
| Un texte comparé n'est pas de l'UTF-8 valide | `Phil\TextDiff\Exception\InvalidText` |
| `text_diff_document` reçoit du JSON trop grand, qui n'est pas du JSON, ou qui n'est pas un document | `Phil\Ujo\Exception\InvalidJson` ou `Phil\Ujo\Exception\InvalidDocument` |
| Un bloc d'un document a un type que le moteur de rendu ne connaît pas | `Phil\Ujo\Exception\UnknownBlockType` |

Dans un gabarit, Twig les enveloppe dans une `Twig\Error\RuntimeError` qui nomme le gabarit et la ligne.
