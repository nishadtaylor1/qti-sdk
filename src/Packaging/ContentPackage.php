<?php

declare(strict_types=1);

namespace QtiSdk\Packaging;

use QtiSdk\Item\AssessmentItem;
use QtiSdk\QtiException;
use QtiSdk\Xml\XmlUtil;

/**
 * An IMS content package: a set of items plus optional media files,
 * ready to be written as a .zip with a top-level imsmanifest.xml.
 */
final class ContentPackage
{
    /** @var array<string, AssessmentItem> */
    private array $items = [];

    /** @var array<string, string> package path => local file path */
    private array $mediaFiles = [];

    public function __construct(
        public readonly string $identifier,
        public readonly string $title = '',
    ) {
        XmlUtil::assertValidIdentifier($identifier, 'package');
    }

    public function addItem(AssessmentItem $item): self
    {
        if (isset($this->items[$item->identifier])) {
            throw new QtiException("Duplicate item identifier '{$item->identifier}' in package.");
        }
        $this->items[$item->identifier] = $item;

        return $this;
    }

    /** Bundle a media file (image/audio) referenced from item HTML. */
    public function addMediaFile(string $packagePath, string $localPath): self
    {
        if (!is_file($localPath)) {
            throw new QtiException("Media file not found: {$localPath}");
        }
        $this->mediaFiles[ltrim($packagePath, '/')] = $localPath;

        return $this;
    }

    /** @return array<string, AssessmentItem> */
    public function items(): array
    {
        return $this->items;
    }

    /** @return array<string, string> */
    public function mediaFiles(): array
    {
        return $this->mediaFiles;
    }

    public function itemHref(AssessmentItem $item): string
    {
        return "items/{$item->identifier}.xml";
    }
}
