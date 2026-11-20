# ASVS 5.0 mapping

The requirements of [OWASP ASVS 5.0.0](https://github.com/OWASP/ASVS/tree/v5.0.0/5.0/en) chapter V6 the library meets,
with the test that shows each one, and those it leaves to the application. The guide of the monorepo,
[ASVS 5.0: what the application keeps](https://github.com/parler-haut-interagir-librement/phil/blob/main/ASVS.md), gathers what every package leaves.

## Met by the library

| Requirement | Level | How | Shown by |
|---|---|---|---|
| 6.2.5 Passwords of any composition, no character rules | 1 | Strength is estimated from the guesses an attacker needs, never from classes of characters | `StrengthEstimatorTest` |
| 6.2.8 The password used exactly as received | 1 | The estimator and the breach check read the bytes given: no trimming, truncation, case folding or Unicode normalization before the hash prefix is sent | `PwnedPasswordsSourceTest::testThePasswordIsHashedExactlyAsTyped`, `NotCompromisedValidatorTest::testThePasswordIsCheckedAsTyped` |
| 6.2.9 Passwords of at least 64 characters | 2 | Any length is accepted; the estimator analyses the first 128 characters, so a long password costs bounded time | `MinimumStrengthValidatorTest::testAPasswordOf200CharactersIsAccepted` |
| 6.2.11 Context-specific words refused | 2 | `PasswordUserInputs` and the user inputs of `estimate()` — name, e-mail address, site name — are looked up first | `StrengthEstimatorTest`, `MinimumStrengthValidatorTest` |
| 6.2.12 Breached passwords refused | 2 | `NotCompromised` and `BreachChecker`, by k-anonymity: only five characters of the SHA-1 leave the server | `NotCompromisedValidatorTest`, `PwnedPasswordsSourceTest` |
| 6.2.4 The 3 000 most common passwords refused | 1 | Met with `NotCompromised`, whose source holds them; the offline dictionary of the estimator holds 314 | `NotCompromisedValidatorTest` |

## Left to the application

| Requirement | Level | Why it is the application's |
|---|---|---|
| 6.1.2 The documented list of context-specific words | 2 | The application knows its name, its domain and its people; it passes them as user inputs |
| 6.2.1 At least 8 characters, 15 recommended | 1 | A `Length` constraint on the form field, beside `MinimumStrength` |
| 6.2.2, 6.2.3 Changing the password, with the current one | 1 | The account pages of the application |
| 6.2.6, 6.2.7 `type=password`, paste and password managers allowed | 1 | The form's markup and scripts |
| 6.2.10 No periodic rotation | 2 | The application's account policy |
| 6.2.12 when the source cannot answer | 2 | `skipOnError` decides: refusing the password, or accepting it and counting the failure ([breaches](breaches.md)) |
