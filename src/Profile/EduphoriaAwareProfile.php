<?php

declare(strict_types=1);

namespace QtiSdk\Profile;

use QtiSdk\Interaction\ChoiceInteraction;
use QtiSdk\Interaction\ExtendedTextInteraction;
use QtiSdk\Interaction\HottextInteraction;
use QtiSdk\Interaction\InlineChoiceInteraction;
use QtiSdk\Interaction\MatchInteraction;
use QtiSdk\Interaction\TextEntryInteraction;
use QtiSdk\Packaging\ContentPackage;

/**
 * Import constraints for Eduphoria Aware (Custom Item Banks, Aware Premium),
 * per Eduphoria's published documentation:
 *
 * - QTI 2.2 and below; packages must be zips with a top-level imsmanifest.xml
 * - Max upload size 20 MB per package
 * - Supported: multiple choice, matching, hotspot, inline text selection
 *   (hottext), dropdown (inline choice), short/long text entry
 * - Unsupported: associations, drawing, sliders, file upload, custom widgets
 * - Standards should be attached so items surface in Author
 */
final class EduphoriaAwareProfile
{
    public const MAX_PACKAGE_BYTES = 20_000_000;

    private const SUPPORTED_INTERACTIONS = [
        ChoiceInteraction::class,
        MatchInteraction::class,
        TextEntryInteraction::class,
        ExtendedTextInteraction::class,
        InlineChoiceInteraction::class,
        HottextInteraction::class,
    ];

    /**
     * Validate a package against the Aware import profile.
     *
     * @return list<array{level: 'error'|'warning', message: string}>
     */
    public function validate(ContentPackage $package, ?string $writtenZipPath = null): array
    {
        $issues = [];

        if ($package->items() === []) {
            $issues[] = ['level' => 'error', 'message' => 'Package contains no items.'];
        }

        foreach ($package->items() as $item) {
            $class = get_class($item->interaction);
            if (!in_array($class, self::SUPPORTED_INTERACTIONS, true)) {
                $issues[] = [
                    'level'   => 'error',
                    'message' => "Item '{$item->identifier}' uses {$class}, which Aware does not import.",
                ];
            }
            if ($item->standards === []) {
                $issues[] = [
                    'level'   => 'warning',
                    'message' => "Item '{$item->identifier}' has no standards attached — it will import " .
                                 'but will not surface in Aware Author until standards are assigned.',
                ];
            }
        }

        if ($writtenZipPath !== null && is_file($writtenZipPath)) {
            $size = filesize($writtenZipPath);
            if ($size !== false && $size > self::MAX_PACKAGE_BYTES) {
                $issues[] = [
                    'level'   => 'error',
                    'message' => sprintf(
                        'Package is %.1f MB; Aware rejects uploads over 20 MB. Split it into smaller packages.',
                        $size / 1_000_000
                    ),
                ];
            }
        }

        return $issues;
    }

    /** @return list<array{level: string, message: string}> */
    public function errors(array $issues): array
    {
        return array_values(array_filter($issues, fn ($i) => $i['level'] === 'error'));
    }
}
