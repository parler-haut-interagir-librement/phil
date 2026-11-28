# Carriers

A deliverable may go to a carrier through its API rather than through a file: `Contract\DeliverableShipper` is that
port. The package ships no adapter; an adapter per carrier implements it.

```php
use Phil\BenefitDeliverables\Shipping\ShipmentRequest;

$receipt = $shipper->ship(new ShipmentRequest('2026-11-postcard-0042', $address, 'postcard'));

$receipt->carrier;          // the carrier's name
$receipt->trackingNumber;   // the parcel's tracking number
```

- **`ShipmentRequest`** holds a reference of the application's, 1 to 64 characters, which names neither the member
  nor their tier; the recipient's `Address`; the code of the benefit; and a telephone number in E.164, only when the
  carrier asks for one.
- **`ShipmentReceipt`** holds the carrier's name and the tracking number.

Once the carrier took the parcel, mark the deliverable done with `Checklist::markDone()`.
