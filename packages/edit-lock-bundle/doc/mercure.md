# Mercure

## Topics

Each resource has one topic, `topic_prefix` followed by its name: `urn:phil:edit-lock:article:42`. The controller
subscribes to it through the URL `ClientConfig` gives — the hub's public URL with the topic in `match`, or in `topic`
for a hub of the protocol before 1.0.

Updates are private unless `mercure.private` is `false`: the hub sends them only to subscribers whose token allows
the topic. The application grants it to the writers it lets edit, by MercureBundle's authorization cookie or a token
of its own; the controller opens its `EventSource` with credentials.

## The messages

Every message is one Turbo Stream:

```html
<!-- presence: the leases active on the resource, after one changed -->
<turbo-stream action="update" target="phil-edit-lock-presence-…">
    <template>
        <ul class="phil-edit-lock-presence">
            <li data-holder="7" data-part="aB3x" data-expires-in="28">Ada</li>
            <li data-holder="9" data-part="" data-expires-in="30">Grace</li>
        </ul>
    </template>
</turbo-stream>

<!-- a block saved: a document of that one block, canonical, and its place -->
<turbo-stream action="replace" target="phil-edit-lock-block-aB3x" data-holder="7">
    <template>
        <div id="phil-edit-lock-block-aB3x" data-list="root" data-after="Zt0q" data-ujo="{&quot;blocks&quot;:[…]}"></div>
    </template>
</turbo-stream>

<!-- a block removed -->
<turbo-stream action="remove" target="phil-edit-lock-block-aB3x" data-holder="7"></turbo-stream>
```

`data-part` is empty for a lease on the whole resource. `data-expires-in` counts the seconds left when the message was
made, so that each browser computes the end on its own clock. `TurboStreams::presenceTarget($resource)` gives the
identifier of the presence element: a page without the editor can show the list by letting Turbo apply the presence
stream to an element of that identifier. The block streams are meant for the controller, which applies them to the
editor's document rather than to the page.

## Without a hub

When MercureBundle is not registered, or `mercure.enabled` is `false`, the `NullBroadcaster` tells nobody.
`ClientConfig` gives no hub; the controller then asks for presence at each heartbeat, so the locks still show, a few
seconds late, and the others' saves reach a writer when the page is opened again.

## A hub that cannot be reached

A publication that fails is logged as a warning by the `logger` service and does not fail the request: the lease or
the save is kept, and the next change carries the whole presence again.
