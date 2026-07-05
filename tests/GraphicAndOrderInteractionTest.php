<?php

declare(strict_types=1);

namespace QtiSdk\Tests;

use PHPUnit\Framework\TestCase;
use QtiSdk\Interaction\GraphicGapMatchInteraction;
use QtiSdk\Interaction\HotspotInteraction;
use QtiSdk\Interaction\OrderInteraction;
use QtiSdk\Item\AssessmentItem;
use QtiSdk\Packaging\ContentPackage;
use QtiSdk\Profile\EduphoriaAwareProfile;
use QtiSdk\QtiException;
use QtiSdk\Xml\ItemSerializer;

final class GraphicAndOrderInteractionTest extends TestCase
{
    private ItemSerializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new ItemSerializer();
    }

    private function parse(AssessmentItem $item): \DOMXPath
    {
        $doc = new \DOMDocument();
        self::assertTrue($doc->loadXML($this->serializer->toXml($item)));
        $xp = new \DOMXPath($doc);
        $xp->registerNamespace('q', ItemSerializer::QTI_NS);

        return $xp;
    }

    public function testOrderInteractionUsesOrderedCardinality(): void
    {
        $item = new AssessmentItem('item-sort-1', 'Chronology', new OrderInteraction(
            choices: ['E1' => 'Texas Revolution', 'E2' => 'Annexation', 'E3' => 'Statehood'],
            correctOrder: ['E1', 'E2', 'E3'],
        ));

        $xp = $this->parse($item);
        $decl = $xp->query('//q:responseDeclaration')->item(0);
        self::assertSame('ordered', $decl->getAttribute('cardinality'));
        $values = $xp->query('//q:correctResponse/q:value');
        self::assertSame(['E1', 'E2', 'E3'], array_map(fn ($n) => $n->textContent, iterator_to_array($values)));
        self::assertSame(3, $xp->query('//q:orderInteraction/q:simpleChoice')->length);
    }

    public function testOrderRejectsIncompleteSequence(): void
    {
        $this->expectException(QtiException::class);
        new OrderInteraction(['A' => 'a', 'B' => 'b'], ['A']);
    }

    public function testHotspotEmitsImageObjectAndRegions(): void
    {
        $item = new AssessmentItem('item-hs-1', 'Find Austin', new HotspotInteraction(
            imageHref: 'media/texas-map.png',
            imageWidth: 640,
            imageHeight: 480,
            hotspots: [
                ['id' => 'AUS', 'shape' => 'circle', 'coords' => '320,260,20'],
                ['id' => 'HOU', 'shape' => 'circle', 'coords' => '420,330,20'],
            ],
            correct: ['AUS'],
        ));

        $xp  = $this->parse($item);
        $obj = $xp->query('//q:hotspotInteraction/q:object')->item(0);
        self::assertSame('media/texas-map.png', $obj->getAttribute('data'));
        self::assertSame('image/png', $obj->getAttribute('type'));
        self::assertSame(2, $xp->query('//q:hotspotChoice')->length);
        self::assertSame('circle', $xp->query('//q:hotspotChoice')->item(0)->getAttribute('shape'));
        self::assertSame('AUS', $xp->query('//q:correctResponse/q:value')->item(0)->textContent);
    }

    public function testHotspotRejectsBadShape(): void
    {
        $this->expectException(QtiException::class);
        new HotspotInteraction('m.png', 10, 10, [
            ['id' => 'A', 'shape' => 'triangle', 'coords' => '1,1,2'],
        ], ['A']);
    }

    public function testGraphicGapMatchPairsLabelsToRegions(): void
    {
        $item = new AssessmentItem('item-label-1', 'Label the map', new GraphicGapMatchInteraction(
            imageHref: 'media/regions.jpg',
            imageWidth: 800,
            imageHeight: 600,
            labels: ['L1' => 'Coastal Plains', 'L2' => 'Great Plains'],
            gaps: [
                ['id' => 'G1', 'shape' => 'rect', 'coords' => '500,400,600,470'],
                ['id' => 'G2', 'shape' => 'rect', 'coords' => '200,100,300,170'],
            ],
            correctPairs: [['L1', 'G1'], ['L2', 'G2']],
        ));

        $xp = $this->parse($item);
        self::assertSame('directedPair', $xp->query('//q:responseDeclaration')->item(0)->getAttribute('baseType'));
        self::assertSame('L1 G1', $xp->query('//q:correctResponse/q:value')->item(0)->textContent);
        self::assertSame(2, $xp->query('//q:gapText')->length);
        self::assertSame(2, $xp->query('//q:associableHotspot')->length);
        self::assertSame(
            'image/jpeg',
            $xp->query('//q:graphicGapMatchInteraction/q:object')->item(0)->getAttribute('type')
        );
    }

    public function testAwareProfileWarnsOnPartialSupportTypes(): void
    {
        $package = new ContentPackage('pkg-partial');
        $package->addItem(new AssessmentItem(
            'item-sort-1',
            'Sort',
            new OrderInteraction(['A' => 'a', 'B' => 'b'], ['A', 'B']),
            standards: ['113.15.b.1.A'],
        ));
        $package->addItem(new AssessmentItem(
            'item-hs-1',
            'Hotspot',
            new HotspotInteraction('m.png', 10, 10, [['id' => 'A', 'shape' => 'circle', 'coords' => '1,1,2']], ['A']),
            standards: ['113.15.b.1.B'],
        ));

        $profile = new EduphoriaAwareProfile();
        $issues  = $profile->validate($package);

        self::assertSame([], $profile->errors($issues));
        $warnings = array_filter($issues, fn ($i) => str_contains($i['message'], 'partially'));
        self::assertCount(1, $warnings, 'Only the order item should carry a partial-support warning');
    }
}
