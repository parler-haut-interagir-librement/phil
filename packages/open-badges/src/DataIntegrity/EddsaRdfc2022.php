<?php

declare(strict_types=1);

namespace Phil\OpenBadges\DataIntegrity;

use DateTimeImmutable;
use DateTimeZone;
use Phil\OpenBadges\Contract\ContextLoader;
use Phil\OpenBadges\Contract\VerificationMethods;
use Phil\OpenBadges\Encoding\Base58Btc;
use Phil\OpenBadges\Exception\InvalidBadge;
use Phil\OpenBadges\Exception\InvalidCredential;
use Phil\OpenBadges\JsonLd\BundledContexts;
use Phil\OpenBadges\JsonLd\ToRdf;
use Phil\OpenBadges\Key\Ed25519Signer;
use Phil\OpenBadges\Rdf\Canonicalizer;

/**
 * The `eddsa-rdfc-2022` cryptosuite of Data Integrity: a proof embedded in the document, signed with an Ed25519 key
 * over the SHA-256 of the canonical form (RDFC-1.0) of the proof's options and of the document's.
 *
 * The documents are read as JSON-LD through the contexts the loader gives — by default only the bundled ones —, in
 * safe mode: a property that no context defines is refused, since it would be left out of the signature.
 */
final readonly class EddsaRdfc2022
{
    public const string CRYPTOSUITE = 'eddsa-rdfc-2022';

    private ToRdf $rdf;

    public function __construct(ContextLoader $contexts = new BundledContexts())
    {
        $this->rdf = new ToRdf($contexts);
    }

    /**
     * The document with its proof, asserted by the verification method at the moment given.
     *
     * @param array<string, mixed> $document an unsecured document, with its `@context`
     *
     * @return array<string, mixed>
     */
    public function sign(array $document, Ed25519Signer $signer, string $verificationMethod, DateTimeImmutable $created): array
    {
        if (\array_key_exists('proof', $document) || !\array_key_exists('@context', $document)) {
            throw InvalidBadge::because('A document given a Data Integrity proof has its "@context" and no proof yet.');
        }

        $options = [
            'type' => 'DataIntegrityProof',
            'cryptosuite' => self::CRYPTOSUITE,
            'created' => $created->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
            'verificationMethod' => $verificationMethod,
            'proofPurpose' => 'assertionMethod',
        ];

        return [...$document, 'proof' => [...$options, 'proofValue' => 'z'.Base58Btc::encode($signer->sign($this->hashData($document, $options)))]];
    }

    /**
     * The document without its proof, once the proof verifies.
     *
     * @param array<array-key, mixed> $document a secured document
     *
     * @return array<array-key, mixed>
     *
     * @throws InvalidCredential
     */
    public function verify(array $document, VerificationMethods $methods): array
    {
        $proof = $document['proof'] ?? null;

        if (!\is_array($proof) || array_is_list($proof)) {
            throw InvalidCredential::because('A document secured with Data Integrity has one proof, an object.');
        }

        if ('DataIntegrityProof' !== ($proof['type'] ?? null) || self::CRYPTOSUITE !== ($proof['cryptosuite'] ?? null)) {
            throw InvalidCredential::because(\sprintf('The proof is not a DataIntegrityProof of the cryptosuite %s.', self::CRYPTOSUITE));
        }

        if ('assertionMethod' !== ($proof['proofPurpose'] ?? null) || !\is_string($proof['verificationMethod'] ?? null) || !\is_string($proof['proofValue'] ?? null) || !str_starts_with($proof['proofValue'], 'z')) {
            throw InvalidCredential::because('The proof asserts with a verification method and holds its value in base 58.');
        }

        $key = $methods->key($proof['verificationMethod']);
        $signature = Base58Btc::decode(substr($proof['proofValue'], 1));
        unset($document['proof'], $proof['proofValue']);

        if ('EdDSA' !== $key->algorithm() || !$key->verify($this->hashData($document, $proof), $signature)) {
            throw InvalidCredential::because('The signature of the credential does not verify.');
        }

        return $document;
    }

    /**
     * The canonical form of a document, as sorted N-Quads.
     *
     * @param array<array-key, mixed> $document
     */
    public function canonicalize(array $document): string
    {
        return Canonicalizer::canonicalize($this->rdf->quads($document));
    }

    /**
     * @param array<array-key, mixed> $document
     * @param array<array-key, mixed> $options
     */
    private function hashData(array $document, array $options): string
    {
        unset($options['@context']);
        $options['@context'] = $document['@context'] ?? null;

        return hash('sha256', $this->canonicalize($options), true).hash('sha256', $this->canonicalize($document), true);
    }
}
