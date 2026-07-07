<?php

declare(strict_types=1);

namespace QtiSdk\Tests;

use PHPUnit\Framework\TestCase;
use QtiSdk\Interaction\ChoiceInteraction;
use QtiSdk\Interaction\ExtendedTextInteraction;
use QtiSdk\Interaction\GraphicGapMatchInteraction;
use QtiSdk\Interaction\HotspotInteraction;
use QtiSdk\Interaction\HottextInteraction;
use QtiSdk\Interaction\InlineChoiceInteraction;
use QtiSdk\Interaction\MatchInteraction;
use QtiSdk\Interaction\OrderInteraction;
use QtiSdk\Interaction\TextEntryInteraction;
use QtiSdk\Item\AssessmentItem;
use QtiSdk\Json\JsonExporter;
use QtiSdk\Packaging\ContentPackage;
use QtiSdk\Packaging\PackageReader;
use QtiSdk\Packaging\PackageWriter;
use QtiSdk\QtiException;
use QtiSdk\Xml\ItemParser;
use QtiSdk\Xml\ItemSerializer;

final class ParserTest extends TestCase
{
    private ItemSerializer $serializer;
    private ItemParser $parser;
    private JsonExporter $exporter;

    protected function setUp(): void
    {
        $this->serializer = new ItemSerializer();
        $this->parser     = new ItemParser();
        $this->exporter   = new JsonExporter();
    }

    /**
     * Serialize each interaction type to XML, parse it back, and require the
     * JSON projection of the parsed item to equal the original's.
     */
    public function testRoundTripEveryInteractionType(): void
    {
        $items = [
            new AssessmentItem('rt-choice', 'Choice', new ChoiceInteraction(
                ['A' => 'Austin', 'B' => 'Houston'], ['A'],
            )),
            new AssessmentItem('rt-multi', 'Multi', new ChoiceInteraction(
                ['A' => 'a', 'B' => 'b', 'C' => 'c'], ['A', 'C'], maxChoices: 2,
            )),
            new AssessmentItem('rt-match', 'Match', new MatchInteraction(
                ['S1' => 'Establish order', 'S2' => 'Provide security'],
                ['T1' => 'speed limits', 'T2' => 'police force'],
                [['S1', 'T1'], ['S2', 'T2']],
            )),
            new AssessmentItem('rt-fib', 'FIB', new TextEntryInteraction(['1836', 'eighteen thirty-six'], expectedLength: 20)),
            new AssessmentItem('rt-essay', 'Essay', new ExtendedTextInteraction(expectedLines: 6)),
            new AssessmentItem('rt-inline', 'Inline', new InlineChoiceInteraction(
                ['J' => 'judicial', 'L' => 'legislative'], 'L',
                textBefore: 'Laws are written by the ', textAfter: ' branch.',
            )),
            new AssessmentItem('rt-hottext', 'Hottext', new HottextInteraction(
                ['Cotton was a ', ['id' => 'H1', 'text' => 'cash crop'], ' in Texas.'], ['H1'],
            )),
            new AssessmentItem('rt-order', 'Order', new OrderInteraction(
                ['E1' => 'Revolution', 'E2' => 'Republic', 'E3' => 'Statehood'], ['E1', 'E2', 'E3'],
            )),
            new AssessmentItem('rt-hotspot', 'Hotspot', new HotspotInteraction(
                'media/map.png', 640, 480,
                [['id' => 'AUS', 'shape' => 'circle', 'coords' => '320,260,20']],
                ['AUS'],
            )),
            new AssessmentItem('rt-ggm', 'Labeling', new GraphicGapMatchInteraction(
                'media/regions.jpg', 800, 600,
                ['L1' => 'Coastal Plains'],
                [['id' => 'G1', 'shape' => 'rect', 'coords' => '500,400,600,470']],
                [['L1', 'G1']],
            )),
        ];

        foreach ($items as $original) {
            $parsed = $this->parser->fromXml($this->serializer->toXml($original));
            self::assertSame(
                $this->exporter->exportItem($original),
                $this->exporter->exportItem($parsed),
                "Round trip mismatch for {$original->identifier}"
            );
        }
    }

    public function testPromptSurvivesRoundTrip(): void
    {
        $original = new AssessmentItem('rt-prompt', 'Prompted', new ChoiceInteraction(
            ['A' => 'x'], ['A'],
        ), promptHtml: '<p>Which city is the capital of <b>Texas</b>?</p>');

        $parsed = $this->parser->fromXml($this->serializer->toXml($original));

        self::assertStringContainsString('capital of <b>Texas</b>', $parsed->promptHtml);
    }

    public function testParsesQti21Namespace(): void
    {
        $xml = $this->serializer->toXml(new AssessmentItem('rt-21', 'Old dialect', new ChoiceInteraction(
            ['A' => 'yes', 'B' => 'no'], ['A'],
        )));
        $xml = str_replace('imsqti_v2p2', 'imsqti_v2p1', $xml);

        $parsed = $this->parser->fromXml($xml);

        self::assertSame('rt-21', $parsed->identifier);
        self::assertInstanceOf(ChoiceInteraction::class, $parsed->interaction);
    }

    public function testRejectsUnsupportedInteraction(): void
    {
        $xml = <<<XML
            <assessmentItem xmlns="http://www.imsglobal.org/xsd/imsqti_v2p2"
                identifier="bad-1" title="Slider" adaptive="false" timeDependent="false">
              <responseDeclaration identifier="RESPONSE" cardinality="single" baseType="integer"/>
              <itemBody>
                <sliderInteraction responseIdentifier="RESPONSE" lowerBound="0" upperBound="10" step="1"/>
              </itemBody>
            </assessmentItem>
            XML;

        $this->expectException(QtiException::class);
        $this->expectExceptionMessageMatches('/sliderInteraction/');
        $this->parser->fromXml($xml);
    }

    public function testPackageRoundTripPreservesItemsAndStandards(): void
    {
        $zipPath = sys_get_temp_dir() . '/qti-parser-test-' . uniqid() . '.zip';

        $package = new ContentPackage('pkg-roundtrip');
        $package->addItem(new AssessmentItem('item-1', 'MC', new ChoiceInteraction(
            ['A' => 'Austin', 'B' => 'Houston'], ['A'],
        ), promptHtml: '<p>Capital?</p>', standards: ['113.15.b.8.A']));
        $package->addItem(new AssessmentItem('item-2', 'Sort', new OrderInteraction(
            ['E1' => 'first', 'E2' => 'second'], ['E1', 'E2'],
        ), standards: ['113.15.b.3.E', '113.15.b.3.F']));

        (new PackageWriter())->write($package, $zipPath);

        $reader   = new PackageReader();
        $imported = $reader->read($zipPath);
        @unlink($zipPath);

        self::assertSame('pkg-roundtrip', $imported->identifier);
        self::assertSame([], $reader->skipped());
        self::assertCount(2, $imported->items());
        self::assertSame('113.15.b.8.A', $imported->items()['item-1']->standards[0]->code);
        self::assertSame(['113.15.b.3.E', '113.15.b.3.F'], array_map(fn ($s) => $s->code, $imported->items()['item-2']->standards));
        self::assertInstanceOf(OrderInteraction::class, $imported->items()['item-2']->interaction);
    }
}
