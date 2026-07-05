<?php

declare(strict_types=1);

namespace QtiSdk\Interaction;

use QtiSdk\Item\ResponseDeclaration;
use QtiSdk\Xml\XmlUtil;

/**
 * Multiple choice / multiple select.
 *
 * $choices:  identifier => XHTML content, e.g. ['A' => 'George Washington', ...]
 * $correct:  list of correct identifiers; more than one (or $maxChoices != 1)
 *            makes this a multiple-select item.
 */
final class ChoiceInteraction extends Interaction
{
    /**
     * @param array<string, string> $choices
     * @param list<string>          $correct
     */
    public function __construct(
        public readonly array $choices,
        public readonly array $correct,
        public readonly int $maxChoices = 1,
        public readonly bool $shuffle = false,
        string $responseIdentifier = 'RESPONSE',
    ) {
        parent::__construct($responseIdentifier);

        foreach (array_keys($choices) as $id) {
            XmlUtil::assertValidIdentifier((string) $id, 'choice');
        }
        foreach ($correct as $id) {
            if (!isset($choices[$id])) {
                throw new \QtiSdk\QtiException("Correct value '{$id}' is not one of the choices.");
            }
        }
        if ($correct === []) {
            throw new \QtiSdk\QtiException('A choice interaction needs at least one correct choice.');
        }
    }

    public function qtiClass(): string
    {
        return 'choiceInteraction';
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
        $el  = $doc->createElementNS(\QtiSdk\Xml\ItemSerializer::QTI_NS, 'choiceInteraction');
        $el->setAttribute('responseIdentifier', $this->responseIdentifier);
        $el->setAttribute('shuffle', $this->shuffle ? 'true' : 'false');
        $el->setAttribute('maxChoices', (string) $this->maxChoices);

        foreach ($this->choices as $id => $content) {
            $choice = $doc->createElementNS(\QtiSdk\Xml\ItemSerializer::QTI_NS, 'simpleChoice');
            $choice->setAttribute('identifier', (string) $id);
            XmlUtil::appendHtml($choice, $content);
            $el->appendChild($choice);
        }

        $body->appendChild($el);
    }
}
