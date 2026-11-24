# Testing

The tests are PHPUnit tests, run from the root of the monorepo with `composer test`, or on their own:

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

- `tests/Unit` tests the configuration and the services it registers, the Flex recipe, the identity providers, the
  sign-in, the users, the logout, the authenticator, the user provider, the logout listener, the controller, the
  check command, the choice of the replay store and the locality of cache pools, without a kernel.
- `tests/Functional` boots `tests/Functional/App/TestKernel`: FrameworkBundle, SecurityBundle with one firewall running
  the authenticator and Symfony's logout, and the bundle with two identity providers shaped as Microsoft Entra ID —
  `acme`, its metadata in a file, and `portal`, which may send unsolicited responses. A browser signs in from the login
  to the dashboard, is refused a response replayed or carried by another browser, signs out towards the identity
  provider and is signed out by it. In its `doctrine` environment, which adds a Doctrine DBAL connection on SQLite, a
  replayed assertion is refused by the table of `DbalReplayGuard`; in `test`, by the cache pool; in `prod`, its cache
  pool on the filesystem stops the container from building. These three tests give their kernels a directory of their
  own under the absolute temporary directory, removed after each test: the SQLite file, the cache and the metadata do
  not depend on the current directory, nor on another test running at the same time.

The responses and logout messages of the tests are written by `tests/Fixtures/Saml.php` and signed by the library's
signer with the test keys of `tests/Fixtures/keys`: the keys of the tests of phil/saml-sp, made for these tests only and
documented in its testing guide. The verification of signatures against an independent signer, the signature wrapping
and the other attacks are tested in phil/saml-sp.
