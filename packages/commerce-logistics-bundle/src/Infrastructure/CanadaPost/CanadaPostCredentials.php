<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Infrastructure\CanadaPost;

use InvalidArgumentException;
use SensitiveParameter;

/**
 * How the shop signs in to Canada Post's API: the key and secret of its developer application, its customer number,
 * its contract when it has one — which gives its commercial rates —, the API's address and the address of its access
 * tokens, and the language of its messages. They come from the configuration, which reads them from the environment
 * or the secrets vault; none is written in code.
 */
final readonly class CanadaPostCredentials
{
    public function __construct(
        public string $apiKey,
        #[SensitiveParameter]
        public string $apiSecret,
        public string $customerNumber,
        public string $baseUri,
        public string $tokenUri,
        public ?string $contractId = null,
        public string $language = 'en-CA',
    ) {
        if ('' === $apiKey || '' === $apiSecret) {
            throw new InvalidArgumentException('Canada Post is asked with the key and the secret of a developer application.');
        }

        if (1 !== preg_match('/^\d{1,10}$/D', $customerNumber) || (null !== $contractId && 1 !== preg_match('/^\d{1,10}$/D', $contractId))) {
            throw new InvalidArgumentException('A Canada Post customer number, and a contract when given, are 1 to 10 digits.');
        }

        foreach ([$baseUri, $tokenUri] as $uri) {
            if (1 !== preg_match('#^https://[a-z0-9.-]+(:\d{1,5})?(/[\x21-\x7E]*)?$#iD', $uri)) {
                throw new InvalidArgumentException('Canada Post is reached over HTTPS only: the API and its tokens have https:// addresses.');
            }
        }

        if (!\in_array($language, ['en-CA', 'fr-CA'], true)) {
            throw new InvalidArgumentException('Canada Post answers in en-CA or fr-CA.');
        }
    }
}
