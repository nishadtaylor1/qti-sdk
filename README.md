# QTI SDK for PHP

A dependency-free PHP library for **authoring IMS/1EdTech QTI 2.2 assessment items** and packaging them as
IMS content packages (`.zip` + `imsmanifest.xml`) that assessment platforms can import.

Built for the export/interchange use case: your application holds the questions; this SDK turns them into
standards-compliant QTI packages. It includes pluggable **target-platform profiles** that validate a package
against a specific importer's documented constraints before you ship it.

- QTI **2.2** output (the most widely-imported dialect in the wild)
- No runtime dependencies beyond `ext-dom`, `ext-libxml`, `ext-zip`
- PHP 8.1+

## Installation

```bash
composer require qti-sdk/qti-sdk
```

## Quick start

```php
use QtiSdk\Interaction\ChoiceInteraction;
use QtiSdk\Item\AssessmentItem;
use QtiSdk\Packaging\ContentPackage;
use QtiSdk\Packaging\PackageWriter;
use QtiSdk\Profile\EduphoriaAwareProfile;

$item = new AssessmentItem(
    identifier: 'item-tx-001',
    title: 'Capital of Texas',
    interaction: new ChoiceInteraction(
        choices: ['A' => 'Austin', 'B' => 'Houston', 'C' => 'Dallas', 'D' => 'San Antonio'],
        correct: ['A'],
    ),
    promptHtml: '<p>Which city is the capital of Texas?</p>',
    standards: ['113.15.b.8.A'],   // emitted as LOM keywords in the manifest
);

$package = new ContentPackage('grade4-unit3', 'Grade 4 – Unit 3');
$package->addItem($item);

(new PackageWriter())->write($package, '/tmp/grade4-unit3.zip');

// Validate against a specific importer before shipping
$profile = new EduphoriaAwareProfile();
$issues  = $profile->validate($package, '/tmp/grade4-unit3.zip');
foreach ($issues as $issue) {
    echo "[{$issue['level']}] {$issue['message']}\n";
}
```

## Supported interactions

| Class | QTI element | Use for |
|---|---|---|
| `ChoiceInteraction` | `choiceInteraction` | Multiple choice / multiple select |
| `MatchInteraction` | `matchInteraction` | Match column A to column B |
| `TextEntryInteraction` | `textEntryInteraction` | Fill in the blank (with accepted alternates) |
| `ExtendedTextInteraction` | `extendedTextInteraction` | Essay / open response (human-scored) |
| `InlineChoiceInteraction` | `inlineChoiceInteraction` | Dropdown inside a sentence |
| `HottextInteraction` | `hottextInteraction` | Select word(s) in a passage |
| `OrderInteraction` | `orderInteraction` | Arrange items into the correct sequence |
| `HotspotInteraction` | `hotspotInteraction` | Select region(s) on an image |
| `GraphicGapMatchInteraction` | `graphicGapMatchInteraction` | Drag labels onto image regions (labeling) |

Scoring uses the standard QTI response-processing templates (`match_correct`, or `map_response`
when alternates/mappings are declared), so any conformant delivery engine can score the items.

## Target-platform profiles

Real-world importers accept subsets of QTI. A profile encodes one importer's documented constraints
and returns errors/warnings for anything that won't survive the trip.

**Included: `EduphoriaAwareProfile`** (Eduphoria Aware Custom Item Banks, common in Texas districts):

- QTI 2.2 and below; zip with top-level `imsmanifest.xml`; max 20 MB per package
- Supports the nine interaction types above; rejects drawing/slider/associate/custom interactions
- Flags `OrderInteraction` and `GraphicGapMatchInteraction` with a warning — Eduphoria documents
  these as *partially* supported, so verify them with a sandbox import
- Warns when items carry no standards codes (unaligned items don't surface in Aware's Author tool)

Contributions of profiles for other platforms (Canvas, Schoology, TAO, …) are welcome.

## Notes & limitations

- **Export-focused.** There is no QTI *parser* yet — this SDK writes QTI, it does not read it.
- Prompt/choice HTML must be XHTML-ish; tag soup is repaired via DOM's HTML parser and worst-case
  falls back to escaped text rather than producing an invalid package.
- Media files can be bundled with `ContentPackage::addMediaFile()` and referenced by relative
  path from item HTML.
- Standards codes are emitted as LOM `general/keyword` metadata. How (and whether) a given importer
  maps those to its own standards database varies by vendor — verify with a sample import.

## Development

```bash
composer install
composer test
```

## License

MIT
