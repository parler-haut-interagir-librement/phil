# Privacy signals

**A browser that sends Do Not Track or Global Privacy Control is not counted, in any counter**: neither the
audience nor the robot-verdict counter.

State: `PrivacySignals` is implemented and tested; `AdmissionRule` applies it
([collection-and-admission.md](collection-and-admission.md)).

## `PrivacySignals`

```php
$signals = PrivacySignals::fromHeaders(
    $request->headers->get('DNT'),                           // Do Not Track
    implode(',', $request->headers->all('Sec-GPC')),         // Global Privacy Control, every occurrence
);
$signals->doNotTrack;            // bool
$signals->globalPrivacyControl;  // bool
$signals->optOut();              // true if either is set

PrivacySignals::none()->optOut(); // false — for tests, or a request that carries no header
```

| `DNT` | `Sec-GPC` | `optOut()` |
|---|---|---|
| absent | absent | `false` |
| `1` | absent | `true` |
| absent | `1` | `true` |
| `1` | `1` | `true` |
| `0` | absent | `false` |
| ` 1 ` (padded) | absent | `true` |
| `yes` | `true` | `false` |
| absent | `0, 1` (a list, or two headers joined) | `true` |
| absent | ` 1 ,0` | `true` |
| absent | empty | `false` |
| absent | `10, 01` | `false` |

**Only the value `1`, after trimming, is a signal.** `DNT: 0` expresses consent to nothing — it is not an
opt-out — and an absent header is no signal.

**`Sec-GPC` is read as a list.** A browser or a proxy may repeat the header or send a list separated by commas:
`CollectController` joins every occurrence with commas, and `fromHeaders()` splits its second argument on them; one
trimmed value `1` is the signal. `DNT` is read as a single value.

## Where it sits in admission

The opt-out is checked right after the site's status, **before** crawler detection, the spam list and the robot
verdict. A browser that opted out therefore leaves no trace in any counter, including the aggregate count of
robot verdicts.

The rate limiter runs before admission, so a request carrying a privacy signal still consumes the limiter's
budget and leaves a short-lived keyed digest in its state; it never reaches a counter.

## What the collection script does

The collection script ([collection-and-admission.md](collection-and-admission.md#the-collection-script)) sends
nothing when the browser sends Do Not Track or Global Privacy Control, sets no cookie, sends none (a `fetch` without
credentials) and uses no local storage. The server-side check on the headers stays authoritative whatever the
script does.
