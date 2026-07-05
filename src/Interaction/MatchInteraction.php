<?php

declare(strict_types=1);

namespace QtiSdk\Interaction;

use QtiSdk\Item\ResponseDeclaration;
use QtiSdk\Xml\XmlUtil;

/**
 * Matching: pair items from a source column with a target column.
 *
 * $sources / $targets:  identifier => XHTML content
 * $correctPairs:        list of [sourceId, targetId]
 */
final class MatchInteraction extends Interaction
{
    /**
     * @param array<string, string>       $sources
     * @param array<string, string>       $targets
     * @param list<array{string, string}> $correctPairs
     */
    public function __construct(
        public readonly array $sources,
        public readonly array $targets,
        public readonly array $correctPairs,
        public readonly bool $shuffle = false,
        string $responseIdentifier = 'RESPONSE',
    ) {
        parent::__construct($responseIdentifier);

        foreach ([...array_keys($sources), ...array_keys($targets)] as $id) {
            XmlUtil::assertValidIdentifier((string) $id, 'match set entry');
        }
        foreach ($correctPairs as [$src, $tgt]) {
            if (!isset($sources[$src]) || !isset($targets[$tgt])) {
                throw new \QtiSdk\QtiException("Correct pair {$src} {$tgt} references an unknown source or target.");
            }
        }
    }

    public function qtiClass(): string
    {
        return 'matchInteraction';
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
        $el  = $doc->createElementNS(\QtiSdk\Xml\ItemSerializer::QTI_NS, 'matchInteraction');
        $el->setAttribute('responseIdentifier', $this->responseIdentifier);
        $el->setAttribute('shuffle', $this->shuffle ? 'true' : 'false');
        $el->setAttribute('maxAssociations', (string) count($this->correctPairs));

        foreach ([$this->sources, $this->targets] as $side => $set) {
            $matchSet = $doc->createElementNS(\QtiSdk\Xml\ItemSerializer::QTI_NS, 'simpleMatchSet');
            foreach ($set as $id => $content) {
                $assoc = $doc->createElementNS(\QtiSdk\Xml\ItemSerializer::QTI_NS, 'simpleAssociableChoice');
                $assoc->setAttribute('identifier', (string) $id);
                // Each source matches exactly one target; targets can host many sources
                $assoc->setAttribute('matchMax', $side === 0 ? '1' : (string) count($this->sources));
                XmlUtil::appendHtml($assoc, $content);
                $matchSet->appendChild($assoc);
            }
            $el->appendChild($matchSet);
        }

        $body->appendChild($el);
    }
}
