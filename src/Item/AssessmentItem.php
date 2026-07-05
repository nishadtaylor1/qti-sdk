<?php

declare(strict_types=1);

namespace QtiSdk\Item;

use QtiSdk\Interaction\Interaction;
use QtiSdk\Xml\XmlUtil;

/**
 * One QTI 2.2 assessment item: a prompt plus a single interaction.
 *
 * $standards carries human-readable standard codes (e.g. TEKS "113.14.b.2.A");
 * they are emitted as LOM keywords in the package manifest so the receiving
 * platform can attach them on import.
 */
final class AssessmentItem
{
    /** @param list<string> $standards */
    public function __construct(
        public readonly string $identifier,
        public readonly string $title,
        public readonly Interaction $interaction,
        public readonly string $promptHtml = '',
        public readonly array $standards = [],
        public readonly string $language = 'en-US',
    ) {
        XmlUtil::assertValidIdentifier($identifier, "item '{$title}'");
    }
}
