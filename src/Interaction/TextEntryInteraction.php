<?php

declare(strict_types=1);

namespace QtiSdk\Interaction;

use QtiSdk\Item\ResponseDeclaration;

/**
 * Short typed answer (fill in the blank).
 *
 * The interaction renders inline inside the prompt; the item body places it
 * after the prompt content. Alternate acceptable answers score via mapping.
 */
final class TextEntryInteraction extends Interaction
{
    /** @param list<string> $acceptedAnswers first entry is the canonical correct answer */
    public function __construct(
        public readonly array $acceptedAnswers,
        public readonly ?int $expectedLength = null,
        string $responseIdentifier = 'RESPONSE',
    ) {
        parent::__construct($responseIdentifier);
        if ($acceptedAnswers === []) {
            throw new \QtiSdk\QtiException('Text entry needs at least one accepted answer.');
        }
    }

    public function qtiClass(): string
    {
        return 'textEntryInteraction';
    }

    public function responseDeclaration(): ResponseDeclaration
    {
        $mapping = [];
        if (count($this->acceptedAnswers) > 1) {
            foreach ($this->acceptedAnswers as $answer) {
                $mapping[$answer] = 1.0;
            }
        }

        return new ResponseDeclaration(
            identifier: $this->responseIdentifier,
            cardinality: 'single',
            baseType: 'string',
            correctValues: [$this->acceptedAnswers[0]],
            mapping: $mapping,
        );
    }

    public function appendTo(\DOMElement $body): void
    {
        $doc = $body->ownerDocument;
        $p   = $doc->createElementNS(\QtiSdk\Xml\ItemSerializer::QTI_NS, 'p');
        $el  = $doc->createElementNS(\QtiSdk\Xml\ItemSerializer::QTI_NS, 'textEntryInteraction');
        $el->setAttribute('responseIdentifier', $this->responseIdentifier);
        if ($this->expectedLength !== null) {
            $el->setAttribute('expectedLength', (string) $this->expectedLength);
        }
        $p->appendChild($el);
        $body->appendChild($p);
    }
}
