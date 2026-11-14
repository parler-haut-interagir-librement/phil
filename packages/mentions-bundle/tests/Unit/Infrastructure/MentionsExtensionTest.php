<?php

declare(strict_types=1);

namespace Phil\MentionsBundle\Tests\Unit\Infrastructure;

use Phil\MentionsBundle\Application\Rendering\MentionLinker;
use Phil\MentionsBundle\Domain\ValueObject\MentionPattern;
use Phil\MentionsBundle\Infrastructure\Twig\MentionsExtension;
use Phil\MentionsBundle\Tests\Unit\Fixture\Directory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Extension\AttributeExtension;
use Twig\Loader\ArrayLoader;
use Twig\RuntimeLoader\FactoryRuntimeLoader;

#[CoversClass(MentionsExtension::class)]
final class MentionsExtensionTest extends TestCase
{
    public function testAPlainTextIsEscapedAndHtmlMarkedSafeIsReadAsItIs(): void
    {
        $twig = new Environment(new ArrayLoader([
            'plain' => '{{ text|mentions }}',
            'safe' => '{{ text|raw|mentions }}',
        ]), ['autoescape' => 'html', 'strict_variables' => true]);
        $twig->addExtension(new AttributeExtension(MentionsExtension::class));
        $twig->addRuntimeLoader(new FactoryRuntimeLoader([MentionsExtension::class => static fn (): MentionsExtension => new MentionsExtension(new MentionLinker(new Directory(), MentionPattern::default(), 20))]));

        self::assertSame('&lt;b&gt;Hi&lt;/b&gt; <a class="mention" href="/members/ada" data-mention="user:1">@ada</a>', $twig->render('plain', ['text' => '<b>Hi</b> @ada']));
        self::assertSame('<p><b>Hi</b> <a class="mention" href="/members/ada" data-mention="user:1">@ada</a></p>', $twig->render('safe', ['text' => '<p><b>Hi</b> @ada</p>']));
    }
}
