<?php

declare(strict_types=1);

namespace QtiSdk\Tests;

use PHPUnit\Framework\TestCase;
use QtiSdk\Interaction\ChoiceInteraction;
use QtiSdk\Interaction\ExtendedTextInteraction;
use QtiSdk\Interaction\HottextInteraction;
use QtiSdk\Interaction\InlineChoiceInteraction;
use QtiSdk\Interaction\MatchInteraction;
use QtiSdk\Interaction\TextEntryInteraction;
use QtiSdk\Item\AssessmentItem;
use QtiSdk\QtiException;
use QtiSdk\Xml\ItemSerializer;

final class ItemSerializerTest extends TestCase
{
    private ItemSerializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new ItemSerializer();
    }

    private function parse(string $xml): \DOMXPath
    {
        $doc = new \DOMDocument();
        self::assertTrue($doc->loadXML($xml), 'Serialized item is not well-formed XML');
        $xp = new \DOMXPath($doc);
        $xp->registerNamespace('q', ItemSerializer::QTI_NS);

        return $xp;
    }

    public function testChoiceItemStructure(): void
    {
        $item = new AssessmentItem(
            identifier: 'item-mc-1',
            title: 'Capital of Texas',
            interaction: new ChoiceInteraction(
                choices: ['A' => 'Austin', 'B' => 'Houston', 'C' => 'Dallas'],
                correct: ['A'],
            ),
            promptHtml: '<p>Which city is the capital of Texas?</p>',
            standards: ['113.14.b.2.A'],
        );

        $xp = $this->parse($this->serializer->toXml($item));

        self::assertSame(1, $xp->query('/q:assessmentItem')->length);
        self::assertSame('item-mc-1', $xp->query('/q:assessmentItem')->item(0)->getAttribute('identifier'));
        self::assertSame('false', $xp->query('/q:assessmentItem')->item(0)->getAttribute('adaptive'));

        self::assertSame('single', $xp->query('//q:responseDeclaration')->item(0)->getAttribute('cardinality'));
        self::assertSame('A', $xp->query('//q:correctResponse/q:value')->item(0)->textContent);
        self::assertSame(3, $xp->query('//q:simpleChoice')->length);
        self::assertSame(1, $xp->query('//q:outcomeDeclaration[@identifier="SCORE"]')->length);
        self::assertStringContainsString(
            'match_correct',
            $xp->query('//q:responseProcessing')->item(0)->getAttribute('template')
        );
    }

    public function testMultiSelectGetsMultipleCardinality(): void
    {
        $item = new AssessmentItem('item-ms-1', 'Pick two', new ChoiceInteraction(
            choices: ['A' => 'a', 'B' => 'b', 'C' => 'c'],
            correct: ['A', 'B'],
            maxChoices: 2,
        ));

        $xp = $this->parse($this->serializer->toXml($item));
        self::assertSame('multiple', $xp->query('//q:responseDeclaration')->item(0)->getAttribute('cardinality'));
        self::assertSame(2, $xp->query('//q:correctResponse/q:value')->length);
    }

    public function testMatchItemUsesDirectedPairs(): void
    {
        $item = new AssessmentItem('item-match-1', 'Match dates', new MatchInteraction(
            sources: ['S1' => '1776', 'S2' => '1836'],
            targets: ['T1' => 'Declaration of Independence', 'T2' => 'Texas independence'],
            correctPairs: [['S1', 'T1'], ['S2', 'T2']],
        ));

        $xp = $this->parse($this->serializer->toXml($item));
        self::assertSame('directedPair', $xp->query('//q:responseDeclaration')->item(0)->getAttribute('baseType'));
        self::assertSame('S1 T1', $xp->query('//q:correctResponse/q:value')->item(0)->textContent);
        self::assertSame(2, $xp->query('//q:simpleMatchSet')->length);
        self::assertSame(4, $xp->query('//q:simpleAssociableChoice')->length);
    }

    public function testTextEntryWithAlternatesUsesMapping(): void
    {
        $item = new AssessmentItem('item-fib-1', 'Fill in', new TextEntryInteraction(
            acceptedAnswers: ['Austin', 'austin'],
        ), promptHtml: '<p>The capital of Texas is:</p>');

        $xp = $this->parse($this->serializer->toXml($item));
        self::assertSame('string', $xp->query('//q:responseDeclaration')->item(0)->getAttribute('baseType'));
        self::assertSame(2, $xp->query('//q:mapping/q:mapEntry')->length);
        self::assertStringContainsString(
            'map_response',
            $xp->query('//q:responseProcessing')->item(0)->getAttribute('template')
        );
    }

    public function testExtendedTextHasNoResponseProcessing(): void
    {
        $item = new AssessmentItem('item-essay-1', 'Essay', new ExtendedTextInteraction(expectedLines: 8));

        $xp = $this->parse($this->serializer->toXml($item));
        self::assertSame(0, $xp->query('//q:responseProcessing')->length);
        self::assertSame(0, $xp->query('//q:correctResponse')->length);
    }

    public function testInlineChoiceRendersInSentence(): void
    {
        $item = new AssessmentItem('item-ic-1', 'Dropdown', new InlineChoiceInteraction(
            choices: ['A' => 'Austin', 'H' => 'Houston'],
            correct: 'A',
            textBefore: 'The capital of Texas is ',
            textAfter: '.',
        ));

        $xp = $this->parse($this->serializer->toXml($item));
        self::assertSame(2, $xp->query('//q:inlineChoice')->length);
        self::assertStringContainsString('The capital of Texas is', $xp->document->textContent);
    }

    public function testHottextMarksSpans(): void
    {
        $item = new AssessmentItem('item-ht-1', 'Find the verb', new HottextInteraction(
            segments: ['The dog ', ['id' => 'H1', 'text' => 'ran'], ' to the ', ['id' => 'H2', 'text' => 'park'], '.'],
            correct: ['H1'],
        ));

        $xp = $this->parse($this->serializer->toXml($item));
        self::assertSame(2, $xp->query('//q:hottext')->length);
        self::assertSame('H1', $xp->query('//q:correctResponse/q:value')->item(0)->textContent);
    }

    public function testSloppyHtmlPromptDoesNotBreakTheItem(): void
    {
        $item = new AssessmentItem('item-html-1', 'Sloppy', new ChoiceInteraction(
            choices: ['A' => 'ok'],
            correct: ['A'],
        ), promptHtml: '<p>Unclosed paragraph<br>with <b>bold text');

        $xp = $this->parse($this->serializer->toXml($item));
        self::assertStringContainsString('Unclosed paragraph', $xp->document->textContent);
    }

    public function testInvalidIdentifierIsRejected(): void
    {
        $this->expectException(QtiException::class);
        new AssessmentItem('9 bad id!', 'Bad', new ChoiceInteraction(['A' => 'x'], ['A']));
    }

    public function testCorrectValueMustBeAChoice(): void
    {
        $this->expectException(QtiException::class);
        new ChoiceInteraction(['A' => 'x'], ['Z']);
    }
}
