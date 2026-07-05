<?php

declare(strict_types=1);

namespace QtiSdk\Interaction;

use QtiSdk\QtiException;
use QtiSdk\Xml\ItemSerializer;

/**
 * Shared helpers for interactions built on a background image with
 * coordinate-addressed regions (hotspot, graphic gap match).
 *
 * Region shape/coords follow the QTI area conventions:
 *   rect:   "x1,y1,x2,y2"   circle: "cx,cy,r"   poly: "x1,y1,x2,y2,..."
 */
trait GraphicInteractionTrait
{
    private const VALID_SHAPES = ['rect', 'circle', 'poly', 'ellipse', 'default'];

    private static function assertShape(string $shape): void
    {
        if (!in_array($shape, self::VALID_SHAPES, true)) {
            throw new QtiException(
                "Invalid hotspot shape '{$shape}'; expected one of: " . implode(', ', self::VALID_SHAPES)
            );
        }
    }

    private static function mimeFromHref(string $href): string
    {
        return match (strtolower(pathinfo($href, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'gif'         => 'image/gif',
            'svg'         => 'image/svg+xml',
            'webp'        => 'image/webp',
            default       => 'image/png',
        };
    }

    private function appendImageObject(\DOMElement $parent, string $href, int $width, int $height): void
    {
        $doc = $parent->ownerDocument;
        $obj = $doc->createElementNS(ItemSerializer::QTI_NS, 'object');
        $obj->setAttribute('data', $href);
        $obj->setAttribute('type', self::mimeFromHref($href));
        $obj->setAttribute('width', (string) $width);
        $obj->setAttribute('height', (string) $height);
        $parent->appendChild($obj);
    }
}
