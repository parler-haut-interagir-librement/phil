# Exceptions

Every exception implements `Phil\SocialCards\Exception\SocialCardsException`.

| Exception | Thrown when |
|---|---|
| `InvalidShareMetadata` | A shared page has no title, an address that is not absolute HTTP or HTTPS, a locale, handle, image size or media type that is not one, an unknown key or a value of the wrong type in `fromArray()`; a card's locale is not one |
| `InvalidCardTemplate` | A template or a card has a colour, size or bound out of range, an unknown key, a font that cannot be read or is not TrueType or OpenType, an image that is not a PNG, JPEG, GIF or WebP within bounds, no title; a store is given what is not a fingerprint |
| `CardRenderingFailed` | GD cannot measure or draw text in a font, read an image or write the PNG; a store cannot write a card |

The reader of other sites' pages, `TagReader`, `LinkPreview` and `TagCheck` throw nothing.
