<!-- phil-dev source-sha256=1d8d948a91ce51855b63f4f64102903ec16faf09ef8279fa6fe1ef5a4a2c92b8 -->
# Référence

| Type | Genre | Rôle |
|---|---|---|
| `PhilInstantMessengerBundle` | `final class`, `AbstractBundle` | Alias `phil_instant_messenger` ; le type de formulaire, le validateur, l'extension Twig |
| `Application\AcceptedMessengers` | `final class` | Les services acceptés sous forme d'ensembles d'enums, et sous forme de choix |
| `Infrastructure\Form\HandleType` | type de formulaire | Le service et l'identifiant |
| `Infrastructure\Form\HandleTransformer` | transformateur de données | Entre un `Handle` et les deux champs |
| `Infrastructure\Validator\HandleOfType`, `HandleOfTypeValidator` | contrainte et validateur | Un identifiant sur un service accepté |
| `Infrastructure\Twig\MessengerExtension` | extension Twig | `messenger_display`, `messenger_link`, `messenger_label` |
