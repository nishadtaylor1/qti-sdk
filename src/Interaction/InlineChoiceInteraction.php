<?php

declare(strict_types=1);

namespace QtiSdk\Interaction;

use QtiSdk\Item\ResponseDeclaration;
use QtiSdk\Xml\XmlUtil;

/**
 * Dropdown embedded in a sentence: "The capital of Texas is [▾]."
 *
 * $textBefore / $textAfter are the plain-text sentence fragments around
 * the dropdown; $choices maps identifier => label.
 */
final class InlineChoiceInteraction extends Interaction
{
    /** @param array<string, string> $choices */
    public function __construct(
        public readonly array $choices,
        public readonly string $correct,
        public readonly string $textBefore = '',
        public readonly string $textAfter = '',
        public readonly bool $shuffle = false,
        string $responseIdentifier = 'RESPONSE',
    ) {
        parent::__construct($responseIdentifier);

        foreach (array_keys($choices) as $id) {
            XmlUtil::assertValidIdentifier((string) $id, 'inline choice');
        }
        if (!isset($choices[$correct])) {
            throw new \QtiSdk\QtiException("Correct value '{$correct}' is not one of the inline choices.");
        }
    }

    public function qtiClass(): string
    {
        return 'inlineChoiceInteraction';
    }

    public function responseDeclaration(): ResponseDeclaration
    {
        return new ResponseDeclaration(
            identifier: $this->responseIdentifier,
            cardinality: 'single',
            baseType: 'identifier',
            correctValues: [$this->correct],
        );
    }

    public function appendTo(\DOMElement $body): void
    {
        $doc = $body->ownerDocument;
        $p   = $doc->createElementNS(\QtiSdk\Xml\ItemSerializer::QTI_NS, 'p');

        if ($this->textBefore !== '') {
            $p->appendChild($doc->createTextNode($this->textBefore));
        }

        $el = $doc->createElementNS(\QtiSdk\Xml\ItemSerializer::QTI_NS, 'inlineChoiceInteraction');
        $el->setAttribute('responseIdentifier', $this->responseIdentifier);
        $el->setAttribute('shuffle', $this->shuffle ? 'true' : 'false');
        foreach ($this->choices as $id => $label) {
            $choice = $doc->createElementNS(\QtiSdk\Xml\ItemSerializer::QTI_NS, 'inlineChoice');
            $choice->setAttribute('identifier', (string) $id);
            $choice->appendChild($doc->createTextNode($label));
            $el->appendChild($choice);
        }
        $p->appendChild($el);

        if ($this->textAfter !== '') {
            $p->appendChild($doc->createTextNode($this->textAfter));
        }

        $body->appendChild($p);
    }
}
