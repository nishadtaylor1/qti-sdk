<?php

declare(strict_types=1);

namespace QtiSdk\Tests;

use PHPUnit\Framework\TestCase;
use QtiSdk\Interaction\ChoiceInteraction;
use QtiSdk\Item\AssessmentItem;
use QtiSdk\Packaging\ContentPackage;
use QtiSdk\Packaging\ManifestBuilder;
use QtiSdk\Packaging\PackageWriter;
use QtiSdk\Profile\EduphoriaAwareProfile;
use QtiSdk\QtiException;

final class PackageTest extends TestCase
{
    private string $zipPath;

    protected function setUp(): void
    {
        $this->zipPath = sys_get_temp_dir() . '/qti-sdk-test-' . uniqid() . '.zip';
    }

    protected function tearDown(): void
    {
        @unlink($this->zipPath);
    }

    private function samplePackage(): ContentPackage
    {
        $package = new ContentPackage('pkg-grade4-ss', 'Grade 4 Social Studies');
        $package->addItem(new AssessmentItem(
            identifier: 'item-1',
            title: 'Capital of Texas',
            interaction: new ChoiceInteraction(['A' => 'Austin', 'B' => 'Houston'], ['A']),
            promptHtml: '<p>Which city is the capital of Texas?</p>',
            standards: ['113.15.b.8.A'],
        ));
        $package->addItem(new AssessmentItem(
            identifier: 'item-2',
            title: 'Texas independence year',
            interaction: new ChoiceInteraction(['A' => '1836', 'B' => '1845'], ['A']),
            standards: ['113.15.b.3.C'],
        ));

        return $package;
    }

    public function testManifestListsEveryItem(): void
    {
        $doc = (new ManifestBuilder())->build($this->samplePackage());
        $xp  = new \DOMXPath($doc);
        $xp->registerNamespace('cp', ManifestBuilder::CP_NS);
        $xp->registerNamespace('md', ManifestBuilder::LOM_NS);

        self::assertSame(1, $xp->query('/cp:manifest')->length);
        $resources = $xp->query('//cp:resource[@type="imsqti_item_xmlv2p2"]');
        self::assertSame(2, $resources->length);
        self::assertSame('items/item-1.xml', $resources->item(0)->getAttribute('href'));
        // Standards travel as LOM keywords
        self::assertStringContainsString('113.15.b.8.A', $doc->saveXML());
    }

    public function testZipHasManifestAtTopLevelAndAllItems(): void
    {
        (new PackageWriter())->write($this->samplePackage(), $this->zipPath);

        $zip = new \ZipArchive();
        self::assertTrue($zip->open($this->zipPath));
        self::assertNotFalse($zip->locateName('imsmanifest.xml'), 'imsmanifest.xml must be at the zip root');
        self::assertNotFalse($zip->locateName('items/item-1.xml'));
        self::assertNotFalse($zip->locateName('items/item-2.xml'));

        // Every packaged item must itself be well-formed XML
        $itemXml = $zip->getFromName('items/item-1.xml');
        $doc = new \DOMDocument();
        self::assertTrue($doc->loadXML($itemXml));
        $zip->close();
    }

    public function testEmptyPackageIsRejected(): void
    {
        $this->expectException(QtiException::class);
        (new PackageWriter())->write(new ContentPackage('pkg-empty'), $this->zipPath);
    }

    public function testDuplicateItemIdsAreRejected(): void
    {
        $package = new ContentPackage('pkg-dup');
        $item = new AssessmentItem('item-1', 'One', new ChoiceInteraction(['A' => 'x'], ['A']));
        $package->addItem($item);
        $this->expectException(QtiException::class);
        $package->addItem($item);
    }

    public function testAwareProfilePassesCleanPackage(): void
    {
        $package = $this->samplePackage();
        (new PackageWriter())->write($package, $this->zipPath);

        $profile = new EduphoriaAwareProfile();
        $issues  = $profile->validate($package, $this->zipPath);

        self::assertSame([], $profile->errors($issues));
    }

    public function testAwareProfileWarnsOnMissingStandards(): void
    {
        $package = new ContentPackage('pkg-nostd');
        $package->addItem(new AssessmentItem('item-1', 'No standards', new ChoiceInteraction(['A' => 'x'], ['A'])));

        $issues = (new EduphoriaAwareProfile())->validate($package);

        self::assertCount(1, $issues);
        self::assertSame('warning', $issues[0]['level']);
        self::assertStringContainsString('standards', $issues[0]['message']);
    }
}
