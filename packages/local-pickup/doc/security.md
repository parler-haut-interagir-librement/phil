# Security and privacy

## The secret

`PickupCodes` takes the secret that keys the digests, at least 32 bytes; keep it with the application's other secrets,
never in the database. The parameter is marked `#[\SensitiveParameter]`, so it stays out of stack traces. Changing the
secret makes the codes already sent unreadable: send them again with `Pickups::resend()`.

## What is stored

| Data | Stored | Where |
|---|---|---|
| The code | Never | Only in the `ReadyNotice` sent to the customer |
| The code's digest | While the pickup is ready | The pickup's row; cleared when it is picked up or expires |
| Wrong attempts | Yes | The pickup's row |
| The designated collector's name | Until erased | The pickup's row |
| Who collected, and the designated name | Until erased | The journal of hand-overs |

The message `Message\NotifyPickupReady` carries the order number and nothing else: no code, no name, no address waits
in a queue. `IssuedCode`, `ScannedCode` and `HandOverRequest` hide the code from `var_dump()` and `print_r()`.

## The limits

| Limit | Value |
|---|---|
| Code | 8 characters, 40 random bits |
| Wrong attempts before the code locks | 1 to 20, 5 by default |
| Typed code read | 32 bytes at most |
| Scanned payload read | 97 bytes at most |
| Expiry batch | 1 to 1,000 pickups |
| Slots read | phil/reservation's bounds: 10,000 candidates and 1,000 slots a call |
| Texts | 500 bytes; identifiers 64 or 255 bytes |

With five attempts, guessing a code succeeds about once in 220 billion tries. The stored rows and the scanned payloads
are read strictly — any field missing, of the wrong type, out of bounds or inconsistent with the state throws
`InvalidPickup` — and both readers are fuzzed.

## Personal data

`PickupPersonalData` serves an application's privacy module: its `export()` gives a customer's pickups and hand-overs
with the collectors' names, and its `erase()` blanks the names of the people designated to collect and of those who
collected, keeping what was handed over, where and when for the accounts. It reports each store as `redacted` and runs
again without harm. An application using phil/privacy-bundle wraps it in its own `PersonalDataExporter` and
`PersonalDataEraser`, under the module name it chooses:

```php
use Phil\LocalPickup\PickupPersonalData;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Domain\Subject\ErasureReport;
use Phil\PrivacyBundle\Domain\Subject\ModuleName;

final readonly class PickupEraser implements PersonalDataEraser
{
    public function __construct(private PickupPersonalData $data)
    {
    }

    public function module(): ModuleName
    {
        return ModuleName::fromString('local-pickup');
    }

    public function erase(DataSubject $subject): ErasureReport
    {
        return ErasureReport::of('local-pickup', array_map(
            static fn (string $effect): ErasureEffect => ErasureEffect::from($effect),
            $this->data->erase($subject->value),
        ));
    }
}
```
