<?php

declare(strict_types=1);

namespace Phil\Ujo\Tests\Unit\Type;

use Phil\Ujo\Block;
use Phil\Ujo\Document;
use Phil\Ujo\Exception\UnknownBlockType;
use Phil\Ujo\Html\HtmlRenderer;
use Phil\Ujo\Html\InlineProfile;
use Phil\Ujo\Html\InlineSanitizer;
use Phil\Ujo\Html\InlineTool;
use Phil\Ujo\Html\Renderer\LayoutRenderer;
use Phil\Ujo\Html\Renderer\ListRenderer;
use Phil\Ujo\Html\Renderer\MediaRenderer;
use Phil\Ujo\Html\Renderer\TextRenderer;
use Phil\Ujo\Html\Rendering;
use Phil\Ujo\Normalizer;
use Phil\Ujo\Schema\Blocks;
use Phil\Ujo\Schema\Boolean;
use Phil\Ujo\Schema\Choice;
use Phil\Ujo\Schema\Context;
use Phil\Ujo\Schema\Html;
use Phil\Ujo\Schema\Integer;
use Phil\Ujo\Schema\Lazy;
use Phil\Ujo\Schema\Link;
use Phil\Ujo\Schema\ListOf;
use Phil\Ujo\Schema\Plain;
use Phil\Ujo\Schema\Shape;
use Phil\Ujo\Text\PlainText;
use Phil\Ujo\Type\BlockType;
use Phil\Ujo\Type\BlockTypeRegistry;
use Phil\Ujo\Type\CommunityTypes;
use Phil\Ujo\Type\EmbedServices;
use Phil\Ujo\Type\LayoutTypes;
use Phil\Ujo\Type\StandardTypes;
use Phil\Ujo\Type\TuneRegistry;
use Phil\Ujo\Type\Tunes;
use Phil\Ujo\Type\TuneType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(BlockTypeRegistry::class)]
#[CoversClass(TuneRegistry::class)]
#[CoversClass(EmbedServices::class)]
#[CoversClass(BlockType::class)]
#[CoversClass(TuneType::class)]
#[CoversClass(Normalizer::class)]
#[CoversClass(Context::class)]
#[CoversClass(Shape::class)]
#[CoversClass(ListOf::class)]
#[CoversClass(Integer::class)]
#[CoversClass(Plain::class)]
#[CoversClass(Choice::class)]
#[CoversClass(Link::class)]
#[CoversClass(Html::class)]
#[CoversClass(Blocks::class)]
#[CoversClass(Lazy::class)]
#[CoversClass(Boolean::class)]
#[CoversClass(StandardTypes::class)]
#[CoversClass(CommunityTypes::class)]
#[CoversClass(LayoutTypes::class)]
#[CoversClass(Tunes::class)]
#[CoversClass(InlineSanitizer::class)]
#[CoversClass(InlineProfile::class)]
#[CoversClass(InlineTool::class)]
#[CoversClass(HtmlRenderer::class)]
#[CoversClass(Rendering::class)]
#[CoversClass(TextRenderer::class)]
#[CoversClass(ListRenderer::class)]
#[CoversClass(MediaRenderer::class)]
#[CoversClass(LayoutRenderer::class)]
#[CoversClass(PlainText::class)]
#[CoversClass(Block::class)]
#[CoversClass(Document::class)]
final class RegistryTest extends TestCase
{
    public function testTheDefaultRegistriesAreBuiltOnce(): void
    {
        self::assertSame(BlockTypeRegistry::default(), BlockTypeRegistry::default());
        self::assertSame(TuneRegistry::default(), TuneRegistry::default());
    }

    public function testTypesAreFoundByNameOrAlias(): void
    {
        $registry = BlockTypeRegistry::default();

        self::assertSame('list', $registry->get('checklist')->name);
        self::assertSame('toggle', $registry->get('accordion')->name);
        self::assertSame('linkTool', $registry->get('link')->name);
        self::assertSame('math', $registry->get('Math')->name);
        self::assertSame('button', $registry->get('AnyButton')->name);
        self::assertSame('gallery', $registry->get('carousel')->name);
        self::assertTrue($registry->has('checklist'));
        self::assertFalse($registry->has('raw'));
        self::assertFalse($registry->has(''));
    }

    public function testAnUnknownTypeNamesItsPath(): void
    {
        $this->expectExceptionObject(UnknownBlockType::block('raw', 'block'));

        BlockTypeRegistry::default()->get('raw');
    }

    public function testTypesAreAddedReplacedAndRemoved(): void
    {
        $mine = new BlockType('paragraph', new Shape(), ['para']);
        $registry = new BlockTypeRegistry(StandardTypes::paragraph(), StandardTypes::header())->with($mine, StandardTypes::raw());

        self::assertSame(['paragraph', 'header', 'raw'], $registry->names());
        self::assertSame($mine, $registry->get('para'));
        self::assertSame(['header'], $registry->without('paragraph', 'raw')->names());
        self::assertFalse($registry->without('paragraph')->has('para'));
        self::assertSame([], new BlockTypeRegistry()->names());
    }

    public function testTunesAreFoundByNameOrAlias(): void
    {
        $registry = TuneRegistry::default();
        $flag = new TuneType('flag', new Boolean(), ['flagTune']);

        self::assertSame('alignment', $registry->get('alignmentTune')->name);
        self::assertTrue($registry->has('anchorTune'));
        self::assertFalse($registry->has('flag'));
        self::assertSame($flag, $registry->with($flag)->get('flagTune'));
        self::assertSame('anchor', $registry->with($flag)->get('anchorTune')->name);
        self::assertFalse(new TuneRegistry()->has('alignment'));

        $this->expectExceptionObject(UnknownBlockType::tune('flag', 'tunes'));

        $registry->get('flag');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function hosts(): iterable
    {
        foreach (EmbedServices::DEFAULT as $service => $hosts) {
            foreach ($hosts as $host) {
                yield $service.' '.$host => [$service, $host];
            }
        }
    }

    #[DataProvider('hosts')]
    public function testEachServiceFramesFromItsHosts(string $service, string $host): void
    {
        self::assertTrue(new EmbedServices()->allows($service, 'https://'.$host.'/embed/x'));
    }

    public function testTheDefaultServices(): void
    {
        self::assertSame([
            'youtube' => ['www.youtube.com', 'www.youtube-nocookie.com'],
            'vimeo' => ['player.vimeo.com'],
            'facebook' => ['www.facebook.com'],
            'instagram' => ['www.instagram.com'],
            'twitter' => ['platform.twitter.com', 'twitframe.com'],
            'twitch-video' => ['player.twitch.tv'],
            'twitch-channel' => ['player.twitch.tv'],
            'miro' => ['miro.com'],
            'gfycat' => ['gfycat.com'],
            'imgur' => ['imgur.com'],
            'vine' => ['vine.co'],
            'aparat' => ['www.aparat.com'],
            'yandex-music-track' => ['music.yandex.ru'],
            'yandex-music-album' => ['music.yandex.ru'],
            'yandex-music-playlist' => ['music.yandex.ru'],
            'coub' => ['coub.com'],
            'codepen' => ['codepen.io'],
            'pinterest' => ['assets.pinterest.com'],
            'github' => ['gist.github.com'],
            'reddit' => ['embed.reddit.com'],
            'figma' => ['www.figma.com'],
            'whimsical' => ['whimsical.com'],
        ], new EmbedServices()->services);
    }

    public function testAFrameComesFromAnHttpsAddressOnAHostOfItsService(): void
    {
        $services = new EmbedServices()->with('peertube', ['video.example.org']);

        self::assertTrue($services->allows('peertube', 'https://VIDEO.example.org/videos/embed/1'));
        self::assertTrue($services->allows('peertube', 'https://video.example.org'));
        self::assertTrue($services->allows('peertube', 'https://video.example.org?a'));
        self::assertTrue($services->allows('youtube', 'https://www.youtube.com#a'));
        self::assertFalse($services->allows('peertube', 'http://video.example.org/x'));
        self::assertFalse($services->allows('peertube', 'https://video.example.org.evil.com/x'));
        self::assertFalse($services->allows('peertube', 'https://video.example.org:8443/x'));
        self::assertFalse($services->allows('peertube', 'https://user@video.example.org/x'));
        self::assertFalse($services->allows('youtube', 'https://video.example.org/x'));
        self::assertFalse($services->allows('dailymotion', 'https://video.example.org/x'));
        self::assertFalse($services->allows('peertube', ' https://video.example.org/x'));
        self::assertContains('peertube', $services->names());
        self::assertSame('youtube', $services->names()[0]);
        self::assertSame(['a' => ['a.example']], new EmbedServices(['a' => ['a.example']])->services);

        $normalizer = new Normalizer(new BlockTypeRegistry(StandardTypes::embed($services)));
        $embed = ['service' => 'peertube', 'source' => 'https://video.example.org/w/1', 'embed' => 'https://video.example.org/videos/embed/1'];

        self::assertSame([...$embed, 'caption' => ''], $normalizer->normalize(new Document([new Block('e', 'embed', $embed)]))->blocks[0]->data);
        self::assertCount(13, StandardTypes::all($services));
    }

    public function testEveryFactoryIsPublic(): void
    {
        foreach ([
            'paragraph' => StandardTypes::paragraph(...), 'header' => StandardTypes::header(...), 'list' => StandardTypes::list(...), 'quote' => StandardTypes::quote(...),
            'warning' => StandardTypes::warning(...), 'delimiter' => StandardTypes::delimiter(...), 'code' => StandardTypes::code(...), 'raw' => StandardTypes::raw(...),
            'table' => StandardTypes::table(...), 'image' => StandardTypes::image(...), 'linkTool' => StandardTypes::linkTool(...), 'attaches' => StandardTypes::attaches(...),
            'personality' => StandardTypes::personality(...), 'alert' => CommunityTypes::alert(...), 'toggle' => CommunityTypes::toggle(...), 'columns' => CommunityTypes::columns(...),
            'math' => CommunityTypes::math(...), 'mermaid' => CommunityTypes::mermaid(...), 'chart' => CommunityTypes::chart(...), 'button' => CommunityTypes::button(...),
            'gallery' => CommunityTypes::gallery(...), 'pageStyle' => LayoutTypes::pageStyle(...), 'columnFlow' => LayoutTypes::columnFlow(...), 'spacer' => LayoutTypes::spacer(...),
            'pageNumber' => LayoutTypes::pageNumber(...), 'runningFooter' => LayoutTypes::runningFooter(...), 'footnote' => LayoutTypes::footnote(...), 'toc' => LayoutTypes::toc(...),
            'index' => LayoutTypes::index(...), 'watermark' => LayoutTypes::watermark(...), 'statblock' => LayoutTypes::statblock(...), 'definitionList' => LayoutTypes::definitionList(...),
            'credit' => LayoutTypes::credit(...), 'banner' => LayoutTypes::banner(...), 'decoration' => LayoutTypes::decoration(...), 'qrCode' => LayoutTypes::qrCode(...),
        ] as $name => $factory) {
            $type = $factory();
            self::assertInstanceOf(BlockType::class, $type);
            self::assertSame($name, $type->name);
        }

        self::assertSame('box', LayoutTypes::box('box')->name);
        self::assertSame('wide', LayoutTypes::container('wide')->name);
        self::assertSame('embed', StandardTypes::embed(new EmbedServices())->name);
    }

    public function testTheDefaultTypesTakeTheServicesGiven(): void
    {
        $registry = new BlockTypeRegistry(...StandardTypes::all(new EmbedServices(['peertube' => ['video.example.org']])));
        $embed = ['service' => 'peertube', 'source' => 'https://video.example.org/w/1', 'embed' => 'https://video.example.org/e/1'];

        self::assertSame([...$embed, 'caption' => ''], new Normalizer($registry)->normalize(new Document([new Block('e', 'embed', $embed)]))->blocks[0]->data);
        self::assertTrue(new EmbedServices()->allows('youtube', 'HTTPS://www.youtube.com/embed/x'));
        self::assertSame('alignment', TuneRegistry::default()->get('alignment')->name);
    }
}
