<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Tests\Unit\Xapi;

use Phil\ElearningPackages\Xapi\ComponentList;
use Phil\ElearningPackages\Xapi\InteractionComponent;
use Phil\ElearningPackages\Xapi\InteractionType;
use Phil\ElearningPackages\Xapi\XapiActivity;
use Phil\ElearningPackages\Xapi\XapiCourse;
use Phil\ElearningPackages\Xapi\XapiCourseReader;
use Phil\ElearningPackages\Xapi\XapiWarning;
use Phil\ElearningPackages\Xapi\XapiWarningKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(XapiCourseReader::class)]
#[CoversClass(XapiActivity::class)]
#[CoversClass(InteractionComponent::class)]
#[CoversClass(ComponentList::class)]
#[CoversClass(XapiWarning::class)]
#[CoversClass(XapiWarningKind::class)]
final class XapiInteractionsTest extends TestCase
{
    private const string QUESTION = 'http://adlnet.gov/expapi/activities/cmi.interaction';

    public function testTheComponentsAndCorrectResponsesOfAQuestionAreRead(): void
    {
        $course = $this->read($this->question('q', 'choice', '<correctResponsePatterns><correctResponsePattern>golf[,]tetris</correctResponsePattern><correctResponsePattern> </correctResponsePattern></correctResponsePatterns>'
            .'<choices><component><id>golf</id><description lang="en-US">Golf</description><description lang="fr">Le golf</description></component><component id="tetris"/><component><id> 1 </id></component></choices>'));

        $question = $course->activity('urn:x:q');
        self::assertInstanceOf(XapiActivity::class, $question);
        self::assertSame([], $course->warnings);
        self::assertSame(['golf[,]tetris', ''], $question->correctResponses);
        self::assertSame(['golf', 'tetris', '1'], array_map(static fn (InteractionComponent $component): string => $component->id, $question->components(ComponentList::Choices)));
        self::assertSame('Le golf', $question->components(ComponentList::Choices)[0]->description('fr-CA'));
        self::assertSame('Golf', $question->components(ComponentList::Choices)[0]->description());
        self::assertSame('', $question->components(ComponentList::Choices)[1]->description('fr'));
        self::assertSame([], $question->components(ComponentList::Scale));
        self::assertSame([
            'name' => ['und' => 'Q'],
            'type' => self::QUESTION,
            'interactionType' => 'choice',
            'correctResponsesPattern' => ['golf[,]tetris', ''],
            'choices' => [
                ['id' => 'golf', 'description' => ['en-us' => 'Golf', 'fr' => 'Le golf']],
                ['id' => 'tetris'],
                ['id' => '1'],
            ],
        ], $question->definition());
    }

    public function testTheDefinitionOfAnActivityLeavesOutWhatItLacks(): void
    {
        self::assertSame([], new XapiActivity('urn:x:a')->definition());
        self::assertSame(['description' => ['en' => 'D'], 'type' => 'urn:t'], new XapiActivity('urn:x:a', 'urn:t', descriptions: ['en' => 'D'])->definition());
        self::assertSame(['correctResponsesPattern' => ['a']], new XapiActivity('urn:x:a', correctResponses: ['a'])->definition());
        self::assertSame(
            ['interactionType' => 'matching', 'source' => [['id' => 's']], 'target' => [['id' => 't']]],
            new XapiActivity('urn:x:a', interactionType: InteractionType::Matching, components: ['target' => [new InteractionComponent('t')], 'source' => [new InteractionComponent('s')]])->definition(),
        );
    }

    /**
     * @return iterable<string, array{?InteractionType, list<ComponentList>}>
     */
    public static function listsByType(): iterable
    {
        yield 'choice' => [InteractionType::Choice, [ComponentList::Choices]];
        yield 'sequencing' => [InteractionType::Sequencing, [ComponentList::Choices]];
        yield 'likert' => [InteractionType::Likert, [ComponentList::Scale]];
        yield 'matching' => [InteractionType::Matching, [ComponentList::Source, ComponentList::Target]];
        yield 'performance' => [InteractionType::Performance, [ComponentList::Steps]];
        yield 'numeric' => [InteractionType::Numeric, []];
        yield 'none' => [null, []];
    }

    /**
     * @param list<ComponentList> $lists
     */
    #[DataProvider('listsByType')]
    public function testEachInteractionTypeUsesItsLists(?InteractionType $type, array $lists): void
    {
        self::assertSame($lists, ComponentList::of($type));
    }

    public function testEachListOfAMatchingOrPerformanceQuestionIsRead(): void
    {
        $course = $this->read($this->question('m', 'matching', '<source><component><id>a</id></component></source><target><component><id>x</id></component></target><correctResponsePatterns><correctResponsePattern>a[.]x</correctResponsePattern></correctResponsePatterns>')
        .$this->question('p', 'performance', '<steps><component><id>s1</id></component></steps><correctResponsePatterns><correctResponsePattern>{order_matters=false}s1[.]5[:]10</correctResponsePattern></correctResponsePatterns>')
        .$this->question('l', 'likert', '<scale><component><id>agree</id></component></scale><correctResponsePatterns><correctResponsePattern>agree</correctResponsePattern></correctResponsePatterns>'));

        self::assertSame([], $course->warnings);
        self::assertSame('a', $course->activity('urn:x:m')?->components(ComponentList::Source)[0]->id);
        self::assertSame('x', $course->activity('urn:x:m')->components(ComponentList::Target)[0]->id);
        self::assertSame('s1', $course->activity('urn:x:p')?->components(ComponentList::Steps)[0]->id);
        self::assertSame(['{order_matters=false}s1[.]5[:]10'], $course->activity('urn:x:p')->correctResponses);
        self::assertSame('agree', $course->activity('urn:x:l')?->components(ComponentList::Scale)[0]->id);
    }

    public function testListsAndResponsesOutOfPlaceAreLeftOut(): void
    {
        $course = $this->read($this->question('n', 'numeric', '<choices><component><id>a</id></component></choices><scale/><correctResponsePatterns><correctResponsePattern>4[:]5</correctResponsePattern></correctResponsePatterns>')
        .'<activity id="urn:x:o" type="'.self::QUESTION.'"><name>O</name><correctResponsePatterns><correctResponsePattern>a</correctResponsePattern></correctResponsePatterns><steps><component><id>s</id></component></steps></activity>');

        self::assertSame([
            XapiWarningKind::MisplacedComponents,
            XapiWarningKind::MisplacedComponents,
            XapiWarningKind::MisplacedResponses,
            XapiWarningKind::MisplacedComponents,
        ], $this->kinds($course));
        self::assertSame('The activity "urn:x:n" declares choices its interaction type does not use: they are left out.', $course->warnings[0]->message);
        self::assertSame('The activity "urn:x:n" declares scale its interaction type does not use: they are left out.', $course->warnings[1]->message);
        self::assertSame('The activity "urn:x:o" declares correct responses without an interaction type: they are left out.', $course->warnings[2]->message);
        self::assertSame('urn:x:o', $course->warnings[2]->activity);
        self::assertSame(['4[:]5'], $course->activity('urn:x:n')?->correctResponses);
        self::assertSame([], $course->activity('urn:x:n')->components);
        self::assertSame([], $course->activity('urn:x:o')?->correctResponses);
        self::assertSame([], $course->activity('urn:x:o')->components);
    }

    public function testComponentsWithoutAUsableIdentifierOrDeclaredTwiceAreLeftOut(): void
    {
        $course = $this->read($this->question('q', 'choice', '<choices><component><description>None</description></component><component><id>two words</id></component><component><id>'.str_repeat('a', 256).'</id></component><component><id>'.str_repeat('b', 255).'</id></component><component><id>a</id></component><component id="a"/></choices>'));

        self::assertSame([
            XapiWarningKind::InvalidComponent,
            XapiWarningKind::InvalidComponent,
            XapiWarningKind::InvalidComponent,
            XapiWarningKind::DuplicateComponent,
        ], $this->kinds($course));
        self::assertSame('A component of the choices of the activity "urn:x:q" has no identifier of 1 to 255 bytes without white space: it is left out.', $course->warnings[0]->message);
        self::assertSame('The component "a" of the choices of the activity "urn:x:q" is declared twice: the second is left out.', $course->warnings[3]->message);
        self::assertSame([str_repeat('b', 255), 'a'], array_map(static fn (InteractionComponent $component): string => $component->id, $course->activity('urn:x:q')?->components(ComponentList::Choices) ?? []));
    }

    public function testComponentsAndResponsesAreBounded(): void
    {
        $components = '';

        for ($index = 0; $index <= XapiCourseReader::MAX_COMPONENTS; ++$index) {
            $components .= \sprintf('<component><id>c%d</id></component>', $index);
        }

        $patterns = str_repeat('<correctResponsePattern>c0</correctResponsePattern>', XapiCourseReader::MAX_RESPONSES + 1);
        $course = $this->read($this->question('q', 'sequencing', '<choices>'.$components.'<component><id>c0</id></component></choices><correctResponsePatterns>'.$patterns.'</correctResponsePatterns>'));
        $question = $course->activity('urn:x:q');

        self::assertSame([XapiWarningKind::TooManyResponses, XapiWarningKind::TooManyComponents], $this->kinds($course));
        self::assertSame('The activity "urn:x:q" declares more than 32 correct responses: the others are left out.', $course->warnings[0]->message);
        self::assertSame('The choices of the activity "urn:x:q" hold more than 100 components: the others are left out.', $course->warnings[1]->message);
        self::assertCount(XapiCourseReader::MAX_COMPONENTS, $question?->components(ComponentList::Choices) ?? []);
        self::assertCount(XapiCourseReader::MAX_RESPONSES, $question->correctResponses);
        self::assertSame('c99', $question->components(ComponentList::Choices)[99]->id);
    }

    public function testTheLimitsAreInclusive(): void
    {
        $patterns = '';

        for ($index = 0; $index <= XapiCourseReader::MAX_RESPONSES; ++$index) {
            $patterns .= \sprintf('<correctResponsePattern>p%d</correctResponsePattern>', $index);
        }

        $over = $this->read($this->question('q', 'fill-in', '<correctResponsePatterns>'.$patterns.'</correctResponsePatterns>'));
        $expected = array_map(static fn (int $index): string => 'p'.$index, range(0, XapiCourseReader::MAX_RESPONSES - 1));

        self::assertSame($expected, $over->activity('urn:x:q')?->correctResponses);
        self::assertSame([XapiWarningKind::TooManyResponses], $this->kinds($over));

        $exact = $this->read($this->question('q', 'fill-in', '<correctResponsePatterns>'.preg_replace('#<correctResponsePattern>p'.XapiCourseReader::MAX_RESPONSES.'</correctResponsePattern>#', '', $patterns).'</correctResponsePatterns>'));

        self::assertSame($expected, $exact->activity('urn:x:q')?->correctResponses);
        self::assertSame([], $exact->warnings);

        $components = '';

        for ($index = 0; $index < XapiCourseReader::MAX_COMPONENTS; ++$index) {
            $components .= \sprintf('<component><id>c%d</id></component>', $index);
        }

        $full = $this->read($this->question('q', 'choice', '<choices>'.$components.'</choices>'));

        self::assertCount(XapiCourseReader::MAX_COMPONENTS, $full->activity('urn:x:q')?->components(ComponentList::Choices) ?? []);
        self::assertSame([], $full->warnings);
    }

    public function testTheTextOfTheIdentifierWinsOverItsAttributeAndOtherWhiteSpaceIsRefused(): void
    {
        $course = $this->read($this->question('q', 'choice', '<choices><component id="attr"><id>text</id></component><component><id>a'."\u{00A0}".'b</id></component><component><id>a'."\u{2003}".'b</id></component></choices>'));

        self::assertSame(['text'], array_map(static fn (InteractionComponent $component): string => $component->id, $course->activity('urn:x:q')?->components(ComponentList::Choices) ?? []));
        self::assertSame([XapiWarningKind::InvalidComponent, XapiWarningKind::InvalidComponent], $this->kinds($course));
    }

    public function testADuplicateDoesNotStopTheComponentsAfterIt(): void
    {
        $course = $this->read($this->question('q', 'choice', '<choices><component><id>a</id></component><component><id>a</id></component><component><id>b</id></component></choices>'));

        self::assertSame(['a', 'b'], array_map(static fn (InteractionComponent $component): string => $component->id, $course->activity('urn:x:q')?->components(ComponentList::Choices) ?? []));
        self::assertSame([XapiWarningKind::DuplicateComponent], $this->kinds($course));
    }

    public function testTheLanguagesOfADescriptionAreBoundedWithOneWarning(): void
    {
        $descriptions = '';

        for ($index = 0; $index < XapiCourseReader::MAX_LANGUAGES + 2; ++$index) {
            $descriptions .= \sprintf('<description lang="%s">t%d</description>', \chr(97 + intdiv($index, 26)).\chr(97 + $index % 26), $index);
        }

        $course = $this->read($this->question('q', 'choice', '<choices><component><id>a</id>'.$descriptions.'</component></choices>'));

        self::assertSame([XapiWarningKind::TooManyLanguages], $this->kinds($course));
        self::assertCount(XapiCourseReader::MAX_LANGUAGES, $course->activity('urn:x:q')?->components(ComponentList::Choices)[0]->descriptions ?? []);
    }

    public function testAMatchingPairIsCutAtItsFirstSeparatorAndNamesAreCutByCharacters(): void
    {
        $lists = '<source><component><id>a</id></component></source><target><component><id>b</id></component></target>';
        $course = $this->read($this->question('m', 'matching', $lists.'<correctResponsePatterns><correctResponsePattern>a[.]b[.]c</correctResponsePattern></correctResponsePatterns>')
        .$this->question('c', 'choice', '<choices><component><id>a</id></component></choices><correctResponsePatterns><correctResponsePattern>'.str_repeat('é', 50).'</correctResponsePattern></correctResponsePatterns>'));

        self::assertSame([XapiWarningKind::UnknownResponseComponent, XapiWarningKind::UnknownResponseComponent], $this->kinds($course));
        self::assertStringContainsString('names "b[.]c", which its target do not', $course->warnings[0]->message);
        self::assertStringContainsString('names "'.str_repeat('é', 40).'", which', $course->warnings[1]->message);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function unknownComponents(): iterable
    {
        yield 'choice' => ['choice', '<choices><component><id>a</id></component></choices>', 'a[,]zz'];
        yield 'sequencing' => ['sequencing', '<choices><component><id>a</id></component></choices>', 'zz[,]a'];
        yield 'likert' => ['likert', '<scale><component><id>a</id></component></scale>', 'zz'];
        yield 'matching source' => ['matching', '<source><component><id>a</id></component></source><target><component><id>b</id></component></target>', 'a[.]b[,]zz[.]b'];
        yield 'matching target' => ['matching', '<source><component><id>a</id></component></source><target><component><id>b</id></component></target>', 'a[.]zz'];
        yield 'performance' => ['performance', '<steps><component><id>a</id></component></steps>', '{case_matters=true}{order_matters=false}a[.]1[,]zz[.]2'];
    }

    #[DataProvider('unknownComponents')]
    public function testACorrectResponseNamingAnUndeclaredComponentIsNotedAndKept(string $type, string $lists, string $response): void
    {
        $course = $this->read($this->question('q', $type, $lists.'<correctResponsePatterns><correctResponsePattern>'.$response.'</correctResponsePattern></correctResponsePatterns>'));

        self::assertSame([XapiWarningKind::UnknownResponseComponent], $this->kinds($course));
        self::assertStringStartsWith('A correct response of the activity "urn:x:q" names "zz", which its ', $course->warnings[0]->message);
        self::assertStringEndsWith(' do not declare: the response is kept.', $course->warnings[0]->message);
        self::assertSame([$response], $course->activity('urn:x:q')?->correctResponses);
    }

    public function testOneWarningIsNotedForEachResponseAndNamesAreCut(): void
    {
        $long = str_repeat('z', 50);
        $course = $this->read($this->question('q', 'choice', '<choices><component><id>a</id></component></choices><correctResponsePatterns><correctResponsePattern>'.$long.'[,]y</correctResponsePattern><correctResponsePattern>a</correctResponsePattern></correctResponsePatterns>'));

        self::assertSame([XapiWarningKind::UnknownResponseComponent], $this->kinds($course));
        self::assertSame('A correct response of the activity "urn:x:q" names "'.str_repeat('z', 40).'", which its choices do not declare: the response is kept.', $course->warnings[0]->message);
    }

    public function testResponsesAreNotCheckedAgainstListsTheQuestionLacks(): void
    {
        $course = $this->read($this->question('a', 'choice', '<correctResponsePatterns><correctResponsePattern>x[,]y</correctResponsePattern></correctResponsePatterns>')
        .$this->question('b', 'matching', '<source><component><id>a</id></component></source><correctResponsePatterns><correctResponsePattern>a[.]q[,]a</correctResponsePattern></correctResponsePatterns>')
        .$this->question('c', 'choice', '<choices><component><id>a</id></component></choices><correctResponsePatterns><correctResponsePattern>{lang=en}</correctResponsePattern></correctResponsePatterns>')
        .$this->question('d', 'fill-in', '<correctResponsePatterns><correctResponsePattern>{case_matters=true}Paris</correctResponsePattern></correctResponsePatterns>'));

        self::assertSame([], $course->warnings);
    }

    private function read(string $activities): XapiCourse
    {
        return new XapiCourseReader()->read('<tincan xmlns="http://projecttincan.com/tincan.xsd"><activities><activity id="urn:x:course"><name>C</name><launch>index.html</launch></activity>'.$activities.'</activities></tincan>');
    }

    private function question(string $id, string $type, string $body): string
    {
        return \sprintf('<activity id="urn:x:%s" type="%s"><name>%s</name><interactionType>%s</interactionType>%s</activity>', $id, self::QUESTION, strtoupper($id), $type, $body);
    }

    /**
     * @return list<XapiWarningKind>
     */
    private function kinds(XapiCourse $course): array
    {
        return array_map(static fn (XapiWarning $warning): XapiWarningKind => $warning->kind, $course->warnings);
    }
}
