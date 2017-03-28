<?php

namespace Verdant;

/**
 * Array2XML: A class to convert array in PHP to XML
 * Returns the XML in form of DOMDocument class.
 * Throws an exception if the tag name or attribute name has illegal chars.
 * Takes into account attributes names unlike SimpleXML in PHP.
 *
 * Author : Lalit Patel, Verdant Industries
 * Website: http://www.lalit.org/lab/convert-php-array-to-xml-with-attributes
 * License: Apache License 2.0
 *          http://www.apache.org/licenses/LICENSE-2.0
 * Version: 0.1 (10 July 2011)
 * Version: 0.2 (16 August 2011)
 *          - replaced htmlentities() with htmlspecialchars() (Thanks to Liel Dulev)
 *          - fixed a edge case where root node has a false/null/0 value. (Thanks to Liel Dulev)
 * Version: 0.3 (22 August 2011)
 *          - fixed tag sanitize regex which didn't allow tagnames with single character.
 * Version: 0.4 (18 September 2011)
 *          - Added support for CDATA section using @cdata instead of @value.
 * Version: 0.5 (07 December 2011)
 *          - Changed logic to check numeric array indices not starting from 0.
 * Version: 0.6 (04 March 2012)
 *          - Code now doesn't @cdata to be placed in an empty array
 * Version: 0.7 (24 March 2012)
 *          - Reverted to version 0.5
 * Version: 0.8 (02 May 2012)
 *          - Removed htmlspecialchars() before adding to text node or attributes.
 * Version: 0.9 (26 August 2013), Verdant Industries
 *          - Converted from static usage to instance usage with static facade for compatibility
 *          - Added configurable attribute/cdata/value special keys
 * 
 *
 * Usage:
 *       $xml = Array2XML::createXML($array);
 *       echo $xml->saveXML();
 */
class Array2XML
{

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
            'rootNodeName'  => null,
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
     * Convert an array to an XML document
     * @param array $array The input array
     * @return \DOMDocument The XML representation of the input array
     */
    public function &buildXml($array)
    {       
        $this->init();
        
        if (array_key_exists('rootNodeName', $this->config) && is_string($this->config['rootNodeName'])) {
            $rootNodeName = $this->config['rootNodeName'];
        } else {
            if (is_array($array) && count($array) == 1) {
                $rootNodeName = array_keys($array)[0];
                $array = $array[$rootNodeName];
            }
        }
        
        $this->xml = $this->getXmlRoot();
        $this->xml->appendChild($this->convert($rootNodeName, $array));

        return $this->xml;
    }
    
    /**
     * Convert an array to an XML document
     * A static facade for ease of use and backwards compatibility
     * @param array $array The input array
     * @param array $config The configuration to use for the conversion
     * @return \DOMDocument The XML representation of the input array
     */
    public static function &createXML($array, $config = array())
    {
        // Lalit implementation had ($nodeName, $array) parameter order, so maintain backwards compatibility
        if (is_array($config) && is_string($array)) {
            $swap = $config;
            $config = array(
                'rootNodeName' => $array,
            );
            $array = $swap;
        }
        $instance = new Array2XML($config);
        return $instance->buildXml($array);
    }

    /**
     * Convert an array to XML nodes
     * @param string $nodeName The name of the node that the data will be stored under
     * @param array $array The array to be converted
     * @return \DOMNode The XML representation of the input data
     */
    protected function &convert($nodeName, $array = array())
    {

        //print_arr($node_name);
        $xml = $this->getXmlRoot();
        $node = $xml->createElement($nodeName);

        if (is_array($array)) {
            // get the attributes first.;
            if (isset($array[$this->config['attributesKey']])) {
                foreach ($array[$this->config['attributesKey']] as $key => $value) {
                    if ( ! $this->isValidTagName($key)) {
                        throw new \Exception('[Array2XML] Illegal character in attribute name. attribute: ' . $key . ' in node: ' . $nodeName);
                    }
                    $node->setAttribute($key, $this->bool2str($value));
                }
                unset($array[$this->config['attributesKey']]); //remove the key from the array once done.
            }

            // check if it has a value stored in @value, if yes store the value and return
            // else check if its directly stored as string
            if (isset($array[$this->config['valueKey']])) {
                $node->appendChild($xml->createTextNode($this->bool2str($array[$this->config['valueKey']])));
                unset($array[$this->config['valueKey']]);    //remove the key from the array once done.
                //return from recursion, as a note with value cannot have child nodes.
                return $node;
            } else if (isset($array[$this->config['cdataKey']])) {
                $node->appendChild($xml->createCDATASection($this->bool2str($array[$this->config['cdataKey']])));
                unset($array[$this->config['cdataKey']]);    //remove the key from the array once done.
                //return from recursion, as a note with cdata cannot have child nodes.
                return $node;
            }
        }

        //create subnodes using recursion
        if (is_array($array)) {
            // recurse to get the node for that key
            foreach ($array as $key => $value) {
                if ( ! $this->isValidTagName($key)) {
                    throw new \Exception('[Array2XML] Illegal character in tag name. tag: ' . $key . ' in node: ' . $nodeName);
                }
                if (is_array($value) && is_numeric(key($value))) {
                    // MORE THAN ONE NODE OF ITS KIND;
                    // if the new array is numeric index, means it is array of nodes of the same kind
                    // it should follow the parent key name
                    foreach ($value as $k => $v) {
                        $node->appendChild($this->convert($key, $v));
                    }
                } else {
                    // ONLY ONE NODE OF ITS KIND
                    $node->appendChild($this->convert($key, $value));
                }
                unset($array[$key]); //remove the key from the array once done.
            }
        }

        // after we are done with all the keys in the array (if it is one)
        // we check if it has any text value, if yes, append it.
        if ( ! is_array($array)) {
            $node->appendChild($xml->createTextNode($this->bool2str($array)));
        }

        return $node;
    }

    /*
     * Get the root XML node. If there isn't one, create it.
     */
    protected function getXmlRoot()
    {
        if (empty($this->xml)) {
            $this->xml = $this->createDomDocument();
        }
        return $this->xml;
    }

    /*
     * Get string representation of boolean value
     */
    protected function bool2str($v)
    {
        //convert boolean to text value.
        $v = $v === true ? 'true' : $v;
        $v = $v === false ? 'false' : $v;
        return $v;
    }

    /*
     * Check if the tag name or attribute name contains illegal characters
     * Ref: http://www.w3.org/TR/xml/#sec-common-syn
     */

    protected function isValidTagName($tag)
    {
        $pattern = '/^[a-z_]+[a-z0-9\:\-\.\_]*[^:]*$/i';
        return preg_match($pattern, $tag, $matches) && $matches[0] == $tag;
    }

}

