# Segments

## The query language

A segment is written as the versioned JSON form of phil/rules, with its name beside its condition:

```json
{
    "version": 1,
    "name": "Weekend buyers who are not crew",
    "condition": {
        "all": [
            {"type": "ticket.holder", "operands": {"event": "spring-fair-2027", "ticket_types": ["weekend"]}},
            {"not": {"type": "person.tagged", "operands": {"tags": ["crew"]}}}
        ]
    }
}
```

The same text is what an API accepts, what a visual builder produces and what the application stores. `SegmentJson`
reads it strictly — any other key, an unknown type or a refused operand is refused, as phil/rules describes in its JSON
form — and writes it back canonically. The segment's identifier belongs to the application and is given when reading.

| Type | Operands | Holds when the member |
|---|---|---|
| `ticket.holder` | `event`, optional `ticket_types` | holds a ticket of the event, of one of the types when given |
| `date.participant` | `occurrence` | took part in the date of an event |
| `selection.member` | `selection` | was chosen by an organiser in the selection |
| `purchase.made` | optional `skus`, `products`, `order_states` | has an order matching every part given; any order without parts |
| `supporter.active` | optional `tiers` | supports now, at one of the tiers when given |
| `person.tagged` | `tags` | carries at least one of the internal tags |

Every operand is an identifier or a list of identifiers. Each evaluation receives one fact, `member`, the opaque
identifier of the member; the types ask their port about that member. Other condition types, the built-in comparisons
of phil/rules or the application's own, may be added to the registry.

## Dynamic segment or frozen list

A **segment** is dynamic: its members are whoever matches the rule now. Asked tomorrow, after a ticket sale or an
unsubscription, it answers differently. It suits a page that lists an audience, a count shown in a builder, or a
journey that starts when someone enters.

A **frozen list** is the members at one moment, kept as they were. When a message is sent, the application freezes
the segment and stores the list with the message: it is the proof of who was meant to receive it, whatever the facts
become. It holds opaque identifiers only, in ascending byte order, the date it was frozen and the fingerprint of the
rule that selected them; two lists with the same fingerprint come from the same rule.

```php
$list = $segments->freeze($segment);
$message->recipients = $list;   // stored with the message, never recomputed

foreach ($list->page(after: null, limit: 500) as $member) { … }
$list->contains('person:42');
```

A sender checks the exclusions again as it sends each batch, for the members who unsubscribed after the freeze, and
records them as skipped.

## Exclusions

The `Exclusions` port answers which of a page of members are excluded: unsubscribed, refused messages, bounced
permanently. `Segments` asks it for every page it reads and removes them before the rule is evaluated, so that no
segment can bypass it. `Segments::check()` still evaluates the rule for an excluded member, so that support sees both
reasons in the `Membership` it returns.

## Pages

`Segments::page($segment, $after, $limit)` reads the audience from the cursor and returns up to `$limit` members
(1 000 at most) and the cursor of the next page, null at the end of the audience. One call reads at most `maxScanned`
members of the audience: a page may come back short with a next cursor when few members match.

## Recalculation

`Recalculation::recalculate()` compares the segment as it is now with the members a `MembershipStore` kept from the
last run. It walks the audience and the store side by side, both in ascending byte order, then writes the changes by
batches, publishes a `MemberEntered` or a `MemberLeft` for each through the PSR-14 dispatcher, and records the date of
the run, which `MembershipStore::lastRecalculation()` gives back for display. A member leaves when the rule fails for
them, when they are excluded, or when they are no longer in the audience.

The events carry the segment identifier, the member identifier and the date: an application may forward them as
Messenger messages as they are, without an address or a name. They follow the writes, so a run stopped between the two
publishes them again on the next run: a listener treats them as at least once.

A full computation, a frozen list or a recalculation, reads at most `maxMembers` members of the audience and throws
`SegmentTooLarge` beyond, before writing anything.

## What a segment refuses

- **Profiling**: a guessed trait, a predicted interest, a behaviour or engagement score. Deciding about a person from
  such an inference is what privacy law asks to disclose and limit; segments are explicit rules on facts the person
  gave or did.
- **Opening and clicking**: whether a message was opened or a link clicked. Opening is measured by tracking pixels,
  which privacy tools block or fake, and clicks by rewritten links; neither is a fact to select people on.
- **External customer data platforms**: segments are computed from the application's own data, through its ports.

`Segment` refuses any rule whose condition types carry, as a word of their name, one of `Segment::FORBIDDEN_WORDS`:
`open`, `opened`, `click`, `clicked`, `score`, `predicted`, `inferred`, `propensity`, `lookalike`, `affinity` and their
forms.

## Bounds

| Bound | Default | Where |
|---|---|---|
| `pageSize` | 500 | members read from the audience at once, 1 to 1 000 |
| `maxScanned` | 10 000 | members one call of `page()` reads |
| `maxMembers` | 200 000 | members a full computation reads |
| `batchSize` | 500 | members read from, and written to, the store at once |

The rule of a segment is bounded by the `Limits` of phil/rules given to `SegmentJson`.
