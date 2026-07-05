<?php

declare(strict_types=1);

namespace QtiSdk\Interaction;

use QtiSdk\Item\ResponseDeclaration;

/**
 * Long constructed response (essay / open response). Human-scored:
 * no correct response is declared and no processing template is emitted.
 */
final class ExtendedTextInteraction extends Interaction
{
    public function __construct(
        public readonly ?int $expectedLines = null,
        string $responseIdentifier = 'RESPONSE',
    ) {
        parent::__construct($responseIdentifier);
    }

    public function qtiClass(): string
    {
        return 'extendedTextInteraction';
    }

    public function isAutoScorable(): bool
    {
        return false;
    }

    public function responseDeclaration(): ResponseDeclaration
    {
        return new ResponseDeclaration(
            identifier: $this->responseIdentifier,
            cardinality: 'single',
            baseType: 'string',
        );
    }

    public function appendTo(\DOMElement $body): void
    {
        $el = $body->ownerDocument->createElementNS(\QtiSdk\Xml\ItemSerializer::QTI_NS, 'extendedTextInteraction');
        $el->setAttribute('responseIdentifier', $this->responseIdentifier);
        if ($this->expectedLines !== null) {
            $el->setAttribute('expectedLines', (string) $this->expectedLines);
        }
        $body->appendChild($el);
    }
}
