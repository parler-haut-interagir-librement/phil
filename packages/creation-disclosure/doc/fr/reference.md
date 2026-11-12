<!-- phil-dev source-sha256=ce291fa95850c49d1049ea4352a67768b174332b6ff67c72f43a1aa405d7a886 -->
# Référence

Chaque type public du paquet, avec son rôle.

| Type | Rôle |
|---|---|
| `DigitalSourceType` | Le vocabulaire Digital Source Type de l'IPTC : codes, adresses, libellés, codes retirés, la vue de chacun |
| `Part` | Une partie d'une œuvre déclarée à part : texte, image, audio, vidéo, code |
| `CreationDisclosure` | Comment une œuvre a été faite, partie par partie, immuable ; ses vues et sa conservation en tableau |
| `GenerativeAi` | Ce qu'une partie ou une œuvre doit à l'IA générative, leur combinaison et leur ordre |
| `DisclosureRequirement` | Les parties qu'un concours ou une boutique demande de déclarer, et le plafond d'IA générative |
| `Exception\CreationDisclosureException` | L'interface de chaque exception de la bibliothèque |
| `Exception\InvalidDisclosure` | Un code, une adresse, une partie ou une déclaration gardée que la bibliothèque ne tient pas |
