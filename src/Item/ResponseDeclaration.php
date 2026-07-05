<?php

declare(strict_types=1);

namespace QtiSdk\Item;

/**
 * Declares what a correct response looks like for one interaction.
 *
 * $mapping (value => points) is optional; when present the item uses the
 * map_response processing template instead of match_correct, which is how
 * QTI expresses "several acceptable answers" (e.g. text entry synonyms).
 */
final class ResponseDeclaration
{
    /**
     * @param list<string>          $correctValues
     * @param array<string, float>  $mapping
     */
    public function __construct(
        public readonly string $identifier,
        public readonly string $cardinality,   // single | multiple | ordered
        public readonly string $baseType,      // identifier | string | directedPair
        public readonly array $correctValues = [],
        public readonly array $mapping = [],
    ) {
    }

    public function toDom(\DOMDocument $doc): \DOMElement
    {
        $el = $doc->createElementNS(\QtiSdk\Xml\ItemSerializer::QTI_NS, 'responseDeclaration');
        $el->setAttribute('identifier', $this->identifier);
        $el->setAttribute('cardinality', $this->cardinality);
        $el->setAttribute('baseType', $this->baseType);

        if ($this->correctValues !== []) {
            $correct = $doc->createElementNS(\QtiSdk\Xml\ItemSerializer::QTI_NS, 'correctResponse');
            foreach ($this->correctValues as $value) {
                $v = $doc->createElementNS(\QtiSdk\Xml\ItemSerializer::QTI_NS, 'value');
                $v->appendChild($doc->createTextNode($value));
                $correct->appendChild($v);
            }
            $el->appendChild($correct);
        }

        if ($this->mapping !== []) {
            $mapping = $doc->createElementNS(\QtiSdk\Xml\ItemSerializer::QTI_NS, 'mapping');
            $mapping->setAttribute('defaultValue', '0');
            foreach ($this->mapping as $key => $points) {
                $entry = $doc->createElementNS(\QtiSdk\Xml\ItemSerializer::QTI_NS, 'mapEntry');
                $entry->setAttribute('mapKey', (string) $key);
                $entry->setAttribute('mappedValue', rtrim(rtrim(number_format($points, 4, '.', ''), '0'), '.'));
                $mapping->appendChild($entry);
            }
            $el->appendChild($mapping);
        }

        return $el;
    }
}
