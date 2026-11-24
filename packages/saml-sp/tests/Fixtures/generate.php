<?php

declare(strict_types=1);

/*
 * Writes the response fixtures of tests/Fixtures/responses from the test keys, with the signer and the encrypter of
 * tests/Support, never with the library's own: `php packages/saml-sp/tests/Fixtures/generate.php` from the root of the
 * monorepo. The encrypted fixtures change at each run, since their keys and vectors are random.
 */

use Phil\SamlSp\Tests\Support\Responses;
use Phil\SamlSp\Tests\Support\TestEncrypter;
use Phil\SamlSp\Tests\Support\TestSigner;

require __DIR__.'/../../../../vendor/autoload.php';

$write = static function (string $name, DOMDocument $document): void {
    file_put_contents(__DIR__.'/responses/'.$name, $document->saveXML());
};

$entra = static function (): DOMDocument {
    $document = Responses::entra();
    TestSigner::sign(Responses::assertion($document), 'entra', '');

    return $document;
};

$okta = static function (): DOMDocument {
    $document = Responses::okta();
    TestSigner::sign(Responses::assertion($document), 'okta', 'ds', ['xs']);

    return $document;
};

// Microsoft Entra ID, as configured by default: the assertion signed, the response not.
$write('entra-signed-assertion.xml', $entra());

// Microsoft Entra ID with "Sign SAML response and assertion".
$document = $entra();
TestSigner::sign(Responses::root($document), 'entra', '');
$write('entra-signed-response-and-assertion.xml', $document);

// Microsoft Entra ID with token encryption: the signed assertion encrypted in AES-256-CBC, the key in RSA-OAEP.
$document = $entra();
TestEncrypter::encrypt(Responses::assertion($document), 'sp');
$write('entra-encrypted.xml', $document);

// Microsoft Entra ID refusing the sign-in, unsigned.
$write('entra-error-status.xml', Responses::entra(['status' => 'urn:oasis:names:tc:SAML:2.0:status:Responder']));

// Okta: the assertion signed with the `xs` prefix kept, then the response signed.
$document = $okta();
TestSigner::sign(Responses::root($document), 'okta');
$write('okta-signed.xml', $document);

// Okta with assertion encryption: AES-256-GCM, the key in RSA-OAEP on SHA-256 beside the data, the response signed.
$document = $okta();
TestEncrypter::encrypt(Responses::assertion($document), 'sp', 'okta', 'http://www.w3.org/2009/xmlenc11#rsa-oaep', 'sha256');
TestSigner::sign(Responses::root($document), 'okta');
$write('okta-encrypted.xml', $document);

// Signature wrapping: the signed assertion moved into an extension, a forged assertion in its place.
$document = $entra();
$signed = Responses::assertion($document);
$forged = $signed->cloneNode(true);
assert($forged instanceof DOMElement);
$forged->setAttribute('ID', '_forged');
Responses::first($document, '//saml:Assertion/saml:Subject/saml:NameID')->textContent;
$extensions = $document->createElementNS(Responses::PROTOCOL, 'samlp:Extensions');
Responses::root($document)->insertBefore($extensions, $signed);
$extensions->appendChild($signed);
Responses::root($document)->appendChild($forged);
foreach ($forged->getElementsByTagNameNS(Responses::ASSERTION, 'NameID') as $nameId) {
    $nameId->textContent = 'mallory@example.org';
}
foreach ($forged->getElementsByTagNameNS(TestSigner::DSIG, 'Signature') as $signature) {
    $signature->parentNode?->removeChild($signature);
}
$write('attack-xsw-extensions.xml', $document);

// Signature wrapping by a repeated ID: an element of the signed response's ID planted beside it.
$document = $entra();
TestSigner::sign(Responses::root($document), 'entra', '');
$planted = $document->createElementNS(Responses::PROTOCOL, 'samlp:Extensions');
$planted->setAttribute('ID', '_c7055387-af61-4fce-8b98-e2927324b306');
Responses::root($document)->appendChild($planted);
$write('attack-xsw-duplicate-id.xml', $document);

// Signature wrapping: the signature of the response moved into the assertion, which it does not reference.
$document = Responses::entra();
TestSigner::sign(Responses::root($document), 'entra');
$signature = Responses::first($document, '/samlp:Response/ds:Signature');
$assertion = Responses::assertion($document);
$assertion->insertBefore($signature, Responses::first($document, '//saml:Assertion/saml:Subject'));
$write('attack-signature-moved.xml', $document);

// The name identifier changed after signing.
$document = $entra();
Responses::first($document, '//saml:Assertion/saml:Subject/saml:NameID')->firstChild->nodeValue = 'mallory@example.org';
$write('attack-modified.xml', $document);

// A comment planted in the name identifier after signing: canonicalisation drops it, the whole text is read.
$document = $entra();
$nameId = Responses::first($document, '//saml:Assertion/saml:Subject/saml:NameID');
$text = $nameId->firstChild;
assert($text instanceof DOMText);
$rest = $text->splitText(12);
$nameId->insertBefore($document->createComment(''), $rest);
$write('attack-comment.xml', $document);

// Signed with a key the identity provider does not have, its certificate in KeyInfo.
$document = Responses::entra();
TestSigner::sign(Responses::assertion($document), 'attacker', '');
$write('attack-attacker-key.xml', $document);

// Nothing signed.
$write('attack-unsigned.xml', Responses::entra());

// Signed with RSA-SHA1 and a SHA-1 digest.
$document = Responses::entra();
TestSigner::sign(Responses::assertion($document), 'entra', '', [], 'http://www.w3.org/2000/09/xmldsig#rsa-sha1', 'http://www.w3.org/2000/09/xmldsig#sha1');
$write('attack-sha1.xml', $document);

// Signed with a 1024-bit key, certificate trusted by a careless configuration.
$document = Responses::entra();
TestSigner::sign(Responses::assertion($document), 'weak', '');
$write('weak-key.xml', $document);
