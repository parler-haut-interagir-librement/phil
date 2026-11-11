<!-- phil-dev source-sha256=874e21be21de9b05a5c000cad93068e0bea5c01b9a2d983ef091c040672971db -->
# Référence

| Type | Genre | Rôle |
|---|---|---|
| `MessengerType` | interface, pour les enums | Un service : la lecture, l'affichage et le lien de ses identifiants |
| `KnownMessenger` | enum adossée à des chaînes, `MessengerType` | Les services internationaux |
| `Handle` | `final readonly class` | Un identifiant sur un service, canonique : `of()` lit un texte tapé, `fromCanonical()` reprend un identifiant conservé |
| `Messengers` | `final class` | La détection du service d'une entrée parmi des ensembles d'enums |
| `Exception\InstantMessengerException` | interface | Toutes les exceptions de la bibliothèque |
| `Exception\InvalidHandle` | `final class`, `InvalidArgumentException` | Une entrée qu'aucun service accepté ne lit |
