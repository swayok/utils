<?php

namespace Swayok\Utils;

use Swayok\Utils\Exception\XmlUtilsException;

abstract class XmlUtils {

    /**
     * Convert XML node to array
     * @param \DOMNode $node
     * @return array
     */
    static public function xmlNodeToArray(\DOMNode $node) {
        $output = [];
        switch ($node->nodeType) {
            case XML_CDATA_SECTION_NODE:
            case XML_TEXT_NODE:
                $output = trim($node->textContent);
                break;
            case XML_ELEMENT_NODE:
                for ($i = 0, $m = $node->childNodes->length; $i < $m; $i++) {
                    $child = $node->childNodes->item($i);
                    $v = static::xmlNodeToArray($child);
                    if (isset($child->tagName)) {
                        $t = $child->tagName;
                        if (!isset($output[$t])) {
                            $output[$t] = [];
                        }
                        $output[$t][] = $v;
                    } elseif ($v || $v === '0') {
                        $output = (string)$v;
                    }
                }
                if ($node->attributes->length && !is_array($output)) { //Has attributes but isn't an array
                    $output = array('@content' => $output); //Change output into an array.
                }
                if (is_array($output)) {
                    if ($node->attributes->length) {
                        $a = [];
                        foreach ($node->attributes as $attrName => $attrNode) {
                            $a[$attrName] = (string)$attrNode->value;
                        }
                        $output['@attributes'] = $a;
                    }
                    /** @noinspection ForeachSourceInspection */
                    foreach ($output as $t => &$v) {
                        if (is_array($v) && count($v) === 1 && $t !== '@attributes') {
                            $v = $v[0];
                        }
                    }
                    unset($v);
                }
                break;
        }
        return $output;
    }

    /**
     * Convert XML string to array
     * @param string $xmlString
     * @param string $xmlVersion
     * @param string $xmlEncoding
     * @return array
     * @throws XmlUtilsException
     */
    static public function xmlStringToArray($xmlString, $xmlVersion = '1.0', $xmlEncoding = 'UTF-8') {
        libxml_use_internal_errors();
        $xml = new \DOMDocument($xmlVersion, $xmlEncoding);
        $xml->loadXML($xmlString);
        if (count(($xmlErrors = libxml_get_errors()))) {
            /** @var \LibXMLError $error */
            foreach ($xmlErrors as $error) {
                if ($error->level > LIBXML_ERR_WARNING) {
                    throw new XmlUtilsException('XML string contains errors: ' . $error->message);
                }
            }
        }
        return static::xmlNodeToArray($xml->documentElement);
    }

    /**
     * Parse XML file to array
     * @param string $xmlFilePath
     * @param string $xmlVersion
     * @param string $xmlEncoding
     * @return array
     * @throws XmlUtilsException
     */
    static public function xmlFileToArray($xmlFilePath, $xmlVersion = '1.0', $xmlEncoding = 'UTF-8') {
        if (!File::exist($xmlFilePath)) {
            throw new XmlUtilsException('File ' . $xmlFilePath . ' does not exist');
        }
        return static::xmlStringToArray(File::contents($xmlFilePath), $xmlVersion, $xmlEncoding);
    }
}