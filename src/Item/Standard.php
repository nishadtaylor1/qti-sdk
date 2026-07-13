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
 *
 * $provider identifies who issued the GUID — Eduphoria Aware aligns Academic
 * Benchmarks GUIDs (providerId "org.academicbenchmarks"), which is also where
 * Studies Weekly's ab_standards GUIDs come from. $region names the standard
 * set the GUID belongs to (e.g. "tea#teks#ss:2018"); Aware needs it, alongside
 * the correct QTI curriculum-standards namespace, to actually link the item.
 */
final class Standard
{
    public function __construct(
        public readonly string $code,
        public readonly ?string $guid = null,
        public readonly string $provider = 'org.academicbenchmarks',
        public readonly ?string $region = null,
    ) {
        if (trim($code) === '') {
            throw new QtiException('A standard needs a non-empty code.');
        }
    }

    /** Accepts 'CODE', ['code' => ..., 'guid' => ..., 'provider' => ..., 'region' => ...], or a Standard. */
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

        return new self(
            (string) $value['code'],
            isset($value['guid']) ? (string) $value['guid'] : null,
            isset($value['provider']) ? (string) $value['provider'] : 'org.academicbenchmarks',
            isset($value['region']) ? (string) $value['region'] : null,
        );
    }
}
