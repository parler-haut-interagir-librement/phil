<?php

declare(strict_types=1);

/*
 * What the shared development tooling reads of this package: its paths and its exceptions to the shared
 * configurations, nothing else.
 */
return [
    'paths' => ['src', 'tests'],
    'deptrac' => [
        // The identity tokens and the keys are signed and read by web-token/jwt-library (EH-1), from the application
        // layer only: the domain keeps no dependency on it.
        'allow' => ['^Jose\\\\.*'],
    ],
    'docs' => ['public' => true, 'translations' => 'blocking', 'words' => ['AAGUID', 'CBOR', 'COSE', 'JWKS', 'PKCE', 'TOTP', 'WebAuthn', 'userinfo']],
    'infection' => [
        'mutators' => [
            // `base << exponent` and `base * (1 << exponent)` are the same integer: dividing by one before the shift keeps it.
            'Multiplication' => [
                'ignoreSourceCodeByRegex' => ['\\$seconds = min\\(\\$this->base \\* \\(1 << \\$exponent\\), \\$this->max\\);'],
            ],
            // A numeric string and its integer are one array key, and the index read from `bindec()` is an integer: the
            // cast changes nothing a caller can see.
            'CastString' => [
                'ignoreSourceCodeByRegex' => ['\\$modules\\[\\(string\\) \\$module\\] = true === \\$on;'],
            ],
            'CastInt' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$text \\.= self::ALPHABET\\[\\(int\\) bindec.*',
                    '\\$bytes \\.= \\\\chr\\(\\(int\\) bindec.*',
                    // PHP compares and subtracts a numeric string as the number it is.
                    'return \\[\\$parts\\[1\\], \\$parts\\[2\\], \\(int\\) \\$parts\\[3\\], \\(int\\) \\$parts\\[4\\]\\];',
                ],
            ],
            // `mb_strtolower()` replaces the bytes that are not UTF-8 before the pattern reads them, and `trim()` strips the
            // newline that `$` would accept: neither flag changes what the pattern matches.
            'PregMatchRemoveFlags' => [
                'ignoreSourceCodeByRegex' => ['if \\(\'\' === \\$email \\|\\| mb_strlen.*'],
            ],
            // The lock of the signing keys is released in `finally`: releasing it again when its object is destroyed
            // changes nothing, and turning that off lets the tests see a release left out.
            'FalseValue' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$lock = \\$this->locks->createLock\\(self::LOCK, autoRelease: false\\);',
                    // A file that cannot be read gives false, and `true` is refused by the `is_string()` that follows, with
                    // the same message.
                    '\\$material = is_file\\(\\$path\\) && is_readable.*',
                    '\\$pem = is_file\\(\\$path\\) && is_readable.*',
                ],
            ],
            // What the callee does anyway: the estimator slices its inputs, a variadic parameter is a list, the
            // identifiers of a scope are distinct once split, and the diff of two lists is spread by position.
            // Doctrine reads the refresh hint with isset(): false refreshes the row as true does.
            'TrueValue' => [
                'ignoreSourceCodeByRegex' => ['->setHint\\(Query::HINT_REFRESH, true\\)'],
            ],
            'UnwrapArrayValues' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$inputs = array_values\\(array_filter.*',
                    '\\$scopes = array_values\\(array_unique\\(\\[\\.\\.\\.\\$asked.*',
                    'return new self\\(array_values\\(\\$reasons\\)\\);',
                    // `usort()` that follows numbers the addresses again.
                    '\\$addresses = array_values\\(array_filter\\(\\$this->addresses.*',
                    'throw Refused::because\\(\'client\\.scope_unknown.*',
                ],
            ],
            // `http_build_query()` leaves out the nulls, and the scope of an access token is never empty.
            'UnwrapArrayFilter' => [
                'ignoreSourceCodeByRegex' => [
                    'return QueryString::append\\(.*array_filter\\(.*',
                    '\\$accessToken = Jws::encode\\(array_filter.*',
                    'return QueryString::append\\(\\(string\\) \\$this->redirectUri, array_filter\\(\\[',
                ],
            ],
            // An account that is locked has the moment it is locked until, and the loop that revokes the tokens of a
            // session runs only when there are tokens, so the repository that holds them exists.
            'NullSafeMethodCall' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$this->journal->record\\(IdentityEventType::AccountLocked.*',
                    '\\$this->grants\\?->saveToken\\(\\$token\\);',
                ],
            ],
            // The three parts of an attested credential are all read or none is, at registration as in a statement.
            // Messenger and the HTTP client are both installed or both absent in a test run: a test cannot make one of
            // them vanish.
            'LogicalOr' => [
                'ignoreSourceCodeByRegex' => [
                    'if \\(null === \\$data->credentialId.*',
                    'if \\(null === \\$data->coseKey \\|\\| null === \\$data->aaguid\\) \\{',
                    'if \\(!interface_exists\\(MessageBusInterface::class\\) \\|\\| !interface_exists\\(HttpClientInterface::class\\)\\) \\{',
                    // A stored token that is another account's, of another purpose or of another expiry fails the signature
                    // that is verified over its fingerprint just after: the checks of the token only spare the work.
                    'if \\(!\\$token instanceof LinkToken \\|\\| \\$token->accountId\\(\\).*',
                    // OpenSSL always parses a certificate it has read, so `$parsed` is an array; a version 1 or 2 certificate
                    // has no extensions, and the basic constraints that follow refuse it.
                    'if \\(!\\\\is_array\\(\\$parsed\\) \\|\\| 2 !==.*',
                ],
            ],
            // OpenSSL always parses a certificate it has read: `$parsed` is an array, and its validity dates are integers.
            // A key that is not there reads as null, which `??` takes as it takes an empty array. A file is read only after
            // `is_file()` and `is_readable()`: a directory read gives an empty string, refused just after like a missing file.
            'LogicalAnd' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$subject = \\\\is_array\\(\\$parsed\\) &&.*',
                    '\\$extensions = \\\\is_array\\(\\$parsed\\) &&.*',
                    'return \\\\is_int\\(\\$from\\) &&.*',
                    '\\$constraints = \\\\is_array\\(\\$parsed\\) &&.*',
                    '\\$pem = is_file\\(\\$path\\) && is_readable.*',
                ],
            ],
            // An assertion that holds for every value the query can give.
            'LogicalOrAllSubExprNegation' => [
                'ignoreSourceCodeByRegex' => ['\\\\assert\\(null === \\$token \\|\\| \\$token instanceof LinkToken\\);'],
            ],
            // A value is read at the same depth as its key, and the key is checked first.
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    // The mark of a missing integer header (-1, -2 or -0) is a length below 1 whatever its value: refused as 0
                    // is, with the same message; and the length that `substr()` reads is used only when it is at least 1.
                    '\\$partLength = \\$offset \\+ 2 <= \\$size.*',
                    '\\$number = ltrim\\(substr\\(\\$der, \\$offset \\+ 2, max\\(0.*',
                    '\\$values\\[\\$key\\] = \\$this->item\\(\\$depth \\+ 1\\);',
                    // `trim()` takes the space that the shorter prefix leaves.
                    '\\$parts = explode\\(\':\', sodium_base642bin\\(trim\\(substr.*',
                    // A text that PHP cannot read as a duration counts as zero seconds; -1 is refused as 0 is, with the
                    // same message.
                    '\\$seconds = 0;',
                ],
            ],
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$partLength = \\$offset \\+ 2 <= \\$size.*',
                    '\\$number = ltrim\\(substr\\(\\$der, \\$offset \\+ 2, max\\(0.*',
                ],
            ],
            // A header that ends the signature one byte earlier or later has no length to read, and a length below 1 is
            // refused with the same message.
            'LessThanOrEqualTo' => [
                'ignoreSourceCodeByRegex' => ['\\$partLength = \\$offset \\+ 2 <= \\$size.*'],
            ],
            'Plus' => [
                'ignoreSourceCodeByRegex' => ['\\$values\\[\\$key\\] = \\$this->item\\(\\$depth \\+ 1\\);'],
            ],
        ],
    ],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
    'fuzz' => [
        // The readers of what browsers, authenticators and clients send, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            'cbor' => 'tests/Fuzz/cbor.php',
            'authenticator-data' => 'tests/Fuzz/authenticator-data.php',
            'client-data' => 'tests/Fuzz/client-data.php',
            'jwt' => 'tests/Fuzz/jwt.php',
            'authorization-request' => 'tests/Fuzz/authorization-request.php',
            'attestation' => 'tests/Fuzz/attestation.php',
        ],
    ],
];
