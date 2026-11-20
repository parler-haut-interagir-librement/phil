# Messages

## The envelope

Every message is a Standard Webhooks request: a JSON body and three headers.

```http
POST /syndication/tea-house HTTP/1.1
content-type: application/json
webhook-id: msg_7Lz2Wq4Yf3sQbH8nT1xKpR
webhook-timestamp: 1793786400
webhook-signature: v1a,Zm9v…

{"type":"product.updated","timestamp":"2026-11-03T10:00:00.123456+00:00","audience":"corner-shop","data":{…}}
```

| Part | Meaning |
|---|---|
| `webhook-id` | The message's id, `msg_` and a Base58 UUID v7; the same on every attempt |
| `webhook-timestamp` | When this attempt was signed, in Unix seconds |
| `webhook-signature` | The Ed25519 signature, `v1a,…`, of the id, the timestamp and the body; `v1a` names the algorithm and the version of the signature scheme |
| `type` | What the message says |
| `timestamp` | When the change happened, to the microsecond: a later message describes its subject after an earlier one |
| `audience` | The code of the partner the message is addressed to |
| `data` | The message's content, always a JSON object |

## `product.updated`

From a supplier to a reseller: a product as it now is.

| Field | Type | Meaning |
|---|---|---|
| `sku` | string | 1 to 64 visible ASCII characters |
| `name` | string | 1 to 255 characters |
| `currency` | string | ISO 4217 code |
| `retail` | integer | The price to sell at, in minor units |
| `cost` | integer | What a unit costs this reseller, in minor units; zero to `retail` |
| `tax_category` | string | The category the reseller's taxes apply to |
| `available` | integer | Units available; 0 stops the sale |
| `active` | boolean | `false` once the product is withdrawn |

## `order.forwarded`

From a reseller to a supplier: an order to ship.

| Field | Type | Meaning |
|---|---|---|
| `order` | string | The order number at the reseller, 1 to 64 visible ASCII characters |
| `currency` | string | ISO 4217 code |
| `lines` | list | Each line's `sku`, `quantity` (1 or more) and `unit_cost` (minor units): a cost the supplier published to this reseller, in effect within its grace window |
| `ship_to` | object | Where to ship, in the shape the partners agreed on; cleared from the sender's outbox once delivered or given up |

## `shipment.sent`

From a supplier to a reseller: a parcel of an order the reseller forwarded left.

| Field | Type | Meaning |
|---|---|---|
| `order` | string | The order number at the reseller |
| `shipment` | string | The parcel's id at the supplier, 1 to 64 visible ASCII characters |
| `carrier` | string or null | 1 to 64 printable ASCII characters |
| `tracking` | string or null | 1 to 128 visible ASCII characters |
| `lines` | list | Each line's `sku` and `quantity` (1 or more); 1 to 500 SKUs, none beyond what the order holds |

## `return.requested`

From a reseller to a supplier: goods of a forwarded order are on their way back.

| Field | Type | Meaning |
|---|---|---|
| `order` | string | The order number at the reseller |
| `return` | string | The return's number at the reseller, 1 to 64 visible ASCII characters |
| `currency` | string | The order's ISO 4217 code |
| `carrier`, `tracking` | string or null | The parcel sent back |
| `lines` | list | Each line's `sku` and `quantity`; never more than the order holds less earlier returns |

## `return.received`

From a supplier to a reseller: the goods of a return arrived, and what was accepted is credited.

| Field | Type | Meaning |
|---|---|---|
| `order` | string | The order number at the reseller |
| `return` | string | The return's number |
| `currency` | string | ISO 4217 code |
| `credit` | integer | The credit, in minor units: each unit accepted at the unit cost of the order's entry; the reseller refuses a credit its own ledger does not give |
| `lines` | list | Each line's `sku` and `quantity` accepted; never more than announced |

None of these messages carries personal data.

## Other types

A message of a type the module does not know is acknowledged and ignored: a partner on a newer version may send more.
