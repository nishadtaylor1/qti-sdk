<?php

declare(strict_types=1);

namespace QtiSdk\Interaction;

use QtiSdk\Item\ResponseDeclaration;
use QtiSdk\Xml\XmlUtil;

/**
 * Hot text: the learner selects word(s)/phrase(s) inside a passage.
 *
 * $segments is the passage in order. Plain strings render as text;
 * ['id' => ..., 'text' => ...] entries become selectable hottext spans.
 */
final class HottextInteraction extends Interaction
{
    /**
     * @param list<string|array{id: string, text: string}> $segments
     * @param list<string>                                 $correct hottext ids
     */
    public function __construct(
        public readonly array $segments,
        public readonly array $correct,
        public readonly int $maxChoices = 1,
        string $responseIdentifier = 'RESPONSE',
    ) {
        parent::__construct($responseIdentifier);

        $ids = [];
        foreach ($segments as $seg) {
            if (is_array($seg)) {
                XmlUtil::assertValidIdentifier($seg['id'], 'hottext span');
                $ids[$seg['id']] = true;
            }
        }
        if ($ids === []) {
            throw new \QtiSdk\QtiException('Hot text needs at least one selectable span.');
        }
        foreach ($correct as $id) {
            if (!isset($ids[$id])) {
                throw new \QtiSdk\QtiException("Correct hottext '{$id}' does not exist in the passage.");
            }
        }
    }

    public function qtiClass(): string
    {
        return 'hottextInteraction';
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
        $el  = $doc->createElementNS(\QtiSdk\Xml\ItemSerializer::QTI_NS, 'hottextInteraction');
        $el->setAttribute('responseIdentifier', $this->responseIdentifier);
        $el->setAttribute('maxChoices', (string) $this->maxChoices);

        $p = $doc->createElementNS(\QtiSdk\Xml\ItemSerializer::QTI_NS, 'p');
        foreach ($this->segments as $seg) {
            if (is_array($seg)) {
                $span = $doc->createElementNS(\QtiSdk\Xml\ItemSerializer::QTI_NS, 'hottext');
                $span->setAttribute('identifier', $seg['id']);
                $span->appendChild($doc->createTextNode($seg['text']));
                $p->appendChild($span);
            } else {
                $p->appendChild($doc->createTextNode($seg));
            }
        }
        $el->appendChild($p);

        $body->appendChild($el);
    }
}
