<?php

declare(strict_types=1);

namespace QtiSdk\Interaction;

use QtiSdk\Item\ResponseDeclaration;

abstract class Interaction
{
    public function __construct(
        public readonly string $responseIdentifier = 'RESPONSE',
    ) {
    }

    /** The QTI element name, e.g. "choiceInteraction". */
    abstract public function qtiClass(): string;

    abstract public function responseDeclaration(): ResponseDeclaration;

    /** Build this interaction's XML inside the given itemBody element. */
    abstract public function appendTo(\DOMElement $body): void;

    /** Whether the item can be machine-scored from declared correct answers. */
    public function isAutoScorable(): bool
    {
        return true;
    }

    /**
     * Maximum attainable raw score — emitted as the SCORE outcome's
     * normalMaximum so importers (e.g. Eduphoria Aware) don't have to guess.
     * Mapped responses total their positive map values; everything else is
     * all-or-nothing (1). Human-scored interactions override with their own
     * point value.
     */
    public function maxScore(): float
    {
        $mapping = $this->responseDeclaration()->mapping;
        if ($mapping !== []) {
            $positive = array_filter($mapping, static fn ($points) => $points > 0);

            return $positive === [] ? 1.0 : (float) array_sum($positive);
        }

        return 1.0;
    }
}
