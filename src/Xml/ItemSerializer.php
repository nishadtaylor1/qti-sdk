<?php

declare(strict_types=1);

namespace QtiSdk\Xml;

use QtiSdk\Item\AssessmentItem;

/**
 * Serializes an AssessmentItem to a QTI 2.2 XML document.
 */
final class ItemSerializer
{
    public const QTI_NS = 'http://www.imsglobal.org/xsd/imsqti_v2p2';
    public const XSI_NS = 'http://www.w3.org/2001/XMLSchema-instance';
    public const SCHEMA_LOCATION =
        'http://www.imsglobal.org/xsd/imsqti_v2p2 ' .
        'http://www.imsglobal.org/xsd/qti/qtiv2p2/imsqti_v2p2.xsd';

    private const RP_MATCH_CORRECT = 'http://www.imsglobal.org/question/qti_v2p2/rptemplates/match_correct';
    private const RP_MAP_RESPONSE  = 'http://www.imsglobal.org/question/qti_v2p2/rptemplates/map_response';

    public function toDom(AssessmentItem $item): \DOMDocument
    {
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        $root = $doc->createElementNS(self::QTI_NS, 'assessmentItem');
        $root->setAttributeNS(self::XSI_NS, 'xsi:schemaLocation', self::SCHEMA_LOCATION);
        $root->setAttribute('identifier', $item->identifier);
        $root->setAttribute('title', $item->title);
        $root->setAttribute('adaptive', 'false');
        $root->setAttribute('timeDependent', 'false');
        $root->setAttribute('xml:lang', $item->language);
        $doc->appendChild($root);

        $responseDecl = $item->interaction->responseDeclaration();
        $root->appendChild($responseDecl->toDom($doc));

        // SCORE outcome — required by the standard processing templates. The
        // normalMinimum/normalMaximum give importers an explicit score range;
        // without normalMaximum a human-scored item (no correct answer to infer
        // from) makes Eduphoria Aware warn and default the max to 1.
        $outcome = $doc->createElementNS(self::QTI_NS, 'outcomeDeclaration');
        $outcome->setAttribute('identifier', 'SCORE');
        $outcome->setAttribute('cardinality', 'single');
        $outcome->setAttribute('baseType', 'float');
        $outcome->setAttribute('normalMinimum', '0');
        $outcome->setAttribute('normalMaximum', self::formatScore($item->interaction->maxScore()));
        $default = $doc->createElementNS(self::QTI_NS, 'defaultValue');
        $value   = $doc->createElementNS(self::QTI_NS, 'value');
        $value->appendChild($doc->createTextNode('0'));
        $default->appendChild($value);
        $outcome->appendChild($default);
        $root->appendChild($outcome);

        $body = $doc->createElementNS(self::QTI_NS, 'itemBody');
        if ($item->promptHtml !== '') {
            $promptDiv = $doc->createElementNS(self::QTI_NS, 'div');
            XmlUtil::appendHtml($promptDiv, $item->promptHtml);
            $body->appendChild($promptDiv);
        }
        $item->interaction->appendTo($body);
        $root->appendChild($body);

        if ($item->interaction->isAutoScorable()) {
            $rp = $doc->createElementNS(self::QTI_NS, 'responseProcessing');
            $rp->setAttribute(
                'template',
                $responseDecl->mapping !== [] ? self::RP_MAP_RESPONSE : self::RP_MATCH_CORRECT
            );
            $root->appendChild($rp);
        }

        return $doc;
    }

    public function toXml(AssessmentItem $item): string
    {
        return $this->toDom($item)->saveXML();
    }

    /** Compact decimal string for a score: 1.0 -> "1", 2.5 -> "2.5", 0.0 -> "0". */
    private static function formatScore(float $score): string
    {
        $formatted = rtrim(rtrim(number_format($score, 4, '.', ''), '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }
}
