<?php

declare(strict_types=1);

namespace QtiSdk\Packaging;

use QtiSdk\QtiException;
use QtiSdk\Xml\ItemSerializer;

/**
 * Writes a ContentPackage to a .zip file with a top-level imsmanifest.xml —
 * the layout QTI importers expect.
 */
final class PackageWriter
{
    public function __construct(
        private readonly ItemSerializer $serializer = new ItemSerializer(),
        private readonly ManifestBuilder $manifestBuilder = new ManifestBuilder(),
    ) {
    }

    public function write(ContentPackage $package, string $zipPath): void
    {
        if ($package->items() === []) {
            throw new QtiException('Refusing to write an empty package.');
        }

        $zip = new \ZipArchive();
        $result = $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        if ($result !== true) {
            throw new QtiException("Could not create zip at {$zipPath} (ZipArchive error {$result}).");
        }

        $zip->addFromString('imsmanifest.xml', $this->manifestBuilder->build($package)->saveXML());

        foreach ($package->items() as $item) {
            $zip->addFromString($package->itemHref($item), $this->serializer->toXml($item));
        }

        foreach ($package->mediaFiles() as $packagePath => $localPath) {
            $zip->addFile($localPath, $packagePath);
        }

        if (!$zip->close()) {
            throw new QtiException("Failed writing zip to {$zipPath}.");
        }
    }
}
