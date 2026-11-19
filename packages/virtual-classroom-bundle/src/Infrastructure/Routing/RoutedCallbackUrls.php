<?php

declare(strict_types=1);

namespace Phil\VirtualClassroomBundle\Infrastructure\Routing;

use Override;
use Phil\Url\Exception\InvalidUrl;
use Phil\Url\Url;
use Phil\VirtualClassroom\RoomId;
use Phil\VirtualClassroom\Webhook;
use Phil\VirtualClassroomBundle\Application\Contract\CallbackUrls;
use Phil\VirtualClassroomBundle\Application\WebhookSigner;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The addresses of the webhook of a room, from the route `phil_virtual_classroom_room_webhook`, each with its proof.
 * An address `phil/url` refuses — `localhost` outside development, an IP address — gives no callback: the room then
 * relies on `phil:virtual-classroom:sync`.
 */
final readonly class RoutedCallbackUrls implements CallbackUrls
{
    public const string ROUTE = 'phil_virtual_classroom_room_webhook';

    public function __construct(
        private UrlGeneratorInterface $urls,
        private WebhookSigner $signer,
        private bool $allowLocalhost = false,
    ) {
    }

    #[Override]
    public function ended(string $provider, RoomId $room): ?Url
    {
        return $this->url($provider, $room, Webhook::ENDED);
    }

    #[Override]
    public function events(string $provider, RoomId $room): ?Url
    {
        return $this->url($provider, $room, Webhook::EVENTS);
    }

    private function url(string $provider, RoomId $room, string $channel): ?Url
    {
        $address = $this->urls->generate(self::ROUTE, [
            'provider' => $provider,
            'room' => $room->value,
            'channel' => $channel,
            'token' => $this->signer->token($provider, $room->value, $channel),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        try {
            return Url::fromString($address, $this->allowLocalhost);
        } catch (InvalidUrl) {
            return null;
        }
    }
}
