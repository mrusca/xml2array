<?php

namespace Verdant;

/**
 * XML2Array: A class to convert XML to an array in PHP
 * Takes a DOMDocument object or an XML string as input.
 *
 * See Array2XML: http://www.lalit.org/lab/convert-php-array-to-xml-with-attributes
 *
 * Author : Lalit Patel, Verdant Industries
 * Website: http://www.lalit.org/lab/convert-xml-to-array-in-php-xml2array
 * License: Apache License 2.0
 *          http://www.apache.org/licenses/LICENSE-2.0
 * Version: 0.1 (07 Dec 2011)
 * Version: 0.2 (04 Mar 2012)
 *             Fixed typo 'DomDocument' to 'DOMDocument'
 * Version: 0.3 (26 August 2013), Verdant Industries
 *          - Converted from static usage to instance usage with static facade for compatibility
 *          - Added configurable attribute/cdata/value special keys
 *          - Added configurable option to retain tag and attribute namespacing
 *
 * Usage:
 *       $array = XML2Array::createArray($xml);
 *       $array = XML2Array::createArray($xml, array('useNamespaces' => true));
 */
class XML2Array
{
    /**
     * The name of the XML attribute that indicates a namespace definition
     */
    const ATTRIBUTE_NAMESPACE = 'xmlns';
    /**
     * The string that separates the namespace attribute from the prefix for the namespace
     */
    const ATTRIBUTE_NAMESPACE_SEPARATOR = ':';

    /**
     * The configuration of the current instance
     * @var array
     */
    public $config = array();
    
    /**
     * The working XML document
     * @var \DOMDocument
     */
    protected $xml = null;
    
    /**
     * The working list of XML namespaces
     * @var array
     */
    protected $namespaces = array();

    /**
     * Constructor
     * @param array $config The configuration to use for this instance
     */
    public function __construct($config = array())
    {
        // The default configuration, set for backwards compatibility
        $this->config = array(
            'version'       => '1.0',
            'encoding'      => 'UTF-8',
            'attributesKey' => '@attributes',
            'cdataKey'      => '@cdata',
            'valueKey'      => '@value',
            'useNamespaces' => false,
        );
        if ( ! empty($config) && is_array($config)) {
            $this->config = array_merge($this->config, $config);
        }
    }
    
    /**
     * Initialise the instance for a conversion
     */
    protected function init()
    {
        $this->xml = null;
        $this->namespaces = array();
    }

    /**
     * Creates a blank working XML document
     */
    protected function createDomDocument()
    {
        return new \DOMDocument($this->config['version'], $this->config['encoding']);
    }

    /**
     * Convert an XML DOMDocument or XML string to an array
     * A static facade for ease of use and backwards compatibility
     * @param DOMDocument|string $inputXml The XML to convert to an array
     * @param array $config The configuration to use for the conversion
     * @return array An array representation of the input XML
     */
    public static function &createArray($inputXml, $config = array())
    {
        $instance = new XML2Array($config);
        return $instance->buildArray($inputXml);
    }

    /**
     * Convert an XML DOMDocument or XML string to an array
     * @param DOMDocument|string $inputXml The XML to convert to an array
     * @return array An array representation of the input XML
     */
    public function &buildArray($inputXml)
    {
        $this->init();

        if (is_string($inputXml)) {
            $this->xml = $this->createDOMDocument();
            $parsed = $this->xml->loadXML($inputXml);
            if ( ! $parsed) {
                throw new \Exception('[XML2Array] Error parsing the XML string.');
            }
        } elseif ($inputXml instanceof \DOMDocument) {
            $this->xml = $inputXml;
        } else {
            throw new \Exception('[XML2Array] The input XML object should be of type: DOMDocument.');
        }
        
        // Convert the XML to an array, starting with the root node
        $docNodeName = $this->xml->documentElement->nodeName;
        $array[$docNodeName] = $this->convert($this->xml->documentElement);
        
        // Add namespacing information to the root node
        if ( ! empty($this->namespaces)) {
            if ( ! isset($array[$docNodeName][$this->config['attributesKey']])) {
                $array[$docNodeName][$this->config['attributesKey']] = array();
            }
            foreach ($this->namespaces as $uri => $prefix) {
                if ($prefix) {
                    $prefix = self::ATTRIBUTE_NAMESPACE_SEPARATOR . $prefix;
                }
                $array[$docNodeName][$this->config['attributesKey']][self::ATTRIBUTE_NAMESPACE . $prefix] = $uri;
            }
        }
        return $array;
    }

    /**
     * Convert an XML DOMDocument (or part thereof) to an array
     * @param \DOMNode $node A single XML DOMNode
     * @return array An array representation of the input node
     */
    protected function &convert(\DOMNode $node)
    {
        $output = array();

        $this->collateNamespaces($node);
        switch ($node->nodeType) {
            case XML_CDATA_SECTION_NODE:
                $output[$this->config['cdataKey']] = trim($node->textContent);
                break;

            case XML_TEXT_NODE:
                $output = trim($node->textContent);
                break;

            case XML_ELEMENT_NODE:

                // for each child node, call the covert function recursively
                for ($i = 0, $m = $node->childNodes->length; $i < $m; $i++) {
                    $child = $node->childNodes->item($i);
                    $v = $this->convert($child);
                    if (isset($child->tagName)) {
                        $t = $child->nodeName;

                        // assume more nodes of same kind are coming
                        if ( ! isset($output[$t])) {
                            $output[$t] = array();
                        }
                        $output[$t][] = $v;
                    } else {
                        //check if it is not an empty text node
                        if ($v !== '') {
                            $output = $v;
                        }
                    }
                }

                if (is_array($output)) {
                    // if only one node of its kind, assign it directly instead if array($value);
                    foreach ($output as $t => $v) {
                        if (is_array($v) && count($v) == 1) {
                            $output[$t] = $v[0];
                        }
                    }
                    if (empty($output)) {
                        //for empty nodes
                        $output = '';
                    }
                }

                // loop through the attributes and collect them
                if ($node->attributes->length) {
                    $a = array();
                    foreach ($node->attributes as $attributeName => $attributeNode) {
                        $attributeName = $attributeNode->nodeName;
                        $a[$attributeName] = (string) $attributeNode->value;
                        $this->collateNamespaces($attributeNode);
                    }
                    // if its an leaf node, store the value in @value instead of directly storing it.
                    if ( ! is_array($output)) {
                        $output = array($this->config['valueKey'] => $output);
                    }
                    $output[$this->config['attributesKey']] = $a;
                }
                break;
        }
        return $output;
    }

    /**
     * Get the namespace of the supplied node, and add it to the list of known namespaces for this document
     * @param \DOMNode $node
     */
    protected function collateNamespaces(\DOMNode $node)
    {
        if ($this->config['useNamespaces'] && $node->namespaceURI 
            && ! array_key_exists($node->namespaceURI, $this->namespaces)) {
            $this->namespaces[$node->namespaceURI] = $node->lookupPrefix($node->namespaceURI);
        }
    }

}
