# Testing

The tests are PHPUnit tests in `tests/Unit`, run from the root of the monorepo with `composer test`, or on their own:

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

- The BigBlueButton adapter is tested against Symfony's `MockHttpClient`: every checksum is computed again and checked,
  every answer is a document the test writes, and the notifications are signed with the test's secret.
- The Jitsi tokens are verified as a server would: the HMAC under the application secret, the RSA signature under the
  public key of a key pair drawn for the test.
- The clock is Symfony's `MockClock`, at 1 700 000 000.

## Fuzz target

`responses`, in `tests/Fuzz/responses.php`, run by `composer fuzz`: its first byte picks the reader — the recordings or
the presences BigBlueButton answers, its attendance, a Jitsi notification signed with the target's secret —, the rest
is the body. Whatever it holds, the reader ends in a result or in a `VirtualClassroomException`. The seeds are in
`tests/Fuzz/corpus/responses`.

## Benchmark

`tests/Benchmark/ClassroomBench.php`, run by `composer bench`: a BigBlueButton join link, a Jitsi join link, the
attendance of 500 attendees posted at the end of a session, and the share of a session of 200 stays.
