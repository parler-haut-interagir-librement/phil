<?php

declare(strict_types=1);

/*
 * What the shared development tooling reads of this package: its paths and its exceptions to the shared
 * configurations, nothing else.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => [
        'public' => true,
        // The padding of RSA key transport and the attack it resists, the object that encrypts, the provisioning protocol
        // the package leaves to others, and the verb of a map that is undone.
        'words' => ['Bleichenbacher', 'encrypter', 'OAEP', 'SCIM', 'unmaps'],
    ],
    'deptrac' => [
        // The core is plain PHP; DbalReplayGuard, which keeps the accepted assertions in a table through Doctrine DBAL,
        // suggested and never required, is the adapter that alone sees it.
        'layers' => [
            'PhilSamlSp' => '^Phil\\\\SamlSp\\\\(?!Store\\\\DbalReplayGuard$).*',
            'PhilSamlSpDbal' => '^Phil\\\\SamlSp\\\\Store\\\\DbalReplayGuard$',
        ],
        'ruleset' => [
            'PhilSamlSp' => ['DefaultAllowed', 'PhilSafeHttp', 'PhilUrl'],
            'PhilSamlSpDbal' => ['PhilSamlSp', 'DefaultAllowed', 'Forbidden'],
        ],
    ],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
    'fuzz' => [
        // The readers of what an identity provider and a browser send, from the seeds of tests/Fuzz/corpus/saml-xml.
        'targets' => [
            'saml-xml' => 'tests/Fuzz/xml.php',
        ],
    ],
    'infection' => [
        'mutators' => [
            // Every line below changes nothing a caller or a test can see: the reason is given above each.
            //
            // `openssl_sign()` and `openssl_x509_export()` set their output text exactly when they return true, so the
            // `is_string()` that follows only tells PHPStan so; `openssl_private_decrypt()` likewise leaves the text null
            // when it fails, so a key that fails never has the length of the key a first one gave; PHP's `C14N()` of a
            // `SignedInfo`, which holds no comment, is the same with or without comments.
            'LogicalOr' => [
                'ignoreSourceCodeByRegex' => [
                    '.*!openssl_sign\\(\\$query, \\$signature, .*\\|\\| !\\\\is_string\\(\\$signature\\).*',
                    '.*!openssl_x509_export\\(\\$certificate, \\$normalised\\) \\|\\| !\\\\is_string\\(\\$normalised\\).*',
                    '.*!openssl_sign\\(\\(string\\) \\$signedInfo->C14N\\(true, false\\), .*',
                    // The attributes of `//@ID` are attributes with an owner element; the checks tell PHPStan so. A
                    // document that loaded has its root element: `loadXML()` returns false otherwise.
                    '.*!\\$attribute instanceof DOMAttr \\|\\| !\\$attribute->ownerElement instanceof DOMElement.*',
                    '.*!\\$loaded \\|\\| !\\$document->documentElement instanceof DOMElement.*',
                ],
            ],
            'LogicalAnd' => [
                'ignoreSourceCodeByRegex' => [
                    '.*openssl_private_decrypt\\(\\$wrapped, .*',
                    // A certificate that was read has a parsed name; the guard tells PHPStan so.
                    '.*false !== \\$parsed && \\\\is_string\\(\\$parsed\\[\'name\'\\] \\?\\? null\\).*',
                ],
            ],
            'FalseValue' => [
                'ignoreSourceCodeByRegex' => ['.*openssl_sign\\(\\(string\\) \\$signedInfo->C14N\\(true, false\\), .*'],
            ],
            // `explode()` gives one or two items, so `array_pad()` to three changes nothing the list reads; OpenSSL reads
            // a PEM whose lines are 63 or 65 characters long as it reads one of 64, and writes it back the same;
            // `createFromFormat()` reads the microseconds `u` as a fraction, so a text padded to five digits reads as the
            // same six, and padded to seven is cut to six; the cipher text of CBC has no tag, and a text one byte too
            // short only fails later, in the decryption, with the same refusal; the guard of the length is there so that
            // OpenSSL is never asked for a text it would warn about.
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '.*array_pad\\(explode\\(\'=\', \\$pair, 2\\), 2, \'\'\\);',
                    '.*chunk_split\\(Base64::encode\\(\\$der\\), 64, .*',
                    '.*\\$micro = substr\\(str_pad\\(.*',
                ],
            ],
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '.*chunk_split\\(Base64::encode\\(\\$der\\), 64, .*',
                    '.*\\$micro = substr\\(str_pad\\(.*',
                    '.*\\$tagLength = \\$algorithm->isGcm\\(\\) \\? 16 : 0;',
                    '.*\\\\strlen\\(\\$cipherText\\) < \\$ivLength \\+ \\$tagLength \\+ 16.*',
                ],
            ],
            'Plus' => [
                'ignoreSourceCodeByRegex' => ['.*\\\\strlen\\(\\$cipherText\\) < \\$ivLength \\+ \\$tagLength \\+ 16.*'],
            ],
            // An attribute that is absent is null, which PHP reads as the empty text as an array key and in a comparison
            // with a text, and `preg_replace()` gives null only for a pattern that is wrong, which this one is not: the
            // casts only tell PHPStan the values are text.
            'CastString' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$uri = \\(string\\) Elements::attribute\\(\\$method, \'Algorithm\'\\);',
                    '.*self::DIGESTS\\[\\$digestMethod instanceof DOMElement \\? \\(string\\) .*',
                    '.*\\$plain = \\(string\\) preg_replace\\(.*',
                ],
            ],
            // The throws behind `??` below read what an earlier check has already made certain: the root element of a
            // document that loaded, the document of an element that is in one, the assertion and the reference an earlier
            // step found; they tell PHPStan so.
            'Throw_' => [
                'ignoreSourceCodeByRegex' => [
                    '.*throw DecryptionFailed::because\\(self::FAILED\\);',
                    '.*Elements::one\\(\\$response, Ns::ASSERTION, \'Assertion\'\\) \\?\\? throw .*',
                    '.*\\$root = \\$document->documentElement \\?\\? throw .*',
                    '.*\\$root = SafeXml::load\\(\\$xml, \\$this->budget\\)->documentElement \\?\\? throw .*',
                    '.*\\$document = \\$element->ownerDocument \\?\\? throw .*',
                    '.*\\$reference = Elements::one\\(\\$signedInfo, Ns::DSIG, \'Reference\'\\) \\?\\? throw .*',
                ],
            ],
            // `InvalidKey` of a certificate reaches the reader inside the `try` that wraps every refusal of the library in
            // `InvalidMetadata`; a key of the verifier is read from certificates already read, so none comes out of it.
            'Catch_' => [
                'ignoreSourceCodeByRegex' => ['.*catch \\(InvalidXml\\|InvalidSignature\\|InvalidKey \\$exception\\).*'],
            ],
            // The document of an element of a document in memory is never null.
            'NullSafeMethodCall' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$root->ownerDocument\\?->saveXML\\(.*',
                ],
            ],
            // `isset()` is true of a key whose value is false, and only the keys of the transforms seen are read.
            'TrueValue' => [
                'ignoreSourceCodeByRegex' => ['.*\\$seen\\[\\$algorithm\\] = true;'],
            ],
            // The `finally` blocks restore the signature to its element and the settings of libxml when `C14N()` or
            // `loadXML()` throws, which neither does; PHP's canonicalisation ignores an empty prefix, so the prefix list
            // read without its trim gives the same text.
            'UnwrapFinally' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$canonical = \\$element->C14N\\(true, false, null, \\$prefixes\\);',
                    '.*\\$loaded = \\$document->loadXML\\(\\$xml, \\\\LIBXML_NONET\\);',
                ],
            ],
            'UnwrapTrim' => [
                'ignoreSourceCodeByRegex' => ['.*trim\\(\\(string\\) Elements::attribute\\(\\$inclusive, \'PrefixList\'\\)\\).*'],
            ],
            // The key of a replayed assertion is the length of the issuer, a colon, the issuer and the ID; with the ID
            // before the issuer, two keys of one length are the same only when their parts are, so the order changes
            // nothing.
            'Concat' => [
                'ignoreSourceCodeByRegex' => ['.*\\$key = \\\\strlen\\(\\$issuer\\)\\.\':\'\\.\\$issuer\\.\\$assertionId;'],
            ],
        ],
    ],
];
