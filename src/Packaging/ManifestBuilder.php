<?php

declare(strict_types=1);

namespace QtiSdk\Packaging;

/**
 * Builds the imsmanifest.xml for a QTI 2.2 content package.
 *
 * Item standards are emitted as LOM general/keyword entries in each
 * resource's metadata — the most widely-read place for alignment codes.
 */
final class ManifestBuilder
{
    public const CP_NS  = 'http://www.imsglobal.org/xsd/imscp_v1p1';
    public const LOM_NS = 'http://ltsc.ieee.org/xsd/LOM';
    public const XSI_NS = 'http://www.w3.org/2001/XMLSchema-instance';

    public const QTI_ITEM_RESOURCE_TYPE = 'imsqti_item_xmlv2p2';

    public function build(ContentPackage $package): \DOMDocument
    {
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        $manifest = $doc->createElementNS(self::CP_NS, 'manifest');
        $manifest->setAttribute('identifier', 'MANIFEST-' . $package->identifier);
        $manifest->setAttributeNS(
            'http://www.w3.org/2000/xmlns/',
            'xmlns:imsmd',
            self::LOM_NS
        );
        $manifest->setAttributeNS(self::XSI_NS, 'xsi:schemaLocation',
            self::CP_NS . ' http://www.imsglobal.org/xsd/qti/qtiv2p2/qtiv2p2_imscpv1p2_v1p0.xsd');
        $doc->appendChild($manifest);

        $metadata = $doc->createElementNS(self::CP_NS, 'metadata');
        $schema = $doc->createElementNS(self::CP_NS, 'schema');
        $schema->appendChild($doc->createTextNode('QTIv2.2 Package'));
        $schemaVersion = $doc->createElementNS(self::CP_NS, 'schemaversion');
        $schemaVersion->appendChild($doc->createTextNode('1.0.0'));
        $metadata->appendChild($schema);
        $metadata->appendChild($schemaVersion);
        $manifest->appendChild($metadata);

        $manifest->appendChild($doc->createElementNS(self::CP_NS, 'organizations'));

        $resources = $doc->createElementNS(self::CP_NS, 'resources');
        foreach ($package->items() as $item) {
            $href = $package->itemHref($item);

            $resource = $doc->createElementNS(self::CP_NS, 'resource');
            $resource->setAttribute('identifier', 'RES-' . $item->identifier);
            $resource->setAttribute('type', self::QTI_ITEM_RESOURCE_TYPE);
            $resource->setAttribute('href', $href);

            $resource->appendChild($this->resourceMetadata($doc, $item->title, $item->standards, $item->language));

            $file = $doc->createElementNS(self::CP_NS, 'file');
            $file->setAttribute('href', $href);
            $resource->appendChild($file);

            $resources->appendChild($resource);
        }

        // Media files ship as plain webcontent resources
        foreach (array_keys($package->mediaFiles()) as $i => $path) {
            $resource = $doc->createElementNS(self::CP_NS, 'resource');
            $resource->setAttribute('identifier', 'MEDIA-' . ($i + 1));
            $resource->setAttribute('type', 'webcontent');
            $resource->setAttribute('href', $path);
            $file = $doc->createElementNS(self::CP_NS, 'file');
            $file->setAttribute('href', $path);
            $resource->appendChild($file);
            $resources->appendChild($resource);
        }

        $manifest->appendChild($resources);

        return $doc;
    }

    /** @param list<string> $standards */
    private function resourceMetadata(\DOMDocument $doc, string $title, array $standards, string $language): \DOMElement
    {
        $metadata = $doc->createElementNS(self::CP_NS, 'metadata');
        $lom      = $doc->createElementNS(self::LOM_NS, 'imsmd:lom');
        $general  = $doc->createElementNS(self::LOM_NS, 'imsmd:general');

        $titleEl = $doc->createElementNS(self::LOM_NS, 'imsmd:title');
        $titleEl->appendChild($this->langString($doc, $title, $language));
        $general->appendChild($titleEl);

        foreach ($standards as $code) {
            $keyword = $doc->createElementNS(self::LOM_NS, 'imsmd:keyword');
            $keyword->appendChild($this->langString($doc, $code, $language));
            $general->appendChild($keyword);
        }

        $lom->appendChild($general);
        $metadata->appendChild($lom);

        return $metadata;
    }

    private function langString(\DOMDocument $doc, string $value, string $language): \DOMElement
    {
        $el = $doc->createElementNS(self::LOM_NS, 'imsmd:langstring');
        $el->setAttribute('xml:lang', $language);
        $el->appendChild($doc->createTextNode($value));

        return $el;
    }
}
