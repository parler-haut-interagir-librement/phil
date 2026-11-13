# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| To | Do |
|---|---|
| Make a page embeddable | Give its route the `_embed` default, or its controller `#[Application\Attribute\Embeddable]` |
| Read the embed a page is answered for | The request attribute `_phil_embed`, or `phil_embed()` in Twig |
| Issue a token from code | `Application\EmbedTokens::issue()` |
| Write the code a partner pastes | `Application\EmbedCodes::snippet()`, or `phil_embed_code()` in Twig |
| Write `frame-ancestors` another way | Implement `Application\Contract\FramingPolicy` and alias it in a compiler pass that runs after the bundle's |
| Carry a page's comments on another site's forum | Implement `Application\Contract\RemoteCommentThreads` |
| React to the frame | Listen to `phil-embed:ready` and `phil-embed:resize` on the element |
| Change how the frame measures itself | Replace the `frame` controller in `assets/controllers.json` with one that posts the same messages |

## The ports

```php
use Phil\EmbedBundle\Application\Contract\FramingPolicy;
use Phil\EmbedBundle\Domain\ValueObject\Origin;
use Symfony\Component\HttpFoundation\Request;

final readonly class EdgeFraming implements FramingPolicy
{
    public function allow(Request $request, Origin $origin): void
    {
        $request->attributes->set('edge_frame_ancestors', $origin->toString());
    }

    public function deny(Request $request): void
    {
        $request->attributes->set('edge_frame_ancestors', "'none'");
    }
}
```

The invariants:

- a `FramingPolicy` lets an allowed response be framed by that origin alone, `X-Frame-Options` dropped, and a denied one
  by none;
- a `RemoteCommentThreads` gives no thread for a page of a host that is not allowed, and always the same thread for the
  same page.
