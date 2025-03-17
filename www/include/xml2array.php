<?php
/**
 * XML2Array: A class to convert XML to array in PHP
 * It returns the array which can be converted back to XML using the Array2XML script
 * It takes an XML string or a DOMDocument object as an input.
 *
 * See Array2XML: http://www.lalit.org/lab/convert-php-array-to-xml-with-attributes
 *
 * Author : Lalit Patel
 * Website: http://www.lalit.org/lab/convert-xml-to-array-in-php-xml2array
 * License: Apache License 2.0
 *          http://www.apache.org/licenses/LICENSE-2.0
 * Version: 0.1 (07 Dec 2011)
 * Version: 0.2 (04 Mar 2012)
 * 			Fixed typo 'DomDocument' to 'DOMDocument'
 *
 * Usage:
 *       $array = XML2Array::createArray($xml);
 
 !! WARNING: This version seeks to escape numeric character entities '&#'=>'@~@' so that the DOM does not crap them up !!
 ** Convert them before calling 
 !! 2018-09-19 Fixed handling of text fields with unprotected (i.e. no CDATA) HTML
 
 */

class XML2Array {

    private static $xml = null;
	private static $encoding = 'UTF-8';
	private static $escape = false;
	

    /**
     * Initialize the root XML node [optional]
     * @param $version
     * @param $encoding
     * @param $format_output
     */
    public static function init($version = '1.0', $encoding = 'UTF-8', $format_output = true, $escape = false) {
        self::$xml = new DOMDocument($version, $encoding);
        self::$xml->formatOutput = $format_output;
		self::$encoding = $encoding;
		self::$escape = $escape;
    }

    /**
     * Convert an XML to Array
     * @param string $node_name - name of the root node to be converted
     * @param array $arr - aray to be converterd
     * @return DOMDocument
     */
    public static function &createArray($input_xml) {
        $xml = self::getXMLRoot();
		if(is_string($input_xml)) {
			if(self::$escape){
				$input_xml = str_replace('&#', '@~@', $input_xml);	  // now escape the entities to stop the DOM from mangling them
			}
			
			if(empty($input_xml)) {
				throw new Exception('[XML2Array] Cannot load empty string. Document not found?');
			}
			
			libxml_use_internal_errors(true);
			if(!$parsed = $xml->loadXML($input_xml)) {
				//$error = print_r(libxml_get_errors(), true);echo $error;
				libxml_clear_errors();
				throw new Exception("[XML2Array] Error parsing the XML string.");
			}
			
		} else {
			if(get_class($input_xml) != 'DOMDocument') {
				throw new Exception('[XML2Array] The input XML object should be of type: DOMDocument.');
			}
			$xml = self::$xml = $input_xml;
		}
		$array[$xml->documentElement->tagName] = self::convert($xml->documentElement);
        self::$xml = null;    // clear the xml node in the class for 2nd time use.
        return $array;
    }

    /**
     * Convert an Array to XML
     * @param mixed $node - XML as a string or as an object of DOMDocument
     * @return mixed
     */
    private static function &convert($node) {
		$output = array();

		switch ($node->nodeType) {
			case XML_CDATA_SECTION_NODE:
				$x = trim($node->textContent);
				if(self::$escape){
					$x = str_replace('@~@', '&#', $x);
				}
				$output['@cdata'] = $x;
				break;

			case XML_TEXT_NODE:
				$x = trim($node->textContent);
				if(self::$escape){
					$x = str_replace('@~@', '&#', $x);
				}
				$output = $x;
				break;

			case XML_ELEMENT_NODE:

				// for each child node, call the covert function recursively
				for ($i=0, $m=$node->childNodes->length; $i<$m; $i++) {
					$child = $node->childNodes->item($i);
					$v = self::convert($child);
					if(self::$escape){
						$v = str_replace('@~@', '&#', $v);
					}
					if(isset($child->tagName)) {
						$t = $child->tagName;
						if(is_array($output)){
							
							// assume more nodes of same kind are coming
							if(!isset($output[$t])) {
								$output[$t] = array();
							}
							$output[$t][] = $v;
							
						} else {
							/* 	i.e. $output is just plain text with childnodes e.g. like unprotected HTML in "<descrip> blah <i>really</i> blah"
								This will effectively strip the HTML tags (to protect them you should use <![CDATA[ ]]>
							
								$tn = $node->tagName;
								echo("<$tn> <$t> ($v) [$output] \r\n");
								
							*/	
							
							//throw new Exception("[XML2Array] Possible missing CDATA / crap HTML? XML read Exception. <$tn> <$t> [$output]");

							$output .= " " . $v;
							
						}
						
					} else {
						//check if it is not an empty text node
						if($v !== '') {
							$output = $v;
						}
					}
				}

				if(is_array($output)) {
					// if only one node of its kind, assign it directly instead if array($value);
					foreach ($output as $t => $v) {
						if(is_array($v) && count($v)==1) {
							$v = $v[0];
							if(self::$escape){
								$v = str_replace('@~@', '&#', $v);
							}
							$output[$t] = $v;
						}
					}
					if(empty($output)) {
						//for empty nodes
						$output = '';
					}
				}

				// loop through the attributes and collect them
				if($node->attributes->length) {
					$a = array();
					foreach($node->attributes as $attrName => $attrNode) {
						$a[$attrName] = (string) $attrNode->value;
					}
					// if its an leaf node, store the value in @value instead of directly storing it.
					if(!is_array($output)) {
						$output = array('@value' => $output);
					}
					$output['@attributes'] = $a;
				}
				break;
		}
		return $output;
    }

    /*
     * Get the root XML node, if there isn't one, create it.
     */
    private static function getXMLRoot(){
        if(empty(self::$xml)) {
            self::init();
        }
        return self::$xml;
    }
}
?>