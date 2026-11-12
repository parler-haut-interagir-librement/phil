# vCard, JSContact and Twig

## `VCardResponse`

```php
return new VCardResponse($shop->contact(), 'shop.vcf');
```

The card as a `.vcf` attachment, `text/vcard; charset=utf-8`; a file name outside ASCII gets an ASCII fallback for
clients that read no other, and a slash or a backslash in the name becomes `_`.

## JSContact

`JSContactResponse` serves a card as JSContact, `application/jscontact+json`, for an address book or a JMAP Contacts
client; with a file name, it is downloaded, with the same ASCII fallback and the same `_` for a slash or a backslash
as `VCardResponse`.

```php
return new JSContactResponse(new JSContactCard('urn:uuid:'.$shop->id, $shop->contact(), [$shop->address()]));
```

The bundle registers `phil/contact`'s `JSContactReader` as a service, its bounds and the country of phone numbers
without their country code in configuration. An import endpoint is a controller of the application's:

```php
#[Route('/contacts/import', methods: ['POST'])]
public function import(Request $request, JSContactReader $reader): Response
{
    try {
        $read = $reader->read($request->getContent());
    } catch (UnreadableJSContact $unreadable) {
        return new Response($unreadable->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    $this->contacts->save($read->card, $read->addresses);

    return $this->json(array_map(static fn (JSContactNotice $notice): array => ['pointer' => $notice->pointer, 'reason' => $notice->reason], $read->notices));
}
```

The notices tell the client what was read but not kept; `$read->unmapped` holds the properties the card does not map,
which the application keeps beside the card when it wants to write them back.

## Twig

| Function | Gives |
|---|---|
| `contact_vcard(card)` | The card as vCard; empty for `null` |
| `person_name(name, format)` | A `PersonName`, or the name of a card, in a format: `display` (by default), as it is written every day; `formal`, with its honorific and credential; `sort`, the key a list sorts it by. A card without a structured name gives its name in every format, an organisation's card its organisation; empty for `null` |
| `contact_json_ld(card)` | The card's schema.org JSON-LD: a node of the page's graph with phil/structured-data-bundle, else `<script type="application/ld+json">…</script>`, its JSON unable to close the element; empty for `null` |

With phil/structured-data-bundle installed, `contact_json_ld` prints nothing: it adds the card, a `Person` or an
`Organization`, to the graph of the page, which `structured_data()` prints in one script element with the other nodes
of the page. Render `structured_data()` after the cards, at the end of the body. Without that bundle, each card prints
its own element.

```twig
{{ person_name(card) }}
{{ person_name(card.structuredName, 'formal') }}
```

Another format than `display`, `formal` and `sort` throws `InvalidArgumentException`.
