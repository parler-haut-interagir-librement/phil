<?php

declare(strict_types=1);

namespace Phil\Lti\Tool;

use Phil\Lti\Contract\ContentItem;
use Phil\Lti\Contract\KeyStore;
use Phil\Lti\Exception\InvalidRequest;
use Phil\Lti\Http\FormPost;
use Phil\Lti\Jwt\JwtSigner;
use Phil\Lti\Message\Claim;
use Phil\Lti\Message\DeepLinkingSettings;
use Phil\Lti\Message\Launch;
use Phil\Lti\Message\MessageType;
use Phil\Lti\Security\Random;
use Psr\Clock\ClockInterface;

/**
 * The answer of the tool to a deep linking request: the content items picked, signed with the tool's key as an
 * `LtiDeepLinkingResponse` for the platform, with the opaque data of the request echoed, posted by the browser to the
 * return address of the request. The items must be of the types the request accepts, one at most unless it accepts
 * several; an empty list says nothing was picked.
 */
final readonly class DeepLinkingResponder
{
    public const int LIFETIME = 300;

    public function __construct(
        private KeyStore $keys,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param list<ContentItem> $items
     * @param string|null       $message      a message for the person, shown by the platform
     * @param string|null       $log          a message for the logs of the platform
     * @param string|null       $errorMessage an error for the person, when the picking failed
     * @param string|null       $errorLog     an error for the logs of the platform
     *
     * @throws InvalidRequest when the launch is not a deep linking request, or the items are not what it accepts
     */
    public function respond(Launch $launch, array $items, ?string $message = null, ?string $log = null, ?string $errorMessage = null, ?string $errorLog = null): FormPost
    {
        $settings = $launch->message->deepLinking;

        if (!$launch->isDeepLinking() || !$settings instanceof DeepLinkingSettings) {
            throw InvalidRequest::because('Content items answer a deep linking request only.');
        }

        if (\count($items) > 1 && !$settings->acceptMultiple) {
            throw InvalidRequest::because('The deep linking request accepts one content item at most.');
        }

        foreach ($items as $item) {
            if (!$settings->accepts($item->type())) {
                throw InvalidRequest::because(\sprintf('The deep linking request does not accept items of the type "%s".', $item->type()));
            }
        }

        $now = $this->clock->now()->getTimestamp();
        $claims = [
            'iss' => $launch->clientId,
            'aud' => $launch->issuer,
            'iat' => $now,
            'exp' => $now + self::LIFETIME,
            'nonce' => Random::token(),
            Claim::MESSAGE_TYPE => MessageType::DeepLinkingResponse->value,
            Claim::VERSION => Claim::LTI_VERSION,
            Claim::DEPLOYMENT_ID => $launch->deploymentId(),
            Claim::CONTENT_ITEMS => array_map(static fn (ContentItem $item): array => $item->toArray(), $items),
        ];

        foreach ([Claim::DATA => $settings->data, Claim::MESSAGE => $message, Claim::LOG => $log, Claim::ERROR_MESSAGE => $errorMessage, Claim::ERROR_LOG => $errorLog] as $name => $value) {
            if (null !== $value) {
                $claims[$name] = $value;
            }
        }

        return new FormPost($settings->returnUrl, ['JWT' => JwtSigner::sign($claims, $this->keys->signingKey())]);
    }
}
