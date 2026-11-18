<!-- phil-dev source-sha256=4e267bc602e0360572be4e556ed0ddb4d515bcb3e3d53fb633a5dd33c708bc40 -->
# Points d'extension

La bibliothèque est en `0.x` : ses points d'extension ne sont stables qu'à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Pour | Faites |
|---|---|
| Accepter un type à vous | Ajoutez un `BlockType` à un `BlockTypeRegistry` et donnez-le à `Normalizer` ; voir [Normalisation](normalization.md) |
| Exiger davantage d'un type avant la publication | `Property::filled()`, un champ `Bounded`, ou le crochet `publication` de `BlockType` ; voir [Normalisation](normalization.md#les-niveaux-de-validation) |
| Borner un champ à vous | Implémentez `Contract\Sized` et enveloppez le champ dans `Bounded` |
| Accepter un réglage à vous | Ajoutez un `TuneType` à un `TuneRegistry` |
| Rendre un type autrement, ou rendre un nouveau type | Implémentez `Html\BlockRenderer` et passez-le à `HtmlRenderer::with()` ; voir [Rendu HTML](html-rendering.md) |
| Colorer les blocs de code sur le serveur | `HtmlRenderer::withCodeHighlighter()` |
| Dessiner les blocs à partir du balisage qu'ils gardent | `HtmlRenderer::withStoredMarkup()`, avec une fermeture qui vérifie la clé |
| Repasser sur le HTML produit | Implémentez `Html\HtmlFilter` et passez-le à `HtmlRenderer::withFilter()` |
| Convertir un type en texte autrement | Remplacez sa conversion dans `PlainText` ; voir [Texte brut](plain-text.md) |
| Assainir le HTML en ligne avec d'autres règles | Construisez un `InlineSanitizer` avec vos propres profils, par nom, et donnez-le à `HtmlRenderer::default()` |
| Borner le HTML en ligne lu | Le `maxBytes` de `InlineSanitizer`, 4 Mio par défaut ; donnez l'assainisseur au `Normalizer`, à `HtmlRenderer::default()` et à `PlainText` |
