<?php

declare(strict_types=1);

namespace Phil\SamlSp\Encryption;

use DOMElement;
use Phil\SamlSp\Exception\DecryptionFailed;
use Phil\SamlSp\Key\PrivateKey;
use Phil\SamlSp\Xml\Base64;
use Phil\SamlSp\Xml\Elements;
use Phil\SamlSp\Xml\Ns;

/**
 * Decrypts the `EncryptedData` of an `EncryptedAssertion` with the private keys of the service provider: the key
 * transport is RSA-OAEP (`rsa-oaep-mgf1p`, or `xmlenc11#rsa-oaep` with the same digest for OAEP and MGF1), never
 * RSA PKCS#1 v1.5; the data is AES in CBC or GCM. The `EncryptedKey` is read inside the `KeyInfo` of the data or beside
 * it, as Okta places it. Every failure past the algorithms reads the same, so that the answer tells nothing of the
 * padding or of the key.
 */
final class Decrypter
{
    private const string OAEP_MGF1P = 'http://www.w3.org/2001/04/xmlenc#rsa-oaep-mgf1p';

    private const string OAEP = 'http://www.w3.org/2009/xmlenc11#rsa-oaep';

    private const string MGF1_PREFIX = 'http://www.w3.org/2009/xmlenc11#mgf1';

    private const string FAILED = 'The encrypted assertion cannot be decrypted with the keys of the service provider.';

    /** @var array<string, string> */
    private const array DIGESTS = [
        'http://www.w3.org/2000/09/xmldsig#sha1' => 'sha1',
        'http://www.w3.org/2001/04/xmlenc#sha256' => 'sha256',
        'http://www.w3.org/2001/04/xmldsig-more#sha384' => 'sha384',
        'http://www.w3.org/2001/04/xmlenc#sha512' => 'sha512',
    ];

    private function __construct()
    {
    }

    /**
     * @param list<PrivateKey> $keys the keys of the service provider, the current one first
     *
     * @return string the plaintext: the assertion as XML
     */
    public static function decrypt(DOMElement $encryptedAssertion, array $keys): string
    {
        $data = Elements::one($encryptedAssertion, Ns::XENC, 'EncryptedData') ?? throw DecryptionFailed::because('An encrypted assertion holds one EncryptedData.');
        $algorithm = DataEncryption::fromUri(self::algorithm($data));
        $encryptedKey = self::encryptedKey($encryptedAssertion, $data);
        [$padding, $digest] = self::transport($encryptedKey);
        $wrapped = self::cipherValue($encryptedKey);
        $cipherText = self::cipherValue($data);

        foreach ($keys as $key) {
            if (openssl_private_decrypt($wrapped, $symmetric, $key->key(), $padding, $digest) && \is_string($symmetric) && $algorithm->keyLength() === \strlen($symmetric)) {
                return self::decryptData($algorithm, $symmetric, $cipherText);
            }
        }

        throw DecryptionFailed::because(self::FAILED);
    }

    private static function encryptedKey(DOMElement $encryptedAssertion, DOMElement $data): DOMElement
    {
        $keyInfo = Elements::one($data, Ns::DSIG, 'KeyInfo');
        $inside = $keyInfo instanceof DOMElement ? Elements::all($keyInfo, Ns::XENC, 'EncryptedKey') : [];
        $candidates = [...$inside, ...Elements::all($encryptedAssertion, Ns::XENC, 'EncryptedKey')];

        if (1 !== \count($candidates)) {
            throw DecryptionFailed::because('An encrypted assertion carries one EncryptedKey.');
        }

        return $candidates[0];
    }

    /**
     * @return array{int, string}
     */
    private static function transport(DOMElement $encryptedKey): array
    {
        $method = Elements::one($encryptedKey, Ns::XENC, 'EncryptionMethod') ?? throw DecryptionFailed::because('An EncryptedKey has one EncryptionMethod.');
        $uri = (string) Elements::attribute($method, 'Algorithm');
        $digestMethod = Elements::one($method, Ns::DSIG, 'DigestMethod');
        $digest = self::DIGESTS[$digestMethod instanceof DOMElement ? (string) Elements::attribute($digestMethod, 'Algorithm') : 'http://www.w3.org/2000/09/xmldsig#sha1'] ?? null;

        if (self::OAEP_MGF1P === $uri && 'sha1' === $digest) {
            return [\OPENSSL_PKCS1_OAEP_PADDING, 'sha1'];
        }

        $mgf = Elements::one($method, Ns::XENC11, 'MGF');
        $mgfDigest = $mgf instanceof DOMElement ? substr((string) Elements::attribute($mgf, 'Algorithm'), \strlen(self::MGF1_PREFIX)) : 'sha1';

        if (self::OAEP === $uri && null !== $digest && $mgfDigest === $digest) {
            return [\OPENSSL_PKCS1_OAEP_PADDING, $digest];
        }

        throw DecryptionFailed::because(\sprintf('The key transport "%s" is refused: RSA-OAEP with the same digest for OAEP and MGF1 is read.', $uri));
    }

    private static function decryptData(DataEncryption $algorithm, string $key, string $cipherText): string
    {
        $ivLength = $algorithm->isGcm() ? 12 : 16;
        $tagLength = $algorithm->isGcm() ? 16 : 0;

        if (\strlen($cipherText) < $ivLength + $tagLength + 16) {
            throw DecryptionFailed::because(self::FAILED);
        }

        $iv = substr($cipherText, 0, $ivLength);
        $body = substr($cipherText, $ivLength, \strlen($cipherText) - $ivLength - $tagLength);
        $plain = $algorithm->isGcm()
            ? openssl_decrypt($body, $algorithm->cipher(), $key, \OPENSSL_RAW_DATA, $iv, substr($cipherText, -$tagLength))
            : self::unpad(openssl_decrypt($body, $algorithm->cipher(), $key, \OPENSSL_RAW_DATA | \OPENSSL_ZERO_PADDING, $iv));

        if (false === $plain || '' === $plain) {
            throw DecryptionFailed::because(self::FAILED);
        }

        return $plain;
    }

    /**
     * XML Encryption pads CBC with any bytes and the count in the last one, which PKCS#7 does not accept.
     */
    private static function unpad(string|false $padded): string|false
    {
        if (false === $padded || '' === $padded) {
            return false;
        }

        $count = \ord($padded[-1]);

        return $count < 1 || $count > 16 ? false : substr($padded, 0, -$count);
    }

    private static function algorithm(DOMElement $element): string
    {
        $method = Elements::one($element, Ns::XENC, 'EncryptionMethod') ?? throw DecryptionFailed::because('An EncryptedData has one EncryptionMethod.');

        return (string) Elements::attribute($method, 'Algorithm');
    }

    private static function cipherValue(DOMElement $element): string
    {
        $data = Elements::one($element, Ns::XENC, 'CipherData');
        $value = $data instanceof DOMElement ? Elements::one($data, Ns::XENC, 'CipherValue') : null;
        $decoded = $value instanceof DOMElement ? Base64::decode($value->textContent) : null;

        if (null === $decoded) {
            throw DecryptionFailed::because('An encrypted value is base64 in a CipherValue.');
        }

        return $decoded;
    }
}
