<?php

declare(strict_types=1);

namespace QtiSdk\Item;

use QtiSdk\Interaction\Interaction;
use QtiSdk\Xml\XmlUtil;

/**
 * One QTI 2.2 assessment item: a prompt plus a single interaction.
 *
 * $standards entries may be plain code strings ('113.15.b.8.A'), arrays
 * (['code' => ..., 'guid' => ...]), or Standard objects. Codes are emitted
 * as LOM keywords in the manifest; GUIDs additionally go into an IMS
 * curriculum standards metadata block for importers that align by GUID.
 *
 * @property-read list<Standard> $standards
 */
final class AssessmentItem
{
    /** @var list<Standard> */
    public readonly array $standards;

    /** @param list<string|array{code: string, guid?: string}|Standard> $standards */
    public function __construct(
        public readonly string $identifier,
        public readonly string $title,
        public readonly Interaction $interaction,
        public readonly string $promptHtml = '',
        array $standards = [],
        public readonly string $language = 'en-US',
    ) {
        XmlUtil::assertValidIdentifier($identifier, "item '{$title}'");
        $this->standards = array_map(Standard::from(...), array_values($standards));
    }
}
