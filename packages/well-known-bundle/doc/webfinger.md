# WebFinger

`/.well-known/webfinger?resource=acct:alice@example.com&rel=self` answers with the JSON Resource Descriptor of the
resource (RFC 7033): its subject, aliases, properties and links.

## The query

The query string is read as it is sent, so that every repeated `rel` counts; PHP's parsed parameters keep only the
last. Other parameters are ignored.

| Query | Answer |
|---|---|
| No `resource`, two, or one that is not an absolute URI | `400`, empty |
| Longer than 4,096 bytes, or more than 32 `rel` | `400`, empty |
| A resource no resolver knows, whatever its scheme | `404`, empty |
| A resource a resolver knows | `200`, the descriptor, with only the links of the `rel` asked for |

Every answer carries `Access-Control-Allow-Origin: *`, as RFC 7033 asks.

## Resources

| Scheme | Read as |
|---|---|
| `acct:` (RFC 7565) | a user part of unreserved characters, sub-delimiters and percent-encoded bytes, `@`, a host and an optional port; the scheme and host in lowercase, the user part as sent |
| `https:` | `//`, a host without credentials and an optional port, then any path, query and fragment; the scheme and host in lowercase |
| another scheme | in lowercase, and known by no resolver of the bundle |

No resource holds a space or a control character, and none is longer than 2,048 bytes. The URI as read,
`WebFingerResource::$uri`, is the key resolvers look the resource up by; `$user` is the user part, percent-decoded.

## Resolvers

The `StaticWebFingerResolver` of `webfinger.resources` answers first, then every service implementing
`WebFingerResolver`, autoconfigured, in the order of their tags. Each returns a `WebFingerDescriptor` or `null`. The
descriptors are merged: the subject and the properties of the first, the aliases of all without repetition, the links
of all in order.

```php
final readonly class AccountResolver implements WebFingerResolver
{
    public function __construct(private AccountRepository $accounts) {}

    public function resolve(WebFingerResource $resource): ?WebFingerDescriptor
    {
        if (!$resource->isAccount() || 'example.com' !== $resource->host) {
            return null;
        }

        $account = $this->accounts->findPublicByName((string) $resource->user);

        return null === $account ? null : new WebFingerDescriptor($resource->uri, links: [
            new WebFingerLink('self', 'application/activity+json', $account->actorUrl()),
        ]);
    }
}
```

## What WebFinger tells

A resource no resolver knows and a resource of a scheme the bundle does not read get the same empty `404`, so that the
answer tells nothing more of the site's accounts than its resolvers describe. A resolver that describes only the
accounts that chose to be found keeps the others private.
