<?php

declare(strict_types=1);

namespace QtiSdk\Tests;

use PHPUnit\Framework\TestCase;
use QtiSdk\Interaction\ChoiceInteraction;
use QtiSdk\Item\AssessmentItem;
use QtiSdk\Item\Standard;
use QtiSdk\Packaging\ContentPackage;
use QtiSdk\Packaging\ManifestBuilder;
use QtiSdk\Packaging\PackageReader;
use QtiSdk\Packaging\PackageWriter;
use QtiSdk\Profile\EduphoriaAwareProfile;

final class StandardsGuidTest extends TestCase
{
    private function itemWithStandards(array $standards): AssessmentItem
    {
        return new AssessmentItem(
            'item-guid-1',
            'GUID test',
            new ChoiceInteraction(['A' => 'x'], ['A']),
            standards: $standards,
        );
    }

    public function testStandardNormalizationAcceptsAllForms(): void
    {
        $item = $this->itemWithStandards([
            '113.15.b.8.A',
            ['code' => '113.15.b.3.C', 'guid' => 'A1B2C3D4'],
            new Standard('113.15.b.7.A', 'E5F6A7B8'),
        ]);

        self::assertSame('113.15.b.8.A', $item->standards[0]->code);
        self::assertNull($item->standards[0]->guid);
        self::assertSame('A1B2C3D4', $item->standards[1]->guid);
        self::assertSame('E5F6A7B8', $item->standards[2]->guid);
    }

    public function testManifestEmitsGuidBlockAndKeywords(): void
    {
        $package = new ContentPackage('pkg-guid');
        $package->addItem($this->itemWithStandards([
            ['code' => '113.15.b.8.A', 'guid' => 'TEA-GUID-123'],
        ]));

        $doc = (new ManifestBuilder())->build($package);
        $xp  = new \DOMXPath($doc);
        $xp->registerNamespace('csm', ManifestBuilder::CSM_NS);
        $xp->registerNamespace('md', ManifestBuilder::LOM_NS);

        self::assertSame('TEA-GUID-123', $xp->query('//csm:labelledGUID/csm:GUID')->item(0)->textContent);
        self::assertSame('113.15.b.8.A', $xp->query('//csm:labelledGUID/csm:label')->item(0)->textContent);
        self::assertStringContainsString('113.15.b.8.A', $doc->saveXML());   // keyword survives too
    }

    public function testManifestEmitsProviderIdRegionAndQtiNamespace(): void
    {
        $package = new ContentPackage('pkg-region');
        $package->addItem($this->itemWithStandards([
            ['code' => 'A2.4.H', 'guid' => '9F64216C-0D0A-11E2-9583-8B2E9DFF4B22', 'region' => 'tea#teks#ma:2012'],
        ]));

        $doc = (new ManifestBuilder())->build($package);
        $xp  = new \DOMXPath($doc);
        $xp->registerNamespace('csm', ManifestBuilder::CSM_NS);

        // The three things Eduphoria Aware needs to actually link the standard.
        self::assertSame('http://www.imsglobal.org/xsd/qti/qtiv2p2/imscsmd_v1p0', ManifestBuilder::CSM_NS);
        self::assertSame(
            'org.academicbenchmarks',
            $xp->query('//csm:curriculumStandardsMetadata')->item(0)->getAttribute('providerId')
        );
        self::assertSame(
            'tea#teks#ma:2012',
            $xp->query('//csm:setOfGUIDs')->item(0)->getAttribute('region')
        );
        self::assertSame(
            '9F64216C-0D0A-11E2-9583-8B2E9DFF4B22',
            $xp->query('//csm:labelledGUID/csm:GUID')->item(0)->textContent
        );
    }

    public function testManifestOmitsGuidBlockWhenNoGuids(): void
    {
        $package = new ContentPackage('pkg-noguid');
        $package->addItem($this->itemWithStandards(['113.15.b.8.A']));

        $doc = (new ManifestBuilder())->build($package);
        self::assertStringNotContainsString('labelledGUID', $doc->saveXML());
    }

    public function testGuidsRoundTripThroughPackage(): void
    {
        $zipPath = sys_get_temp_dir() . '/qti-guid-test-' . uniqid() . '.zip';
        $package = new ContentPackage('pkg-guid-rt');
        $package->addItem($this->itemWithStandards([
            ['code' => '113.15.b.8.A', 'guid' => 'TEA-GUID-123'],
            '113.15.b.3.C',
        ]));

        (new PackageWriter())->write($package, $zipPath);
        $imported = (new PackageReader())->read($zipPath);
        @unlink($zipPath);

        $standards = $imported->items()['item-guid-1']->standards;
        self::assertCount(2, $standards);
        self::assertSame('TEA-GUID-123', $standards[0]->guid);
        self::assertSame('113.15.b.8.A', $standards[0]->code);
        self::assertNull($standards[1]->guid);
    }

    public function testAwareProfileWarnsOnCodesWithoutGuids(): void
    {
        $package = new ContentPackage('pkg-warn');
        $package->addItem($this->itemWithStandards(['113.15.b.8.A']));

        $issues = (new EduphoriaAwareProfile())->validate($package);

        self::assertCount(1, $issues);
        self::assertStringContainsString('GUID', $issues[0]['message']);
    }

    public function testAwareProfileCleanWhenGuidsPresent(): void
    {
        $package = new ContentPackage('pkg-clean');
        $package->addItem($this->itemWithStandards([
            ['code' => '113.15.b.8.A', 'guid' => 'TEA-GUID-123'],
        ]));

        self::assertSame([], (new EduphoriaAwareProfile())->validate($package));
    }
}
