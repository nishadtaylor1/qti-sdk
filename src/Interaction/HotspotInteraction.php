<?php

declare(strict_types=1);

namespace QtiSdk\Interaction;

use QtiSdk\Item\ResponseDeclaration;
use QtiSdk\Xml\ItemSerializer;
use QtiSdk\Xml\XmlUtil;

/**
 * Hot spot: the learner selects region(s) on an image.
 *
 * $imageHref is the package-relative path of the image; bundle the file via
 * ContentPackage::addMediaFile() with the same path.
 *
 * $hotspots: list of ['id' => ..., 'shape' => rect|circle|poly, 'coords' => 'x1,y1,...']
 */
final class HotspotInteraction extends Interaction
{
    use GraphicInteractionTrait;

    /**
     * @param list<array{id: string, shape: string, coords: string}> $hotspots
     * @param list<string>                                           $correct
     */
    public function __construct(
        public readonly string $imageHref,
        public readonly int $imageWidth,
        public readonly int $imageHeight,
        public readonly array $hotspots,
        public readonly array $correct,
        public readonly int $maxChoices = 1,
        string $responseIdentifier = 'RESPONSE',
    ) {
        parent::__construct($responseIdentifier);

        $ids = [];
        foreach ($hotspots as $spot) {
            XmlUtil::assertValidIdentifier($spot['id'], 'hotspot');
            self::assertShape($spot['shape']);
            $ids[$spot['id']] = true;
        }
        if ($ids === []) {
            throw new \QtiSdk\QtiException('A hotspot interaction needs at least one hotspot.');
        }
        foreach ($correct as $id) {
            if (!isset($ids[$id])) {
                throw new \QtiSdk\QtiException("Correct hotspot '{$id}' is not defined.");
            }
        }
        if ($correct === []) {
            throw new \QtiSdk\QtiException('A hotspot interaction needs at least one correct hotspot.');
        }
    }

    public function qtiClass(): string
    {
        return 'hotspotInteraction';
    }

    public function responseDeclaration(): ResponseDeclaration
    {
        $multiple = $this->maxChoices !== 1 || count($this->correct) > 1;

        return new ResponseDeclaration(
            identifier: $this->responseIdentifier,
            cardinality: $multiple ? 'multiple' : 'single',
            baseType: 'identifier',
            correctValues: $this->correct,
        );
    }

    public function appendTo(\DOMElement $body): void
    {
        $doc = $body->ownerDocument;
        $el  = $doc->createElementNS(ItemSerializer::QTI_NS, 'hotspotInteraction');
        $el->setAttribute('responseIdentifier', $this->responseIdentifier);
        $el->setAttribute('maxChoices', (string) $this->maxChoices);

        $this->appendImageObject($el, $this->imageHref, $this->imageWidth, $this->imageHeight);

        foreach ($this->hotspots as $spot) {
            $choice = $doc->createElementNS(ItemSerializer::QTI_NS, 'hotspotChoice');
            $choice->setAttribute('identifier', $spot['id']);
            $choice->setAttribute('shape', $spot['shape']);
            $choice->setAttribute('coords', $spot['coords']);
            $el->appendChild($choice);
        }

        $body->appendChild($el);
    }
}
