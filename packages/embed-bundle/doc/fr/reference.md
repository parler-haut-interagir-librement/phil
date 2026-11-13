<!-- phil-dev source-sha256=ed0a1b54b2a1ee8967ce38801410ba139362af929011fcf50bb57a44f4e167b9 -->
# Référence

Chaque type public du paquet, avec son rôle.

| Type | Rôle |
|---|---|
| `Phil\EmbedBundle\PhilEmbedBundle` | Le bundle : sa configuration `phil_embed`, ses services, sa passe du compilateur et le chemin d'AssetMapper. |
| `Domain\ValueObject\Origin` | Une origine lue par `phil/url`. |
| `Domain\ValueObject\SigningKey` | Une clé : son identifiant et son secret. |
| `Domain\ValueObject\EmbedClaims` | Ce que dit un jeton. |
| `Domain\ValueObject\EmbedMessage` | Un message du protocole du cadre ; la référence du protocole. |
| `Domain\Enum\MessageType` | `ready`, `resize`, `theme`. |
| `Domain\Exception\EmbedException` | Ce que toute exception du bundle implémente. |
| `Domain\Exception\InvalidEmbed` | Une origine, une clé, une intégration ou des revendications refusées. |
| `Domain\Exception\InvalidEmbedToken` | Un jeton refusé. |
| `Domain\Exception\InvalidEmbedMessage` | Un message refusé. |
| `Application\Embed` | Une intégration de la configuration. |
| `Application\Embeds` | Les intégrations par nom. |
| `Application\EmbedTokenCodec` | Signe et lit les jetons avec les clés ordonnées. |
| `Application\EmbedTokens` | Émet des jetons et les relit dans un contexte. |
| `Application\EmbedContext` | L'intégration pour laquelle une requête est servie. |
| `Application\EmbedCodes` | Le code qu'un partenaire colle. |
| `Application\Attribute\Embeddable` | Marque un contrôleur comme la page d'une intégration. |
| `Application\Contract\FramingPolicy` | Écrit `frame-ancestors`. |
| `Application\Contract\RemoteCommentThreads` | Le port des commentaires portés par le forum d'un autre site, sans adaptateur. |
| `Infrastructure\Http\EmbedRequestListener` | Sert la page d'une intégration pour son jeton. |
| `Infrastructure\Http\HeaderFraming` | L'encadrement dans un en-tête propre au bundle. |
| `Infrastructure\Http\EmbedScriptController` | Le script à une adresse stable. |
| `Infrastructure\BrowserSecurity\BrowserSecurityFraming` | L'encadrement par `phil/browser-security-bundle`. |
| `Infrastructure\Twig\EmbedExtension` | `phil_embed()`, `phil_embed_frame_attributes()`, `phil_embed_code()`. |
| `Infrastructure\Console\IssueTokenCommand` | `phil:embed:token`. |
| `Infrastructure\DependencyInjection\EmbedServices` | Enregistre les services. |
| `Infrastructure\DependencyInjection\FramingPass` | Choisit l'encadrement une fois tous les bundles chargés. |

| Fichier | Rôle |
|---|---|
| `config/routes.php` | La route `phil_embed_script`. |
| `assets/src/phil-embed.js` | L'élément `<phil-embed>`, servi aux autres sites. |
| `assets/src/lib/message.js` | Le protocole du cadre, partagé par l'élément et le contrôleur. |
| `assets/src/frame_controller.js` | Le contrôleur du cadre. |
