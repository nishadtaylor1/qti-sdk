<?php

declare(strict_types=1);

namespace QtiSdk\Packaging;

use QtiSdk\QtiException;
use QtiSdk\Xml\ItemParser;
use QtiSdk\Xml\XmlUtil;

/**
 * Reads a QTI content package zip back into SDK objects.
 *
 * Items whose interaction type the SDK does not support are collected as
 * skipped entries instead of aborting the whole package, mirroring how
 * real importers behave.
 */
final class PackageReader
{
    /** @var list<array{href: string, reason: string}> */
    private array $skipped = [];

    public function __construct(
        private readonly ItemParser $parser = new ItemParser(),
    ) {
    }

    public function read(string $zipPath): ContentPackage
    {
        $this->skipped = [];

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::RDONLY) !== true) {
            throw new QtiException("Could not open zip at {$zipPath}.");
        }

        $manifestXml = $zip->getFromName('imsmanifest.xml');
        if ($manifestXml === false) {
            throw new QtiException('Package has no top-level imsmanifest.xml.');
        }

        $manifest = new \DOMDocument();
        if (!@$manifest->loadXML($manifestXml)) {
            throw new QtiException('imsmanifest.xml is not well-formed.');
        }

        $package = new ContentPackage(
            $this->packageIdentifier($manifest),
        );

        foreach ($manifest->getElementsByTagNameNS('*', 'resource') as $resource) {
            $type = $resource->getAttribute('type');
            if (!str_starts_with($type, 'imsqti_item')) {
                continue;
            }

            $href = $resource->getAttribute('href');
            $itemXml = $zip->getFromName($href);
            if ($itemXml === false) {
                $this->skipped[] = ['href' => $href, 'reason' => 'File listed in manifest but missing from zip.'];
                continue;
            }

            try {
                $package->addItem($this->parser->fromXml($itemXml, $this->standardsFor($resource)));
            } catch (QtiException $e) {
                $this->skipped[] = ['href' => $href, 'reason' => $e->getMessage()];
            }
        }

        $zip->close();

        return $package;
    }

    /**
     * Items that could not be parsed on the last read() call, with reasons.
     *
     * @return list<array{href: string, reason: string}>
     */
    public function skipped(): array
    {
        return $this->skipped;
    }

    /** @return list<string> LOM keywords attached to the resource */
    private function standardsFor(\DOMElement $resource): array
    {
        $standards = [];
        foreach ($resource->getElementsByTagNameNS('*', 'keyword') as $keyword) {
            $text = trim($keyword->textContent);
            if ($text !== '') {
                $standards[] = $text;
            }
        }

        return $standards;
    }

    private function packageIdentifier(\DOMDocument $manifest): string
    {
        $raw = $manifest->documentElement?->getAttribute('identifier') ?? '';
        $id  = preg_replace('/^MANIFEST-/', '', $raw) ?: 'imported-package';

        if (!XmlUtil::isValidIdentifier($id)) {
            $id = preg_replace('/[^A-Za-z0-9._-]/', '-', $id) ?? 'imported-package';
            if (!preg_match('/^[A-Za-z_]/', $id)) {
                $id = 'pkg-' . $id;
            }
        }

        return $id;
    }
}
