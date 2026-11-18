<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Unit\Fixture;

use RuntimeException;
use ZipArchive;

/**
 * The packages the tests import, written for them: a SCORM 1.2 manifest, a SCORM 2004 manifest with sequencing, a
 * cmi5 course structure, and the ZIP archives that hold them, built in the temporary directory.
 */
final class Packages
{
    public const string SCORM12 = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <manifest identifier="com.example.safety" version="1"
            xmlns="http://www.imsproject.org/xsd/imscp_rootv1p1p2"
            xmlns:adlcp="http://www.adlnet.org/xsd/adlcp_rootv1p2">
          <metadata><schema>ADL SCORM</schema><schemaversion>1.2</schemaversion></metadata>
          <organizations default="org-safety">
            <organization identifier="org-safety">
              <title>Workshop safety</title>
              <item identifier="intro" identifierref="res-intro"><title>Welcome</title></item>
              <item identifier="part-1">
                <title>Hand tools</title>
                <item identifier="saws" identifierref="res-saws" parameters="?page=2">
                  <title>Saws</title>
                  <adlcp:masteryscore>80</adlcp:masteryscore>
                  <adlcp:prerequisites type="aicc_script">intro</adlcp:prerequisites>
                </item>
                <item identifier="nested">
                  <title>Chisels</title>
                  <item identifier="chisel-care" identifierref="res-chisels"><title>Care</title></item>
                </item>
                <item identifier="hidden" identifierref="res-saws" isvisible="false"><title>Hidden</title></item>
                <item identifier="empty"><title>Nothing</title></item>
              </item>
              <item identifier="outro" identifierref="res-web"><title>Further reading</title></item>
            </organization>
          </organizations>
          <resources xml:base="content/">
            <resource identifier="res-intro" type="webcontent" adlcp:scormtype="sco" href="intro/index.html">
              <file href="intro/index.html"/>
              <dependency identifierref="res-common"/>
            </resource>
            <resource identifier="res-saws" type="webcontent" adlcp:scormtype="sco" xml:base="tools/" href="saws.html?lang=en"/>
            <resource identifier="res-chisels" type="webcontent" adlcp:scormtype="asset" href="tools/chisels%20care.html"/>
            <resource identifier="res-web" type="webcontent" adlcp:scormtype="asset" href="https://example.org/reading"/>
            <resource identifier="res-common" type="webcontent" adlcp:scormtype="asset"><file href="common/style.css"/></resource>
          </resources>
        </manifest>
        XML;

    public const string SCORM2004 = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <manifest identifier="org.example.first-aid" version="1.0"
            xmlns="http://www.imsglobal.org/xsd/imscp_v1p1"
            xmlns:adlcp="http://www.adlnet.org/xsd/adlcp_v1p3"
            xmlns:imsss="http://www.imsglobal.org/xsd/imsss">
          <metadata><schema>ADL SCORM</schema><schemaversion>2004 4th Edition</schemaversion></metadata>
          <organizations default="second">
            <organization identifier="first"><title>Unused</title><item identifier="u1" identifierref="r1"><title>U1</title></item></organization>
            <organization identifier="second">
              <title>First aid</title>
              <item identifier="m1">
                <title>Basics</title>
                <item identifier="a1" identifierref="r1">
                  <title>Call for help</title>
                  <adlcp:completionThreshold minProgressMeasure="0.75"/>
                  <imsss:sequencing><imsss:controlMode flow="true"/></imsss:sequencing>
                </item>
              </item>
              <imsss:sequencing><imsss:controlMode choice="false" flow="true"/></imsss:sequencing>
            </organization>
          </organizations>
          <resources>
            <resource identifier="r1" type="webcontent" adlcp:scormType="sco" href="sco/start.html"/>
          </resources>
          <imsss:sequencingCollection><imsss:sequencing ID="common"/></imsss:sequencingCollection>
        </manifest>
        XML;

    public const string CMI5 = <<<'XML'
        <?xml version="1.0" encoding="utf-8"?>
        <courseStructure xmlns="https://w3id.org/xapi/profiles/cmi5/v1/CourseStructure.xsd">
          <course id="https://example.org/courses/navigation">
            <title><langstring lang="en-US">Navigation</langstring><langstring lang="fr-CA">Navigation à l'estime</langstring></title>
            <description><langstring lang="en-US">Reading a chart.</langstring></description>
          </course>
          <objectives><objective id="https://example.org/objectives/chart"><title><langstring lang="en-US">Chart</langstring></title></objective></objectives>
          <au id="https://example.org/aus/welcome" moveOn="NotApplicable">
            <title><langstring lang="en-US">Welcome</langstring></title>
            <url>welcome/index.html?mode=short</url>
          </au>
          <block id="https://example.org/blocks/charts">
            <title><langstring lang="en-US">Charts</langstring></title>
            <au id="https://example.org/aus/symbols" moveOn="CompletedAndPassed" masteryScore="0.8" launchMethod="OwnWindow" activityType="http://adlnet.gov/expapi/activities/lesson">
              <title><langstring lang="en-US">Symbols</langstring></title>
              <url>https://content.example.net/symbols/</url>
              <launchParameters>{"level":2}</launchParameters>
              <entitlementKey>key-123</entitlementKey>
            </au>
            <block id="https://example.org/blocks/tides">
              <title><langstring lang="en-US">Tides</langstring></title>
              <au id="https://example.org/aus/tides" moveOn="Completed"><title><langstring lang="en-US">Tide tables</langstring></title><url>tides/start.html</url></au>
            </block>
          </block>
        </courseStructure>
        XML;

    private function __construct()
    {
    }

    /**
     * A ZIP archive of those files, in the temporary directory.
     *
     * @param array<string, string> $files
     */
    public static function zip(array $files, ?string $name = null): string
    {
        $path = sys_get_temp_dir().'/'.($name ?? 'phil-courses-'.bin2hex(random_bytes(6)).'.zip');
        $zip = new ZipArchive();

        if (true !== $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
            throw new RuntimeException('The archive cannot be written: '.$path);
        }

        foreach ($files as $file => $contents) {
            $zip->addFromString($file, $contents);
        }

        $zip->close();

        return $path;
    }

    /**
     * @return array<string, string>
     */
    public static function scorm12Files(): array
    {
        return [
            'imsmanifest.xml' => self::SCORM12,
            'content/intro/index.html' => '<h1>Welcome</h1>',
            'content/tools/saws.html' => '<h1>Saws</h1>',
            'content/tools/chisels care.html' => '<h1>Chisels</h1>',
            'content/common/style.css' => 'h1 { color: navy; }',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function scorm2004Files(): array
    {
        return [
            'imsmanifest.xml' => self::SCORM2004,
            'sco/start.html' => '<h1>Call for help</h1>',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function cmi5Files(): array
    {
        return [
            'cmi5.xml' => self::CMI5,
            'welcome/index.html' => '<h1>Welcome</h1>',
            'tides/start.html' => '<h1>Tides</h1>',
        ];
    }
}
