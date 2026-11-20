# Scope

Returns for the core of commerce.

| Part | Content | State |
|---|---|---|
| Requests | Lines and reasons, the window from the placement or the last shipment, quantities left, a rate limit per customer | Implemented and tested ([returns.md](returns.md)) |
| Steps | Authorization, questions to the customer, rejection, cancellation, expiry, reception with each line's condition | Implemented and tested |
| Value | Each unit's share of its line, sets returned whole, specials undone, the restocking fee, missing units | Implemented and tested |
| Delivery refunds | The delivery charge given back once per order, as `delivery_refund` says | Implemented and tested |
| Restocking | The `Restocking` port, called for the resellable units | Implemented and tested |
| Settling | Refund to the payments, store credit, exchange | Implemented and tested |
| Partial credit notes | A credit note for exactly what a return gives back, numbered in the core's series | Implemented and tested ([credit-notes-and-slips.md](credit-notes-and-slips.md)) |
| Return slips | A slip numbered in its own series, drawn as a PDF | Implemented and tested ([credit-notes-and-slips.md](credit-notes-and-slips.md)) |
| Return labels | A carrier's label through the `ReturnLabels` port | Implemented and tested |
| Events | Each step, each settlement, each credit note | Implemented and tested |
| Labels from the logistics module's carriers | An adapter of `ReturnLabels` on the carriers of `phil/commerce-logistics-bundle`, which needs the customer's address as the origin of a parcel | Not started: an application's adapter serves the port meanwhile |

## The ports of the core it implements

- `PaymentGateway`: `ReturnsGateway`, for the method `exchange`.
- `DocumentTypes`: `ReturnDocumentTypes`, the series `return_slip`.
- `DocumentNumbering` and `DocumentRegister` are read for the numbers of credit notes and slips.

## Its own ports

- `Restocking`: puts units back on the shelf.
- `StoreCreditGranter`: grants store credit; `StoredValueStoreCredit` on `phil/commerce-stored-value-bundle`.
- `SetParts`: the parts of a set; `CatalogSetParts` on `phil/commerce-catalog-bundle`.
- `SpecialDiscounts`: which discounts are specials; `PromotionSpecials` on `phil/commerce-promotions-bundle`.
- `ShipmentDates`: an order's last shipment; `LogisticsShipmentDates` on `phil/commerce-logistics-bundle`.
- `ReturnLabels`: a carrier's label; the application's adapter.
- `ReturnSlipRenderer`: a slip as a PDF; `PagedMediaReturnSlips` on `phil/paged-media`.
