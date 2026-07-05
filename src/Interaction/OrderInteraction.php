<?php

declare(strict_types=1);

namespace QtiSdk\Interaction;

use QtiSdk\Item\ResponseDeclaration;
use QtiSdk\Xml\ItemSerializer;
use QtiSdk\Xml\XmlUtil;

/**
 * Ordering / sequencing: the learner arranges items into the correct order.
 *
 * $choices:      identifier => XHTML content
 * $correctOrder: choice identifiers in the correct sequence
 */
final class OrderInteraction extends Interaction
{
    /**
     * @param array<string, string> $choices
     * @param list<string>          $correctOrder
     */
    public function __construct(
        public readonly array $choices,
        public readonly array $correctOrder,
        public readonly bool $shuffle = true,
        string $responseIdentifier = 'RESPONSE',
    ) {
        parent::__construct($responseIdentifier);

        foreach (array_keys($choices) as $id) {
            XmlUtil::assertValidIdentifier((string) $id, 'order choice');
        }
        if (count($correctOrder) !== count($choices)
            || array_diff($correctOrder, array_keys($choices)) !== []
            || array_diff(array_keys($choices), $correctOrder) !== []) {
            throw new \QtiSdk\QtiException(
                'correctOrder must be a permutation of every choice identifier.'
            );
        }
    }

    public function qtiClass(): string
    {
        return 'orderInteraction';
    }

    public function responseDeclaration(): ResponseDeclaration
    {
        return new ResponseDeclaration(
            identifier: $this->responseIdentifier,
            cardinality: 'ordered',
            baseType: 'identifier',
            correctValues: $this->correctOrder,
        );
    }

    public function appendTo(\DOMElement $body): void
    {
        $doc = $body->ownerDocument;
        $el  = $doc->createElementNS(ItemSerializer::QTI_NS, 'orderInteraction');
        $el->setAttribute('responseIdentifier', $this->responseIdentifier);
        $el->setAttribute('shuffle', $this->shuffle ? 'true' : 'false');

        foreach ($this->choices as $id => $content) {
            $choice = $doc->createElementNS(ItemSerializer::QTI_NS, 'simpleChoice');
            $choice->setAttribute('identifier', (string) $id);
            XmlUtil::appendHtml($choice, $content);
            $el->appendChild($choice);
        }

        $body->appendChild($el);
    }
}
