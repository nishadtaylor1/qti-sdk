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
}
