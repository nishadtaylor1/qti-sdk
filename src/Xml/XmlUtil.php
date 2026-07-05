<?php

declare(strict_types=1);

namespace QtiSdk\Xml;

final class XmlUtil
{
    /**
     * Append author-supplied HTML to a QTI element.
     *
     * QTI item bodies are XML, so the HTML must be well-formed XHTML.
     * Content that fails to parse as a fragment is appended as escaped
     * text instead of aborting the whole item — importers render it as
     * plain text, which beats a rejected package.
     */
    public static function appendHtml(\DOMElement $parent, string $html): void
    {
        $html = trim($html);
        if ($html === '') {
            return;
        }

        $fragment = $parent->ownerDocument->createDocumentFragment();
        $ok = @$fragment->appendXML($html);
        if ($ok && $fragment->hasChildNodes()) {
            $parent->appendChild($fragment);
            return;
        }

        // Second chance: run it through DOM's HTML parser to repair tag soup,
        // then import the resulting nodes.
        $repair = new \DOMDocument();
        if (@$repair->loadHTML(
            '<?xml encoding="UTF-8"><div>' . $html . '</div>',
            LIBXML_NOERROR | LIBXML_NOWARNING
        )) {
            $div = $repair->getElementsByTagName('div')->item(0);
            if ($div !== null) {
                foreach (iterator_to_array($div->childNodes) as $child) {
                    $parent->appendChild($parent->ownerDocument->importNode($child, true));
                }
                return;
            }
        }

        $parent->appendChild($parent->ownerDocument->createTextNode($html));
    }

    /**
     * QTI identifiers must be valid NCNames (no spaces, no leading digit, no colon).
     */
    public static function isValidIdentifier(string $id): bool
    {
        return (bool) preg_match('/^[A-Za-z_][A-Za-z0-9._-]*$/', $id);
    }

    public static function assertValidIdentifier(string $id, string $context): void
    {
        if (!self::isValidIdentifier($id)) {
            throw new \QtiSdk\QtiException(
                "Invalid QTI identifier '{$id}' for {$context}: identifiers must start with " .
                'a letter or underscore and contain only letters, digits, ".", "-", "_".'
            );
        }
    }
}
