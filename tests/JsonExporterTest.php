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

final class JsonExporterTest extends TestCase
{
    private JsonExporter $exporter;

    protected function setUp(): void
    {
        $this->exporter = new JsonExporter();
    }

    public function testPackageExportEnvelope(): void
    {
        $package = new ContentPackage('pkg-json-1', 'JSON sample');
        $package->addItem(new AssessmentItem(
            'item-1',
            'MC',
            new ChoiceInteraction(['A' => 'Austin', 'B' => 'Houston'], ['A']),
            promptHtml: '<p>Capital?</p>',
            standards: ['113.15.b.8.A'],
        ));

        $data = $this->exporter->exportPackage($package);

        self::assertSame('qti-sdk', $data['format']);
        self::assertSame(1, $data['version']);
        self::assertSame('pkg-json-1', $data['package']['identifier']);
        self::assertCount(1, $data['package']['items']);

        $item = $data['package']['items'][0];
        self::assertSame('item-1', $item['identifier']);
        self::assertSame(['113.15.b.8.A'], $item['standards']);
        self::assertSame('choice', $item['interaction']['type']);
        self::assertSame(['A'], $item['interaction']['correct']);
    }

    public function testToJsonProducesValidParseableJson(): void
    {
        $item = new AssessmentItem('item-2', 'FIB', new TextEntryInteraction(['1836']));
        $json = $this->exporter->toJson($item);

        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('qti-sdk', $decoded['format']);
        self::assertSame('textEntry', $decoded['item']['interaction']['type']);
        self::assertSame(['1836'], $decoded['item']['interaction']['acceptedAnswers']);
    }

    public function testEveryInteractionTypeExports(): void
    {
        $interactions = [
            'choice'          => new ChoiceInteraction(['A' => 'a'], ['A']),
            'match'           => new MatchInteraction(['S1' => 's'], ['T1' => 't'], [['S1', 'T1']]),
            'textEntry'       => new TextEntryInteraction(['x']),
            'extendedText'    => new ExtendedTextInteraction(),
            'inlineChoice'    => new InlineChoiceInteraction(['A' => 'a'], 'A'),
            'hottext'         => new HottextInteraction([['id' => 'H1', 'text' => 'w']], ['H1']),
            'order'           => new OrderInteraction(['A' => 'a', 'B' => 'b'], ['B', 'A']),
            'hotspot'         => new HotspotInteraction('i.png', 10, 10, [['id' => 'A', 'shape' => 'circle', 'coords' => '1,1,2']], ['A']),
            'graphicGapMatch' => new GraphicGapMatchInteraction('i.png', 10, 10, ['L1' => 'l'], [['id' => 'G1', 'shape' => 'rect', 'coords' => '1,1,2,2']], [['L1', 'G1']]),
        ];

        foreach ($interactions as $expectedType => $interaction) {
            $data = $this->exporter->exportItem(new AssessmentItem('item-x', 'x', $interaction));
            self::assertSame($expectedType, $data['interaction']['type'], "type key for {$expectedType}");
            self::assertSame('RESPONSE', $data['interaction']['responseIdentifier']);
        }
    }
}
