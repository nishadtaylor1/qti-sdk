<?php

declare(strict_types=1);

namespace QtiSdk\Interaction;

use QtiSdk\Item\ResponseDeclaration;
use QtiSdk\Xml\ItemSerializer;
use QtiSdk\Xml\XmlUtil;

/**
 * Labeling: the learner drags text labels onto target regions of an image.
 *
 * $imageHref is the package-relative path of the image; bundle the file via
 * ContentPackage::addMediaFile() with the same path.
 *
 * $labels:       label identifier => text
 * $gaps:         list of ['id' => ..., 'shape' => ..., 'coords' => ...] target regions
 * $correctPairs: list of [labelId, gapId]
 */
final class GraphicGapMatchInteraction extends Interaction
{
    use GraphicInteractionTrait;

    /**
     * @param array<string, string>                                  $labels
     * @param list<array{id: string, shape: string, coords: string}> $gaps
     * @param list<array{string, string}>                            $correctPairs
     */
    public function __construct(
        public readonly string $imageHref,
        public readonly int $imageWidth,
        public readonly int $imageHeight,
        public readonly array $labels,
        public readonly array $gaps,
        public readonly array $correctPairs,
        string $responseIdentifier = 'RESPONSE',
    ) {
        parent::__construct($responseIdentifier);

        foreach (array_keys($labels) as $id) {
            XmlUtil::assertValidIdentifier((string) $id, 'gap label');
        }
        $gapIds = [];
        foreach ($gaps as $gap) {
            XmlUtil::assertValidIdentifier($gap['id'], 'gap target');
            self::assertShape($gap['shape']);
            $gapIds[$gap['id']] = true;
        }
        if ($labels === [] || $gapIds === []) {
            throw new \QtiSdk\QtiException('Graphic gap match needs at least one label and one gap.');
        }
        foreach ($correctPairs as [$label, $gap]) {
            if (!isset($labels[$label]) || !isset($gapIds[$gap])) {
                throw new \QtiSdk\QtiException("Correct pair {$label} {$gap} references an unknown label or gap.");
            }
        }
        if ($correctPairs === []) {
            throw new \QtiSdk\QtiException('Graphic gap match needs at least one correct pair.');
        }
    }

    public function qtiClass(): string
    {
        return 'graphicGapMatchInteraction';
    }

    public function responseDeclaration(): ResponseDeclaration
    {
        return new ResponseDeclaration(
            identifier: $this->responseIdentifier,
            cardinality: 'multiple',
            baseType: 'directedPair',
            correctValues: array_map(fn ($p) => "{$p[0]} {$p[1]}", $this->correctPairs),
        );
    }

    public function appendTo(\DOMElement $body): void
    {
        $doc = $body->ownerDocument;
        $el  = $doc->createElementNS(ItemSerializer::QTI_NS, 'graphicGapMatchInteraction');
        $el->setAttribute('responseIdentifier', $this->responseIdentifier);

        $this->appendImageObject($el, $this->imageHref, $this->imageWidth, $this->imageHeight);

        foreach ($this->labels as $id => $text) {
            $gapText = $doc->createElementNS(ItemSerializer::QTI_NS, 'gapText');
            $gapText->setAttribute('identifier', (string) $id);
            $gapText->setAttribute('matchMax', '1');
            $gapText->appendChild($doc->createTextNode($text));
            $el->appendChild($gapText);
        }

        foreach ($this->gaps as $gap) {
            $spot = $doc->createElementNS(ItemSerializer::QTI_NS, 'associableHotspot');
            $spot->setAttribute('identifier', $gap['id']);
            $spot->setAttribute('shape', $gap['shape']);
            $spot->setAttribute('coords', $gap['coords']);
            $spot->setAttribute('matchMax', '1');
            $el->appendChild($spot);
        }

        $body->appendChild($el);
    }
}
