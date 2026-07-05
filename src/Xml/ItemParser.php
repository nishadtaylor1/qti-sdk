<?php

declare(strict_types=1);

namespace QtiSdk\Xml;

use QtiSdk\Interaction\ChoiceInteraction;
use QtiSdk\Interaction\ExtendedTextInteraction;
use QtiSdk\Interaction\GraphicGapMatchInteraction;
use QtiSdk\Interaction\HotspotInteraction;
use QtiSdk\Interaction\HottextInteraction;
use QtiSdk\Interaction\Interaction;
use QtiSdk\Interaction\InlineChoiceInteraction;
use QtiSdk\Interaction\MatchInteraction;
use QtiSdk\Interaction\OrderInteraction;
use QtiSdk\Interaction\TextEntryInteraction;
use QtiSdk\Item\AssessmentItem;
use QtiSdk\QtiException;

/**
 * Parses a QTI 2.x assessmentItem XML document into SDK objects.
 *
 * Deliberately lenient: matching is done on local element names, so QTI 2.1
 * and 2.2 namespaces (and vendor documents with sloppy namespacing) all
 * parse. Items whose interaction type is outside the SDK's supported set
 * throw a QtiException naming the offender.
 */
final class ItemParser
{
    private const SUPPORTED = [
        'choiceInteraction', 'matchInteraction', 'textEntryInteraction',
        'extendedTextInteraction', 'inlineChoiceInteraction', 'hottextInteraction',
        'orderInteraction', 'hotspotInteraction', 'graphicGapMatchInteraction',
    ];

    /** @param list<string> $standards attached to the resulting item (e.g. from manifest metadata) */
    public function fromXml(string $xml, array $standards = []): AssessmentItem
    {
        $doc = new \DOMDocument();
        if (!@$doc->loadXML($xml)) {
            throw new QtiException('Item XML is not well-formed.');
        }

        return $this->fromDom($doc, $standards);
    }

    /** @param list<string> $standards */
    public function fromDom(\DOMDocument $doc, array $standards = []): AssessmentItem
    {
        $root = $doc->documentElement;
        if ($root === null || $root->localName !== 'assessmentItem') {
            throw new QtiException('Expected an assessmentItem root element.');
        }

        $identifier = $root->getAttribute('identifier');
        if ($identifier === '') {
            throw new QtiException('assessmentItem is missing its identifier attribute.');
        }

        $declarations = [];
        foreach ($this->childrenByLocal($root, 'responseDeclaration') as $declEl) {
            $declarations[$declEl->getAttribute('identifier')] = $this->parseDeclaration($declEl);
        }

        $body = $this->firstChildByLocal($root, 'itemBody')
            ?? throw new QtiException('assessmentItem has no itemBody.');

        $interactionEl = $this->findInteraction($body);
        $interaction   = $this->buildInteraction($interactionEl, $declarations);
        $promptHtml    = $this->extractPrompt($body, $interactionEl);

        $title = $root->getAttribute('title');
        $lang  = $root->getAttribute('xml:lang');

        return new AssessmentItem(
            identifier: $identifier,
            title: $title !== '' ? $title : $identifier,
            interaction: $interaction,
            promptHtml: $promptHtml,
            standards: $standards,
            language: $lang !== '' ? $lang : 'en-US',
        );
    }

    // ── Interaction construction ────────────────────────────────────────────

    /** @param array<string, array{cardinality: string, baseType: string, correct: list<string>, mapping: array<string, float>}> $decls */
    private function buildInteraction(\DOMElement $el, array $decls): Interaction
    {
        $rid  = $el->getAttribute('responseIdentifier') ?: 'RESPONSE';
        $decl = $decls[$rid] ?? ['cardinality' => 'single', 'baseType' => 'string', 'correct' => [], 'mapping' => []];

        return match ($el->localName) {
            'choiceInteraction' => new ChoiceInteraction(
                choices: $this->identifiedContent($el, 'simpleChoice'),
                correct: $decl['correct'],
                maxChoices: $this->intAttr($el, 'maxChoices') ?: max(1, count($decl['correct'])),
                shuffle: $el->getAttribute('shuffle') === 'true',
                responseIdentifier: $rid,
            ),
            'orderInteraction' => new OrderInteraction(
                choices: $this->identifiedContent($el, 'simpleChoice'),
                correctOrder: $decl['correct'],
                shuffle: $el->getAttribute('shuffle') !== 'false',
                responseIdentifier: $rid,
            ),
            'matchInteraction' => $this->buildMatch($el, $decl, $rid),
            'textEntryInteraction' => new TextEntryInteraction(
                acceptedAnswers: array_values(array_unique([...$decl['correct'], ...array_keys($decl['mapping'])])),
                expectedLength: $this->intAttr($el, 'expectedLength'),
                responseIdentifier: $rid,
            ),
            'extendedTextInteraction' => new ExtendedTextInteraction(
                expectedLines: $this->intAttr($el, 'expectedLines'),
                responseIdentifier: $rid,
            ),
            'inlineChoiceInteraction' => $this->buildInlineChoice($el, $decl, $rid),
            'hottextInteraction' => new HottextInteraction(
                segments: $this->collectHottextSegments($el),
                correct: $decl['correct'],
                maxChoices: $this->intAttr($el, 'maxChoices') ?: max(1, count($decl['correct'])),
                responseIdentifier: $rid,
            ),
            'hotspotInteraction' => new HotspotInteraction(
                imageHref: $this->imageAttr($el, 'data'),
                imageWidth: (int) $this->imageAttr($el, 'width'),
                imageHeight: (int) $this->imageAttr($el, 'height'),
                hotspots: $this->regions($el, 'hotspotChoice'),
                correct: $decl['correct'],
                maxChoices: $this->intAttr($el, 'maxChoices') ?: max(1, count($decl['correct'])),
                responseIdentifier: $rid,
            ),
            'graphicGapMatchInteraction' => new GraphicGapMatchInteraction(
                imageHref: $this->imageAttr($el, 'data'),
                imageWidth: (int) $this->imageAttr($el, 'width'),
                imageHeight: (int) $this->imageAttr($el, 'height'),
                labels: $this->identifiedText($el, 'gapText'),
                gaps: $this->regions($el, 'associableHotspot'),
                correctPairs: $this->pairs($decl['correct']),
                responseIdentifier: $rid,
            ),
            default => throw new QtiException("Unsupported interaction type '{$el->localName}'."),
        };
    }

    /** @param array{correct: list<string>} $decl */
    private function buildMatch(\DOMElement $el, array $decl, string $rid): MatchInteraction
    {
        $sets = $this->childrenByLocal($el, 'simpleMatchSet');
        if (count($sets) < 2) {
            throw new QtiException('matchInteraction needs two simpleMatchSet children.');
        }

        return new MatchInteraction(
            sources: $this->identifiedContent($sets[0], 'simpleAssociableChoice'),
            targets: $this->identifiedContent($sets[1], 'simpleAssociableChoice'),
            correctPairs: $this->pairs($decl['correct']),
            shuffle: $el->getAttribute('shuffle') === 'true',
            responseIdentifier: $rid,
        );
    }

    /** @param array{correct: list<string>} $decl */
    private function buildInlineChoice(\DOMElement $el, array $decl, string $rid): InlineChoiceInteraction
    {
        $before = $after = '';
        $parent = $el->parentNode;
        if ($parent instanceof \DOMElement && $parent->localName !== 'itemBody') {
            $seen = false;
            foreach ($parent->childNodes as $node) {
                if ($node->isSameNode($el)) {
                    $seen = true;
                    continue;
                }
                if ($node instanceof \DOMText) {
                    $seen ? $after .= $node->nodeValue : $before .= $node->nodeValue;
                }
            }
        }

        return new InlineChoiceInteraction(
            choices: $this->identifiedText($el, 'inlineChoice'),
            correct: $decl['correct'][0]
                ?? throw new QtiException('inlineChoiceInteraction has no declared correct response.'),
            textBefore: $before,
            textAfter: $after,
            shuffle: $el->getAttribute('shuffle') === 'true',
            responseIdentifier: $rid,
        );
    }

    /** @return list<string|array{id: string, text: string}> */
    private function collectHottextSegments(\DOMElement $el): array
    {
        $segments = [];
        $walk = function (\DOMNode $node) use (&$walk, &$segments): void {
            foreach ($node->childNodes as $child) {
                if ($child instanceof \DOMText) {
                    if (trim($child->nodeValue) !== '') {
                        $segments[] = $child->nodeValue;
                    }
                } elseif ($child instanceof \DOMElement) {
                    if ($child->localName === 'hottext') {
                        $segments[] = ['id' => $child->getAttribute('identifier'), 'text' => $child->textContent];
                    } elseif ($child->localName !== 'prompt') {
                        $walk($child);
                    }
                }
            }
        };
        $walk($el);

        return $segments;
    }

    // ── Response declarations ────────────────────────────────────────────────

    /** @return array{cardinality: string, baseType: string, correct: list<string>, mapping: array<string, float>} */
    private function parseDeclaration(\DOMElement $el): array
    {
        $correct = [];
        if (($correctEl = $this->firstChildByLocal($el, 'correctResponse')) !== null) {
            foreach ($this->childrenByLocal($correctEl, 'value') as $value) {
                $correct[] = trim($value->textContent);
            }
        }

        $mapping = [];
        if (($mappingEl = $this->firstChildByLocal($el, 'mapping')) !== null) {
            foreach ($this->childrenByLocal($mappingEl, 'mapEntry') as $entry) {
                $mapping[$entry->getAttribute('mapKey')] = (float) $entry->getAttribute('mappedValue');
            }
        }

        return [
            'cardinality' => $el->getAttribute('cardinality'),
            'baseType'    => $el->getAttribute('baseType'),
            'correct'     => $correct,
            'mapping'     => $mapping,
        ];
    }

    // ── Body / prompt handling ───────────────────────────────────────────────

    private function findInteraction(\DOMElement $body): \DOMElement
    {
        foreach ($body->getElementsByTagNameNS('*', '*') as $el) {
            if (in_array($el->localName, self::SUPPORTED, true)) {
                return $el;
            }
        }
        foreach ($body->getElementsByTagName('*') as $el) {
            if (in_array($el->localName, self::SUPPORTED, true)) {
                return $el;
            }
        }

        $found = [];
        foreach ($body->getElementsByTagNameNS('*', '*') as $el) {
            if (str_ends_with((string) $el->localName, 'Interaction')) {
                $found[] = $el->localName;
            }
        }

        throw new QtiException(
            $found === []
                ? 'itemBody contains no recognizable interaction.'
                : "Unsupported interaction type '{$found[0]}'."
        );
    }

    private function extractPrompt(\DOMElement $body, \DOMElement $interaction): string
    {
        $parts = [];
        foreach ($body->childNodes as $child) {
            if ($child instanceof \DOMElement
                && ($child->isSameNode($interaction) || $this->contains($child, $interaction))) {
                continue;
            }
            $parts[] = $child;
        }

        // The interaction may carry its own <prompt>; fold it into the prompt HTML
        $promptChild = $this->firstChildByLocal($interaction, 'prompt');

        $html = '';
        foreach ($parts as $node) {
            $html .= $this->serialize($node);
        }
        if ($promptChild !== null) {
            $html .= $this->innerXml($promptChild);
        }

        $html = trim($html);

        // Unwrap the single <div> wrapper this SDK's own serializer emits
        if (preg_match('/^<div>(.*)<\/div>$/s', $html, $m) === 1) {
            $html = trim($m[1]);
        }

        return $html;
    }

    private function contains(\DOMElement $ancestor, \DOMElement $node): bool
    {
        for ($p = $node->parentNode; $p !== null; $p = $p->parentNode) {
            if ($p->isSameNode($ancestor)) {
                return true;
            }
        }

        return false;
    }

    // ── Small DOM helpers ────────────────────────────────────────────────────

    /** @return list<\DOMElement> */
    private function childrenByLocal(\DOMElement $el, string $local): array
    {
        $out = [];
        foreach ($el->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === $local) {
                $out[] = $child;
            }
        }

        return $out;
    }

    private function firstChildByLocal(\DOMElement $el, string $local): ?\DOMElement
    {
        return $this->childrenByLocal($el, $local)[0] ?? null;
    }

    /** @return array<string, string> identifier => inner XHTML */
    private function identifiedContent(\DOMElement $el, string $childLocal): array
    {
        $out = [];
        foreach ($this->childrenByLocal($el, $childLocal) as $child) {
            $out[$child->getAttribute('identifier')] = $this->innerXml($child);
        }

        return $out;
    }

    /** @return array<string, string> identifier => plain text */
    private function identifiedText(\DOMElement $el, string $childLocal): array
    {
        $out = [];
        foreach ($this->childrenByLocal($el, $childLocal) as $child) {
            $out[$child->getAttribute('identifier')] = $child->textContent;
        }

        return $out;
    }

    /** @return list<array{id: string, shape: string, coords: string}> */
    private function regions(\DOMElement $el, string $childLocal): array
    {
        $out = [];
        foreach ($this->childrenByLocal($el, $childLocal) as $child) {
            $out[] = [
                'id'     => $child->getAttribute('identifier'),
                'shape'  => $child->getAttribute('shape'),
                'coords' => $child->getAttribute('coords'),
            ];
        }

        return $out;
    }

    /** @return list<array{string, string}> */
    private function pairs(array $values): array
    {
        return array_map(function (string $value): array {
            $parts = preg_split('/\s+/', trim($value));
            if (count($parts) !== 2) {
                throw new QtiException("Malformed directedPair value '{$value}'.");
            }

            return [$parts[0], $parts[1]];
        }, $values);
    }

    private function imageAttr(\DOMElement $el, string $attr): string
    {
        $object = $this->firstChildByLocal($el, 'object')
            ?? throw new QtiException("{$el->localName} has no <object> image element.");

        return $object->getAttribute($attr);
    }

    private function intAttr(\DOMElement $el, string $attr): ?int
    {
        $value = $el->getAttribute($attr);

        return $value === '' ? null : (int) $value;
    }

    private function serialize(\DOMNode $node): string
    {
        $xml = $node->ownerDocument->saveXML($node);

        return preg_replace('/ xmlns(:[A-Za-z0-9_-]+)?="[^"]*"/', '', $xml) ?? $xml;
    }

    private function innerXml(\DOMElement $el): string
    {
        $out = '';
        foreach ($el->childNodes as $child) {
            $out .= $this->serialize($child);
        }

        return trim($out);
    }
}
