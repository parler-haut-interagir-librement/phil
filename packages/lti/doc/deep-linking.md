# Deep Linking

Deep Linking 2.0 lets an instructor pick content in the tool — a quiz, a chapter, a video — and place it in the
platform, which then launches it as a resource link.

## The request

The platform launches the tool with an `LtiDeepLinkingRequest`. Its `DeepLinkingSettings` say what it accepts back:

| Setting | What it says |
|---|---|
| `returnUrl` | Where the response is posted. |
| `acceptTypes` | The types of content items: `ltiResourceLink`, `link`, `file`, `html`, `image`. |
| `acceptPresentationDocumentTargets` | `iframe`, `window`, `embed`. |
| `acceptMediaTypes` | For files and images, the media types accepted. |
| `acceptMultiple` | Whether several items may be sent. |
| `acceptLineItem` | Whether a resource link may ask for a line item. |
| `autoCreate` | Whether the platform places the items without asking again. |
| `title`, `text` | Defaults the platform suggests. |
| `data` | An opaque value the response must echo. |

On the tool, `$launch->isDeepLinking()` tells the request from a resource link launch, and
`$launch->message->deepLinking` holds the settings.

## The content items

| Class | Type | What it is |
|---|---|---|
| `ResourceLinkItem` | `ltiResourceLink` | A resource link of the tool, with its address or the tool's default, custom parameters, a line item to create, icon, thumbnail, and windows of availability and submission. |
| `LinkItem` | `link` | A page, opened outside LTI. |
| `FileItem` | `file` | A file for the platform to keep, before its address expires. |
| `HtmlItem` | `html` | A fragment of HTML, 64 KiB at most. |
| `ImageItem` | `image` | An image, with its size. |

## The response of the tool

```php
use Phil\Lti\DeepLinking\LineItemRequest;
use Phil\Lti\DeepLinking\ResourceLinkItem;
use Phil\Lti\Tool\DeepLinkingResponder;

$post = new DeepLinkingResponder($keys, $clock)->respond($launch, [
    new ResourceLinkItem($quizUrl, 'Quiz 3', custom: ['quiz' => '3'], lineItem: new LineItemRequest(10.0, 'Quiz 3', 'quiz-3')),
], message: 'Quiz 3 added.');
// render a form that posts $post->fields (one field, JWT) to $post->action
```

`respond()` refuses a launch that is not a deep linking request, an item of a type the request does not accept, and more
than one item when it accepts only one. An empty list says nothing was picked; `errorMessage` and `errorLog` say why the
picking failed. The response is a JWT signed with the tool's key: `iss` the client, `aud` the platform, five minutes of
life, a fresh `nonce`, the message type `LtiDeepLinkingResponse`, the deployment, the items, the request's `data`, and the
messages.

## The return on the platform

```php
use Phil\Lti\Platform\DeepLinkingReturn;

$result = new DeepLinkingReturn('https://courses.example.com', $tools, $nonces, $verifier)->read($_POST['JWT']);
```

`read()` checks that the response is issued by a registered client and addressed to the platform, that it verifies with
a key of the tool's key set, that its times hold, that its `nonce` is used once, that its deployment is the tool's, and
that it is an `LtiDeepLinkingResponse` of LTI 1.3. `DeepLinkingResult` gives the client, the deployment, the items, the
`data` the platform put in the request — to find the placement it was for —, and the messages. An item of a type the
package does not know is skipped; the platform sanitises the HTML of an `HtmlItem` before it shows it.
