<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Tests\Unit;

use Phil\ElearningPackages\Scorm\ScormItem;
use Phil\ElearningPackages\Scorm\ScormManifestReader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ScormManifestReader::class)]
#[CoversClass(ScormItem::class)]
final class RuntimeManifestTest extends TestCase
{
    public function testWhatTheRunTimeHandsAScoOfScorm12IsRead(): void
    {
        $manifest = new ScormManifestReader()->read(<<<'XML'
            <manifest identifier="m" xmlns:adlcp="http://www.adlnet.org/xsd/adlcp_rootv1p2"><metadata><schemaversion>1.2</schemaversion></metadata><organizations><organization identifier="o">
              <item identifier="a" identifierref="r"><adlcp:datafromlms>start=3</adlcp:datafromlms><adlcp:maxtimeallowed>00:30:00</adlcp:maxtimeallowed><adlcp:timelimitaction>Exit,Message</adlcp:timelimitaction></item>
              <item identifier="b" identifierref="r"><adlcp:datafromlms></adlcp:datafromlms><adlcp:maxtimeallowed>half an hour</adlcp:maxtimeallowed><adlcp:timelimitaction>stop</adlcp:timelimitaction></item>
              <item identifier="c" identifierref="r"><adlcp:datafromlms>LONG</adlcp:datafromlms></item>
            </organization></organizations><resources><resource identifier="r" href="a.html"/></resources></manifest>
            XML
            |> (static fn (string $xml): string => str_replace('LONG', str_repeat('d', 5000), $xml)));
        [$a, $b, $c] = $manifest->organization()->items;

        self::assertSame(['start=3', '00:30:00', 'exit,message', null], [$a->dataFromLms, $a->maxTimeAllowed, $a->timeLimitAction, $a->scaledPassingScore]);
        self::assertSame([null, null, null], [$b->dataFromLms, $b->maxTimeAllowed, $b->timeLimitAction]);
        self::assertSame(4096, mb_strlen((string) $c->dataFromLms));
        self::assertNull($c->timeLimitAction);
    }

    public function testWhatTheRunTimeHandsAScoOfScorm2004IsRead(): void
    {
        $manifest = new ScormManifestReader()->read(<<<'XML'
            <manifest identifier="m" xmlns:adlcp="http://www.adlnet.org/xsd/adlcp_v1p3" xmlns:imsss="http://www.imsglobal.org/xsd/imsss"><metadata><schemaversion>2004 4th Edition</schemaversion></metadata><organizations><organization identifier="o">
              <item identifier="a" identifierref="r"><adlcp:dataFromLMS>level=2</adlcp:dataFromLMS><adlcp:timeLimitAction>continue,no message</adlcp:timeLimitAction>
                <imsss:sequencing><imsss:limitConditions attemptAbsoluteDurationLimit="PT1H30M"/><imsss:objectives><imsss:primaryObjective objectiveID="p" satisfiedByMeasure="true"><imsss:minNormalizedMeasure>0.6</imsss:minNormalizedMeasure></imsss:primaryObjective></imsss:objectives></imsss:sequencing></item>
              <item identifier="b" identifierref="r"><imsss:sequencing><imsss:objectives><imsss:primaryObjective satisfiedByMeasure="TRUE"/></imsss:objectives></imsss:sequencing></item>
              <item identifier="c" identifierref="r"><imsss:sequencing><imsss:limitConditions attemptAbsoluteDurationLimit="long"/><imsss:objectives><imsss:primaryObjective satisfiedByMeasure="false"><imsss:minNormalizedMeasure>0.6</imsss:minNormalizedMeasure></imsss:primaryObjective></imsss:objectives></imsss:sequencing></item>
              <item identifier="d" identifierref="r"><imsss:sequencing><imsss:limitConditions/><imsss:objectives><imsss:primaryObjective satisfiedByMeasure="true"><imsss:minNormalizedMeasure>high</imsss:minNormalizedMeasure></imsss:primaryObjective></imsss:objectives></imsss:sequencing></item>
              <item identifier="e" identifierref="r"><imsss:sequencing><imsss:objectives><imsss:primaryObjective satisfiedByMeasure="true"><imsss:minNormalizedMeasure>1.5</imsss:minNormalizedMeasure></imsss:primaryObjective></imsss:objectives></imsss:sequencing></item>
              <item identifier="f" identifierref="r"><imsss:sequencing><imsss:objectives><imsss:primaryObjective satisfiedByMeasure="true"><imsss:minNormalizedMeasure>-1</imsss:minNormalizedMeasure></imsss:primaryObjective></imsss:objectives></imsss:sequencing></item>
              <item identifier="g" identifierref="r"><imsss:sequencing><imsss:objectives/></imsss:sequencing></item>
              <item identifier="h" identifierref="r"><imsss:sequencing><imsss:objectives><imsss:primaryObjective/></imsss:objectives></imsss:sequencing></item>
              <item identifier="i" identifierref="r"><imsss:sequencing/></item>
            </organization></organizations><resources><resource identifier="r" href="a.html"/></resources></manifest>
            XML);
        [$a, $b, $c, $d, $e, $f, $g, $h, $i] = $manifest->organization()->items;

        self::assertSame(['level=2', 'PT1H30M', 'continue,no message', 0.6], [$a->dataFromLms, $a->maxTimeAllowed, $a->timeLimitAction, $a->scaledPassingScore]);
        self::assertSame(1.0, $b->scaledPassingScore);
        self::assertSame([null, null], [$c->maxTimeAllowed, $c->scaledPassingScore]);
        self::assertSame([null, null], [$d->maxTimeAllowed, $d->scaledPassingScore]);
        self::assertNull($e->scaledPassingScore);
        self::assertSame(-1.0, $f->scaledPassingScore);
        self::assertNull($g->scaledPassingScore);
        self::assertNull($h->scaledPassingScore);
        self::assertNull($i->scaledPassingScore);
    }

    public function testTheFirstSpellingOfAnElementWinsAndTheLimitsAreInCharacters(): void
    {
        $manifest = new ScormManifestReader()->read(<<<'XML'
            <manifest identifier="m" xmlns:adlcp="http://www.adlnet.org/xsd/adlcp_v1p3" xmlns:imsss="http://www.imsglobal.org/xsd/imsss"><metadata><schemaversion>2004 4th Edition</schemaversion></metadata><organizations><organization identifier="o">
              <item identifier="a" identifierref="r"><adlcp:datafromlms>lower</adlcp:datafromlms><adlcp:dataFromLMS>camel</adlcp:dataFromLMS><adlcp:timelimitaction>exit,message</adlcp:timelimitaction><adlcp:timeLimitAction>continue,message</adlcp:timeLimitAction></item>
              <item identifier="b" identifierref="r"><adlcp:datafromlms>LONG</adlcp:datafromlms></item>
              <item identifier="c" identifierref="r"><imsss:sequencing><imsss:limitConditions attemptAbsoluteDurationLimit="xPT1H"/></imsss:sequencing></item>
              <item identifier="d" identifierref="r"><imsss:sequencing><imsss:limitConditions attemptAbsoluteDurationLimit="PT1Hx"/></imsss:sequencing></item>
            </organization></organizations><resources><resource identifier="r" href="a.html"/></resources></manifest>
            XML
            |> (static fn (string $xml): string => str_replace('LONG', str_repeat('é', 5000), $xml)));
        [$a, $b, $c, $d] = $manifest->organization()->items;

        self::assertSame(['lower', 'exit,message'], [$a->dataFromLms, $a->timeLimitAction]);
        self::assertSame(str_repeat('é', 4096), $b->dataFromLms);
        self::assertSame([null, null], [$c->maxTimeAllowed, $d->maxTimeAllowed]);
    }
}
