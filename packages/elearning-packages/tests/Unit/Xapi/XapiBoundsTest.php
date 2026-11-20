<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Tests\Unit\Xapi;

use Phil\ElearningPackages\Exception\PackageRefused;
use Phil\ElearningPackages\PackageLimits;
use Phil\ElearningPackages\PackageReader;
use Phil\ElearningPackages\Tests\Fixture\MemoryArchive;
use Phil\ElearningPackages\Xapi\XapiActivity;
use Phil\ElearningPackages\Xapi\XapiCourse;
use Phil\ElearningPackages\Xapi\XapiCourseReader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The borders of what the xAPI reader accepts, and the order in which it picks and skips.
 */
#[CoversClass(XapiCourseReader::class)]
#[CoversClass(XapiCourse::class)]
#[CoversClass(XapiActivity::class)]
#[CoversClass(PackageReader::class)]
final class XapiBoundsTest extends TestCase
{
    public function testTheNumberOfActivitiesTheLimitAllowsIsAccepted(): void
    {
        $course = new XapiCourseReader(new PackageLimits(items: 2))->read($this->tincan('<activity id="urn:x:a"><launch>a.html</launch></activity><activity id="urn:x:b"/>'));

        self::assertCount(2, $course->activities);
    }

    public function testTheCourseIsTheActivityOfThatTypeBeforeTheFirstLaunchable(): void
    {
        $course = new XapiCourseReader()->read($this->tincan('<activity id="urn:x:a"><launch>a.html</launch></activity><activity id="urn:x:c" type="'.XapiActivity::COURSE.'"><launch>c.html</launch></activity>'));

        self::assertSame('urn:x:c', $course->id());
    }

    public function testAnEmptyTextDoesNotEndTheReadingOfTheOthers(): void
    {
        $course = new XapiCourseReader()->read($this->tincan('<activity id="urn:x:a"><name lang="en"></name><name lang="fr">Bonjour</name><launch lang="de"></launch><launch lang="fr">a.html</launch></activity>'));

        self::assertSame(['fr' => 'Bonjour'], $course->course->names);
        self::assertSame('a.html', $course->course->launch);
    }

    public function testAnotherTextOfTheSameLanguageDoesNotEndTheReadingOfTheOthers(): void
    {
        $course = new XapiCourseReader()->read($this->tincan('<activity id="urn:x:a"><name lang="en">A</name><name lang="en">B</name><name lang="fr">C</name><launch>a.html</launch></activity>'));

        self::assertSame(['en' => 'A', 'fr' => 'C'], $course->course->names);
    }

    public function testTheFirstLaunchOfALanguageIsKept(): void
    {
        $course = new XapiCourseReader()->read($this->tincan('<activity id="urn:x:a"><launch>first.html</launch><launch>second.html</launch></activity>'));

        self::assertSame('first.html', $course->course->launch);
    }

    public function testAnUnknownInteractionTypeIsLeftOutAndShownShort(): void
    {
        $value = str_repeat('é', 60);
        $course = new XapiCourseReader()->read($this->tincan('<activity id="urn:x:a" type="'.XapiActivity::COURSE.'"><name>A</name><launch>a.html</launch><interactionType>'.$value.'</interactionType></activity>'));

        self::assertNull($course->course->interactionType);
        self::assertCount(1, $course->warnings);
        self::assertSame('The interaction type "'.str_repeat('é', 40).'" of the activity "urn:x:a" is not one xAPI defines: it is left out.', $course->warnings[0]->message);
    }

    public function testAnIriHoldsUpToTwoThousandAndFortyEightBytes(): void
    {
        $longest = 'urn:'.str_repeat('x', 2044);
        $course = new XapiCourseReader()->read($this->tincan('<activity id="'.$longest.'"><launch>a.html</launch></activity>'));

        self::assertSame($longest, $course->id());

        $this->expectException(PackageRefused::class);

        new XapiCourseReader()->read($this->tincan('<activity id="'.$longest.'x"><launch>a.html</launch></activity>'));
    }

    public function testAnIriHasNoSpaceAndAnySchemeCase(): void
    {
        self::assertSame('URN:abc', new XapiCourseReader()->read($this->tincan('<activity id="URN:abc"><launch>a.html</launch></activity>'))->id());

        $this->expectException(PackageRefused::class);

        new XapiCourseReader()->read($this->tincan('<activity id="urn:a b"><launch>a.html</launch></activity>'));
    }

    public function testALanguageTagHoldsUpToThirtyFiveCharacters(): void
    {
        $longest = 'ABCDEFGH-abcdefgh-abcdefgh-abcdefgh';
        $course = new XapiCourseReader()->read($this->tincan('<activity id="urn:x:a"><name lang="'.$longest.'">A</name><name lang="'.$longest.'x">B</name><launch>a.html</launch></activity>'));

        self::assertSame([strtolower($longest) => 'A', 'und' => 'B'], $course->course->names);
    }

    public function testAnExactLanguageComesBeforeAnotherOfTheSamePrimaryLanguage(): void
    {
        $activity = new XapiActivity('urn:x:a', names: ['fr-ca' => 'Canadian', 'fr' => 'French']);

        self::assertSame('French', $activity->name('fr'));
    }

    public function testThePrimaryLanguageIsNotAPrefixOfAnother(): void
    {
        $activity = new XapiActivity('urn:x:a', names: ['en' => 'English', 'fra' => 'Fra']);

        self::assertSame('English', $activity->name('fr'));
    }

    public function testALaunchFileAfterAnAddressIsStillChecked(): void
    {
        $package = new PackageReader()->read(new MemoryArchive([
            'tincan.xml' => $this->tincan('<activity id="urn:x:a" type="'.XapiActivity::COURSE.'"><name>A</name><launch>https://example.org/a</launch></activity><activity id="urn:x:b"><name>B</name><launch>b.html</launch></activity>'),
        ]));

        self::assertSame(['The launch file "b.html" of the activity "urn:x:b" is not in the package.'], $package->warnings);
    }

    private function tincan(string $activities): string
    {
        return '<tincan xmlns="http://projecttincan.com/tincan.xsd"><activities>'.$activities.'</activities></tincan>';
    }
}
