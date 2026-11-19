<?php

declare(strict_types=1);

namespace Phil\ConversationBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\ConversationBundle\Domain\Conversation\Kind;
use Phil\ConversationBundle\Domain\Model\Conversation;
use Phil\ConversationBundle\Domain\ValueObject\ConversationId;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

/**
 * The attributes of the real time, which only an application with MercureBundle renders: the hub's URL given by its
 * function `mercure()` for the reader's own topic, the conversation, and the page of its latest messages.
 */
#[CoversNothing]
final class RealtimeTemplateTest extends TestCase
{
    public function testTheControllerIsGivenTheHubTheConversationAndThePage(): void
    {
        $loader = new FilesystemLoader();
        $loader->addPath(\dirname(__DIR__, 3).'/templates', 'PhilConversation');

        $twig = new Environment($loader, ['strict_variables' => true]);
        $asked = [];
        $twig->addFunction(new TwigFunction('mercure', static function (string $topic, array $options) use (&$asked): string {
            $asked[] = [$topic, $options];

            return 'https://hub.test/.well-known/mercure?topic='.rawurlencode($topic);
        }));
        $twig->addFunction(new TwigFunction('path', static fn (string $route, array $parameters): string => '/'.$route.'/'.implode('/', array_map(strval(...), array_filter($parameters, \is_string(...))))));

        $id = ConversationId::fromString('0192f0c1-5c5e-7a3b-9e1d-0123456789ab');
        $html = $twig->render('@PhilConversation/_realtime.html.twig', [
            'topic' => 'urn:phil:conversation:ada',
            'conversation' => new Conversation($id, Kind::Direct, null, new DateTimeImmutable()),
        ]);

        self::assertSame(
            'data-controller="phil--conversation-bundle--thread" data-phil--conversation-bundle--thread-hub-value="https://hub.test/.well-known/mercure?topic=urn%3Aphil%3Aconversation%3Aada" data-phil--conversation-bundle--thread-conversation-value="0192f0c1-5c5e-7a3b-9e1d-0123456789ab" data-phil--conversation-bundle--thread-url-value="/phil_conversation_messages/0192f0c1-5c5e-7a3b-9e1d-0123456789ab"',
            $html,
        );
        self::assertSame([['urn:phil:conversation:ada', ['subscribe' => 'urn:phil:conversation:ada']]], $asked);
    }
}
