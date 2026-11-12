# Testing

| Area | What is covered |
|---|---|
| `PhilSocialCardsBundle`, `SocialCardsServices`, `RequiredServicesPass` | The defaults, every option, the services, their arguments and tags, what the configuration refuses, the optional services removed without theirs |
| `SharePage`, `CardTemplates`, `CardRequest`, `SocialCards` | The layers of values, templates read once, requests kept and read back, cards queued once and drawn once, a template gone |
| `ShareMetadataFactory`, `SocialMetaExtension`, `MediaShareImages` | The four layers, the request's address and locale, a page without a title or address, the card of a page and its template, a medium as the image |
| `SocialCardController`, the queue and the handler | The cache headers, the `ETag` and its `304`, the cache tags, a `404`, the message and its handling |
| `GuardedPageFetcher`, `LinkPreviews`, `DirectoryCardRequestStore` | The head read through phil/safe-http, the bounds, the redirects, private hosts refused at the start and at a redirect, answers that are not pages, the first link of a text, the cache, the files |
| `CheckSocialTagsCommand` | A path rendered at the router's address and port, warnings, errors, pages not read, an address fetched |
| A whole application | FrameworkBundle, Messenger, Twig and its components: a page's tags, its card drawn by the handler and served, `304` and `404`, the previews, a kept preview, the endpoint of pasted links and its rate limit, the command |
| `link_preview_controller.js` | The first link of a text, the card built from text nodes, the field, a preview removed and kept removed, answers without a preview, errors, a late answer dropped, the delay |

```sh
vendor/bin/phpunit packages/social-cards-bundle/tests
```

The controller runs under Node's test runner, Stimulus resolved to a stub:

```sh
cd packages/social-cards-bundle && node --import ./tests/js/register.mjs --test tests/js
```
