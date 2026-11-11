# Validation and types

| Method | Gives |
|---|---|
| `isValid()` | Whether the number exists in its country's numbering plan |
| `region()` | The country of the number, `001` for numbers of no country, `null` when it is in no plan |
| `type()` | What the number is, `PhoneNumberType::Unknown` when it is in no plan |

Countries that share a country code — the 25 of `+1`, the United Kingdom and its Crown Dependencies — are told apart
by their leading digits and their plans: `+44 7624…` is the Isle of Man, `+39 06 698…` the Vatican.

## The types

| `PhoneNumberType` | Means |
|---|---|
| `FixedLine`, `Mobile` | A landline, a mobile |
| `FixedLineOrMobile` | A number of a plan where they cannot be told apart, as in North America |
| `TollFree`, `PremiumRate`, `SharedCost` | Free for the caller, charged more, cost shared |
| `Voip`, `PersonalNumber`, `Pager`, `Uan`, `Voicemail` | Internet telephony, a number following a person, a pager, a universal access number, a voicemail |
| `Unknown` | In no plan |

A field for mobiles accepts `Mobile` and `FixedLineOrMobile`, or North American numbers are refused.
