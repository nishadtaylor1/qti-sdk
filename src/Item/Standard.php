<?php

declare(strict_types=1);

namespace QtiSdk\Item;

use QtiSdk\QtiException;

/**
 * A curriculum standard attached to an item.
 *
 * $code is the human-readable identifier (e.g. TEKS "113.15.b.8.A").
 * $guid is the machine-readable identifier used by importers that align by
 * GUID (e.g. TEA's identifiers from teks.texasgateway.org for Eduphoria
 * Aware). Codes without GUIDs still travel as LOM keywords, but GUID-aligned
 * platforms will not auto-attach them.
 */
final class Standard
{
    public function __construct(
        public readonly string $code,
        public readonly ?string $guid = null,
    ) {
        if (trim($code) === '') {
            throw new QtiException('A standard needs a non-empty code.');
        }
    }

    /** Accepts 'CODE', ['code' => ..., 'guid' => ...], or a Standard. */
    public static function from(string|array|self $value): self
    {
        if ($value instanceof self) {
            return $value;
        }
        if (is_string($value)) {
            return new self($value);
        }
        if (!isset($value['code'])) {
            throw new QtiException("Array-form standards need a 'code' key.");
        }

        return new self((string) $value['code'], isset($value['guid']) ? (string) $value['guid'] : null);
    }
}
