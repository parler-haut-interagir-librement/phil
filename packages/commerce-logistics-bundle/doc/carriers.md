# Carriers

A carrier's rates and labels, and what each shipment really cost the shop, kept beside what the buyer paid for
delivery. The delivery price the buyer pays stays the delivery method's (see [Delivery](delivery.md)): nothing here
changes an order.

## The port

`Application\Port\CarrierGateway` is one carrier: its code, its rates for a parcel, and the label it produces or sells
for one. Each carrier is an adapter of its own, named after it; the port and the services keep neutral names. A
service implementing the port is tagged `phil_commerce_logistics.carrier` by autoconfiguration.

| Method | Returns |
|---|---|
| `carrier()` | The carrier's code, `canada_post` for instance |
| `rates(RateRequest)` | A list of `RateQuote`: service code and name, cost to the shop, expected business days |
| `label(LabelRequest)` | A `Label`: tracking number, cost to the shop, the document's bytes and media type |

A carrier that refuses a request or does not answer throws `CarrierRefused::unavailable()`, with the carrier's own
messages and never its credentials.

## Rates, labels and costs

```php
$request = new RateRequest(
    ShippingAddress::of('CA', 'K2B 8J6'),
    ShippingAddress::of('CA', 'J0E 1X0'),
    Parcel::of(1_250, 300, 200, 100),   // grams, then millimetres
);

$labels->rates($request);                  // every carrier that answers, the cheapest first
$labels->rates($request, 'canada_post');   // one carrier; its failure shows

$label = $labels->label('canada_post', LabelRequest::of($from, $to, $parcel, 'DOM.RP', $order->number));
$shipment = $shipments->ship($order, $clock->now(), carrier: 'canada_post', tracking: $label->tracking);
$labels->recordLabel($shipment, $label, $clock->now());

// A label bought at the counter
$labels->record($shipment, 'canada_post', 'counter', Money::ofDecimal('14.25', Currency::of('CAD')), $clock->now());

$labels->margin($order);   // ShippingMargin: charged, cost, margin()
```

- `ShippingAddress` needs a country and a postal code for a rate; a label needs a name, a street line, a city and,
  in Canada and the United States, a postal code. Fields are single lines of up to 128 characters.
- `Parcel` weighs 1 g to 1 t, with its three dimensions or none.
- One cost per shipment: a second is refused with `CarrierRefused::alreadyRecorded()`.
- `margin()` sums the order's recorded costs against its shipping adjustments; a cost in another currency than the
  order's is refused.

## Canada Post

`Infrastructure\CanadaPost\CanadaPostCarrier` asks Canada Post's REST API in JSON through Symfony's HTTP client. It
signs in with an OAuth 2.0 access token for the shop's developer application, kept until 60 seconds before it expires
and dropped between the requests of a worker (`kernel.reset`). It asks `POST /prices` for the rates and
`POST /shipments` for a label, then fetches the label's PDF from the link the answer gives; the price comes from the
answer, or from its `price` link.

```yaml
# config/packages/phil_commerce_logistics.yaml
phil_commerce_logistics:
    carriers:
        canada_post:
            enabled: true
            api_key: '%env(CANADA_POST_API_KEY)%'
            api_secret: '%env(CANADA_POST_API_SECRET)%'
            customer_number: '%env(CANADA_POST_CUSTOMER_NUMBER)%'
            contract_id: '%env(default::CANADA_POST_CONTRACT_ID)%'   # commercial rates; null for counter rates
            base_uri: '%env(CANADA_POST_API_URI)%'                   # the test or production gateway
            token_uri: '%env(CANADA_POST_TOKEN_URI)%'
            language: en-CA                                          # or fr-CA
```

The credentials come from the environment or the secrets vault, never from code. The adapter needs FrameworkBundle's
`http_client` service.

| Guard | Behaviour |
|---|---|
| HTTPS only | The API and token addresses must be `https://` |
| Links | A link from an answer is followed only to the API's own scheme, host and port, so the token never leaves for another host; no redirect is followed. The link is read by `phil/url`, as a browser reads it, and sent as it was read: a user name or a password refuses it, a backslash ends its host |
| Size | A JSON answer is read up to 1 MiB, a label up to 5 MiB, chunk by chunk |
| Time | 10 seconds of inactivity, 30 seconds in all, per request |
| Answers | Each field is checked for its type; a missing or odd one refuses the whole answer; amounts are Canadian dollars, never negative |

The JSON field names live in one class, `CanadaPostPayloads`. The tests run on answers shaped as the API's, kept in
`tests/Fixtures/CanadaPost`, and never call the network.

**Limit**: the field names have not been compared with the OpenAPI document of Canada Post's developer portal, which
is published to registered developers only. Compare `CanadaPostPayloads` with that document before the first
production call; a field the API names otherwise refuses the whole answer rather than reading a wrong value.

## Storage

| Table | Holds |
|---|---|
| `phil_commerce_logistics_shipping_cost` | `shipment` (key), `order_number`, `carrier`, `service`, `tracking`, `cost` (minor units), `currency`, `recorded_at`; indexed by order |
