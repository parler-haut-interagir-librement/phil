# Reference

Every public type of the package, with its role.

| Type | Role |
|---|---|
| `Phil\EmbedBundle\PhilEmbedBundle` | The bundle: its configuration `phil_embed`, its services, its compiler pass and the path of AssetMapper. |
| `Domain\ValueObject\Origin` | An origin read by `phil/url`. |
| `Domain\ValueObject\SigningKey` | A key: its identifier and its secret. |
| `Domain\ValueObject\EmbedClaims` | What a token says. |
| `Domain\ValueObject\EmbedMessage` | A message of the frame protocol; the protocol's reference. |
| `Domain\Enum\MessageType` | `ready`, `resize`, `theme`. |
| `Domain\Exception\EmbedException` | What every exception of the bundle implements. |
| `Domain\Exception\InvalidEmbed` | An origin, a key, an embed or claims refused. |
| `Domain\Exception\InvalidEmbedToken` | A token refused. |
| `Domain\Exception\InvalidEmbedMessage` | A message refused. |
| `Application\Embed` | An embed of the configuration. |
| `Application\Embeds` | The embeds by name. |
| `Application\EmbedTokenCodec` | Signs and reads tokens with the ordered keys. |
| `Application\EmbedTokens` | Issues tokens and reads them back into a context. |
| `Application\EmbedContext` | The embed a request is answered for. |
| `Application\EmbedCodes` | The code a partner pastes. |
| `Application\Attribute\Embeddable` | Marks a controller as the page of an embed. |
| `Application\Contract\FramingPolicy` | Writes `frame-ancestors`. |
| `Application\Contract\RemoteCommentThreads` | The port of comments carried by another site's forum, without adapter. |
| `Infrastructure\Http\EmbedRequestListener` | Answers the page of an embed for its token. |
| `Infrastructure\Http\HeaderFraming` | The framing in a header of the bundle's own. |
| `Infrastructure\Http\EmbedScriptController` | The script under a stable address. |
| `Infrastructure\BrowserSecurity\BrowserSecurityFraming` | The framing through `phil/browser-security-bundle`. |
| `Infrastructure\Twig\EmbedExtension` | `phil_embed()`, `phil_embed_frame_attributes()`, `phil_embed_code()`. |
| `Infrastructure\Console\IssueTokenCommand` | `phil:embed:token`. |
| `Infrastructure\DependencyInjection\EmbedServices` | Registers the services. |
| `Infrastructure\DependencyInjection\FramingPass` | Chooses the framing once every bundle is loaded. |

| File | Role |
|---|---|
| `config/routes.php` | The route `phil_embed_script`. |
| `assets/src/phil-embed.js` | The `<phil-embed>` element, served to other sites. |
| `assets/src/lib/message.js` | The frame protocol, shared by the element and the controller. |
| `assets/src/frame_controller.js` | The frame controller. |
