# Installation

## Requirements

| Package | Version | Why |
|---|---|---|
| PHP | 8.5 or later | Readonly classes, typed constants, `new` without parentheses, `Random\Randomizer`. |
| `ext-mbstring` | any | Every text of an event is cut to a number of characters, as valid UTF-8. |
| `ext-sodium` | any | Event identifiers are written in hexadecimal without timing leaks. |
| `psr/clock` | `^1.0` | Events are dated by a PSR-20 clock. |
| `phil/ip-anonymizer` | `^0.1` | The client's address is kept only as one of its strategies leaves it. |

## Installing

```sh
composer require phil/error-tracking
```

## Building the tracker

`ErrorTracker` needs an `EventFactory` and a store; the rest has a default.

```php
use Phil\ErrorTracking\ErrorTracker;
use Phil\ErrorTracking\EventFactory;
use Phil\ErrorTracking\Fingerprinter;
use Phil\ErrorTracking\Sampler;
use Phil\ErrorTracking\Scrubber;
use Symfony\Component\Clock\NativeClock;

$tracker = new ErrorTracker(
    new EventFactory('/srv/app', new NativeClock(), release: '1.4.0', environment: 'prod'),
    $store,                                  // the application's ErrorStore
    new Scrubber(['birth_date']),            // fields of the application, beside the defaults
    new Fingerprinter(),
    new Sampler(1.0),                        // keep every event
    $throttle,                               // a CaptureThrottle, or null
    $alerter,                                // a GroupAlerter, or null
);
```

The factory's project directory decides which frames are the application's: a file under it and outside its
`vendor/` directory. Its last two arguments bound the frames of a trace (50) and the exceptions of a chain (10).

The tracker keeps nothing between two captures: one instance serves every request of a long-running worker.
