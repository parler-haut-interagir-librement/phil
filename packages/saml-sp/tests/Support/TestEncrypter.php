<?php

declare(strict_types=1);

namespace Phil\SamlSp\Tests\Support;

use DOMElement;
use RuntimeException;

/**
 * Encrypts an assertion for the tests, as the identity providers do: AES-256-CBC with RSA-OAEP (MGF1 SHA-1) and the
 * `EncryptedKey` inside the `KeyInfo` of the data, as Microsoft Entra ID sends it, or AES-256-GCM with
 * `xmlenc11#rsa-oaep` on SHA-256 and the `EncryptedKey` beside the data, as Okta can.
 */
final class TestEncrypter
{
    public const string XENC = 'http://www.w3.org/2001/04/xmlenc#';

    public static function encrypt(DOMElement $assertion, string $certificateName, string $style = 'entra', string $keyTransport = 'http://www.w3.org/2001/04/xmlenc#rsa-oaep-mgf1p', string $digest = 'sha1'): void
    {
        $document = $assertion->ownerDocument ?? throw new RuntimeException('The assertion has no document.');
        $gcm = 'okta' === $style;
        $plain = (string) $document->saveXML($assertion);
        $key = random_bytes(32);

        if ($gcm) {
            $iv = random_bytes(12);
            $cipher = $iv.openssl_encrypt($plain, 'aes-256-gcm', $key, \OPENSSL_RAW_DATA, $iv, $tag).$tag;
        } else {
            $iv = random_bytes(16);
            $cipher = $iv.openssl_encrypt($plain, 'aes-256-cbc', $key, \OPENSSL_RAW_DATA, $iv);
        }

        openssl_public_encrypt($key, $wrapped, Keys::pem($certificateName.'.crt'), \OPENSSL_PKCS1_OAEP_PADDING, $digest);
        $digestUri = ['sha1' => 'http://www.w3.org/2000/09/xmldsig#sha1', 'sha256' => 'http://www.w3.org/2001/04/xmlenc#sha256'][$digest];

        $prefix = 'okta' === $style ? 'saml2' : 'saml';
        $encrypted = Dom::create($document, 'urn:oasis:names:tc:SAML:2.0:assertion', $prefix.':EncryptedAssertion');
        $data = self::element($encrypted, 'xenc:EncryptedData');
        $data->setAttribute('Type', self::XENC.'Element');
        self::element($data, 'xenc:EncryptionMethod')->setAttribute('Algorithm', $gcm ? 'http://www.w3.org/2009/xmlenc11#aes256-gcm' : self::XENC.'aes256-cbc');
        $keyParent = $gcm ? $encrypted : self::element($data, 'ds:KeyInfo', TestSigner::DSIG);
        $encryptedKey = Dom::create($document, self::XENC, 'xenc:EncryptedKey');
        $method = self::element($encryptedKey, 'xenc:EncryptionMethod');
        $method->setAttribute('Algorithm', $keyTransport);
        self::element($method, 'ds:DigestMethod', TestSigner::DSIG)->setAttribute('Algorithm', $digestUri);

        if ('http://www.w3.org/2009/xmlenc11#rsa-oaep' === $keyTransport) {
            self::element($method, 'xenc11:MGF', 'http://www.w3.org/2009/xmlenc11#')->setAttribute('Algorithm', 'http://www.w3.org/2009/xmlenc11#mgf1'.$digest);
        }

        self::element(self::element($encryptedKey, 'xenc:CipherData'), 'xenc:CipherValue')->appendChild($document->createTextNode(base64_encode(\is_string($wrapped) ? $wrapped : '')));
        self::element(self::element($data, 'xenc:CipherData'), 'xenc:CipherValue')->appendChild($document->createTextNode(base64_encode($cipher)));
        $keyParent->appendChild($encryptedKey);

        $assertion->parentNode?->replaceChild($encrypted, $assertion);
    }

    private static function element(DOMElement $parent, string $name, string $namespace = self::XENC): DOMElement
    {
        return Dom::append($parent, $namespace, $name);
    }
}
