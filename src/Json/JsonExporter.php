<?php

declare(strict_types=1);

namespace QtiSdk\Json;

use QtiSdk\Interaction\ChoiceInteraction;
use QtiSdk\Interaction\ExtendedTextInteraction;
use QtiSdk\Interaction\GraphicGapMatchInteraction;
use QtiSdk\Interaction\HotspotInteraction;
use QtiSdk\Interaction\HottextInteraction;
use QtiSdk\Interaction\InlineChoiceInteraction;
use QtiSdk\Interaction\Interaction;
use QtiSdk\Interaction\MatchInteraction;
use QtiSdk\Interaction\OrderInteraction;
use QtiSdk\Interaction\TextEntryInteraction;
use QtiSdk\Item\AssessmentItem;
use QtiSdk\Packaging\ContentPackage;
use QtiSdk\QtiException;

/**
 * Exports items and packages to a JSON representation of the SDK's object
 * model. QTI has no official JSON binding; this format exists for debugging,
 * data pipelines, and non-XML consumers. The "format"/"version" envelope
 * keys allow a future importer to evolve without breaking older exports.
 */
final class JsonExporter
{
    public const FORMAT  = 'qti-sdk';
    public const VERSION = 1;

    /** @return array<string, mixed> */
    public function exportItem(AssessmentItem $item): array
    {
        return [
            'identifier'  => $item->identifier,
            'title'       => $item->title,
            'language'    => $item->language,
            'standards'   => $item->standards,
            'promptHtml'  => $item->promptHtml,
            'interaction' => $this->exportInteraction($item->interaction),
        ];
    }

    /** @return array<string, mixed> */
    public function exportPackage(ContentPackage $package): array
    {
        return [
            'format'  => self::FORMAT,
            'version' => self::VERSION,
            'package' => [
                'identifier' => $package->identifier,
                'title'      => $package->title,
                'items'      => array_values(array_map(
                    fn (AssessmentItem $item) => $this->exportItem($item),
                    $package->items()
                )),
                'mediaFiles' => array_keys($package->mediaFiles()),
            ],
        ];
    }

    public function toJson(ContentPackage|AssessmentItem $subject, bool $pretty = true): string
    {
        $data = $subject instanceof ContentPackage
            ? $this->exportPackage($subject)
            : ['format' => self::FORMAT, 'version' => self::VERSION, 'item' => $this->exportItem($subject)];

        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;
        if ($pretty) {
            $flags |= JSON_PRETTY_PRINT;
        }

        return json_encode($data, $flags);
    }

    /** @return array<string, mixed> */
    private function exportInteraction(Interaction $interaction): array
    {
        $base = ['responseIdentifier' => $interaction->responseIdentifier];

        return $base + match (get_class($interaction)) {
            ChoiceInteraction::class => [
                'type'       => 'choice',
                'choices'    => $interaction->choices,
                'correct'    => $interaction->correct,
                'maxChoices' => $interaction->maxChoices,
                'shuffle'    => $interaction->shuffle,
            ],
            MatchInteraction::class => [
                'type'         => 'match',
                'sources'      => $interaction->sources,
                'targets'      => $interaction->targets,
                'correctPairs' => $interaction->correctPairs,
                'shuffle'      => $interaction->shuffle,
            ],
            TextEntryInteraction::class => [
                'type'            => 'textEntry',
                'acceptedAnswers' => $interaction->acceptedAnswers,
                'expectedLength'  => $interaction->expectedLength,
            ],
            ExtendedTextInteraction::class => [
                'type'          => 'extendedText',
                'expectedLines' => $interaction->expectedLines,
            ],
            InlineChoiceInteraction::class => [
                'type'       => 'inlineChoice',
                'choices'    => $interaction->choices,
                'correct'    => $interaction->correct,
                'textBefore' => $interaction->textBefore,
                'textAfter'  => $interaction->textAfter,
                'shuffle'    => $interaction->shuffle,
            ],
            HottextInteraction::class => [
                'type'       => 'hottext',
                'segments'   => $interaction->segments,
                'correct'    => $interaction->correct,
                'maxChoices' => $interaction->maxChoices,
            ],
            OrderInteraction::class => [
                'type'         => 'order',
                'choices'      => $interaction->choices,
                'correctOrder' => $interaction->correctOrder,
                'shuffle'      => $interaction->shuffle,
            ],
            HotspotInteraction::class => [
                'type'        => 'hotspot',
                'imageHref'   => $interaction->imageHref,
                'imageWidth'  => $interaction->imageWidth,
                'imageHeight' => $interaction->imageHeight,
                'hotspots'    => $interaction->hotspots,
                'correct'     => $interaction->correct,
                'maxChoices'  => $interaction->maxChoices,
            ],
            GraphicGapMatchInteraction::class => [
                'type'         => 'graphicGapMatch',
                'imageHref'    => $interaction->imageHref,
                'imageWidth'   => $interaction->imageWidth,
                'imageHeight'  => $interaction->imageHeight,
                'labels'       => $interaction->labels,
                'gaps'         => $interaction->gaps,
                'correctPairs' => $interaction->correctPairs,
            ],
            default => throw new QtiException(
                'No JSON export mapping for interaction ' . get_class($interaction)
            ),
        };
    }
}
