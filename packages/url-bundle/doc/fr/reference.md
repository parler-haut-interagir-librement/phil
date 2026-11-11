<!-- phil-dev source-sha256=f803d08279846ffcc0e53b4b343ceb95f0e1b7d44b193bc0924421516b433be5 -->
# Référence

| Type | Nature | Rôle |
|---|---|---|
| `PhilUrlBundle` | `final class`, `AbstractBundle` | Alias `phil_url` ; le type de formulaire, le validateur, l'extension Twig, le type de colonne |
| `Application\AcceptedUrlTypes` | `final class` | Les genres acceptés en ensembles d'enums |
| `Infrastructure\Form\TypedUrlType` | type de formulaire | Un seul champ, son genre détecté |
| `Infrastructure\Form\TypedUrlTransformer` | transformateur de données | Entre un `TypedUrl` et le texte du champ |
| `Infrastructure\Validator\UrlOfType`, `UrlOfTypeValidator` | contrainte et validateur | Une adresse d'un genre accepté |
| `Infrastructure\Doctrine\UrlColumnType` | type DBAL `phil_url` | Un `Url` dans un `VARCHAR(2048)` |
| `Infrastructure\Twig\UrlExtension` | extension Twig | `url_display`, `url_type()`, `url_type_label` |
