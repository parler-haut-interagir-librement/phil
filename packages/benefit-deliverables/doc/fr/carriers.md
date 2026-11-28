<!-- phil-dev source-sha256=ffb90d39f1610dc09ec497b1ae51cd09fb0fd5b49f1b452945cd7482d0e3e1f7 -->
# Transporteurs

Un élément à livrer peut aller à un transporteur par son API plutôt que par un fichier : `Contract\DeliverableShipper`
est ce port. Le paquet ne fournit aucun adaptateur ; un adaptateur par transporteur l'implémente.

```php
use Phil\BenefitDeliverables\Shipping\ShipmentRequest;

$receipt = $shipper->ship(new ShipmentRequest('2026-11-postcard-0042', $address, 'postcard'));

$receipt->carrier;          // the carrier's name
$receipt->trackingNumber;   // the parcel's tracking number
```

- **`ShipmentRequest`** contient une référence de l'application, de 1 à 64 caractères, qui ne nomme ni le membre ni son
  palier ; l'`Address` du destinataire ; le code de l'avantage ; et un numéro de téléphone en E.164, seulement quand le
  transporteur en demande un.
- **`ShipmentReceipt`** contient le nom du transporteur et le numéro de suivi.

Une fois que le transporteur a pris le colis, marquez l'élément à livrer fait avec `Checklist::markDone()`.
