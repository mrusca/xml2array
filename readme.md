Readme
===

Convert XML to an array representation, and then convert back to XML again.

Drop-in replacement for Lalit.org's XML2Array and Array2XML classes, based on their work.

* Configurable to use different special array keys for attributes/cdata/value
* Configurable to preserve tag and attribute namespaces
* Array to XML conversion automatically uses the root array element as the root XML node, if only one element exists at the top-level in the array


Usage Examples
---
#### Basic usage

    $array = XML2Array::createArray($xml);
    $xml = Array2XML::createXML($array);

Note that there's no need to specify the 'rootNode' parameter from the previous implementation. If the array contains a single root item, that will automatically be used as the root node.

#### Drop-in replacement

Of course, if you need a drop-in replacement, the old syntax works as before.

    $array = XML2Array::createArray($xml);
    $xml = Array2XML::createXML('rootNode', $array);

#### Preserve namespaces
    
    $config = array(
        'useNamespaces' => true,
    );
    $array = XML2Array::createArray($xml, $config);

#### Use JSON-friendly special keys
    
    $config = array(
        'attributesKey' => '$attributes',
        'cdataKey'      => '$cdata',
        'valueKey'      => '$value',
    );
    $array = XML2Array::createArray($xml, $config);
    $xml = Array2XML::createXML($array, $config);

Further Reading
---

Original [XML2Array](http://www.lalit.org/lab/convert-xml-to-array-in-php-xml2array/) and [Array2XML](http://www.lalit.org/lab/convert-php-array-to-xml-with-attributes/) libraries from Lalit.org