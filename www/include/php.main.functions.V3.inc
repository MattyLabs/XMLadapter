<?php
/* 	Main Ehaus PHP Functions - 
				 
*/	
/*******************************************************************************/
/*******************************************************************************/
	
    require_once  __DIR__ . "../../vendor/autoload.php";	
    use MattyLabs\XMLAdapter\XMLAdapter;
	
/*******************************************************************************/
/*******************************************************************************/
function get_query_string($purify = false) {
// NEW! [2017-03-03] Uses HTMLPurifier instead of CleanCSSH (which didn't really do anything!)
// !! mandatory.inc loads ALL GET & POST params into $params[] array using this function
// !! ergo we don't need to use check_post_and_get  - whatever was posted etc. will be in $params[] !!
// !! We need to permit some special elements & attributes for the CMS..Maybe better not to use in CMS mandatory.inc

	global $params;
	
	msg('Start: HTMLPurifier', 'get_query_string');
	$restricted_keys	=	[];	// why do we avoid checking these?
	$filtered_keys 		= array_diff_key($_REQUEST, $restricted_keys);
	$url 				= 	'';
	if ($purify == true) {
		
		if(file_exists($params['app_root'] . '/scripts/vendor/htmlpurifier-4.15.0-standalone/HTMLPurifier.standalone.php')){
			require_once $params['app_root'] . '/scripts/vendor/htmlpurifier-4.15.0-standalone/HTMLPurifier.standalone.php';
		}elseif(file_exists($params['app_root'] . '/scripts/vendor/htmlpurifier-4.14.0-standalone/HTMLPurifier.standalone.php')){
			require_once $params['app_root'] . '/scripts/vendor/htmlpurifier-4.14.0-standalone/HTMLPurifier.standalone.php';
		} elseif(file_exists($params['app_root'] . '/scripts/vendor/htmlpurifier-4.13.0-standalone/HTMLPurifier.standalone.php')){
			require_once $params['app_root'] . '/scripts/vendor/htmlpurifier-4.13.0-standalone/HTMLPurifier.standalone.php';
		} elseif(file_exists($params['app_root'] . '/scripts/vendor/htmlpurifier-4.10.0-standalone/HTMLPurifier.standalone.php')){
			require_once $params['app_root'] . '/scripts/vendor/htmlpurifier-4.10.0-standalone/HTMLPurifier.standalone.php';
		} 

		$config = HTMLPurifier_Config::createDefault();
		// add CMS special tags !! listproducts cmsection
		$config->set('Attr.EnableID', true);
	
		$def = $config->getHTMLDefinition(true);
		
		//data-margin="0" data-items="1" data-pagi="false" data-loop="true" data-animate-in="rollIn" data-speed="450" data-animate-out="rollOut" data-autoplay="5000">
		
		$def->info_global_attr['data-margin'] = new HTMLPurifier_AttrDef_Text;
		$def->info_global_attr['data-pagi'] = new HTMLPurifier_AttrDef_Text;
		$def->info_global_attr['data-loop'] = new HTMLPurifier_AttrDef_Text;
		$def->info_global_attr['data-animate-in'] = new HTMLPurifier_AttrDef_Text;
		$def->info_global_attr['data-speed'] = new HTMLPurifier_AttrDef_Text;
		$def->info_global_attr['data-animate-out'] = new HTMLPurifier_AttrDef_Text;
		$def->info_global_attr['data-autoplay'] = new HTMLPurifier_AttrDef_Text;
		$def->info_global_attr['data-items'] = new HTMLPurifier_AttrDef_Text;
		
		$def->addAttribute('div', 'contenteditable', new HTMLPurifier_AttrDef_Enum(
		  array('true','false')
		));
		
		$def->addElement(
		  'cmsection',   // name
		  'Block',  // content set
		  'Flow', // allowed children
		  'Common', // attribute collection
		  array( // attributes
			'data-section' => 'URI',
			'id' => 'URI',
		  )
		);
		$def->addElement(
		  'listproducts',   // name
		  'Block',  // content set
		  'Flow', // allowed children
		  'Common', // attribute collection
		  array( // attributes
			'data-section' => 'URI',
			'id' => 'URI',
			'rel' => 'URI',
			'title' => 'URI',
			)
		 );
		 $def->addElement(
		  'section',   // name
		  'Block',  // content set
		  'Flow', // allowed children
		  'Common', // attribute collection
		  array( // attributes
			'data-section' => 'URI',
			'id' => 'URI',
			'rel' => 'URI',
			'title' => 'URI',
		  )
		);
		
		/* Start */
		$purifier = new HTMLPurifier($config);

		foreach($filtered_keys as $key => $val) {
								
			$key = $purifier->purify($key);
			$val = $purifier->purify($val);
			$params[strtolower($key)]	= $val;	// cleans $params[] values as well :)
			$url .= "$key=$val&";
		}

	// CLEAN $params['this_uri'] and 'this_page' whilst we are about it
		$x = explode('?', AddBaseRef($_SERVER['REQUEST_URI'], 'url'))[0];
		$params['this_uri']  = $purifier->purify($x); 
		$params['this_page'] = trim($params['this_uri'], " \t\n\r\0\x0B/:");

		if( !empty($params['ds']) ){
			$params['ds'] = str_replace('"', '&#39;', $params['ds']);
			$params['ds'] = str_replace("'", '&#39;', $params['ds']);
			$params['ds'] = str_replace(":", '-', $params['ds']);
		}
		$qstring = Rtrim($url, '[&]');
		msg( $qstring, 'HTMLPurifier is ON');
		return $_SERVER['PHP_SELF'] . '?' . $qstring;
		
	} else {
		
		// HTMLPurifier is OFF (caused too many problems in CMS!) See mandatory.inc
		msg(http_build_query($filtered_keys), 'HTMLPurifier is OFF!!' );
		
		foreach($filtered_keys as $key => $val) {
								
			// Here's where we load up $params[] 
			$params[strtolower($key)]	= $val;

		}
		
		if( !empty($params['ds']) ){
			$params['ds'] = str_replace('"', '&#39;', $params['ds'] );
			$params['ds'] = str_replace("'", '&#39;', $params['ds'] );
			$params['ds'] = str_replace(":", '-', $params['ds'] );
		}
		
	// SET 'this_uri' and 'this_page': N.B. it's really not a good idea to turn off the HTMLPurifier!
		$params['this_uri']  = explode('?', AddBaseRef($_SERVER['REQUEST_URI'], 'url'))[0];
		$params['this_page'] = trim($params['this_uri'], " \t\n\r\0\x0B/:");
		
		
		return $_SERVER['PHP_SELF'] . '?' . http_build_query($filtered_keys);
		
		
	}

}
/*******************************************************************************/
function check_params($var) {
// Checks for existence of a $params[] variable and returns NULL (rather than undefined) :)
// N.B. $params[] will contain ALL $_REQUEST params (i.e. $_GET, $_POST, $_COOKIE) and is loaded up via mandatory.inc

	global $params;
	
	if(!empty($params[$var])){
		return $params[$var];	
	} else {
		return null;
	}
}
/*******************************************************************************/
function LoadCMSPageData($dbm, $FieldName, $PageID, $key){
// Retrieves all fields from a CMS record. Ideally you only need to call one CMS page
// e.g.  PageName = "articles": x = LoadCMSPageData("SiteName-cms", "cms_pagename", pid )
// Loads field values into RCMFV("cms_fieldname") etc.
// All Sections <cmsection> loaded into RCMFV("section_01") etc. in sequence and all <listproducts> nodes are processed and evaluated.
// If you HAVE to query another page as well [suggest you use a <listproducts> node instead] - then 
// - CMS Data-Sections only will be extracted from records with a $key > 0 and added using the $key => RCMFV['section_$key_01'] etc.
	
	global $params, $RCMFV;
	require($dbm . '-dbm.inc');
	
	if($key == ''){	$key = '0'; }
	$rUrl = $params['xmla'] . "?dbm=".$dbm."&SF1=".$FieldName."&ST1=".$PageID;
	msg($rUrl, 'LoadCMSPageData: ');
	$remoteOutput = get_xml($rUrl);
	//msg($remoteOutput);
	
	if(xmlfield($remoteOutput, "errorcode", false, "") != ""){
		//page not found
		msg(xmlfield($remoteOutput, "errorcode", false, ''), 'LoadCMSPageData: ERR: ');
		$RCMFV[$key] = 'Error retrieving data ['. $rUrl .'] ['.xmlfield($remoteOutput, "errormessage", false, "").']';
		
	} else {
	
		if($remoteOutput != ""){
		
			$parts = xmlfield($remoteOutput, "resultfields", false, chr(29));
			$parts = explode(chr(29), $parts);
			$val = $parts[0];
			$val = str_replace("&#38;", "&", $val);
			$val = preg_replace("((<listproducts.*?>).*?(<\/listproducts>))", "$1$2", $val); // removes the Text used in the CMS to label the <lisproducts>Some Text</listproducts> in the CKEditor
			$RCMFV[$key] = $val;
			
		} else {
			
			$RCMFV[$key] = 'Error retrieving data ['. $rUrl .'] ['.xmlfield($remoteOutput, "errormessage", false, "").']';
		}
	}
	//printHash($RCMFV);
}
/*******************************************************************************/
function ProcessCMSSections(){
// We should now have RCMFV[$key] = <resultfields> for the relevant pagename called
// If $key = '0' then load straight in to RCMFV[] else just extract <section>s or 
// <cmsection>s and add as RCMFV['section_$key_01'] etc. 
// We also process any <listproducts> nodes as we find them if inside a section.
	
	global $RCMFV;
	//print_r($RCMFV);
	msg('START', 'ProcessCMSSections:');
	
	$myHash = array();
	foreach($RCMFV as $k=>$v){
		
		if($k == '0' && strpos($RCMFV[$k], "Error retrieving data") === false){
			// this is our primary page data..
			$x = $RCMFV[$k];			
			$fields = GetDelimStr($x, "</fv_", ">", false);
			//msg($fields, 'ProcessCMSSections:');
			$fields = explode("|", $fields);
			//printHash($fields);
			foreach($fields as $f){
				$val = xmlfield($x, "fv_".$f, false, chr(29));
				//msg($val, $f);
				$myHash[$f] = $val;
			}

		}else{
			// we've gone and done a second lookup..
			// hunt for <cmsection>s and load these as RCMFV['section_$key_01'] etc.
			// $key need not be numeric e.g. RCMFV['section_leftmargin_01'] etc.
			$x = $RCMFV[$k];
			$sections = xmlfield($x, "cmsection", false, chr(29));
			// <section> may be used as valid html so to be avoided - here for backward compat
			//if($sections == ''){ $sections = xmlfield($x, "section", false, chr(29)); }
			$sections = explode(chr(29), $sections);
			$scount = 0;
			foreach($sections as $s){
				$scount += 1;
				msg('scount:'.$scount, 'ProcessCMSSections:');
				$myHash['section_'.$k.'_'.padz($scount, 2)] = ListProducts($s);
			}
			
		}
	}
			
	//now if we have processed text swap it out for the unprocessed text
	if(@$myHash['cms_processed_text'] != false){
		$myHash["cms_text"] = $myHash['cms_processed_text'];
		$myHash['cms_processed_text'] = "Y";
	}

	//	We need to pass a full list of ISBNs/ref_no's ($params['dhkey-<DBM>']) to CalculatePricesCMS() 
	// 	i)  Where CMS Process Text = Y AND CMS Process Prices = N - in this case page.php retrieves only <pricing:span> tags 
	//		 and $pricesHash[] will be empty. We ran ListProducts() when we Processed Text, this loaded $params['all_isbns']
	//		 which were then saved to cms_isbn_list. We now store this as $RCMFV['isbn_list'] for prices.inc
	//	ii) Where we are getting price data from a separate lookup db in place of or as well as the main bib db
	//		 in case we will need to look this up too - see prices.inc
	//
	// 	N.B. ListProducts() => $dataHash[] => ProcessTemplate() => $pricesHash[] => ListProducts() => ProcessCMSSections()
	if(@$myHash['cms_isbn_list'] != false){
		if(@$myHash['isbn_list'] != false){
			// shouldn't ever get here as ALL ISBNs will have been listed via ListProducts()
			$myHash['isbn_list'] = rtrim($myHash['isbn_list'], ',') . ',' . rtrim($myHash['cms_isbn_list'], ',');
		}else{
			$myHash['isbn_list'] = rtrim($myHash['cms_isbn_list'], ',');
		}
	}
	
	//printHash($myHash);
	
	//now we can process the section tags into the RCMFV('section_01') etc and process any <listproducts> tags too
	if(!empty($myHash['cms_text'])){
		$x = $myHash['cms_text'];
		$sections = xmlfield($x, "cmsection", true, chr(29));
		//if($sections == ''){ $sections = xmlfield($x, "section", true, chr(29)); }
		$sections = explode(chr(29), $sections);
		//print_r($sections); die;
		$scount = 0;
		foreach($sections as $s){
			$scount += 1;
			$attr = getAttribute($s, "id"); //we are looking for <cmsection id='sx1'>
			//msg($attr, 'processCMSSections');
			$attr = str_replace("sx", "", $attr);
			if(is_numeric($attr)){
				$sc = padz($attr, 2);
			}else{
				$sc = padz($scount, 2);
			}
			
			// strip out the surrounding section tags: we may need to change to <cmsection> if CSS uses real sections :)
			//if(strpos($s, '</section>') > 0 ) {$s = xmlfield($s, 'section'); }
			if(strpos($s, '</cmsection>') > 0 ) {$s = xmlfield($s, 'cmsection'); }


            $s = ProcessQuiz($s);
			//msg($s, 'ProcessCMSSections:s');
			//echo "<!-- sss[$s]]] -->\r\n";
			
			//msg('section_'.$sc, 'ProcessCMSSections:sx');
			$myHash['section_'.$sc] = ListProducts($s);
			
			//	CODE REMOVED AS NOT EXPLAINED - N.B. Articles-db html is NOT contained in <cmsections> by DESIGN. Call using <fv_cms_text>
			/*
				$_sections = xmlfield($myHash['section_'.$sc], "cmsection", true, chr(29));
				//if($sections == ''){ $sections = xmlfield($x, "section", true, chr(29)); }
				$_sections = explode(chr(29), $_sections);
				//printHash($sections);
				$scount = 0;
				foreach($_sections as $s){
					$myHash['section_'.$sc] = str_ireplace($s, xmlfield($s, 'cmsection'), $myHash['section_'.$sc]);
				}
			*/
		}
		//print_r($myHash); die;
	}
	
	$RCMFV = $myHash;
	unset($myHash);
	//$x = print_r($RCMFV, true); echo "<!-- $x -->\r\n";die;
}
/*******************************************************************************/
function ReadMyFile($filePath){
// Should only require the file_name that you want. To add to the include_path see mandatory.inc
	
	$x = @file_get_contents($filePath, FILE_USE_INCLUDE_PATH);
	if ($x === false){
		msg('failed to open file: [' . $filePath . ']', 'ReadFile: ERROR:');
		return 'File not found [' . $filePath . ']';	
		
	} else {
		return $x;	
		
	}
	
}
/*******************************************************************************/
/*******************************************************************************/
	function data_crc($data)
	{
		$crc = crc32($data);
	   
		return sprintf("%u", $crc);
	} 
/*******************************************************************************/
	function mask($unmaskedContent, $shift)
	{
		$chars	=	array();
		
		$i		=	0;
		
		do
		{
			$chars[$i]	=	chr(ord(substr($unmaskedContent, $i, 1)) + $shift);
			$i++;
		}
		while ($i != strlen($unmaskedContent));
		
		return implode('', $chars);
	
	}
/*******************************************************************************/
	function unmask($maskedContent, $shift)
	{
		$chars	=	array();
		
		$i		=	0;
		
		do
		{
			$chars[$i]	=	chr(ord(substr($maskedContent, $i, 1)) - $shift);
			$i++;
		}
		while ($i != strlen($maskedContent));
		
		return implode('', $chars);
	}
/*******************************************************************************/
/*******************************************************************************/
	/**
	*	XMLAdapter: provides direct interface with the Elasticsearch client.
	*	- returns XML, JSON, JSON2, RAW (array).
	*	- employs Ehaus search query syntax (i.e. name=value pairs in form of querystring
	*	- attempts to determine the default hosts from LOCAL_ADDR. NOT any more from Elastic-config.inc
	* 	- the default hosts can also be set via the php.server.defaults 
	*
	*	N.B. If you want to search an Elastic index use get_xml() NOT file_get_contents("..include/xma-elastic.php")
			 If you want to GET a REST request use get_rest()
	*/
	function get_xml( $rurl, array $arr=NULL ){
		
		global $params;
		
		//echo "<!-- get_xml::rurl:[$rurl] -->\r\n"; die;	
		if( strpos($rurl, '?') > 0){
			$query = explode('?', $rurl)[1];
		}else{
			$query = $rurl;
		}
		parse_str($query, $querystring_array); $querystring_array = array_change_key_case($querystring_array);
		//print_r($querystring_array);
		
		/* BDSLive Licencing 
		$indexes = ''; $dbm = $querystring_array['dbm'] ?: '';
		if(preg_match('/main/i', $dbm) ){
			
			$idx = @$querystring_array['idx'] ?: @$params['user_idx'] ?: $dbm;
			msg($idx, "final idx");
			$idx = explode(',', $idx);
			foreach($idx as $key){
				//echo "<!-- get_xml::key:[$key] -->\r\n"; //die;
				if(!preg_match('/bds/i', $key)){	// e.g. where db.server sends in a dbm of 'bds-main'
					$indexes .= "bds-$key,";
				}
			}
			$indexes = rtrim($indexes, ',');
		}
		*/
		
	// The Adapter needs 'elastic_client_config' as a minimum. Reset here OR (best) just pass thru php.server.defaults.inc::$params[] (loaded via mandatory.inc)
		/* 
		$add_params = [
			//'idx' => "$indexes",	// will reset indexes to be searched
			//'dbm' => "{$params['sitename']}-main", //will reset DBM
			//'www_root' => "{$params['www_root']}",	// some scripts (i.e. not running in browser) need this
			//'elastic_client_config' => $params['elastic_client_config'],	// resets hosts set i) php.server.defaults.php, ii) DBM
			
		];
		//$add_params = [];	// OR rely on XMLAdapter to try to work out the local cluster - OR set it via the DBM			
		*/
		
		$x = preg_replace('/&Fields=.*?&/i', '&FIELDS=*&', $query);
		msg("$x", 'get_xml(XMLAdapter): start:');
		
		//file_put_contents("D:/temp/get_xml.txt", "$x\r\n", FILE_APPEND);
		
		
		$xmla = new XMLAdapter($query, $params);
		
		
	// Now check how to return the data: defaults to raw xml / json if nothing specified
		$view = @$querystring_array['view'] ?: 'xml';
		
		$response = $xmla->search($view) ?: '';
		
		msg("", "get_xml: stop: $view");
		if(preg_match('/-acs/', $response)){
			//file_put_contents("D:/temp/get_xml.txt", "$response\r\n", FILE_APPEND);
		}

	// Tidy?
		if($view == 'xml'){
			
			if(@$arr['clean'] == 'xml'){
		
				msg("", 'get_xml: Clean');	
			// This SHOULD convert XMLHTTP Dom crap back into real chars [e.g. -> Gonz?lez Morales]
				$response = mb_convert_encoding($response, 'UTF-8', 'AUTO');
			// Most Browsers re-interpret lots of spaces together as space&nbsp;&nbsp; - which is different!
			// the offending space characters in your source code are not SPACE (U+0020), but are actually NO-BREAK SPACE (U+00A0). Visually, they appear identical, but if you view your source code in a hex editor (which shows you the individual bytes in the file), you'll see a different encoding for these characters. 
				$strNoBreakSpace = mb_convert_encoding('&#x00A0;', 'UTF-8', 'HTML-ENTITIES');
				$strNormalSpace  = mb_convert_encoding('&#x0020;', 'UTF-8', 'HTML-ENTITIES');
				$response = str_replace( $strNoBreakSpace, $strNormalSpace, $response );
			// This converts XMLHTTP Dom crap straight into numerical char entities [e.g. Gonz&#225;lez Morales]
			// Note: (0x80, 0xffff, ..) is the equivalent of (128, 65535, ,,) i.e start at charCode > 127..apparently :)
				$convmap = array(0x80, 0xff, 0, 0xff);
				$response = mb_encode_numericentity($response, $convmap, 'UTF-8');
				
			}
				
			$response = str_replace('<?xml version="1.0" encoding="UTF-8"?>', '', $response);
					
			if(preg_match('/xmla/i', (@$params['debug'] ?: ''))){
				echo "<!-- debug requested -->\r\n";
				$t1 = timer();
				echo "<!-- $t1 data:[$x] -->\r\n"; //die;
				echo $xmla->viewLog(@$params['debug']);
				//echo "response:[$response]\r\n"; //die;
			}
		
		}
		unset($xmla);
		return $response;
	
	}
// *************************************************************************** //
/*
	file_get_contents has a default timneout of 30 seconds and the image service is a bit flaky
	so use this instead. 
	N.B. get_xml is restricted to retrieving Elasticsearch searches.
*/
	function get_rest($rurl, array $options = Null ){
	
		global $params;
		
		msg($rurl, 'get_rest..');
	// Defaults
		$options['method'] 			= @$options['method'] ?: 'GET';
		$options['header'] 			= @$options['header'] ?: 'Content-Type: text/html; charset=UTF-8';
		$options['timeout'] 		= @$options['timeout'] ?: 15;
		$options['ignore_errors'] 	= @$options['ignore_errors'] ?: true;
		$options['postdata'] 		= @$options['postdata'] ?: '';
		
		$options['clean_url'] 		= @$options['clean_url'] ?: false;
		$options['clean_xml'] 		= @$options['clean_xml'] ?: false;
				
		if( @$options['clean_rurl'] ){
		/* N.B. fopen cannot handle spaces or certain other characters in a url - they just fuck it up	*/
		
			$rurl = str_replace('&amp;', '&', $rurl);
			$rurl = str_replace("+", "%20", $rurl); 
			$rurl = str_replace(" ", "%20", $rurl);
			//$rurl = str_replace("-", "%2D", $rurl);
			//$rurl = str_replace(":", "%3A", $rurl);  //N.B. this seems to break ehTracker / Ajax requests
		
		}
		
		$opts = [ 
			'http' => [ 
				'method'		=>	$options['method'], 
				'header'   	 	=> 	$options['header'],	
				'timeout'		=> 	$options['timeout'],
				'ignore_errors'	=> 	$options['ignore_errors'],
				'content'		=>	@$options['postdata'],
			],
			/**/
			'ssl' => [
				'allow_self_signed' => true,
				'verify_peer' => false,
				'verify_peer_name' => false,
			]
			
		]; 

		//echo "<!-- get_rest::rurl [$rurl] -->\r\n";
		//$x = print_r($opts, true); echo "<!-- get_rest::opts [$x] -->\r\n";
		$context = stream_context_create($opts); $response = ''; $t1 = timer();
		//$response = file_get_contents($rurl, false, $context);
		if (($response = @file_get_contents($rurl, false, $context)) === false) {
			  $error = error_get_last();
			  //echo "<!-- [$t1] get_rest:: HTTP request failed.: " . $error['message'] . "-->\r\n";
		} else {
			  //echo "<!-- [$t1] get_rest:: OK! [$rurl] -->\r\n";
		}
			
		
		if( $options['clean_xml'] ) {
		
		// This SHOULD convert XMLHTTP Dom crap back into real chars [e.g. -> Gonz?lez Morales]
			$response = mb_convert_encoding($response, 'UTF-8', 'AUTO');
		// Most Browsers re-interpret lots of spaces together as space&nbsp;&nbsp; - which is different!
		// the offending space characters in your source code are not SPACE (U+0020), but are actually NO-BREAK SPACE (U+00A0). Visually, they appear identical, but if you view your source code in a hex editor (which shows you the individual bytes in the file), you'll see a different encoding for these characters. 
			$strNoBreakSpace = mb_convert_encoding('&#x00A0;', 'UTF-8', 'HTML-ENTITIES');
			$strNormalSpace  = mb_convert_encoding('&#x0020;', 'UTF-8', 'HTML-ENTITIES');
			$response = str_replace( $strNoBreakSpace, $strNormalSpace, $response );
		// This converts XMLHTTP Dom crap straight into numerical char entities [e.g. Gonz&#225;lez Morales]
		// Note: (0x80, 0xffff, ..) is the equivalent of (128, 65535, ,,) i.e start at charCode > 127..apparently :)
			$convmap = array(0x80, 0xff, 0, 0xff);
			$response = mb_encode_numericentity($response, $convmap, 'UTF-8');
		
		}
		
		//print_r($response); //die;	
		msg('', 'get_rest..done.');
		return $response;
		
	}	
/*******************************************************************************/
function entities_to_unicode($str) {
    $str = html_entity_decode($str, ENT_QUOTES, 'UTF-8');
    $str = preg_replace_callback("/(&#[0-9]+;)/", function($m) { return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES"); }, $str);
    return $str;
}
/*******************************************************************************/

function xml_character_encode($string, $trans='') {
	// be careful what you wish for!
	$trans=(is_array($trans)) ? $trans : get_html_translation_table(HTML_ENTITIES, ENT_QUOTES);
	foreach ($trans as $k=>$v) $trans[$k]= "&#".ord($k).";";
	return strtr($string, $trans);
}
/*******************************************************************************/
//	Revamped version of postXML
//	$rUrl	->	Where we're posting to : db.server
//	$rBody	->	Assoc array of data, usually $_POST    [watch for funnny chars (see GetXML above)]
//
//	Returns full output of $rUrl	
//
function post_xml($rurl, $rBody, $https = false, $ccto = 10, $cto = 10){
	
	$rurl = str_replace(array('http://','https://'), '', $rurl);
	
	switch($https)
	{
		case true:
			$rurl = 'https://' . $rurl;
			break;
			
		default:
			$rurl = 'http://' . $rurl;
			break;
	}
	
	$rurl = str_replace(' ', '+', $rurl);
	msg( $rurl, "post_xml:");
					
	$curl	= curl_init();
	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($curl, CURLOPT_CONNECTTIMEOUT ,$ccto); // 0 = forever
	curl_setopt($curl, CURLOPT_TIMEOUT, $cto); //timeout in seconds
	curl_setopt($curl, CURLOPT_URL, $rurl);
	curl_setopt($curl, CURLOPT_POST, true);
	curl_setopt($curl, CURLOPT_POSTFIELDS, $rBody);
					
	$res = curl_exec($curl);
					
					
	if(curl_error($curl) ){
		msg(curl_error($curl), "post_xml:curl error:");
		//print_r(curl_error($curl));
	}
						
	$contents = $res;
	//print_r($contents); die;
	curl_close($curl);	
	
    // This converts XMLHTTP Dom crap back into real chars [e.g. -> Gonz�lez Morales]
    //$contents = mb_convert_encoding($contents, 'ISO-8859-15', 'UTF-8');
    //
    // Most Browsers re-interpret lots of spaces together as space&nbsp;&nbsp; - which is different!
    /* the offending space characters in your source code are not SPACE (U+0020), but are actually NO-BREAK SPACE (U+00A0). Visually, they appear identical, but if you view your source code in a hex editor (which shows you the individual bytes in the file), you'll see a different encoding for these characters. */
    $strNoBreakSpace = mb_convert_encoding('&#x00A0;', 'UTF-8', 'HTML-ENTITIES');
    $strNormalSpace  = mb_convert_encoding('&#x0020;', 'UTF-8', 'HTML-ENTITIES');
    $contents = str_replace( $strNoBreakSpace, $strNormalSpace, $contents );
    
    // This converts XMLHTTP Dom crap straight into numerical char entities [e.g. Gonz&#225;lez Morales]
    // Note: (0x80, 0xffff, ..) is the equivalent of (128, 65535, ,,) i.e start at charCode > 127..apparently :)
    $convmap = array(0x80, 0xffff, 0, 0xffff);
    $contents = mb_encode_numericentity($contents, $convmap, 'UTF-8');

	return $contents;
//	return $res;
}
/*******************************************************************************/
// to return a string set $ax = 'str'. to return an array() set $ax = 'arr'
// $parameter lets you overwrite a QS value [e.g. 'param=newval&param2=newval2']
function ModifySQS($str, $parameter, $ax) {
	
	$myHash = array();
	$PQ = '';
	if($str != '' ){
		
		// preserve &amp; (e.g. as part of search) and then replace later
		$str = str_replace('&amp;', '{38}', $str);
		//msg('['.$str . ']-[' . $parameter ."]\n\r", "ModifySQS: str");
		if(strpos($str, '&') !== false){
			$pairs = explode("&", $str);

			foreach ($pairs as $pair) {
				if(strpos($pair, '=') !== false){
					//list($k, $v) = explode('=', $pair);
					list($k, $v) = array_map("urldecode", explode('=', $pair));
					$myHash[strtolower($k)] = trim($v);
				}
			}

		}else{
			// if no '&' Then its a lone key=val pair
			if(strpos($str, '=') !== false){
				$k = explode('=', $str);
				$myHash[strtolower($k[0])] = trim($k[1]);
			}
		}
		//print_r($myHash);	die;
		// Now we can overwrite with the supplied $parameters	
		$pairs = explode('&', $parameter);
		foreach ($pairs as $pair) {
			if(strpos($pair, '=') !== false){
				list($k, $v) = array_map("urldecode", explode("=", $pair));
				$myHash[strtolower($k)] = trim($v); 
			}
		}
		//print_r($myHash);	die;
		// load up a return string	
		foreach (array_keys($myHash) as $k) {
			if ($myHash[$k] != '') {
				$PQ .= $k.'='.$myHash[$k].'&';
			}else{
				unset($myHash[$k]);
			}
		}
		
		$PQ = rtrim($PQ, '&');
		//msg($PQ, 'ModifySQS: PQ');
		//msg($ax, 'ModifySQS: ax');
		//printhash($myHash);
			
		// i'm sorry but this seemed the best way :)
		if ($ax == 'arr' && count($myHash) > 0){
			return $myHash;
			
		}elseif ($ax == 'str'){
			return trim($PQ);
			
		}else{
			return trim(''.$parameter);
		}

	} else { 
		return trim('');
	}
}
/*******************************************************************************/	
// 2013-03-10:
function GetDelimStr($txt, $StartTag, $StopTag, $tags, $FieldDelimiter = '|'){

	// escape regular expression syntax
	$StartTag = preg_quote($StartTag);
	$StopTag = preg_quote($StopTag);

	//msg("Start Tag : [" .$StartTag . " ] Stop Tag : [" . $StopTag . "]");
	$pattern = "@".$StartTag . "(.*?)" . $StopTag."@";
	preg_match_all($pattern, $txt, $matches);
  
	//var_dump($matches);
	$returnStr = "";
	if($tags == true){
		foreach($matches[0] as $key=>$value){
			$returnStr .= $value.$FieldDelimiter;
		}
	}else{
		foreach($matches[1] as $key=>$value){
			$returnStr .= $value.$FieldDelimiter;
		}	
	}
  
	if(substr($returnStr,-1) == $FieldDelimiter){
		$returnStr = substr($returnStr, 0, strlen($returnStr)-1);
	}
   
	return $returnStr;
  
}
/*******************************************************************************/
function xmltag($tag, $val, $action = ''){
// 0=<tag>val</tag> | 1=<tag> | 2=</tag> | 3=<tag><![CDATA[val]]></tag> | 4=<tag />

	$retstr = '';
	strtolower($tag); trim($tag);
	if ($action == 0 || $action == ''){
		// Test $val for blankness beofre you get here as WriteResponse actually needs to send the tags even if empty
		//if($val != ''){
			$retstr = '<' . $tag . '>' . $val . '</' . $tag . '>';
		//}
	}elseif($action == 1){
		$retstr = '<' . $tag . '>';
	}elseif($action == 2){
		$retstr = '</' . $tag . '>';
	}elseif($action == 3){
		$retstr = '<' . $tag . '><![CDATA[' . $val . ']]></' . $tag . '>';
	}elseif($action == 4){
		$retstr = '<' . $tag . ' />';
	}
	
	return $retstr;
}
/*******************************************************************************/
function xmltagattrib($tag, $attrib, $val)
{
	//	Add an attribute to an xml tag
	$tag	=	strtolower($tag);
	$val	=	preg_replace('/\|$/','',$val);
	return "<".$tag." ".$attrib.">".$val."</".$tag.">\r\n";
}
/*******************************************************************************/
// N.B. to find <img src="" /> use GetDelimStr()
// 2013-03-10: Uses simplified patrn and handles nested requests 'ehaus/eh_field_name'	
function xmlfield($xmlText, $FieldName, $tags = false, $FieldDelimiter = '' ){

	// oops. Most xmlfield calls actually specify the 4th param [$FieldDelimiter = ''] rather than leaving it out.
	if ($FieldDelimiter == '') {
		$FieldDelimiter = chr(29);
	} 
	// N.B. MatchByRegExp expects only 1 (subMatch): the element value
	$x = $xmlText;
	
	if(!empty($FieldName) ){
		
	$FieldName = str_replace("\\", "\/", $FieldName);
	$FieldName = preg_quote($FieldName); // this escapes things that need escaping :)
	$parts = explode("/", $FieldName);
	foreach($parts as $p){
		//msg("p:[" . $p . "]");
		$ptrn = "@<" . $p . "(?:>| [\s\S]+?>)(|[\s\S]+?)(?:<\/" . $p . ">)@";
		$x = ReturnPtrnMatches($x, $ptrn, $tags, $FieldDelimiter, $p);
		//$z = print_r($x, true); echo "<!-- xmlfield $FieldName [ $z ] -->\r\n";
	}
	
	if($tags == false){
		$x = str_replace('&#60;', '<', $x);
		$x = str_replace('&#62;', '>', $x);
		$x = str_replace('&lt;', '<', $x); 
		$x = str_replace('&gt;', '>', $x);
		$x = str_replace ('<![CDATA[', '',$x);
		$x = str_replace(']]>', '', $x);
	}
		//$z = print_r($FieldName, true); echo "<!-- xmlfield:fv [$z] val[$x] -->\r\n";
	}
	
	return $x;	
}
/*******************************************************************************/	
function ReturnPtrnMatches($xmlText, $ptrn, $tags, $dlmtr, $fn){	
	
	$returnStr = "";
	
	if( !empty($xmlText) ){
		
		preg_match_all($ptrn, $xmlText, $matches, PREG_PATTERN_ORDER);
		//$x = print_r($matches, true); echo "<!-- ReturnPtrnMatches $fn [ $x ] -->\r\n";
		if(count($matches[0]) == 0){
			// try with/without the 'fv_' if initial request fails
			if( preg_match('/fv_/', $fn) ){
				$ptrn = str_replace('fv_', '', $ptrn);
			}else{
				$ptrn = str_replace($fn, 'fv_'.$fn, $ptrn);
			}
			preg_match_all($ptrn, $xmlText, $matches, PREG_PATTERN_ORDER);
		}
		// N.B. $matches is an array[0,1] containing an array of all the matches
		//var_dump($matches);
		//msg($matches[0][0]);
		//msg($matches[1][0]);
		//printHash($matches[0]); // returns all matches with their tags
		//printHash($matches[1]); // returns all matches withOUT tags
		
		if($tags == true){
			foreach($matches[0] as $key=>$value){
				$returnStr .= $value.$dlmtr;
			}
		}else{
			foreach($matches[1] as $key=>$value){
				$returnStr .= $value.$dlmtr;
			}	
		}
		//echo $returnStr;
		$returnStr = preg_replace("/$dlmtr$/", '', $returnStr);
		if(substr($returnStr, -1) == $dlmtr){
		  //remove trailing pipe
			$returnStr = substr($returnStr, 0, strlen($returnStr)-1);
		}
		
	}

	return $returnStr;
	
}
/*******************************************************************************/
/* 
e.g. to call: 
	$RequestString = "SF1=content/user/email&ST2={$params['user_name']}&SORT=sort_date/d";
	$DBM = $params['sitename'] . '-order';
	$FieldList = 'user/billingaddress,user/shippingaddress';
	$NodeName = ''; $rEmoteServer = ''; $IncTags = false;
	$xml = QuickSearchText($RequestString, $DBM, $FieldList, $NodeName, $RemoteServer, $IncTags)
	
*/
/*******************************************************************************/
function QuickSearchText($RequestString, $DBM, $FieldList, $NodeName, $RemoteServer, $IncTags) {
	
	global $params;
	require($DBM . '-dbm.inc');
	
	/* 
		$RemoteServer is no longer used. ALL XMLa requests go to a local /include/xmla-[server].php
		The DBM _ini file specifies the $params['xmla'] to use and this will handle any remote server calls
	*/
	
	if (substr($RequestString,0,1) == "&") {
		$RequestString = substr($RequestString,1,(strlen($RequestString)));	
	}

	$rurl = $params['xmla']."?DBM=".$DBM."&Fields=".$FieldList."&".$RequestString;
	//echo "<!-- QST:: $rurl -->\r\n";
	if (strpos($DBM, '-related') === false){
		// the debug messaging breaks the -related <listproducts> node so best excluded.
		//msg($rurl, 'QuickSearchText:');
	}
	
	//ToDo:check force post is ok
	/*
	if(strpos($rurl, '?') > 0){
		$parts = explode('?', $rurl);
		$RemoteOutput = post_xml($parts[0], $parts[1]);
	}else{
		$RemoteOutput = GetXML($rurl);
	}
	*/
	$RemoteOutput = get_xml($rurl);
	//$x = print_r($RemoteOutput, true); echo "<!-- QuickSearchText: [$rurl] [$x] -->\r\n";
	//msg($RemoteOutput, 'QuickSearchText: RemoteOutput:');
	if($NodeName == "") { $NodeName = "resultfields"; }
	// We need to save the ResultSetInformation.
	// N.B. For Search Results the Facets rely on the ResultSetInformation being from the main search - but a prior ListProducts will
	//		- will update the ResultSetInformation and then the Facets can't run. SO, be careful of the sequence you fire off QuickSearchTexts
	//		- N.B. the ResultSetInformation is 'probably' only used by the WP_Search_Results.tpl so we could try saving it by reference to the .tpl like $params['save_search_terms_mts']
	$params['resultsetinformation'] = '<![CDATA[' . xmlfield($RemoteOutput, 'resultsetinformation', true, chr(29)) .']]>';
	$params['errordetails'] = xmlfield($RemoteOutput, 'errordetails', true, chr(29));
	
	$QST = xmlfield($RemoteOutput, $NodeName, $IncTags, '');
	
	return trim($QST);
	
}
/*******************************************************************************/
function GetAttribute($txt, $attr){
// returns an XML style attribute value e.g.['something' from <field attr1='else' attr2="something">]	
	
	$txt = preg_replace('@\s+([=])\s+@', '$1', $txt);
	//msg($attr . '[' . $txt .']' , 'GetAttribute');

	if(!empty($txt)){
		$txt = str_replace('&#39;', "'", $txt);
		$txt = str_replace('"', "'", $txt);
		$quot = "'";
		if(strpos($txt, $attr.'=') !== false){
			
			if (!empty($quot)){
				// move to the $attr=
				$txt = substr($txt, strpos($txt, $attr.'=')+1); 
				// move to the first occurrence
				$txt = substr($txt, strpos($txt, $quot)+1); 
				// return up to the next occurrence
				//echo '<<'.substr($txt, 0, strpos($txt, $quot)).'>>' . "\r\n";
				return substr($txt, 0, strpos($txt, $quot));
			}
			
		}else{
			return '';
		}
	}
	
}
/******************************************************************************/
function ProcessQuiz($txt)
{
    preg_match_all('/<quizlist[^<]+<\/quizlist>/mi', $txt, $matches);
    $quizListElements = $matches[0];

    if ($quizListElements) {
        foreach ($quizListElements as $quizListElement) {
            $quizHtmlContent = getQuizContent($quizListElement);
            $txt = str_replace($quizListElement, $quizHtmlContent, $txt);
        }
    }

    return $txt;
}

function getQuizContent($quizListElement)
{
    // load data from html element
    preg_match_all("/data-id='([^']+)'/", $quizListElement, $matches);
    $id = $matches[1][0];
    preg_match_all("/data-template='([^']+)'/", $quizListElement, $matches);
    $templateName = $matches[1][0];

    // load quiz by ID
    $url = AddBaseRef($params['xmla'], 'url') . '?DBM=' . SITENAME . '-quiz&k=' . $id;
    $dbDataXml = file_get_contents($url);
    // fix newlines encoding
    $dbDataXml = str_replace(array(chr(0xA0),  chr(0xC2)), '', $dbDataXml);
    $response = json_decode(json_encode((array) simplexml_load_string($dbDataXml)), 1);
    $quizRecord = @$response['resultfields'];
    $quizJson = $quizRecord['fv_quiz'];
    $quizData = @json_decode($quizJson);

    if (!$quizData) {
        return 'Error loading Quiz Data.';
    }

    // load template
    $template = @file_get_contents($params['www_root'] . '/' . SITENAME . '/include/templates/' . $templateName);
    if (!$template) {
        return 'Error loading Quiz Template.';
    }

    // load quiz defaults
    $defaultsJSON = @file_get_contents($params['www_root'] . '/' . SITENAME . '/admin/quiz/storage/default.options.php');
    if (!$defaultsJSON) {
        return 'Error loading Quiz default settings.';
    }

    $templateVariables = array(
        '<quiz_id>'             => $id,
        '<quiz_defaults_json>'  => $defaultsJSON,
        '<quiz_json>'           => $quizJson,
    );

    // fill the template
    $template = str_replace(array_keys($templateVariables), array_values($templateVariables), $template);


//    print_r(array(
//        $id,
//        $templateName,
//        $quizData,
//        $quizRecord,
//        $template,
//    ));

    return $template;
}

/*******************************************************************************/
function ListProducts($txt) {

// called from page.php - $txt is a <cmsections> which may or may not contain a <listproducts> node
// - replace out the entire <listproducts> node with the resolved data
// called from xml.proxy - $txt = '' and the <listproducts> params are in $params['url_qs']
// - just return the resolved values
	
	global $params, $dataHash, $lpHash, $pricesHash;

	$txt = str_replace("&#38;" , "&", $txt);
	$txt = str_replace("&amp;" , "&", $txt);
	$saveTxt = $txt;
	msg('', 'Start ListProducts..');
	//msg($txt, "ListProducts:txt: ");
	//msg($saveTxt, "ListProducts:saveTxt: ");
	//msg(@$params['ax'], "ListProducts:ax:");
	if(@$params['ax'] == '' && $txt ==''){
		msg('Nothing to do', "ListProducts: ");
		return '';
	}
	
// first off rationalise the <listproducts> params into $myHash[<listproducts>] = 'rel=..'
	$myHash = array();
	
	if ($txt != ''){
		$parts = xmlfield($txt, "listproducts", true, chr(29));
		if ($parts != ''){
			//msg($parts, 'PARTS');
			$parts = explode(chr(29), $parts);
			//print_r($parts); //die;
		
			foreach($parts as $p){
				//msg($p, 'ListProducts:p:');
				$rel = GetDelimStr($p, "rel='", "'", false);
				if ($rel == "" ) {$rel = GetDelimStr($p, "rel=".chr(34), chr(34), false); }
				$myHash[$p] = $rel;
			}
		}
	}else{
		// we're in xml.proxy load up the QueryString
		$myHash['qs'] = $params['url_qs'];
		$myHash['qs'] = ModifySQS($myHash['qs'], 'process=php', 'str');	// this stops an ajax from being processed except via xml.proxy
	}
	//print_r($myHash); //die;
	
// OK So now we want to process each of the <listproducts> nodes in $myHash[]..
	foreach ($myHash as $key=>$value){
		// now we need to tidy things up a bit - 
		// this reads the &name=value pairs into a local array..
		$lpHash = ModifySQS($value, '', 'arr');

		// Try to work out the Key Field and save it to the $lpHash[]
		// The Key field is usually <barcode> for products and <ref_no> for everything else. It can be set in the Main SiteName-Main_ini.php file
		if( empty($lpHash['dbm']) ){
			// i.e. this defaults to the Main-bib database
			$dbm =  '-main';
		} else {
			// then we have a dbm inside the <listproducts> query (as we should)
			$dbm = str_ireplace(SITENAME, '', $lpHash['dbm']);
		}

		if( is_array($lpHash) ){
			
			$lpHash['key_field'] = @$params['dhkey'.$dbm] ?: '';
			if(@$lpHash['key_field'] == ''){
				$lpHash['key_field'] = 'ref_no';
			}
			
		} else {
			
			//echo "<-- ListProducts:lpHash [$lpHash] -->\r\n";
		}
		//
		//print_r($lpHash);die;
		
		// ONLY proceed if process=asp|php
		if (!empty($lpHash['process'])){
			if ($lpHash['process'] == 'asp' || $lpHash['process'] == 'php'){
			
			// decode the 'query' for XMLa [e.g. sf1:keyword@st1:ref_no@sf2:format_code@st2:BB@sort:sort_date/d]
				if(!empty($lpHash['query']) ){
					
					$lpHash['query'] = urldecode($lpHash['query']);
					// Preserve any '&' part of the query - better escape as '{38}' before you get here
					$lpHash['query'] = str_replace('&', '{38}', $lpHash['query']);
					
					$lpHash['query'] = str_replace(':', '=', $lpHash['query']);
					$lpHash['query'] = str_replace('@', '&', $lpHash['query']);
					$lpHash['query'] = str_replace('~', ':', $lpHash['query']);
					$lpHash['query'] = str_replace('{39}', "'", $lpHash['query']);
					$lpHash['query'] = str_replace('{34}', '"', $lpHash['query']);
					$lpHash['query'] = str_replace('{40}', '@', $lpHash['query']);
					$lpHash['query'] = str_replace('yyyymmdd', rdate(0), $lpHash['query']);
					$lpHash['query'] = str_replace('yyyymm', DisplayDateTime('Ym'), $lpHash['query']);
					
					
					
				}
				
				// If we have a listof ISBNs this is what we go with [in case the ListProducts Editor sends thru ISBNs AND a search query]
				if (@$lpHash['isbn'] != false){ $lpHash['query'] = 'ISB=' . $lpHash['isbn']; }
				//print_r($lpHash);
				// See if the <ListProducts>query parameter contains &sort= and &pl= params and load these values into $lpHash
				if(!empty($lpHash['query']) ){
					$temp = array();
					parse_str($lpHash['query'], $temp);
					if(!empty($temp['pl'])){ $lpHash['pl'] = $temp['pl'];}
					if(!empty($temp['sort'])){ $lpHash['sort'] = $temp['sort'];}
					//print_r($temp);
				}
				
				//	Pagecount  [?JS check function - what is this for? - needs to be changed as follows!]
				if (!empty($params['m'])) { 
					$lpHash['m'] = (@$lpHash['m']) ?: @$params['m']; 
					$lpHash['query'] = ModifySQS(@$lpHash['query'], 'm='.@$lpHash['m'], 'str');
				}
				
				// Pagelength: order of precedence is; ListProducts [which mostly sends it thru separately NOT part of the Query] THEN QS
				// N.B. check: if QS contains '&pl=&' then CGI contains its own default of 20 in preference to the _ini default. May need to remove '&pl='
				if (@$lpHash['pl'] == '') { $lpHash['pl'] = @$params['pl']; }			
				if(@$lpHash['query'] != false){
					$lpHash['query'] = ModifySQS($lpHash['query'], 'pl='.@$lpHash['pl'], 'str');
				}else{
					// No harm is done - The <cmsection> is most likely empty 
					msg('!NOTICE: Nothing to do.', 'ListProducts:');
					//msg($txt, 'ListProducts:txt:');
					return '';
				}

				// SORT - the <listproducts> value will, if present, override the QS
				if (@$lpHash['sort'] == '') { $lpHash['sort'] = @$params['sort']; }
				if(@$lpHash['query'] != false){
					$lpHash['query'] = ModifySQS($lpHash['query'], 'sort='.@$lpHash['sort'], 'str');
				}
				//
				//print_r($lpHash);die;
				// the 'More' link may also have suffered..
				if(@$lpHash['more'] != false){
					
					$lpHash['more'] = str_replace(':', '=', $lpHash['more']);
					$lpHash['more'] = str_replace('@', '&', $lpHash['more']);
					$lpHash['more'] = str_replace('~', ':', $lpHash['more']);
					$lpHash['more'] = str_replace('{39}', "'", $lpHash['more']);
					$lpHash['more'] = str_replace('{34}', '"', $lpHash['more']);
					$lpHash['more'] = str_replace('yyyymmdd', rdate(0), $lpHash['more']);
					$lpHash['more'] = str_replace('.asp', '.php', $lpHash['more']);
					
				}
				
		// check we don't want to convert $#\d\d\d; => {$1} => hex
		// also any bits that might need ' ' => '+' [like fv_tple_title]		
				
				// Read in the Mini-Template
				$lpHash['template'] = ReadMyFile($lpHash['tpl']);
				
				// Try to retrieve '<fv_ field>' names from the .tpl file and add any from the ini file
				
				$rf = $lpHash['key_field'] .'|'. @$params['setpricefields'] .'|'. @$params['list_products_add_fields'].'|'. GetDelimStr($lpHash['template'], "<fv_", ">", false);
				$x = RemoveDuplicates($rf );
				$lpHash['fields'] = str_replace('|', ',', $x);
				if (stripos(@$params['facet_distinct_fields_mts'], $lpHash['tpl']) !== false){ 
					// if the MT_.tpl is listed then add distinct_fields to the list of fields..
					$lpHash['fields'] .= ',distinct';
				}
				
				// Average Ratings <fn_avg_rating barcode="9781234567890" /> 
				if (strpos($lpHash['template'], 'avg_rating') > 0) {
					// lord knows how you configure the 'count' param here
					$lpHash['avgratings'] = GetAverageRating(@$params['isbn'], '');
					msg($lpHash['avgratings'], 'ListProducts: AvgeRatings:');
				}
				
				//msg(GetDelimStr($lpHash['template'], "<fv_", ">", false), 'ListProducts:<fv_fields>');
				//printHash($lpHash);
				
				// here's where we lookup the XMLa to get the raw data for the <listproducts> request..
				// Check we haven't repointed the remoteserver of the database DBM - defaults to $params['this_server_url']
				if (!empty($params['repoint_url_'.$lpHash['dbm']])){ 
					$lpHash['remoteserver'] = $params['repoint_url_'.$lpHash['dbm']];					
					//printHash($lpHash);
				}
				// N.B.: All double-quotes ["] in URL submitted are converted into single-quotes [']
				// - if you are writing the links [href="xyz?'query'"] you have to use single quotes anyway
				// - cgi [1.0.0.4 onwards] converts back to double qutes as that is what dtsearch expects for a phrase search: e.g. (<descrip> contains ("black muslims"))
				//$lpHash['query'] = str_replace("'", '"', $lpHash['query']);
				
				// Orders etc - if we are searching using email addresses - make sure the @ sign (which we use as a replacement for '&') is converted into a '!'
				$lpHash['query'] = str_replace('!', '@', $lpHash['query']);
				$lpHash['query'] = str_replace('{38}', '%26', $lpHash['query']);
				//print_r($lpHash);die;
				
				$data = QuickSearchText($lpHash['query'], $lpHash['dbm'], $lpHash['fields'], '', @$lpHash['remoteserver'], true);
				msg('..data retrieved.', 'ListProducts:');
				//$x = print_r($data, true); echo "<!-- ListProducts: [$x] -->\r\n";			
				//print_r($lpHash); print_r($data); //die; // 
				
				if (xmlfield($data, 'errorcode', false, '') != ''){
					msg('No files retrieved in search. Err ['.xmlfield($data, 'errorcode', false, '').']', 'ListProducts');
				}else{
					$x = LoadDataHash($data);
					// $lpHash['cisb'] = <listproducts> param for current ISBN showing..
					if(!empty($lpHash['cisb']) && !empty($dataHash) ) {
						if(array_key_exists($lpHash['cisb'], $dataHash)){
							unset($dataHash[$lpHash['cisb']]);
						}
					}
					//print_r($dataHash);
					msg('dataHash loaded.', 'ListProducts:');
				}
				
			// Save Search Terms - just the ones entered by visitors on Quick Search Box
				// QuickSearchText saves the XMLa <resultsetinformation> each time it is run
				// so if the MT_Search_Results.tpl is being used, we are interested in the <search_terms> returned by the XMLa
				// ToDo: There may some psc / subject searches we are not interested in so may need a bit of cleaning up
				//if (stripos($params['save_search_terms_mts'], $lpHash['tpl']) !== false){ 
				
					$st = trim(xmlfield($params['resultsetinformation'], 'search_terms')) ?: @$params['ds'] ?: '';
					$st = make_pagename($st);
					$st = trim(str_replace('-', ' ', $st));
					$params['search_terms'] = @$st ?: '';
					$cst = $params['search_terms'];
					set_cookie('cst', $cst);
						
				//}
				// end Save Search Terms :)
				
			// Save every ISBN as it comes along - in case we need to do a prices lookup
				@$params['all_isbns'] .= xmlfield($data, $lpHash['key_field'], false, '') . '|';
				if( !empty($params['all_isbns']) ){
					$params['all_isbns'] = str_replace(chr(29), '|', $params['all_isbns']);
				}
				
				
			// Previously Viewed Titles: store these as <ref_no>s in a separate cookie
				if (stripos($params['previously_viewed_mts'], $lpHash['tpl']) !== false){ 
					// provided links to title detail are always ?K=<ref_no> this gets us the data we need..
					
					$pvt = get_cookie('pvt');
					//echo "<!-- pvt:[$pvt] -->\r\n";
					if(!empty($pvt)){
						
						$pvts = explode(',', $pvt);
						if(is_array($pvts)){
							$pvt = array_slice($pvts, 0, 10);
							$add_pvt = trunc($params['all_isbns']);
							if(!empty($add_pvt)){
								array_unshift($pvt, $add_pvt);
							}
							$pvt = implode(',', $pvt);
							set_cookie('pvt', $pvt);
						}
					}else{
						
						$pvt = trunc($params['all_isbns']);
						set_cookie('pvt', $pvt);
						
					}
											
				}
				// end Previously Viewed Products
				
				// Search ERRORs - we are doing lots of searches, but only searches initiated by users need to check for 
				// a search error [a related titles lookup just returns a blank string] e.g. search results and title detail pages
				// [the old results.asp and display.asp] - which now are best identified by reference to the Mini-Template used.
				// Save the XMLa <errorcode> to $params[] - we have to assume that there is only one main searcvh to report on per page
				// You will need to list ALL results tpl's at the very least.
				if (stripos($params['check_error_code_mts'], $lpHash['tpl']) !== false){ 
					$params['errorcode'] = trim(xmlfield($params['errordetails'], 'errorcode', false, ''));
					$params['errormessage'] = trim(xmlfield($params['errordetails'], 'errormessage', false, ''));
					$params['errortpl'] = $lpHash['tpl'];
					
				}
				
				
				// Save the <title> text for the Title Detail template [WP_Search_Detail.tpl]
				@$params['title_tag_text'] = xmlfield($data, 'fv_ctitle', false, '') . ' by ' . Trunc(xmlfield($data, 'fv_contributor', false, ''));
				$x = ProcessTemplate() ?: '';
				//echo "<!-- ListProducts: [$saveTxt] -->\r\n";
				if ($key == 'qs'){
					// then we are coming in via xml.proxy and no $txt was passed - just a QueryString..
					if(!empty($x)){
						$saveTxt = AddBaseRef($x);
					}
					
				}else{
					// we need to replace the <listproducts> nodes in the supplied $txt string with the new values..
					$saveTxt = str_replace($key, $x, $saveTxt);

				}
				unset($GLOBALS['lpHash']);
				global $lpHash;	// we still want the array - just not any old values :)
			}
		}
		msg('unload dataHash', 'ListProducts');
		unset($GLOBALS['dataHash']);
		global $dataHash; 	// we still want the array - just not any old values :)
		//printHash($dataHash);
		
	}
	// Oddly its quicker here as there is less text to parse :)
	// - but you lose having $RCMFV[] in scope - but hey you can use <fv_fieldname> instead so not a problem.
	//	N.B. crappy data can break the eval() in CheckForScriptVariables() use mtFunction('<fn_encode><fv_field></<fn_encode>');
	$saveTxt = CheckForScriptVariables($saveTxt);
	
	return $saveTxt;
		
}
/*******************************************************************************/
/*
	Updated 2023-06-22 to contain list of permitted / forbidden function calls to eval
	See version 9 for previous approach
	Any code contained in the WP_*.tpl's is EVALuated when the tpl is loaded [see page.php:ob_start();]
	This function is only designed to  EVALuate PHP code in MT_*.tpl's and is limited to
	any_function() calls and $params[] and $dataHash[] which are in scope for ListProducts().
	if used inside page.php maybe also $RCMFV[] is in scope too :)
	Should also handle code staements like if($x){$y};
	N.B. Any functions used in a MT_*.tpl is ideally named mtFunctionName(); and configured in Site.Functions.php
*/
function CheckForScriptVariables($txt){
/* Any code contained in the WP_*.tpl's is EVALuated when the tpl is loaded [see page.php:ob_start();]
// This function is only designed to  EVALuate PHP code in MT_*.tpl's and is limited to
// any_function() calls and $params[] and $dataHash[] which are in scope for ListProducts().
// if used inside page.php maybe also $RCMFV[] is in scope too :)
// Should also handle code staements like if($x){$y};
// N.B. Any functions used in a MT_*.tpl is ideally named mtFunctionName(); and configured in Site.Functions.php
*/ 
	global $params, $RCMFV, $dataHash;
	
	msg('', 'CheckForScriptVariables:');
	//	N.B. crappy data (cr / lf) can break the eval() e.g. ctitle/contributor - use mtFunction('<fn_encode><fv_field [must'nt contain a single quote></<fn_encode>');
	//  N.B. If an inner_hits field contains the same quote ['"] as the function call this will break the eval too! <fn_encode> now replaces out quots too.
	//preg_match('/(php.*?\?)/', $txt, $matches);$x = print_r($matches, true); echo "<!-- aaargh[$x] -->\r\n";
	
	ob_start();
	
	eval(" ?>".$txt."<?php ");
	
	$html = ob_get_clean();

	return $html;

}
/*******************************************************************************/

	function strpos_arr($needle, $haystack) 
	{
		
		if(!is_array($needle)) $needle = array($needle);
		foreach($needle as $what) {
			if(($pos = strpos($haystack, $what))!==false) return $pos;
		}
		return false;
		
	}
/*******************************************************************************/
function ProcessTemplate(){
	
	global $dataHash, $params, $lpHash, $pricesHash;
	
	msg('start..', 'ProcessTemplate');
	//$x = print_r($lpHash, true); echo "<!-- PRocessTemplate: [$x] -->\r\n";
	//print_r($lpHash);
	
	if(!isset($lpHash['template']) || $lpHash['template'] == '') { msg('template blank', 'ProcessTemplate'); }
	
	// special fields passed from the listproducts -> fv_tpl_title - fv_tpl_more - fv_tpl_id
	// Convert {233} back into &#233; - remove any '+''s etc.
	if(@$lpHash['title'] != ''){
		$lpHash['title'] = preg_replace('/\{(\d{1,3})\}/', '&#$1;', $lpHash['title']) ;
		$lpHash['title'] = str_replace('+', ' ', $lpHash['title']);
		$lpHash['template'] = str_replace("<fv_tpl_title>", $lpHash['title'], $lpHash['template']);
		// Clean version of fv_tpl_title, useful for the jquery selector, but breaks if contains reserved charcters.
		$lpHash['template'] = str_replace("<fv_tpl_id>", strtolower(preg_replace('/&#\d*?;|&\w*?;|\W/', '', $lpHash['title'])) , $lpHash['template']);
	}
	// replace <fv_tpl_more>. If this occurs within a form, then write out search as hidden inputs so's we can POST the form
	// e.g. <form method="post" action="results.asp" id="<fv_tpl_id>"><fv_tpl_more></form>
	// +	<a class="more" href="#" onclick="$('#<fv_tpl_id>').submit();">More &raquo;</a>
	$more = GetDelimStr($lpHash['template'], '<a', '</a>', true);
	$more .= xmlfield($lpHash['template'], 'form', true, '');
	$parts = explode(chr(29), $more);
	foreach( $parts as $p){
		if (strpos($p, '<fv_tpl_more>') > 0){
			if (empty($lpHash['more'])){
				$lpHash['template'] = str_replace($p, '', $lpHash['template']);
			}else{
				if (strpos($p, '<form') > 0){
					if (strpos($lpHash['more'], '?') > 0 ){ $lpHash['more'] = substr($lpHash['more'], strpos($lpHash['more'], '?')+1); }
					$frm = array();
					$frm = ModifySQS($lpHash['more'], '', 'arr');
					foreach ($frm as $k=>$v ){
						@$xblob .= '<input type="hidden" name="'.$k.'" value="'.$v.'">'."\r\n";
					}
					$lpHash['more'] = $xblob;
					unset($xblob);
				}
				$lpHash['template'] = str_replace('<fv_tpl_more>', $lpHash['more'], $lpHash['template']);
			}
		}
	}
	
// Work out the Next:Previous buttons :)
	/*  Comment: In order to WritePageLinks you have to know which page you are on [like: ../browse/results/?] so that
		you can navigate the search results while remaining on the same page. The $params['pagename'] is set in page.php
		when it finds a <listproducts> node with 'process=res'. $lpHash['pagename'] is set from the pagename=paramvalue 
		passed thru by the <listproduct> node [so $params['pagename'] and $lpHash['pagename'] are pretty much interchangeable
		but this does provide a check that the process is running according to plan :)
*/

	if (@$lpHash['pagename'] != ''){
		$x = WritePageLinks($lpHash['pagename'], '', $params['num_page_links']);
	}
	
	
//now begin: take the repeating element and remember the bit to replace out..
	$tList = xmlfield($lpHash['template'], "fi_list", true, '');
	$rList = xmlfield($lpHash['template'], "fi_list", false, '');
	if ($tList == ''){
		// e.g. on the Title Detail it a loop of one $tList is the saved original tpl and $rList is the one we interpolate..
		$tList = $lpHash['template'];
		$rList = $lpHash['template'];
	}
	//loop through each title
	$counter = 1;
	$blob = '';
	if(empty($dataHash)){
		msg('WARNING: dataHash is empty. Check search or index..', 'ProcessTemplate');
	} else {
		//$x = print_r($dataHash, true); echo "<!-- dataHash:  $x -->\r\n";
		foreach(array_keys($dataHash) as $k) {
			
			if($dataHash[$k] != '') {
			
				$xml = simplexml_load_string($dataHash[$k], "SimpleXMLElement", LIBXML_NOCDATA);
				$json = json_encode($xml);
				$array = json_decode($json, TRUE);
				unset($array['fv_inner_hits']);
				
				//$x = print_r($array, true); echo "<!-- array: [$tf]  $x -->\r\n";
			// Save the price fields into pricesHash[]..
				$parts = explode(',', $params['setpricefields']);
				foreach( $parts as $p){
					// try to avoid loading the same price field twice :)
					if (xmlfield(@$pricesHash[$k], $p) == ''){
						//msg($k .'-'.$p , 'ProcessTemplate: Load PricesHash');
						//msg(xmlfield($pricesHash[$k], $p), 'PricesHash:k.p:');
						//msg(xmlfield($dataHash[$k], 'fv_'.$p, true), 'dataHash:k.p:');
						//@$pricesHash[$k] .= str_replace('fv_', '', xmlfield($dataHash[$k], 'fv_'.$p, true));
						$v = @$array["fv_$p"] ?: '';
						if( is_array($v) ) { $v = implode('|', $v); }
						@$pricesHash[$k] .= xmltag($p, $v, '');
						//@$pricesHash[$k] .= str_replace('fv_', '', xmlfield($dataHash[$k], 'fv_'.$p, true));
						
					}
				}
				//$x = print_r($pricesHash, true); echo "<!-- pricesHash:  $x -->\r\n";
				
			// Now check the start_date and end_date: N.B. OECD will want this to the minute rather than the day!
				// Switch this filter off in the relevant _ini file by setting $params['apply_date_filter'] = false
				if(!empty($params['apply_date_filter']) && $params['apply_date_filter'] == true){
					$start_date = xmlfield($dataHash[$k], 'fv_start_date') ?: xmlfield($dataHash[$k], 'fv_sort_date');
					$end_date = xmlfield($dataHash[$k], 'fv_stop_date');
				} else {
					$start_date = '';
					$end_date = '';
				}
				
				if ( ($start_date != '' && $start_date > rdate(0)) || ($end_date != '' && rdate(0) > $end_date) ){
					msg("Date Filter Applied: [$k]");
					msg('Start Date ['.$start_date.'] End Date ['.$end_date.']', 'ProcessTemplate');
					
				}else{
					//msg('Start Date ['.$start_date.'] End Date ['.$end_date.']', 'ProcessTemplate');
					
					$blob .= InterpolateData($rList, $k, $counter);
					$counter += 1;


				}
				
			} else {
				msg('dataHash[] is empty ['. $k .']', 'ProcessTemplate'); 
			}
		}
	}

	if ($blob != '') {
		$x = str_replace($tList, $blob, $lpHash['template']);
		//echo "<!-- x: $x -->\r\n";
		//msg($x, 'ProcessTemplate:x:');
		return $x;
		
	}
	
}
/******************************************************************************/
// N.B. to pick up single <tags /> use GetDelimStr()
function InterpolateData($txt, $ref, $counter){
	
	global $params, $dataHash, $lpHash;
	msg($ref, 'InterpolateData');
	//$x = print_r($dataHash, true); echo "<!-- InterpolateData [$x] -->\r\n";
		
	$txt = str_replace("<fv_count>", $counter, $txt);
	if(!empty(trim(XMLField($params['resultsetinformation'], "distinct_fields")))){
		$params['distinct_fields'] = trim(XMLField($params['resultsetinformation'], "distinct_fields"));
		$txt = str_replace('<fv_distinct>', XMLField($params['resultsetinformation'], "distinct_fields"), $txt);
	}
	

/*-----------------------------------------------------------------------------*/	
//replace out <fv_field> tags
	$parts = explode(",", $lpHash['fields']);

	foreach($parts as $p){
		
		/* N.B. 
			- Multi-instance fields can be stored either as:
				<mif>ABC|XYZ</mif> 
					OR
				<mif>ABC</mif>
				<mif>XYZ</mif>
			IN EITHER case xmlfield($data, 'mif', false. '|'); will return 'ABC|XYZ'
			BUT we don't want to use pipe as separator as some HTML and some XML contain pipes!!
			SO use chr(29) instead here
			
			- mtFunctions should expect multi-instance fields to be spearated either by a pipe OR chr(29)
				so code accordingly
		
			- <fn_function></fn_function> calls can be retrieved using xmlfield() but don't use '|' or chr(29) as separator!
			
			N.B. "fv_$p" is important as this excludes matchng inner_hits fields (that don't have the fv_)
			
		*/
		$fv = Trim(xmlfield($dataHash[$ref], "fv_$p", false, chr(29)));
		//$x = print_r($dataHash[$ref], true); echo "<!-- InterpolateData:fv $p [$fv] -->\r\n";
		
		// single quotes in the data play havoc with anchor links and need escaping
		/*
		if(preg_match('/ctitle/', $p)){
			$fv = str_replace("'", "&#39;", $fv);
			$fv = str_replace('"', "&#34;", $fv);
		}	
		*/
		//print_r($dataHash[$ref]); //die;
		$txt = str_replace('<fv_'.$p.'>', $fv, $txt);
		$txt = str_replace('<fv_id>',  XMLField($dataHash[$ref], "id"), $txt);
		$txt = str_replace('<fv_score>',  XMLField($dataHash[$ref], "score"), $txt);

	}

/*-----------------------------------------------------------------------------*/	
// <fn_escape tags="N" pipes="N"><fv_ctitle></fn_escape>: escape quotes and other stuff in named field retrieved from $dataHash (not tpl)
	if (strpos($txt, "fn_escape") !== false){
		
		$parts = xmlfield($txt, "fn_escape", true, '~~~');
		//echo "<!-- fn_escape:[$parts] -->\r\n";
		$parts = explode('~~~', $parts);
		if(is_array($parts)){
			
			foreach($parts as $p){
				
				//echo "<!-- fn_escape2:[$p] -->\r\n"; 
				$fv = xmlfield($p, "fn_escape");
				$tags = GetAttribute($p, "tags");
				$pipes = GetAttribute($p, "pipes");
				if($tags == 'N'){
					$fv	= strip_tags($fv);
				}
				if($pipes == 'N'){
					$fv = str_replace('|', ' ', $fv);
				}
				
				$fv = str_replace("'", "&apos;", $fv); //[&apos;|&#8217;|&#34;||&quot|&#8220;|&#39;]
				$fv = str_replace('"', "&apos;", $fv);
				$fv = str_replace("&#39;", "&apos;", $fv);	//' = &apos;
				$fv = str_replace('&#34;', "&apos;", $fv);	//" - &quot;
				$fv = str_replace (array("\r\n", "\n", "\r"), '', $fv);
				//echo "<!-- fn_escape3:[$fv] -->\r\n"; die;
				$txt = str_replace($p, trim($fv), $txt);
				
			}
		}
	}
	
/*-----------------------------------------------------------------------------*/	
// <fn_tidy tags="strip"><fv_ctitle></fn_tidy>: escape quotes and other stuff in named field retrieved from $dataHash (not tpl)
	if (strpos($txt, "fn_tidy") !== false){
		
		$parts = xmlfield($txt, "fn_tidy", true, '~~~');
		//echo "<!-- fn_tidy:[$parts] -->\r\n";
		$parts = explode('~~~', $parts);
		if(is_array($parts)){
			
			foreach($parts as $p){
				
				//echo "<!-- fn_tidy2:[$p] -->\r\n"; 
				$fv = xmlfield($p, "fn_tidy");
				$tags = GetAttribute($p, "tags");
				if($tags == 'strip'){
					$fv	= strip_tags($fv);
				}
				
				$fv = str_replace('|', ' ', $fv);
				//$fv = str_replace("'", "&apos;", $fv);
				$fv = str_replace('"', "@", $fv);
				$fv = str_replace("&#39;", "@", $fv);	//' = &apos;
				$fv = str_replace('&#34;', "@", $fv);	//" - &quot;
				$fv = preg_replace('/[@]+/', "'", $fv);
				$fv = preg_replace('/[\s]+/', " ", $fv);
				$fv = str_replace (array("\r\n", "\n", "\r"), '', $fv);
				//echo "<!-- fn_tidy3:[$fv] -->\r\n"; die;
				$txt = str_replace($p, trim($fv), $txt);
				
			}
		}
	}
	
/*-----------------------------------------------------------------------------*/	
// <fn_encode tags="N" pipes="N"><fv_ctitle></fn_encode>: escape quotes and other stuff in named field retrieved from $dataHash (not tpl)
	if (strpos($txt, "fn_encode") !== false){
		
		$parts = xmlfield($txt, "fn_encode", true, '~~~');
		//echo "<!-- fn_encode:start:[$parts] -->\r\n";
		$parts = explode('~~~', $parts);
		//print_r($parts); die;
		if(is_array($parts)){
			
			foreach($parts as $p){
				
				//echo "<!-- fn_encode:parts:\r\n[$p] -->\r\n\r\n"; 
				$fv = xmlfield($p, "fn_encode");
				$fv = str_replace(array("\r\n", "\n", "\r"), ' ', $fv);
				$fv = str_replace(array("'", '"'), '&quot;', $fv);	// e.g. if <ctitle> is included in <inner_hits>
				
				$tags = GetAttribute($p, "tags");
				$pipes = GetAttribute($p, "pipes");
				if($tags == 'N'){
					$fv	= strip_tags($fv);
				}
				if($pipes == 'N'){
					$fv = str_replace('|', ' ', $fv);
				}
				$fv = urlencode($fv);
				$txt = str_replace($p, trim($fv), $txt);
				
			}
		}
	}
		
/*-----------------------------------------------------------------------------*/		
	
// If you need more mumnging pass the escaped string thru mtFunction('<fn_strip_html><fv_desc></fn_strip_html>');
// e.g.: <fn_strip_html><fv_desc></fn_strip_html>
// N.B. pipes are FIELD seperators and should NOT appear in field texts e.g. like descriptions
	if (strpos($txt, 'fn_strip_html') > 0){
		$parts	=	xmlfield($txt, 'fn_strip_html',true, '~~~');
		$parts	=	explode('~~~', $parts);
		foreach ($parts as $p)
		{
			$fv		= xmlfield($p, 'fn_strip_html');
			$fv		= strip_tags($fv);
			$fv 	= str_replace('|', ' ', $fv);
			$fv 	= str_replace("'", '&apos;', $fv);
			$fv 	= str_replace('"', '&quot;', $fv);
			$fv 	= str_replace("&#39;", "&apos;", $fv);	//' = &apos;
			$fv 	= str_replace('&#34;', "&quot;", $fv);	//" - &quot;
			$fv		= str_replace (array("\r\n", "\n", "\r"), '', $fv);
			$txt	= str_replace($p, trim($fv), $txt);
		}
	}
	
// If you need more mumnging pass the escaped string thru mtFunction('<fn_strip_html><fv_desc></fn_strip_html>');
// e.g.: <fn_strip_field ax="trunc"><fv_contributor></fn_strip_field>
// removed pipe handling for BDSLive. N.B. pipes are field seperators.
	if (strpos($txt, 'fn_strip_field') > 0){
		$parts	=	xmlfield($txt, 'fn_strip_field',true, '~~~');
		$parts	=	explode('~~~', $parts);
		foreach ($parts as $p)
		{
			$fv		= xmlfield($p, 'fn_strip_field');
			$fv		= strip_tags($fv);
			$fv 	= str_replace("'", '&apos;', $fv);
			$fv 	= str_replace('"', '&quot;', $fv);
			$fv		= str_replace (array("\r\n", "\n", "\r"), '', $fv);
			
			$ax 	= GetAttribute($p, "ax");
			if($ax == 'trunc'){ $fv = trunc($fv); }
			
			$txt	= str_replace($p, trim($fv), $txt);
		}
	}

// Just get the field data cleanly WITH tags into mtFunction('<fn_strip_contrib><fv_contributors></fn_strip_contrib>');
	if (strpos($txt, 'fn_strip_contrib') > 0){
		$parts	=	xmlfield($txt, 'fn_strip_contrib',true, '~~~');
		$parts	=	explode('~~~', $parts);
		foreach ($parts as $p)
		{
			$fv		= xmlfield($p, 'fn_strip_contrib');
			//$fv		= strip_tags($fv);
			$fv 	= str_replace("'", '&apos;', $fv);
			$fv 	= str_replace('"', '&quot;', $fv);
			$fv		= str_replace (array("\r\n", "\n", "\r"), '', $fv);
			$txt	= str_replace($p, trim($fv), $txt);
		}
	}
/*-----------------------------------------------------------------------------*/		
// Strip style attributes	
	if (strpos($txt, 'fn_strip_styles') > 0){
		$parts	=	xmlfield($txt, 'fn_strip_styles',true, '~~~');
		$parts	=	explode('~~~', $parts);
		foreach ($parts as $p){
			
			$fv		= xmlfield($p, 'fn_strip_styles');
			//$fv		= strip_tags($fv);
			$fv 	= preg_replace('/(style=[\"|\'].*?[\"|\'])/', ' ', $fv);
			$txt	= str_replace($p, trim($fv), $txt);
			
		}
	}
				
/*-----------------------------------------------------------------------------*/		
// <fn_php>echo 1234;</fn_php>: execute php code
    if (strpos($txt, 'fn_php') > 0){
        $parts = xmlfield($txt, 'fn_php', true, '~~~');
        $parts = explode('~~~', $parts);
        foreach($parts as $p){
            $fv = xmlfield($p, 'fn_php', false, '');
			//echo "<!-- eval[$fv] -->\r\n";
            ob_start();
            eval($fv);
            $fv = ob_get_clean();
            $txt = str_replace($p, $fv, $txt);
        }
    }

/*-----------------------------------------------------------------------------*/	
// Handle <fn_trim length="" tags="no"><fv_descrip></fn_trim> 
	if (strpos($txt, 'fn_trim') > 0){
		
		$parts = xmlfield($txt, 'fn_trim', true, '~~~');
		$parts = explode('~~~', $parts);
		foreach($parts as $p){
			
			$fv = xmlfield($p, 'fn_trim', false, '');
			$ln = GetAttribute($p, "length");
			$tags = GetAttribute($p, "tags");
			if($tags == 'no' || $tags == 'N'){
				$fv	= strip_tags($fv);
			}
			if($ln == ''){ $ln = 100; }
			$fv = TruncateHTML($fv, $ln);
			$txt = str_replace($p, $fv, $txt);
		}
	}

/*-----------------------------------------------------------------------------*/	
// Handle <fn_section id="1" tags="no" trim="100"><fv_cms_text></fn_section> 
	if (strpos($txt, 'fn_section') > 0){
		
		$parts = xmlfield($txt, 'fn_section', true, '~~~');
		$parts = explode('~~~', $parts);
		foreach($parts as $p){
			
			$sections = explode('~~~', xmlfield($p, 'cmsection', false, '~~~'));
			$id = (GetAttribute($p, 'id'))-1;
			$trim = GetAttribute($p, "trim");
			$tags = GetAttribute($p, "tags");
			
			if($id >= 0 and $id <= count($sections)){
			
				$fv = $sections[$id];
				
				if($tags == 'no' || $tags == 'N'){
					$fv	= strip_tags($fv);
				}
				
				if($trim != ''){ 
					$fv = TruncateHTML($fv, $trim);
				}
								
				$txt = str_replace($p, $fv, $txt);
				
			}
		}
	}

/*-----------------------------------------------------------------------------*/	
// Handle <fn_uscore><fv_ctitle></fn_uscore>
	if (strpos($txt, 'fn_uscore') > 0 or strpos($txt, 'fn_pagename') > 0){
		
		$parts = xmlfield($txt, 'fn_uscore', true, '~~~');
		//$p = print_r($parts, true); echo "<!-- fn_uscore: [$p] -->\r\n";
		
		$parts = explode('~~~', $parts);
		
		foreach($parts as $p){
			
			$val = xmlfield($p, 'fn_uscore', false);
			$val = htmlentities($val);
			$val =  preg_replace('/&[a-z0-9#]{2,6};/', '', $val);
			//echo "<!-- guk [$val] -->\r\n";
			$val = preg_replace('/\s/', '_', $val);
			$val = preg_replace('/\-/', '_', $val);
			$val = preg_replace('/\//', '_', $val);
			$val = preg_replace("/(_$)|(^_)|([\W]+)/", '', $val);
			//echo "<!-- gee [$val] -->\r\n";
			$val = preg_replace('/\_+/', '-', $val);
			$val = strtolower($val);
			//echo "<!-- gzz [$val] -->\r\n";
			$txt = str_replace($p, $val, $txt);
			
		}
	}

/*-----------------------------------------------------------------------------*/	
	
// <fn_trunc><fv_field></fn_trunc>: trunc's whatever you send
	if (strpos($txt, 'fn_trunc') > 0){
		
		$txt = urldecode($txt);
		$parts = xmlfield($txt, 'fn_trunc', true, '~~~');
		$parts = explode('~~~', $parts);
		foreach($parts as $p){
			$fv = xmlfield($p, 'fn_trunc', false, '');
			$fv = Trunc($fv);
			$txt = str_replace($p, $fv, $txt);
		}
	}

/*-----------------------------------------------------------------------------*/		
//replace out any <fn_free_text>
	if(!empty($lpHash['free_text'])){
		$txt = str_replace("<fn_free_text>", $lpHash['free_text'], $txt);
	}
/*-----------------------------------------------------------------------------*/		
	// REMOVED AS SITE SPECIFIC: WRITE A mtFunction() in site.functions.php!!
	// <fn_contributor_detail names="<fv_contributor_code>" roles="<fv_contributor>" /

/*-----------------------------------------------------------------------------*/	
//RSS feed date
	$objDate = new DateTime('today');
	$rssDate = $objDate->format(DateTime::RSS);
	if (strpos($txt, '<fn_date>') > 0){
		$txt = str_replace("<fn_date>", $rssDate, $txt);
	}

/*-----------------------------------------------------------------------------*/	
//handle <fn_if_either_present fields="fv_cms_new_flag,fv_conbributor> Scooby </fn_if_either_present>
	if (strpos($txt, 'fn_if_either_present') > 0){
			
		$parts = xmlfield($txt, "fn_if_either_present", true, '~~~');
		$parts	=	explode('~~~', $parts);
		foreach($parts as $p){
			
			if(!empty($p)){
				
				//$x = print_r($p, true); echo "<!-- fn_if_either_present: [$x] -->\r\n";
			$fields	=	GetAttribute($p, "fields");
			$fields	=	explode(",", $fields);
			$found	=	false;
				foreach($fields as $field){
					
					if(!$found){
						
					$fv		=	xmlfield($dataHash[$ref], $field);
						if (!empty($fv)){
						$found	=	true;
						$txt	=	str_replace($p, xmlfield($p, "fn_if_either_present"), $txt);
					}
				}
			}
				if (!$found){
				$txt	=	str_replace($p, '', $txt);
			}
		}
	}
	}
/*-----------------------------------------------------------------------------*/
//handle <fn_any_check_present fields="fv_cms_new_flag,fv_conbributor></fn_any_check_present>
	if (strpos($txt, 'fn_any_check_present') > 0){
		
		$parts	=	xmlfield($txt, "fn_any_check_present", true, '~~~');
		$parts	=	explode('~~~', $parts);
		foreach($parts as $p){
			
			$fields	= GetAttribute($p, "fields");
			$fields	= explode(",", $fields);
			$found	= false;
			foreach($fields as $field){

				if(!$found){
					
					$fv	= xmlfield($dataHash[$ref], $field);
					//echo "<!-- ekk:[$fv] - $field - [$ref] -->\r\n";
					printHash($dataHash);
					if (!empty($fv)){
						
						$found	=	true;
						$val = xmlfield($p, 'fn_any_check_present', false);
						$txt	=	str_replace($p, $val, $txt);
					}
				}
			}
			
			if (!$found){
				$txt	=	str_replace($p, '', $txt);
			}
		}
	}
/*-----------------------------------------------------------------------------*/	
//handle <fn_any_check_value fields="fv_cms_new_flag,fv_conbributor></fn_any_check_value>
	if (strpos($txt, 'fn_any_check_value') > 0){
		
		$parts	=	xmlfield($txt, "fn_any_check_value", true, '~~~');
		$parts	=	explode('~~~', $parts);
		foreach($parts as $p)
		{
			
			$fields	= GetAttribute($p, "fields");
			$fields	= explode(",", $fields);
			$found	= false;
			foreach($fields as $field)
			{
				if(!$found)
				{
					$fv	= xmlfield($dataHash[$ref], $field);
					if (!empty($fv)){
						
						$found	=	true;
						$txt	=	str_replace($p, $fv, $txt);
					}
				}
			}
			if (!$found)
			{
				$txt	=	str_replace($p, '', $txt);
			}
		}
	}
/*-----------------------------------------------------------------------------*/
/*	i.e. load difficult data into $params[] directly from $dataHash, so it can be manipulated in the MT_ tpls
	e.g. <fn_load_params fields='contributor_list,contributor,contributors'><fv_contributor_list><fv_contributor><fv_contributors></fn_load_params>
	N.B. You only need to do this once per field!
	N.B. Fields are only loaded into $dataHash where <fv_field_name> is found in the template
*/
	if (strpos($txt, 'fn_load_params') > 0 ){
		
		$parts = xmlfield($txt, "fn_load_params", true, '~~~');
		$parts = explode('~~~', $parts);
		foreach($parts as $p) {
			
			$gaf = GetAttribute($p, "fields");
			$fields = explode(',', $gaf);
			foreach($fields as $f) {
				$params["fv_$f"] = xmlfield($dataHash[$ref], "fv_$f", true, '|');
			}
			$txt = str_replace($p, "<!-- fn_load_params: [$gaf] -->", $txt);
			
		}
		
	}
/*-----------------------------------------------------------------------------*/	
//handle <fn_if_present field="fv_cms_new_flag" value="<fv_cms_new_flag>" test="Y"> <strong> by </strong> <fv_author></fn_if_present>
/*
	fields are only loaded into the datahash if <fv_fiel_name> is present in the tpl file
	- so, if the field is present e.g. in the <fn>contents</fn> then:
	[ <fn_if_present field="fv_cms_new_flag"> <strong> by </strong> <fv_author></fn_if_present>] will test for value
	- if you just enter the value - then it is just a test for presence of 'field'
	- if you add the test then the field value must match the test in order to show..

*/

	if (strpos($txt, 'fn_if_present') > 0 ){
		$parts = xmlfield($txt, "fn_if_present", true, '~~~');
		$parts = explode('~~~', $parts);
		//$p = print_r($parts, true);echo "<!-- fn_if_present:p [$p] -->\r\n";
		foreach($parts as $p) {
			
			if( !empty($p) ){
				
				//echo "<!-- fn_if_present:p [$p] -->\r\n";
			$fv = ''; $test = ''; $tval = ''; $fval = ''; $part = '';
			$fv = GetAttribute($p, "field");
			$test = GetAttribute($p, "test");
			$tval = GetAttribute($p, "value");
			$fval = xmlfield($dataHash[$ref], $fv);
			$part = xmlfield($p, 'fn_if_present');
			//echo 'fv:[' . $fv . ']' . 'test:[' . $test . ']' . 'tval:[' . $tval . ']' . 'fval:[' . $fval . ']'. 'part:[' . $part . ']' ;
			$x = '';
			// I don't know who wrote this but.. if you are testing by field name then don't also send in a value
			if($fv != ''){
				// you sent in a field name - you want to check the field name value direct from the $dataHash
				$x = $fval; 
			} else {
				// you just sent in the <fv_field_value> direct thru the <fn_if_present value attribute
				$x = $tval;
			}
			if(!empty($x)){
				// so we have a value for 'field_name' direct from $dataHash - if test = '' then show it
				if(empty($test)) {
					//$txt = str_replace($p, '<!-- ['.$fval.'] <> [' . $tval . '] -->', $txt);
					$txt = str_replace($p, $part, $txt);
					
				} else {
				
					if(strtolower($tval) == strtolower($test) ) {
						
						$txt = str_replace($p, $part, $txt);
						
					} else {
						//$txt = str_replace($p, '<!-- ['.$val.'] <> [' . $test . '] -->', $txt);
						$txt = str_replace($p, '', $txt);
						
					}
				
				}
				
			} else {
			
				$txt = str_replace($p, '<!-- ['.$fv.'] not present -->', $txt);
				$txt = str_replace($p, '', $txt);
			}
		}
	}
	}

/*-----------------------------------------------------------------------------*/	
//handle <fn_if_not_present field="fv_author> <strong> by </strong> <fv_author></fn_if_not_present>
	if (strpos($txt, 'fn_if_not_present') > 0 ){
		$parts = xmlfield($txt, "fn_if_not_present", true, '~~~');
		$parts = explode('~~~', $parts);
		foreach($parts as $p) {
			$fv = GetAttribute($p, "field");
			$x = XMLField($dataHash[$ref], $fv, false, '');
			if($x == ""){
				$y = XMLField($p, "fn_if_not_present", false, '');
				$txt = str_replace($p, $y, $txt);
			} else {
				$txt = str_replace($p, '<!-- Field ['.$fv.'] present -->', $txt);
			}
			$y = "";
			$x = "";
		}
	}
/*-----------------------------------------------------------------------------*/	

//handle <fn_if_equal val1="fv_flag1" val2="fv_flag2"></fn_if_equal>
	if (strpos($txt, 'fn_if_equal') > 0 ){
		$parts = xmlfield($txt, "fn_if_equal", true, '~~~');
		$parts = explode('~~~', $parts);
		foreach($parts as $p) {
			$fv1 = GetAttribute($p, "val1");
			$fv2 = GetAttribute($p, "val2");
			
		    if($fv1 == $fv2){
				
				$y = xmlfield($p, "fn_if_equal", false);
                $txt = str_replace($p, $y, $txt);
				//echo "<!-- fn_if_equal: [$fv1]=[$fv2] insert: [$y] -->\r\n";
				
			} else {
				
				$txt = str_replace($p, '', $txt);
				
			}
			
			$fv1 = "";
			$fv2 = "";
			$y = '';
		}
	}

/*-----------------------------------------------------------------------------*/		
//handle <fn_if_not_equal val1="fv_flag1" val2="fv_flag2"></fn_if_not_equal>
	if (strpos($txt, 'fn_if_not_equal') > 0 ){
		$parts = xmlfield($txt, "fn_if_not_equal", true, '~~~');
		$parts = explode('~~~', $parts);
		
		foreach($parts as $p) {
			$fv1 = GetAttribute($p, "val1");
			$fv2 = GetAttribute($p, "val2");
		    if($fv1 != $fv2){
				$y = xmlfield($p, "fn_if_not_equal", false, '');
                $txt = str_replace($p, $y, $txt);
			} else {
				$txt = str_replace($p, '', $txt);
			}
			
			$fv1 = "";
			$fv2 = "";
			$y = '';
		}
	}

/*-----------------------------------------------------------------------------*/	
//handle <fn_if_greater field="fv_documentcount" value="fv_documentcount"> <strong> by </strong> <fv_documentcount></fn_if_greater>
	if (strpos($txt, 'fn_if_greater') > 0 ){
		$parts = xmlfield($txt, "fn_if_greater", true, '~~~');
		$parts = explode('~~~', $parts);
		foreach($parts as $p) {
			$fv = GetAttribute($p, "field");
			$value = intval(GetAttribute($p, "value"));
			$x = intval(xmlfield($dataHash[$ref], $fv));
			if($x > $value){
				//field is greater than value
				$y = XMLField($p, "fn_if_greater", true, '');
				$txt = str_replace($p, $y, $txt);
			}
			else {
				$txt = str_replace($p, '<!-- Field ['.$fv.'] present -->', $txt);
			}
			$y = "";
			$x = "";
		}
	}	
	
/*-----------------------------------------------------------------------------*/	
// Attempt to process <fn_top repeat="n" tag="div" class="x"></fn_top> tags
// e.g. n = 4 >> <div class="x"> stuff0, stuff1, stuff2, stuff3 </div> 
	if(strpos($txt, "fn_top") > 0) {
		
		$fnTag = xmlfield($txt, "fn_top", true, '~~~');
		$parts = explode('~~~', $fnTag);
		// <fv_count now numbers from 1 - but this needs to number from 0
		$tt_counter = $counter-1;
		
		foreach($parts as $p){
			//echo "<!-- wheee  $p  [$tt_counter] -->\r\n";
			$repeatNo = GetAttribute($p, "repeat");
			$div = GetAttribute($p, "tag");
			$css = GetAttribute($p, "class");
			$repeatTotal = count($dataHash);
			$mod = $tt_counter % $repeatNo;
			
			
			//echo "<!-- Repeat:" . $repeatNo . "|Div: " . $div . "|Css: " . $css . "|p: " . $p . "|counter: " . $tt_counter . "-->\r\n";
			if(is_numeric($repeatNo) && $repeatNo != ''){
				
				if($tt_counter == 0){
					//$txt = str_replace("<".$div."class=");	
					$txt = str_replace($p, '<'.$div.' class="'.$css.'">', $txt);
					//echo "<!-- <div> $tt_counter [$mod] -->\r\n";
				} elseif( ($tt_counter % $repeatNo) == 0) {
					$txt = str_replace($p, '<'.$div.' class="'.$css.'">', $txt);
					//echo "<!-- <div> $tt_counter [$mod] -->\r\n";					
				} else {
					$txt = str_replace($p, "", $txt);
					//echo "<!-- -  -->\r\n";
				}
				
			//replace <fn_tail> tags
				$repeatTotal = count($dataHash);
				if((($tt_counter +1) % $repeatNo) == 0){
					$txt = str_replace("<fn_tail>", "</$div>", $txt);
					//echo "<!-- </div> $tt_counter [$mod] -->\r\n";
				} elseif(($tt_counter + 1) == $repeatTotal){
					$txt = str_replace("<fn_tail>", "</$div>", $txt);
					//echo "<!-- </div> $tt_counter [$mod] -->\r\n";
				} else {
					$txt = str_replace("<fn_tail>", "", $txt);
					//echo "<!-- -/ -->\r\n";
				}
			} // end numeric if
		}
	}
/*-----------------------------------------------------------------------------*/	
// <fn_facet field='bic_tree'><fv_distinct></fn_facet>: 
	if (strpos($txt, 'fn_facet') > 0){
		
		require_once("search.functions.php");
		$parts = xmlfield($txt, 'fn_facet', true, '~~~');
		$parts = explode('~~~', $parts);
		foreach($parts as $p){
			$fv = GetAttribute($p, "field");
			$fct = xmlfield($p, $fv, false, chr(29));
			$fct = WriteOutFacets($fv, $fct);
			$txt = str_replace($p, $fct, $txt);
		}
	}
	

/*-----------------------------------------------------------------------------*/	
// Set the jacket source in -main_ini [$params['fn_jacket_source']] else set to 'all'
//	N.B. don't forget to set the php.main.functions.inc [image_server_url]
	$jsource = (!empty($params['fn_jacket_source']) ? $params['fn_jacket_source'] : 'all');
	$jref = explode('|', xmlfield($dataHash[$ref], 'barcode', false, '|'))[0];
	$jsub_path = substr($jref, 0, 6);
	
/* !! jlocal_path/_src:: This has changed from /images/ to /img/ in latest version 01/01/2023 */
	$jlocal_path = "{$params['www_root']}/{$params['sitename']}/img/localjackets";
	$jlocal_src = "img/localjackets";
	$ssl = (!empty($params['image_service_ssl']) ? $params['image_service_ssl'] : 'on');
	//$j1 = timer();echo "<!-- ($j1) fn_jacket: $ref:[$jref] -->\r\n";
	
/*-----------------------------------------------------------------------------*/	
//<img src="<fn_jacket>" width="100" alt="Alt text" />
	if (strpos($txt, '<fn_jacket>') > 0 ){
		$ImgSrcRef = '';
		$path = "$jlocal_path/m/$jref.jpg";
		//echo "<!-- fn_jacket: [$path] -->\r\n";
		if (file_exists($path)){
			
			$ImgSrcRef = AddBaseRef("$jlocal_src/m/$jref.jpg", 'url');
			//echo "<!-- fn_jacket:isf: [$ImgSrcRef] -->\r\n";
			
		}
		if($ImgSrcRef == ''){
			
			if(preg_match('/(^978|^979)/', $jref)){
				
				$ImgSrcRef = $params['image_server_url'] . "/bds-images/l/$jsub_path/$jref.jpg";
				
				if(@$params['fn_jacket_save_locally']){
					
					file_put_contents($path, file_get_contents($ImgSrcRef));
					
				}
			}
		}
		
		$txt = str_replace("<fn_jacket>", $ImgSrcRef, $txt);	
		
	}

/*-----------------------------------------------------------------------------*/		
//<img src="<fn_small_jacket>" width="100" alt="Alt text" />
	if (strpos($txt, '<fn_small_jacket>') > 0 ){
		$ImgSrcRef = '';
		$path = "$jlocal_path/s/$jref.jpg";
		//echo <!-- fn_small_jacket: [$path] -->\r\n";
		if (file_exists($path)){
			$ImgSrcRef = AddBaseRef("$jlocal_src/s/$jref.jpg", 'url');
		}
		if($ImgSrcRef == ''){
			
			$ImgSrcRef = $params['image_server_url'] . "/bds-images/m/$jsub_path/$jref.jpg";
			
			if(@$params['fn_jacket_save_locally']){
				file_put_contents($path, file_get_contents($ImgSrcRef));
			}
		}
		$txt = str_replace("<fn_small_jacket>", $ImgSrcRef, $txt);
	}

/*-----------------------------------------------------------------------------*/	
//<img src="<fn_large_jacket>" width="100" alt="Alt text" />
	if (strpos($txt, '<fn_large_jacket>') > 0 ){
		$ImgSrcRef = '';
		$path = "$jlocal_path/l/$jref.jpg";
		//echo "<!-- fn_large_jacket:path: [$path] -->\r\n";
		if (file_exists($path)){
			//echo "<!-- fn_large_jacket:file: [$ImgSrcRef] -->\r\n";
			$ImgSrcRef = AddBaseRef("$jlocal_src/l/$jref.jpg", 'url');
		}
		if($ImgSrcRef == ''){
			
			$ImgSrcRef = $params['image_server_url'] . "/bds-images/l/$jsub_path/$jref.jpg";
			//$ImgSrcRef = $params['image_server_url'] . "/xmla/image-service.asp?k=".$jref."&dbm=".SITENAME."&size=l&source=$jsource&ssl=$ssl";
			//$ImgSrcRef = get_rest("{$params['image_server_url']}/xmla/image-service.asp?DBM={$params['sitename']}&source=$jsource&AX=url&ISBN=$jref&SIZE=l");
			//echo "<!-- fn_large_jacket: [$ImgSrcRef] -->\r\n";
			if(@$params['fn_jacket_save_locally']){
				file_put_contents($path, file_get_contents($ImgSrcRef));
			}
		}
		$txt = str_replace("<fn_large_jacket>", $ImgSrcRef, $txt);
	}

/*-----------------------------------------------------------------------------*/	
// Handle <fn_jacket_zm> - You can get fancier than this if you want :)
	if (strpos($txt, 'fn_jacket_zm') > 0){
		$ImgSrcRef = '';
	// N.B. Going off to the image server to check for the image uri takes a LOT of time. Better to go LocalJackets..	
		//$rUrl = $params['image_server_url'] . "/xmla/image-service.asp?k=".$ref."&dbm=".SITENAME."&size=l&source=all&ax=url";	
		//$ImgSrcRef = GetXML($rUrl);
		//$ImgSrcRef = '<a href="' . $ImgSrcRef . '" rel="jacketzm">Enlarge Image</a>';
	// ================================================================
		if (file_exists($path)){
			$ImgSrcRef = "img/localjackets/l/".$jref.".jpg";
		}
		if($ImgSrcRef == ''){
			$ImgSrcRef = $params['image_server_url'] . "/xmla/image-service.asp?k=".$jref."&dbm=".SITENAME."&size=l&source=$jsource&ssl=$ssl";	
		}
		if($ImgSrcRef != ''){
			$ImgSrcRef = '<a href="' . $ImgSrcRef . '" rel="jacketzm">Enlarge Image</a>';
			$txt = str_replace('<fn_jacket_zm>', $ImgSrcRef, $txt);
		}
	}

/*-----------------------------------------------------------------------------*/	
// Handle <fn_jacket_zm_link> -	
	if (strpos($txt, 'fn_jacket_zm_link') > 0){
		$ImgSrcRef = '';
	// N.B. Going off to the image server to check for the image uri takes a LOT of time. Better to go LocalJackets..	
		//$rUrl = $params['image_server_url'] . "/xmla/image-service.asp?k=".$ref."&dbm=".SITENAME."&size=l&source=all&ax=url";	
		//$ImgSrcRef = GetXML($rUrl);
		//$ImgSrcRef = '<a href="' . $ImgSrcRef . '" rel="jacketzm">Enlarge Image</a>';
	// ================================================================
		if (file_exists($path)){
			$ImgSrcRef = "img/localjackets/l/".$jref.".jpg";
		}
		if($ImgSrcRef == ''){
			$ImgSrcRef = $params['image_server_url'] . "/xmla/image-service.asp?k=".$jref."&dbm=".SITENAME."&size=l&source=$jsource&ssl=$ssl";	
		}
		if($ImgSrcRef != ''){
			$txt = str_replace('<fn_jacket_zm_link>', $ImgSrcRef, $txt);
		}
	}


/*-----------------------------------------------------------------------------*/	
// <fn_quot field="ctitle"/>: escape quotes and other stuff in named field retrieved from $dataHash (not tpl)
	if (strpos($txt, "fn_quot") !== false){
		$fnTags = GetDelimStr($txt, '<fn_quot', '/>', true, '~~~');
		$fnTags = explode('~~~', $fnTags);

		foreach($fnTags as $fnTag){
			$field = GetAttribute($fnTag, "field");
			$fv = trim(xmlfield($dataHash[$ref], "fv_".$field));
			$fv = str_replace("'", '&apos;', $fv);
			$fv = str_replace('"', '&quot;', $fv);
			$txt = str_replace($fnTag, $fv, $txt);
		}
	}

/*-----------------------------------------------------------------------------*/	
// <fn_avg_rating barcode="<fv_barcode>" /> 
// N.B. xmlfield no longer handles <img blah /> so use GetDelimStr
	if (strpos($txt, 'fn_avg_rating') > 0){
		$parts = GetDelimStr($txt, '<fn_avg_rating', '/>', true, '~~~');
		$parts = explode('~~~', $parts);
		foreach($parts as $p){
			$id = GetAttribute($p, "barcode");
			$x = xmlfield($lpHash['avgratings'], $id, false, '');
			if ($x != ''){
				$avg = '<div class="star" data-score="'. $x .'"></div>';
				$txt = str_replace($p, $avg, $txt);
			}else{
				$txt = str_replace($p, '', $txt);
			}
		}
	}


/*-----------------------------------------------------------------------------*/	
// <fn_pipe_links val="<fv_cms_related_author>" type="author" />
// <fn_pipe_links val="<fv_cms_related_title>" type="barcode" />
	if (strpos($txt, 'fn_pipe_links') > 0){
		$parts = GetDelimStr($txt, '<fn_pipe_links', '/>', true, '~~~');
		$parts = explode('~~~', $parts);
		foreach($parts as $p){
			$type = GetAttribute($p, "type");
			$x = '';
			if($type == 'author'){
				$val = GetAttribute($p, "val");
				$authors = explode('|', $val);
				foreach($authors as $a){
					$x .= '<a href="page/results/?AUB=\'' . $a .'\'">' . Trunc($a) . '</a>, ';
				}
				$x = RTrim($x, '[, ]');
				$txt = str_replace($p, $x, $txt);

			} elseif($type == 'barcode') { 
			
				$val = GetAttribute($p, "val");
				if($val != ''){
					$val = str_replace('|', ',', $val);
					$x = '<a href="page/results/?ISB=' . $val . '">View related titles</a>';
					$txt = str_replace($p, $x, $txt);
				}
				
			}
			
		}
	}

/*-----------------------------------------------------------------------------*/	
// <fn_avg_review barcode="<fv_barcode>" />
	if (strpos($txt, 'fn_avg_review') > 0){
		$id = '';
		$return = '';
	
		$parts	=	GetDelimStr($txt, '<fn_avg_review', '/>', true, '~~~');
		$parts	=	explode('~~~', $parts);
	
		foreach ($parts as $p){
			$id = getAttribute($p, "barcode");
			
			if(!empty($id)){
				// calculate average [James: reverted back to original. [[macs=GetAverageReviewAndCount()]]) 
				$return = GetAverageReviewRating($id);
			}
			
			$txt	=	str_replace($p, $return, $txt);
		}
	}

/*-----------------------------------------------------------------------------*/		
// NOTE: Please don't delete. This function is needed.
// <fn_user_review_rating rating="<fv_rev_rating>" />
	if (strpos($txt, 'fn_user_review_rating') > 0){
		$return = '';
	
		$parts	=	GetDelimStr($txt, '<fn_user_review_rating', '/>', true, '~~~');
		$parts	=	explode('~~~', $parts);
	
		foreach ($parts as $p){
			$rating = getAttribute($p, "rating");
			
			if(!empty($rating)){
				// display stars
				for ($i = 1; ; $i++) {
					if ($i > 5) {
						break;
					}
					if ($i <= $rating){
						$return .= '<i class="star-on"></i>';
					}
					else{
						$return .= '<i class="star-off"></i>';
					}
				}
			}
			
			$txt	=	str_replace($p, $return, $txt);
		}
	}
	
/*-----------------------------------------------------------------------------*/	
// <fn_review_count barcode="<fv_barcode>" />
	if (strpos($txt, 'fn_review_count') > 0){
		$id = '';
		$return = '';
	
		$parts	=	GetDelimStr($txt, '<fn_review_count', '/>', true, '~~~');
		$parts	=	explode('~~~', $parts);
	
		foreach ($parts as $p){
			$id = getAttribute($p, "barcode");
			
			if(!empty($id)){
				// calculate average
				$return = GetReviewCount($id);
			}
			
			$txt	=	str_replace($p, $return, $txt);
		}
	}
	
/*-----------------------------------------------------------------------------*/	
//handle <fn_not_contains val1="foo bar" val2="bar"></fn_not_contains> - if "foo bar" contains "bar"
	if (strpos($txt, 'fn_not_contains') > 0 ){
		$parts = xmlfield($txt, "fn_not_contains", true, '~~~');
		$parts = explode('~~~', $parts);
		foreach($parts as $p) {
			$fv1 = GetAttribute($p, "val1");
			$fv2 = GetAttribute($p, "val2");
			if(!empty($fv1)){
				if (strpos($fv1, $fv2) === false) {
					$y = xmlfield($p, "fn_not_contains", false);
					$txt = str_replace($p, $y, $txt);
					
				} else {
				
					$txt = str_replace($p, '', $txt);
					
				}
				
			} else {
				// the field sent in was empty
				$txt = str_replace($p, '', $txt);
			}

			$fv1 = "";
			$fv2 = "";
			$y = '';
		}
	}
	
/*-----------------------------------------------------------------------------*/	
//handle <fn_contains val1="foo bar" val2="bar"/></fn_contains> - if "foo bar" contains "bar"
	if (strpos($txt, 'fn_contains') > 0 ){
		$parts = xmlfield($txt, "fn_contains", true, '~~~');
		$parts = explode('~~~', $parts);
		foreach($parts as $p) {
			//echo '['. $p . ']';
			$fv1 = GetAttribute($p, "val1");
			$fv2 = GetAttribute($p, "val2");
			//echo '['.$fv1 .'<<>>'.$fv2.']';
			// N.B. '0' is considered empty by PHP :)
			if(!empty($fv1) || $fv1 == '0'){
				if (strpos($fv1, $fv2) !== false) {
					$y = xmlfield($p, "fn_contains", false);
					$txt = str_replace($p, $y, $txt);
				} else {
					$txt = str_replace($p, '', $txt);
				}
			} else {
				// the field sent in was empty
				$txt = str_replace($p, '', $txt);
			}
				

			$fv1 = "";
			$fv2 = "";
			$y = '';
		}
	}
	

/*-----------------------------------------------------------------------------*/	
//	<fn_pretty_date format="l, jS F Y"><fv_cms_date></fn_pretty_date>
// assumes you pass in the usual 'yyyymmdd'
	if (strpos($txt, '<fn_pretty_date') > 0){
	
		$parts = xmlfield($txt, "fn_pretty_date", true, '~~~');
		$parts	=	explode('~~~', $parts);
		foreach($parts as $p) {
		
			$fmt = GetAttribute($p, "format");
			$dte = xmlfield($p, 'fn_pretty_date');
			$opd = DisplayDate($dte, $fmt);
			$txt = str_replace($p, $opd, $txt);
			
		}
		
	}

/*-----------------------------------------------------------------------------*/	
// <fn_replace replace="-" replace_with=""><fv_macro></fn_replace>
	if (strpos($txt, 'fn_replace') > 0)
	{
		$parts	=	xmlfield($txt, 'fn_replace',true, '~~~');
		$parts	=	explode('~~~', $parts);
		foreach ($parts as $p)
		{
			$replace		= getAttribute($p, "replace");
			$replace_with	= getAttribute($p, "replace_with");
			$value			= xmlfield($p, 'fn_replace');
			$return = '';
			
			if(!empty($value)){
				$return = str_replace($replace,$replace_with,$value);
			}
			
			$txt = str_replace($p,$return,$txt);
		}
	}

/*-----------------------------------------------------------------------------*/		
//	<fn_attribute attr='src'><fv_cms_image_back></fn_attribute>
//  <fn_attribute attr='href'><fv_cms_banner_link></fn_attribute>
/*  
  N.B. this is better than <fn_get_attribute> as it handles the difference between <img src='' /> and <a href=''></a> - i.e. self-closing tags and not :)
*/
	if (strpos($txt, 'fn_attribute') > 0)
	{
		$parts	=	xmlfield($txt, 'fn_attribute', true, '~~~');
		$parts	=	explode('~~~', $parts);
	
		foreach ($parts as $p){

			$html 	=	xmlfield($p, "fn_attribute");
			$attr	=	getAttribute($p, "attr");
			
			//echo 'FN Attribute:['. $attr .'] ('. $html .')'. "\r\n";
			
			$_attr	=	getAttribute($html, $attr) ?: '';
			$txt	=	str_replace($p, $_attr, $txt);
			
		}
	}
/*-----------------------------------------------------------------------------*/		
//	<fn_live_attribute attr='src'><fv_cms_image_back></fn_live_attribute>
//  <fn_live_attribute attr='href'><fv_cms_banner_link></fn_live_attribute>
/*  
  Writes in a full path using the live_url (e.g. for Email templates)
*/
	if (strpos($txt, 'fn_live_attribute') > 0)
	{
		$parts	=	xmlfield($txt, 'fn_live_attribute', true, '~~~');
		$parts	=	explode('~~~', $parts);
	
		foreach ($parts as $p){

			$html =	xmlfield($p, "fn_live_attribute");
			$attr =	getAttribute($p, "attr");

			//echo 'FN Attribute:['. $attr .'] ('. $html .')'. "\r\n";
			
			$_attr = getAttribute($html, $attr);
			$_attr = preg_replace('@\/'.$params['sitename'].'@', $params['live_server_url'] , $_attr, 1);
			
			$txt	=	str_replace($p, $_attr, $txt);
			
		}
	}
/*-----------------------------------------------------------------------------*/		
//							???

//	<fn_get_attribute field="cms_banner_link" key="<fv_ref_no>" attr="href" />
//	<fn_get_attribute str="<fv_cms_location>" attr="href" />
/* you might need to call the field value directly because of escaping "'" etc.
	- expects EITHER str OR field 
	- if its a banner you need to pass in the ref_no so you can pick up the field from the $dataHash
*/
	if (strpos($txt, 'fn_get_attribute') > 0)
	{
		$parts	=	GetDelimStr($txt, '<fn_get_attribute', '/>', true, '~~~');
		$parts	=	explode('~~~', $parts);
	
		foreach ($parts as $p)
		{
			$id 	=	getAttribute($p, "key");
			$attr	=	getAttribute($p, "attr");
			$str	=	getAttribute($p, "str");
			$fv		=   getAttribute($p, "field");	
			
			//echo 'FN Get Attribute:['. $id .'|'. $attr .'|'. $str  .'|'.  $fv . "\r\n";


			if(!empty($fv)){
				// make sure the <fv_field_name> is called in the template otherwise it wont be in the $dataHash
				$str	= 	xmlfield($dataHash[$id], $fv);
			}
			
			$_attr	=	getAttribute($str, $attr);
			$txt	=	str_replace($p, $_attr, $txt);
			
		}
	}
	
/*-----------------------------------------------------------------------------*/	
	

	return $txt;
	
	
/*-----------------------------------------------------------------------------*/		
/*-----------------------------------------------------------------------------*/		
}
/******************************************************************************/

/******************************************************************************/
function LoadDataHash($data){
	
	global $dataHash, $params, $lpHash;
	
	// OK if $lpHash("isbn") is supplied and $lpHash("sort") is blank, then we have to sort in the order of the ISBNs supplied !!
	// we can't rely on the $lpHash("isbn")'s mapping directly to <ref_no>, so we have to re-sort the data 
	// according to the field that contains the $lpHash("isbn")'s > most often the <barcode> field!! so here goes..
	msg('', 'LoadDataHash');
	//$x = print_r($data, true); echo "<!-- LoadDataHash: [$x] -->\r\n";
	$lpHash["sort"] = GetDelimStr($lpHash['query'], 'sort=', '&', false);
	if(@$lpHash['key_field'] != false){ $k = $lpHash['key_field']; }
	if(isset($k) == false){ $k = 'ref_no'; }
	
	//print_r($data);
	// Check +/- $params['isbn'] (from QS) vs $lpHash['isbn'] from <listproducts> params ??
	if (@$lpHash['isbn'] != false && $lpHash["sort"] == ''){
	
		$refHash = array();
		$parts = explode(chr(29), $data);
		foreach($parts as $p){
			// so first load the data into a hash using the correct field as the key
			// N.B. $lpHash['key_field'].. is usually <fv_barcode>.. but set it in the _ini file $params['dhkey-<DBM>']
			
			$refHash[xmlfield($p,'fv_'.$k)] = $p;	
			
		}
		
		//print_r($lpHash['isbn']);print_r($refHash);
		
		//now troll thru $lpHash("isbn")'s in order and load the dataHash from the RefHash
		// using the correctly mapped key!
		$rej = array();
		$parts = explode(",", $lpHash['isbn']);
		foreach($parts as $p) {
			if (@$refHash[$p] != false){
				$dataHash[$p] = $refHash[$p];
			}else{
				msg($p, 'REJECT: Check Site_ini: dhkey:: key_field');
				$rej[] = $p;
			}
		}
		//print_r($dataHash);
		// you will get misses if a crap ISBN has been entered into the <listproducts> by the CKEDITOR window
		// or if PageLength is set to less than the number of ISBNs entered
		if (isset($rej)){
			msg('Not found [' . join('|', $rej) .']', 'LoadDataHash:');
		}
		
	} else {
		//it''ll be in the (right) order it comes back in from the search (usually sorted)
		// so load as you find it..	
		$parts = explode(chr(29), $data);
		foreach($parts as $p){
			$dataHash[xmlfield($p, 'fv_'.$k)] = $p;
		}
	}
	unset($refHash);
	//everything should be inside datahash now
	//$d = print_r($dataHash, true); echo "<!-- LoadDataHash:: [$d] -->\r\n";
}
/*******************************************************************************/
// On account of re-writing the uri's we need to ensure that all href's and img 
// src's are full references.
// $x = AddBaseRef($txt, [$ax]); optional $ax = ['url'] permits you to send a relative url and have it BaseRef'd
// [MSP] we should also look at CSS escaping at this point..
// !!! N.B. AddBaseRef will break if the <img src=""/> tags are not on the same line (its the line break breaks the regex).

function AddBaseRef($blob = '', $ax = '', $loc=null){
	
	global $params;
	
	$pathInfo = strtolower( $_SERVER['PHP_SELF'] );
	$sitename = strtolower(@$params['sitename']);
	if (stripos($pathInfo, $sitename) !== false){
		$baseRef = "".$_SERVER['HTTP_HOST']."/".$sitename."/";
	} else {
		$baseRef =  "".$_SERVER['HTTP_HOST']."/";
	}
	$params['this_base'] = trim($baseRef, '[\/]');
	
	if ($_SERVER["HTTPS"] == "on") {
		$baseRef = "https://" . str_ireplace("http://", "", $baseRef);
		$params['this_prot'] = 'https';
	} else {
		$baseRef = "http://" . str_ireplace("http://", "", $baseRef);
		$params['this_prot'] = 'http';
	}
	
	if( empty($blob) ){
		return $baseRef;
	}
	
	// Send in a relative reference and get it completed whether on dev or live servers
	if (@$ax == "url"){
		
		if(preg_match('@^http@', $blob)){
			
			return $blob;
			
		} else {

			$blob = trim($blob, '[/]');
			if(preg_match("/^".$params['sitename']."/", $blob) ){

				$blob = stringfrom_first($blob, $params['sitename']);

			}
			$blob = trim($blob, '[/]');
			return $baseRef.$blob;
			
		}
	}
	
	msg("start match", "AddBaseRef: ");
	//$pattern = '/(href=\".*?\")|(src=\".*?\")|(href=\'.*?\')|(src=\'.*?\')|(action=\".*?\")/';
	$pattern = '/(href=\".*?\>)|(src=\".*?\>)|(href=\'.*?\>)|(src=\'.*?\>)|(action=\".*?\>)|(action=\'.*?\>)|(image:\s?\'.*?\')|(zoomImage:\s?\'.*?\')|(url\(.*?\))/';		
	preg_match_all($pattern, $blob, $match);
	
	msg("done match", "AddBaseRef: ");
	//printHash($match[0]);
	foreach($match[0] as $v){
		//msg($v);
		$oldStr = $v;
		//if(stripos($oldStr,"http") !== false || stripos($oldStr,"#") !== false || stripos($oldStr, "java") !== false || stripos($oldStr, "mailto") !== false || stripos($oldStr, "base64") !== false || stripos($oldStr, '<?php')){
		if(stripos($oldStr,"http") !== false || preg_match('/\"#|\'#/', $oldStr) || stripos($oldStr, "javascript:") !== false|| stripos($oldStr, "mailto:") !== false || stripos($oldStr, "tel:") !== false || stripos($oldStr, "base64") !== false || stripos($oldStr, '<?php') || stripos($oldStr, 'src="//')===0 || stripos($oldStr, 'href="//')!==false){
			//msg($v, 'addbaseref-ignore:');
			// base64 img src refs [used by the Help DB] need '+'s being put back in for spaces which get removed by ckEditor..
			if(stripos($oldStr, "base64") !== false){
				$newStr = str_replace(' ', '+', $oldStr);
				$blob = str_replace($oldStr, $newStr, $blob);
			}
		}else {
			//bit of tweaking here and there...
			//msg($oldStr, 'AddBaseRef:old: ');
			$newStr = str_ireplace('="'.$sitename.'/',  '="', $oldStr);
			$newStr = str_ireplace('="/'.$sitename.'/', '="', $newStr);
			$newStr = str_ireplace("='".$sitename."/",  "='", $newStr);
			$newStr = str_ireplace("='/".$sitename."",  "='", $newStr);
			$newStr = str_ireplace("url('/".$sitename."",  "url('", $newStr);
			$newStr = str_ireplace("url(/".$sitename."",  "url(", $newStr);
			
			$pattern = '/(href=\")|(src=\")|(data-dark-logo=\")|(href=\')|(src=\')|(action=\")|(action=\')|(image:\s?\')|(zoomImage:\s?\')|(url\(\')/';
			
			if(stripos($newStr, 'href=\'') !== false && $loc !== null)
			{
				$newStr = preg_replace($pattern, "$0".$baseRef.$loc, $newStr);	
			}
			else
			{
				$newStr = preg_replace($pattern, "$0".$baseRef, $newStr);	
			}
			$newStr = str_ireplace($sitename."//",$sitename."/",$newStr);
			$newStr = preg_replace('/\/\/"/', '"' , $newStr);
			
			
			$blob = str_replace($oldStr, $newStr, $blob);
			//msg($newStr, 'AddBaseRef:new: ');
		}
	}
	
	msg("loop done", "AddBaseRef: " . count($match[0]));
	$blob = str_replace('include/auto.complete.php','http://'.$baseRef.'include/auto.complete.php',$blob);
	$blob = str_replace('include/xml.proxy.php','http://'.$baseRef."include/xml.proxy.php",$blob);
	
	// New ckEditor contenteditable/editable='true' needs to be removed!!
	$blob = str_replace('editable="true"', 'editable="false"', $blob);
	$blob = str_replace("editable='true'", 'editable="false"', $blob);
	
	
	// Shopping Cart Add2B needs an escaped return URL to the current page. We Replace any instance of 'PGEURL' we find in the page.
	$params['this_page'] = @$params['url_page'].'/'.@$params['pid'].'/'.@$params['ds'].'?'.@$params['url_qs'] ;
	$params['this_page'] = str_replace('//', '/', $params['this_page']);
	$params['this_page'] = preg_replace('/\?$/', '', $params['this_page']);
	$params['this_page'] = $baseRef.''.$params['this_page'];
	//msg($params['this_page'], "AddBaseRef: this_page:");
	$blob = str_replace('<fv_this_page>', urlencode($params['this_page']), $blob);
	
	// for fancy css & javascript widgets
	$blob = str_replace('<fv_baseref>', trim($baseRef, '[\/]'), $blob);

	return $blob;
	
}

/***********************************************************************************/
function AppendCSS(){
// Operates with the CSS Query String param which can be set in mandatory.inc
// This allows you to add a style sheet dynamically via the URL
// This function is configureed for the page.asp template to hide the logic required.
// N.B. would be required on every page..	

	global $params;
	$quot = chr(34);
	$css = get_cookie('css');
	if($css != '' && $css != 'null'){
		$x = '<link rel="stylesheet" type="text/css" href="css/' . $css . '.css" media="screen" />' . "\r\n";
		return $x;
	}
}

/*******************************************************************************/
function eh_encode($str){
// preserves a url for HTMl transport

	$str = str_replace(':', '~', $str);
	$str = str_replace('=', ':', $str);
	$str = str_replace('&', '@', $str);
	$str = str_replace('?', '!', $str);
	
	return $str;
	
}
/*******************************************************************************/
function eh_decode($str){
// retrieves a url after HTMl transport

	if(!empty($str)){

		$str = str_replace(':', '=', $str);
		$str = str_replace('@', '&', $str);
		$str = str_replace('~', ':', $str);
		$str = str_replace('!', '?', $str);
		
	}
	
	return $str;
	
}
/*******************************************************************************/
/*	
' Returns a sorted XML fragment with the average star rating for each ISBN submitted.
' CALL: x = GetAverageRating(9781472126689,9781472126696)

' e.g. 	<average_rating>
			<9781472126689 data-count="8">3.5</9781472126689>
			<9781472126696 data-count="3">3</9781472126696>
		</average_rating>
		
' N.B. Listproducts etc respond to <avgrating> rather than 	<average_rating>

	See also below: <fn_avg_rating>
*/
function GetAverageRating($keys){


	global $params;
	msg($keys, 'GetAverageRating:Start');
		
	// ../include/xmla-elastic-tracker.php?view=xml&debug=on,query&dbm=zzz-tracker&pl=0&fields=distinct,default&msm=1&kyt=9781472126689,9781472126696&aggs=average_rating
	/* i.e. search tracker-db for <data_item_id> or <rel_id> returns a count based on <data_item_rating>: 
		<average_rating>
			<9781472126689 data-count="8">3.5</9781472126689>
			<9781472126696 data-count="3">3</9781472126696>
		</average_rating>
	*/

	$rurl = 'include/xmla-elastic-tracker.php?DBM=' . $params['sitename'] . '-tracker&pl=1&fields=distinct&msm=1&aggs=average_rating&SF1=data_item_id&ST1=' . $keys; 
	// We need to force xmla queries to use local server rather than up-out-n-down with risk that server has Moved!
	msg($rurl);
	$x = get_xml($rurl);
	$xml = "<avgrating>" . xmlfield($x, 'average_rating', false) . "</avgrating>";
	
	msg($xml, 'GetAverageRating:Done');
	return $xml;

}
/*******************************************************************************/
/*
' Returns list of titles (comma separated barcodes) ranked according to frequency viewed/added to basket etc.
' Can be filtered by category <psc> and also by Type [DISPLAY|ADD2BASKET|REVIEW|]
' CALL: x = GetPopularTitles($query, $pl)
' example $query: 	
					SF1=keyword&ST1=page,titledetail,add2basket,review
					&SF2=data_item_category&ST2=fiction
					&SF3=rel-sort_rating&ST3=01,02,03,04,05
					
' Returns: 9780745950938,9781859858974,978185985..

*/
function GetPopularTitles($query = 'kyt=page,add2basket,titledetail' , $max = 10){

	global $params;
	
	// ../include/xmla-elastic-tracker.php?DBM=zzz-tracker&pl=0&fields=distinct&msm=1&aggs=data_item_id&kyt=page,add2basket,titledetail
	msg('GetPopularTitles:Start');
	$rurl = 'include/xmla-elastic.php?DBM=' . $params['sitename'] . '-tracker&pl=1&fields=distinct&msm=1&aggs=data_item_id&' . $query; 
	$x = get_xml($rurl);
	msg($rurl, 'GetPopularTitles:');
	
	$xml = xmlfield($x, 'data_item_id', false);
	$xml = preg_replace('/ \[\d{1,5}\]/', '', $xml);
	$xml = str_replace('|', ',', $xml);
	
	$xml = explode(',', $xml);
	if (count($xml) < $max){ $max = count($xml); }
	$output = array_slice($xml, 0, $max); 
	$ret = implode(',', $output);
	//print_r($ret);
	msg('GetPopularTitles:Done');
	
	$x = addPromotionalOverride();
	if(!empty($x)){
		msg($x, 'GetPopularTitles:addPromotionalOverride');
		$ret = $x . ',' . $ret;
		$ret = rtrim($ret, ',');
		
	}
	
	return $ret;

}
/*******************************************************************************/
function GetRelatedTitles($ref, $rType, $max){
	
	global $params;

	$relHash = array();
	msg($ref, 'GetRelatedTitles:');
	// ../include/xmla-elastic-tracker.php?view=xml&debug=on,query&dbm=zzz-tracker&pl=0&fields=distinct,default&msm=1&sf1=rel_group&st1=9780762306657&aggs=significant_items,popular_items
	/* returns other titles related by user session (see fnMunge-Tracker.php)
		- rel_type no longer supported - just add page views and basket adds together into one rel_group...
	
	<distinct_fields>
		<popular_items>9780762306657 [8]|9780762304509 [4]|9780762307869 [4]|978085724199311164 [4]|978178635050311219 [4]|9781786352965112120 [4]|9781848552906 [4]|9780080441283 [1]|9780123891723 [1]|9780762301775 [1]</popular_items>
		<significant_items>9780762306657 [8]|9780762307869 [4]|9781786352965112120 [4]|978178635050311219 [4]|9780762304509 [4]|978085724199311164 [4]|9781848552906 [4]</significant_items>
    </distinct_fields>
	
	*/
	
	/* !! This needs further work. Use instead the LIKE query (see: MT-Searhc_Detail.tpl and suggestion_engine.php */
	//return '';
	
	$rurl = "include/xmla-elastic.php?DBM={$params['sitename']}-main&LIKE=$ref&PL=5&COLLAPSE=work_id&FIELDS=&MSM=1&AGGS=&SF1=&ST1="; 
	// We need to force xmla queries to use local server rather than up-out-n-down with risk that server has Moved!
	msg($rurl, 'GetRelatedTitles');
	$x = get_xml($rurl);
	//msg($x, 'GetRelatedTitles:1');
	$xml = xmlfield($x, 'fv_barcode', false, ",");
	$xml = preg_replace('/ \[\d{1,5}\]/', '', $xml);
	$xml = str_replace('|', ',', $xml);
	
	msg($xml, 'GetRelatedTitles');
	return $xml;
	

}
/*******************************************************************************/
	function addPromotionalOverride($cat = '', $imprint = ''){
	// See OECD for implementing additional keys such as imprint, lang etc.
	// N.B. We load the $related_title_overrides[] via Prices.inc 

		global $params, $related_title_overrides;
		
		$ret = '';
		
		$key = strtolower($cat);
		if(empty($key)){$key = strtolower($imprint); }
		if(empty($key)){$key = 'bs'; }
		
		$addISBNs = @$related_title_overrides[$key];

		if(!empty($addISBNs)){
			$addISBNs = str_replace('|', ',', $addISBNs);
			$addISBNs = rtrim($addISBNs, ',');
			$ret = $addISBNs;
		}
		//msg($ret, 'addPromotionalOverride:');
		return $ret;
		
	}
/*******************************************************************************/
	function RemoveDuplicates($ftext){
		
		$DeDeupHash = array();
		$string = "";
		$ftext = str_replace(',', '|', $ftext);
		
		$parts = explode('|', $ftext);
		foreach($parts as $p){
			if ($p != ''){
				$DeDeupHash[$p] = " ";	
			}
		}
		ksort($DeDeupHash);
		$string .= implode('|', array_keys($DeDeupHash));
		
		if (substr($string, -1) == '|') {$string = substr($string, 0, -1);}
		return $string;
		
	}
/*******************************************************************************/
	function start_session()
	{
		
		global $params;
		
		if($params['php_sessions_enabled'] and !preg_match("/proxy/", $params['url_page']) ){
				
			msg('starting session');
			session_start();
			set_cookie('sid', session_id());
			//echo "<!-- sid: " . session_id() . " -->\r\n";
			
		}
		
	}
/*******************************************************************************/
	function stop_session()
	{
		
		global $params;
		
		save_cookie_params();
		
		if($params['php_sessions_enabled'] and !preg_match("/proxy/", $params['url_page']) ){
			
			msg('closing session');
			session_write_close();
			
		}
		
	}
/*******************************************************************************/
	function save_cookie_params()
	{
		global $params;
		
		$names = explode('|', $params['cookie_params']);
		foreach($names as $name){
			
			set_cookie($name, @$params[$name]);
			
		}
		
	}
/*******************************************************************************/
	function refresh_cookie_params()
	{
		global $params;
		
		msg($params['cookie_params'], "refresh_cookie_params:: []");
		$names = explode('|', $params['cookie_params']);
		foreach($names as $name){
			
			
			$params[$name] = @$params[$name] ?: get_cookie($name);
			msg("val:[{$params[$name]}]", $name);
			
		}
		
	}
/*******************************************************************************/
/* Empty values are ignored - preserving the previous cookie value.
	To reset a cookie pass 'null'
*/
	function set_cookie($name, $value, $ax = [])
	{
		
		global $params;
		
	// These are the Defaults
		$options = [
			'expires' 	=> time()+60*60*24*30,
			'path' 		=> '/',
			'domain' 	=> $_SERVER['HTTP_HOST'], 	// '',
			'secure' 	=> true,
			'httponly' 	=> false,	// false if you want to read from javascript too
			'samesite' 	=> 'Strict',
		
		];
			
	// Override the Defaults..
		$options = array_merge($options, $ax);
		
		if(!empty($value)){
		
			if( preg_match("/null/i", @$value ?: '') ){
				
				msg(@$value, "Unsetting cookie: $name");
				$value = '';
				$options['expires'] = -1;
				unset($_COOKIE[$name]);
				unset($params[$name]);
				
			}
			
			if( @$params['encrypt_cookie_params'] == true ){
				msg($value, "set_cookie:encrypting: [$name]");
				$value = encrypt_decrypt($value, true);
				
			}
			
			if(PHP_VERSION_ID < 70300) {
				
				if($options['samesite'] == 'Strict'){
					// path ('; samesite=strict' - may not actually work)
					$options['path'] = '/; samesite=strict';
				}
				
				setcookie( 
					$name, 
					$value, 
					$options['expires'],
					$options['path'],
					$options['domain'],	
					$options['secure'],	
					$options['httponly']	
					
				);
							
			} else {
						
				//echo "<!-- cookie: [$c_name] [$x] -->\r\n";
				setcookie($name, @$value ?: '', $options);
						
			}
			msg($value, "set_cookie: $name");
			//$_COOKIE[$name] = $value;
			
		}
		
		
	}
/*******************************************************************************/
	function get_cookie($name){
		
		global $params;
		
		$cookie = @$_COOKIE[$name] ?: '';
		
		if( @$params['encrypt_cookie_params'] == true ){
			
			$cookie = encrypt_decrypt($cookie, false);
			
		}
		
		//msg($cookie, "get_cookie:[$name]");
		return $cookie;
		
	}

/*******************************************************************************/
/*
	The purpose here is to simply obfuscate certain visible cookie values. 
*/
	function encrypt_decrypt($string, $action = true ) 
	{
		$output = false;
		$encrypt_method = "AES-256-CBC";
		$secret_key = 'This-is-my-secret-key';
		$secret_iv = 'This-is-my-secret-iv';
		// hash
		$key = hash('md2', $secret_key);

		// iv - encrypt method AES-256-CBC expects 16 bytes - else you will get a warning
		$iv = substr(hash('md2', $secret_iv), 0, 16);
		if ( $action == true ) { 	// 'encrypt'
			$output = openssl_encrypt($string, $encrypt_method, $key, 0, $iv);
			$output = base64_encode($output);
		} else if( $action == false ) {	// 'decrypt'
			$output = openssl_decrypt(base64_decode($string), $encrypt_method, $key, 0, $iv);
		}
		return $output;
	}	
/*******************************************************************************/
function checkUserIP(){

	global $params;

	if (@$params['ip_filter']){
		msg("IP Filter Check is ON..", "checkUserIP");
		$filter = explode("|" ,$params['ip_filter']);
		
		//check ip from share internet
		if (!empty($_SERVER['HTTP_CLIENT_IP']))
		{
		  $ip=$_SERVER['HTTP_CLIENT_IP'];
		}
		//to check ip is pass from proxy
		elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))
		{
		  $ip=$_SERVER['HTTP_X_FORWARDED_FOR'];
		}
		else
		{
		  $ip=$_SERVER['REMOTE_ADDR'];
		}
		//return $ip;
		$visitorIp = trim($ip);
		$status = in_array($visitorIp, $filter);
		
		// if $visitorIp IS in $filter then cool
		if($status > 0) {
			msg("IP [". $visitorIp ."] is OK", "checkUserIP");

		} else {
			msg("IP [". $visitorIp ."] NOT OK. Conection refused.", "checkUserIP");
			exit; 
		}	
		
		
	}else{
		msg("IP Filter Check is OFF.", "checkUserIP");
	}
	
}
/*******************************************************************************/
/*
@param string $text String to truncate.
@param integer $length Length of returned string, including ellipsis.
@param string $ending Ending to be appended to the trimmed string.
@param boolean $exact If false, $text will not be cut mid-word
@param boolean $considerHtml If true, HTML tags would be handled correctly
@return string Trimmed string.
*/
function TruncateHTML($text, $length = 100, $ending = '...', $exact = false, $considerHtml = true) {

	if ($considerHtml) {
		// if the plain text is shorter than the maximum length, return the whole text
		if (strlen(preg_replace('/<.*?>/', '', $text)) <= $length) {
			return $text;
		}
		// splits all html-tags to scanable lines
		preg_match_all('/(<.+?>)?([^<>]*)/s', $text, $lines, PREG_SET_ORDER);
		$total_length = strlen($ending);
		$open_tags = array();
		$truncate = '';
		foreach ($lines as $line_matchings) {
			// if there is any html-tag in this line, handle it and add it (uncounted) to the output
			if (!empty($line_matchings[1])) {
				// if it's an "empty element" with or without xhtml-conform closing slash
				if (preg_match('/^<(\s*.+?\/\s*|\s*(img|br|input|hr|area|base|basefont|col|frame|isindex|link|meta|param)(\s.+?)?)>$/is', $line_matchings[1])) {
					// do nothing
				// if tag is a closing tag
				} else if (preg_match('/^<\s*\/([^\s]+?)\s*>$/s', $line_matchings[1], $tag_matchings)) {
					// delete tag from $open_tags list
					$pos = array_search($tag_matchings[1], $open_tags);
					if ($pos !== false) {
						unset($open_tags[$pos]);
					}
				// if tag is an opening tag
				} else if (preg_match('/^<\s*([^\s>!]+).*?>$/s', $line_matchings[1], $tag_matchings)) {
					// add tag to the beginning of $open_tags list
					array_unshift($open_tags, strtolower($tag_matchings[1]));
				}
				// add html-tag to $truncate'd text
				$truncate .= $line_matchings[1];
			}
			
			// calculate the length of the plain text part of the line; handle entities as one character
			$content_length = strlen(preg_replace('/&[0-9a-z]{2,8};|&#[0-9]{1,7};|[0-9a-f]{1,6};/i', ' ', $line_matchings[2]));
			if ($total_length+$content_length> $length) {
				// the number of characters which are left
				$left = $length - $total_length;
				$entities_length = 0;
				// search for html entities
				if (preg_match_all('/&[0-9a-z]{2,8};|&#[0-9]{1,7};|[0-9a-f]{1,6};/i', $line_matchings[2], $entities, PREG_OFFSET_CAPTURE)) {
					// calculate the real length of all entities in the legal range
					foreach ($entities[0] as $entity) {
						if ($entity[1]+1-$entities_length <= $left) {
							$left--;
							$entities_length += strlen($entity[0]);
						} else {
							// no more characters left
							break;
						}
					}
				}
				$truncate .= substr($line_matchings[2], 0, $left+$entities_length);
				// maximum lenght is reached, so get off the loop
				break;
			} else {
				$truncate .= $line_matchings[2];
				$total_length += $content_length;
			}
			// if the maximum length is reached, get off the loop
			if($total_length>= $length) {
				break;
			}
		}
	} else {
		if (strlen($text) <= $length) {
			return $text;
		} else {
			$truncate = substr($text, 0, $length - strlen($ending));
		}
	}
	// if the words shouldn't be cut in the middle...
	if (!$exact) {
		// ...search the last occurance of a space...
		$spacepos = strrpos($truncate, ' ');
		if (isset($spacepos)) {
			// ...and cut the text in this position
			$truncate = substr($truncate, 0, $spacepos);
		}
	}
	// add the defined ending to the text
	$truncate .= $ending;
	if($considerHtml) {
		// close all unclosed html-tags
		foreach ($open_tags as $tag) {
			$truncate .= '</' . $tag . '>';
		}
	}
	return $truncate;
}
/*******************************************************************************/
// Returns the remainder of a string from after the first instance of the pointer to the end of the string 
// OR a '' string. case insensitive e.g. [stringfrom($params['filename'], '\\');)]
function stringfrom_first($txt, $pointer){

	if( empty($pointer) ){
		return $txt;
	}else{
		$pos = stripos($txt, $pointer);
		//msg($pos, 'stringfrom_first:');
		if ($pos === false) {
			$pos = strlen($txt);
		}
		$ret = substr($txt, $pos + strlen($pointer));
		if ($ret === false || !isset($ret)) { $ret = ''; }
		return $ret;
	}

}
/*******************************************************************************/
// Returns the remainder of a string from after the last instance of the pointer to the end of the string 
// OR a '' string. case insensitive e.g. [stringfrom($params['filename'], '\\');)]
function stringfrom($txt, $pointer){

	$pos = strripos($txt, $pointer);
	//msg($pos, 'stringfrom:');
	if ($pos === false) {
		$pos = strlen($txt);
	}
	$ret = substr($txt, $pos + strlen($pointer));
	if ($ret === false || !isset($ret)) { $ret = ''; }
	return $ret;

}
/*******************************************************************************/
// Returns the first part of a string up to the pointer 
// OR a '' string. Case insensitive e.g. [stringto($params['filename'], '\\data');)]
function stringto($txt, $pointer){

	$pos = stripos($txt, $pointer);
	//msg($pos, 'string:');
	if ($pos === false) {$pos = 0;}
	$ret = substr($txt, 0, $pos);
	if ($ret === false || !isset($ret)) { $ret = ''; }
	return $ret;

}
/*******************************************************************************/
// Returns the first part of a string up to last occurrence of the pointer 
// OR a '' string. Case insensitive e.g. [stringto_last($params['filename'], '\\data');)]
// true = defaults to include the pointer character
function stringto_last($txt, $pointer, $inc = true)
{
	$pos = strripos($txt, $pointer);
	//msg($pos, 'string:');
	if ($pos === false) {$pos = 0;}
	$plus = $inc ? 1 : 0;
	$ret = substr($txt, 0, $pos + $plus);
	if ($ret === false || !isset($ret)) { $ret = ''; }
	return $ret;
}
/***********************************************************************************/
//ToDo: ?? see page.php I think is is now not used ??
function PermanentRedirects() {

	$x = $_SERVER['HTTP_HOST'];
	$url = $_SERVER['PHP_SELF'];
	
	$permRedirect = "|kurumba.co.uk||||||";
	$redirect = "www.kurumba.co.uk";
	$redirect = "http://". str_replace("//", "/", $redirect.$url);
	//permanently redirects the user
	if (strpos($permRedirect, "|".$x."|") > 0 ) {
		header("HTTP/1.1 301 Moved Permanently");
		header("Location:http://www.kurumba.co.uk");
		exit;
	}
}
/*******************************************************************************/
function security_required()
{
	//ToDo: James/David to find another way :)
	global $params;
	
	//	This is experifuckingmental - If you share to facebook, linked in etc, you'll
	//	Share the cookie warning. This is something we don't want.
	
	$_user_agent			=	$_SERVER['HTTP_USER_AGENT'];
	
	$_no_ssl_user_agents	=	array(
									'facebookexternalhit/1.0 (+http://www.facebook.com/externalhit_uatext.php)' => true,
									'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)' => true,
									'LinkedInBot/1.0 (compatible; Mozilla/5.0; Jakarta Commons-HttpClient/3.1 +http://www.linkedin.com)' => true,
									'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)' => true,
									'Googlebot/2.1 (+http://www.google.com/bot.html)' => true
								);
	
	if(array_key_exists($_user_agent, $_no_ssl_user_agents))
	{
		$params['ssl_enabled'] = false;
		return false;
	}
	else
	{
		$params['ssl_enabled'] = true;
		return true;
	}
}
/*******************************************************************************/
function check_page_requested($inc){
// e.g. PageRedirects.inc should be sat in the /include/ folder and contain
// an Array: $redirect['request-page'] = 'page/proper';
// ToDo: trap query_strings - check for ISBNs and redirect.. - maybe better local to each site if you want more than this.

	global $params;
	// Load the PageRedirects.inc containing the array[]
	require_once($inc);
	
	// Find the page requested..try to clear up the IIS redirect string e.g.
	// KEY: [qs] -> VALUE: [/kurumba/page.php?404;http://127_0_0_1:80/kurumba/folder_php?pid=sub&ds=child&os=/page.php&qas=plop]
	$url = urldecode($params['qs']);

	if(strpos($url, '404;') > 0) {
		// we have a non .NET IIS 404 redirect - yippee!
		$parts = explode(':80', $url);
		$url = $parts[1];
		$url = preg_replace('@_php|\?pid=|&ds=|&os=@i', '/', $url);
		if( !empty($params['url_qs']) ){
			$url = preg_replace('@'.$params['url_qs'].'@i', '', $url);	
		}
		
	} elseif(strpos($url, 'aspxerrorpath') > 0) {
		
		$parts = explode('=', $url);
		$url = $parts[1];
	
	}

	$url = preg_replace('@\/\/@', '/', $url);
	$url = preg_replace('@&$|\?$|\=$@', '', $url);
	$url = preg_replace('@_@', '.', $url);	// CAUTION: if request contains '_' you may need to adjust the $redirect['key_s'] to $redirect['key.s'] 
	$url = preg_replace('@'.SITENAME.'@', '', $url);
	$url = preg_replace('@^\/+@', '', $url);

	$page = $url;

	msg($url, 'Check Page:: Request:');
	msg($params['url_qs'], 'Check Page:: Original QS: ');
	msg('from:(' . $page .') => to:(' . @$redirect[$page] .')', "Check Page:: ");
	

	if( !empty($redirect[$page]) ){
		if( $redirect[$page] == 'dynamic' ) {
			// apply rules stored in PageRedirects.inc
			if(function_exists('ProcessRedirect') ){
				$redirect[$page] = ProcessRedirect($page, $params['url_qs']);
			} 
		} 
		
		write_to_log('REQUEST:' . $page .'|QS:' . $params['url_qs'] . '|REDIRECT:' . @$redirect[$page], 'date-time', 'Ym', 'REDIRECTS-LOG');
		$rUrl = AddBaseRef($redirect[$page], 'url');
		msg($rUrl, "Check Page:: Redirect To Switched OFF:");
		
		////header("HTTP/1.1 301 Moved Permanently");
		////header("HTTP/1.1 302 Moved Temporarily");
		header('Location: '.$rUrl, TRUE, 301);
		exit;
		
	} else {
		
		write_to_log('REQUEST:' . $page .'|QS:' . $params['url_qs'] . '|REDIRECT:' . 'FAILED', 'date-time', 'Ym', 'REDIRECTS-LOG');
	}
	// test for a folder e.g. $key: [business/dl/di/] will redirect $page:[business/dl/di/some-page/index.asp]
	if(!empty($redirect)){
		foreach($redirect as $key=>$value){
			// as key contains '/' use '@' for regex delimiters :)
			if (preg_match("@^".preg_quote($key).".*@", $page) )	{
				$rUrl = AddBaseRef($value, 'url');
				msg($rUrl, "Check Page:: Folder Re-direct Switched OFF:");
				////header("HTTP/1.1 301 Moved Permanently");
				////header("HTTP/1.1 302 Moved Temporarily");
				//header('Location: '.$rUrl, TRUE, 301);
				//exit;
				
			}
		
		}
	}
	
}
/*******************************************************************************/
//ToDo: nah ??remove??
function KeyChange ($hash,$cKey, $nKey) {
		//swaps the key=value to value=key
		$hash = array_flip($hash);
		foreach ($hash as $k => $v) {
			if ($v == $cKey) {
				$hash[$k] = $nKey;
			} else {
				$hash[$k] = $v;
			}
		}
		$x = array_flip($hash);
		return $x;
	}
	
/*******************************************************************************/
//ToDo: see ListProducts and redo this..
function PreviousSearches(){

	global $params;
	
	//$x = UpdateLatestSearch();
	$keyname = "|keyword|st1|st2|st3|ST1|ST2|ST3||||||";
	if(!empty($_COOKIE['pst']) ) {
		
		$blob = "<li>";
		$blob .= "<h4>Previous Searches</h4>";
		$blob .= "<ul class='closed'>";
		$parts = explode("|", $_COOKIE['pst']);
		foreach($parts as $p) {
			if($p != ""){
				//echo $p;
				$p = str_replace(":","=",$p);
				$p = str_replace("@","&",$p);
				$parts = explode("&",$p);
				foreach($parts as $p2){
					//echo $p2;	
					$k = explode("=",$p2);
					$k = $k[0];
					$v = explode("=",$p2);
					$v = $v[1];
					
					if(stripos($keyname,"|".$k."|") !== false && trim($v) != ""){
						$blob .= "<li><a href='results.php?".str_replace(chr(34),"%22",$p)."'>".$v."</a></li>";	
					}
				}
				
			}
		}
		
		if(stripos($params['url_root'], "results.php") !== false){
			$blob .= "<li> <a href='".$params['url_root']."?".ModifySQS($params['url_qs'],"PST=null", 'str')."'>(clear saved searches)</a> </li>";
		}
		$blob .= "</ul></li>";
		
		return $blob;
		
	} else {
		//echo "empty";
		return "";	
	}
}
/*******************************************************************************/
function WriteResponse($response){
// Typically $response[] is an array of the form..
// $response = array('errormsg' => '', 'errorcode' => '', 'responsetext' => '', 'rid' => '', 'ref_no' => '', 'logfile' => '', 'ctitle' => '', 'response_node' => 'record', 'response_type' => 'xml', );
//  e.g. for old refs $response = array('errorcode' => 0, 'errormsg' => '', 'responsetext' => '', 'rid' => '', );

	$response_node = @$response['response_node'] ?: 'response';
	$response_type = @$response['response_type'] ?: 'xml';
	$return_string	=	'';
	switch ($response_type) {
		
		case "json":
			header("Content-type: application/json; charset=utf-8");
			$return_string = json_encode(array($response['response_node'] => $response));
			break;
		
		default:
			header("Content-type: text/xml; charset=utf-8");
			unset($response['response_node']);
			foreach($response as $key => $value)
			{				
				/* msg($value, $key);  //N.B. This debug will break the AJAX by repeating the responsetxt */
				if(empty($value) || $value == @$response_node || @$value == $response_type) {
					// largely..do nowt..
					// 'empty' considers "0" as string to be empty - but we are using it to mean 'true - no errors' :)
					if($value === 0) { 
						$value = "0"; 
						$return_string .= xmltag($key, $value, 0) . "\r\n";
					}
					
				} else {
				
					$return_string .= xmltag($key, $value, 0) . "\r\n";
				}
			}			
			$return_string = preg_replace("/^[\r\n]+/", '', $return_string);	
			$return_string = preg_replace("/[\r\n]+$/", '', $return_string);				
			$return_string = "<response>$return_string</response>";
			break;
			
	}
	
	return $return_string;
	
}
/******************************************************************************/
function getYear(){
	
	$date = getdate();
	$year = $date['year'];
	
	return $year;
		
}
// *************************************************************************** //
function msg(){

	global $params, $ErrMessage;
	
	$numArgs = func_num_args();
	 if ($numArgs == 1) {
		 $txt = func_get_arg(0);
	}else if ($numArgs == 2) {
		$txt = func_get_arg(0);
		$label = func_get_arg(1);
	}
	if (@$params['debug'] == 'on') {
		if($ErrMessage == ''){
			$ErrMessage = "\r\n<!-- [". timer() ."] [". @$label . "] - [". $txt ."] -->\r\n";
		}else{
			$ErrMessage .= "<!-- [". timer() ."] [". @$label . "] - [". $txt ."] -->\r\n";
		}
	}

}

/*******************************************************************************/
function mt_replace_p2c($str){
// Eval doesn't do str_replace very well :)
// Replace pipes with commas 
	if( !empty($str) ){
		
		return str_replace('|', ',', $str);
		
	}
		
}
/*******************************************************************************/
function Trunc($FieldStr) {
	
	if(!empty($FieldStr)){
	
		if (strpos($FieldStr, '(') !== false) {
			$FieldStr = explode('(',$FieldStr,2);
			$FieldStr = $FieldStr[0];
		}
		if (strpos($FieldStr, '[') !== false) {
			$FieldStr = explode('[',$FieldStr,2);
			$FieldStr = $FieldStr[0];
		}
		if (strpos($FieldStr, '|') !== false) {
			$FieldStr = explode('|',$FieldStr,2);
			$FieldStr = $FieldStr[0];
		}
		if (strpos($FieldStr, chr(29)) !== false) {
			$FieldStr = explode(chr(29),$FieldStr,2);
			$FieldStr = $FieldStr[0];
		}
		
		$FieldStr = trim($FieldStr);
	}
	
	return $FieldStr;
	
}
/*******************************************************************************/
	function MakeExact($txt) {
	// Creates our standard CODE string

		if(!empty($txt)){
			
		$txt = Trunc($txt);
		$pattern = '/(&.{1,5};|@#.{1,5};|[^a-zA-Z0-9]|\W|\s+)/';
		$replacement = '';
		
		$txt = preg_replace($pattern, $replacement, $txt);
		$txt = strtoupper($txt);
		$txt = substr($txt, 0, 120);
		
		}
		
		return $txt;
		
	}
	
	function make_pagename($str){
		
		$str = preg_replace('/(&.{1,5};|@#.{1,5};|\W|\s+)/', '_', $str);
		$str = preg_replace("/(_$)|(^_)/", '', $str);
		$str = preg_replace("/_([s|d])_/", "$1_", $str);	// to keep consistent with CMS elide 'd and 's
		$str = preg_replace('/\_+/', '-', $str);
		$str = strtolower($str);
		return $str;	
	}
	
/*******************************************************************************/
	function CleanUpString ($str){
// Not entirely successful Attempt to convert all nasty characters to Numeric Character entities..
	
	$str = html_entity_decode($str, ENT_QUOTES, 'UTF-8');
    $str = preg_replace_callback("/(&#[0-9]+;)/", function($m) { return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES"); }, $str);
    return $str;
	
}
/*******************************************************************************/
	/* We get some real shit passed thru to us...
	   - the CMS should be converting all horrid characterst o numeric entities - but there remain a few problems
	   - lone ampersands need to be escaped for one..
	
	*/
	
		function CleanXML($xml){
			
			//print_r('['.$xml."]\r\n");
			
			$xml = preg_replace('/&(?!(?:apos|quot|[gl]t|amp);|#)/', '&amp;', $xml);
			//$xml = str_replace('&amp;amp;' , '&amp;', $xml);
			//$xml = str_replace('&amp;' , '&#38;', $xml);
			
			//print_r('['.$xml.']');
			
			return $xml;
			
		}
/*******************************************************************************/
	function printHash ($hash) {

	global $ErrMessage;

	if(DEBUGFLAG){
		//ksort($hash);
		$ErrMessage .= "<!--  \n\r";
		foreach ($hash as $key=>$value){
			if(is_array($value)){
				$ErrMessage .= "KEY: [".$key."] -> VALUE: [array] <br>\n\r";	
			} else {
				$ErrMessage .= "KEY: [".$key."] -> VALUE: [".$value."] <br>\n\r";	
			}
		}
		$ErrMessage .= " --> \n\r";
	}
}
/***********************************************************************************/
function BDFormatCurrency($getAmount, $precision = 2){	
	
	if (is_numeric($getAmount)){
		$getAmount = round($getAmount , $precision , PHP_ROUND_HALF_DOWN);
		$getAmount = number_format($getAmount, $precision, '.', '');
	} else {
		$getAmount = '';	
	}
	return $getAmount;
}
/***********************************************************************************/
function padz($s, $n){

	$s = str_pad($s, $n, "0", STR_PAD_LEFT);
	return $s;
	
}
/***********************************************************************************/	
function timer(){
	
	$timer = explode(" ", microtime());
	$timer = $timer[0] + $timer[1];
	$start = number_format($timer, 2, '.', '');
	return $start;	
	
}
/*******************************************************************************/
function write_logs($extra, $ax = '', $interval = 'Ymd') {
// To switch this on uncomment $params['setlogfile'] in _ini.php
// To add $extra - set this 

	global $params;
	
	date_default_timezone_set('Europe/London'); 
	$date = date('Ymd');
	$time = date('H:i:s'); 
	$errmsg = '';
	// e.g. set interval to date('Ym') to have one file per month :)
	$interval = date($interval);
	
	if(@$params['setlogdir'] == '') {$params['setlogdir'] = 'd:/tbp/logs';}
	if(@$params['setlogfile'] == '') {$params['setlogfile'] = $params['sitename'] . '_PageLog';}
	$log_file = $params['setlogdir'] . '\\' . strtoupper($params['setlogfile']) . '_' . '' . $interval . '.log';	
	
	if($extra != '') { $extra = ' |' . trim($extra, '|') . '' ;}
	if(@$params['errorcode'] != '') {$errmsg = $params['errorcode'];}
	if(@$params['error_page'] != '') {$errmsg .= '' . $params['error_page'];}
	
	if ($ax == '') {
	
		//DATE|TIME|RURL|SESSIONID|DBM|PID|CID|ERROR_MSG|SEARCH_TERMS|THIS_PAGE|LOG_EXTRA
		$log_str = $date . ' |' . $time. ' |' . $_SERVER['REMOTE_ADDR'] . ' |' . @$_SESSION['sid'] . ' |' . @$params['dbm'] . ' |' . @$params['pid'] . ' |' . @$_SESSION['cid'] . ' |' . $errmsg . ' |' . @$params['search_terms'] . ' |' . @$params['this_page'] . $extra . "\n"; 
			
	} elseif ($ax == 'date-time') {
	
		$log_str = $date . ' |' . $time . $extra . "\n"; ;
	
	}
	
	
	file_put_contents($log_file, $log_str, FILE_APPEND );
}

/***********************************************************************************/
function SentenceCase($line) {
	
	$WrdList = '|and|the|in|to|or|at|of|a|';
	$str=ucwords(strtolower($line));
	$str = explode(" ", $str);
	$Acronyms = '|gb|elt|uk|usa|';
	
	foreach ($str as $key => $value) {
		
		if (stripos($WrdList, "|".$value."|") !==false){
			
			$match = strtolower($value);
			//iterates through the array changing the values where there are matches
			for($i=0;$i<count($str);$i++) {
   				if($str[$i] == $value) {
   					$str[$i] = $match;
  				 }
			}	
		}
		
		if (stripos($Acronyms, "|".$value."|") !==false) {
			
			$match = strtoupper($value);
			
			for($i=0;$i<count($str);$i++) {
   				if($str[$i] == $value) {
   					$str[$i] = $match;
  				}
			}
		}
	}
	//makes the array a string again
	$line = implode(' ', $str);
	return $line;
}
/*******************************************************************************/
function rdate($d1=0) {
// Returns Today [e.g. rdate(0)] as [yyyymmdd] +/- days..

	if(empty($d1)){$d1=0;} 
	$date = new DateTime('today');
	$date->modify(''.$d1.' day');	// looks like PHPv8.3 doesn't like '+'.$d1
	return $date->format("Ymd");
	
}
/*******************************************************************************/
function mdate($d1) {
// Returns Today [e.g. rdate(0)] as [yyyymmdd] +/- days..
	
	$date = new DateTime('today');
	$date->modify('+'.$d1.' month');
	return $date->format("Ym");
	
}
/*******************************************************************************/
function DisplayDateTime($fmt = 'Ymd H:i:s', $d1 = 0) {
// New & improved! Returns Date or DateTime in the specified format - combination of DisplayDate and rdate
// e.g. DisplayDateTime('Ymd', 20) - 'yyyymmdd' + 20 days
// e.g. DisplayDateTime('Ymd')	   - 'yyyymmdd' toaday
// e.g. DisplayDateTime('l, jS F Y') - 'Monday, 8th April 2013'

	if(empty($fmt)){$fmt = 'Ymd H:i:s';} // default params only work for the last param in the function call!PHP
	
	date_default_timezone_set('Europe/London');
	$date = new DateTime('now');
	$date->modify('+'.$d1.' day');
	return $date->format($fmt);
	
}
/***********************************************************************************/
function DisplayDate( $d1, $fmt = 'Y-m-d'){
// expects yyyymmdd +/- a DateTime Format. Should return a formatted date string
// 'l jS \of F Y h:i:s A' = 'Monday 8th of April 2013 12:00:00 AM'
// 'l, jS F Y' = 'Monday, 8th April 2013'
// e.g. Today = DisplayDate(rdate(0), 'l, jS F Y' );
// e.g. Date/Time updated = DisplayDate(rdate(0), 'Ymd-h:i:s');
// 

	date_default_timezone_set('Europe/London');
	if (($timestamp = strtotime($d1)) === false) {
		return '';
	}else{
		return date($fmt, strtotime($d1)); 
	}
}
/***********************************************************************************/
// captcha hash decryption
function rpHash($value) {
	$hash = 5381;
	$value = strtoupper($value);
	for($i = 0; $i < strlen($value); $i++) {
		$hash = (($hash << 5) + $hash) + ord(substr($value, $i));
	}
	
	return $hash;
}
/***********************************************************************************/
/*
	Replaces getGeoLocation() & getGeoIP(): Called from mandatory.inc
	use GeoIp2\Database\Reader; [See: https://github.com/maxmind/GeoIP2-php]
	requires up-to-date MaxMind GeoLite2 database: "/scripts/vendor/maxmind-db/data/GeoLite2-City.mmdb";
	Geo data saved to cookie eh1: Site_ini [set cookie_params: 'loc|cur|sid|lang|country|city|zip|region']
	Site.Functions.php:: mtBodyTagData() sets <body data-attributes>
	ehaus.tracker.js (reads values from <body data-attributes> if present) else another lookup
*/
	function get_geo_location($reader) {
		
		global $params;
		
		msg("start", 'get_geo_location::');
		
		//$ip = '31.185.253.30';	// test ip
		$loc = '';
		$ip  = @$_SERVER['HTTP_X_FORWARDED_FOR'] ?: @$_SERVER['REMOTE_ADDR'] ?: '';
		$bot = @$_SERVER['HTTP_USER_AGENT'] ?: '';
		//echo "<!-- $bot [$ip] -->\r\n" ; //die;
		
		/* Try to skip botnets & CMS calls (we only have 10k lookups p/month :)
		if( is_banned($bot) || is_banned($ip) || empty($ip) || empty($bot)  ) { 
			//echo "<!-- get_geo_location::skipped [$ip] == ($bot) -->\r\n";
			msg("[$ip] - [$bot]", 'get_geo_location::skipped');
			return;
		}
		*/
		
		try {
			
			$record = $reader->city($ip);
			//$x = print_r($record, true); echo "<!-- get_geo_location: [$x] -->\r\n";
			
		} catch (Exception $e) {

			//print_r($e);die;	// N.B. Laptop IP is not recognised and causes an Exception
			msg(get_cookie('loc'), "Exception: get_cookie:loc:");
			//echo "<!-- geo_location exception: [{$params['url_page']}] -->\r\n";
			//echo "<!-- geo_location get_cookie: [" . get_cookie('loc') . "] -->\r\n";
			//echo "<!-- geo_location _COOKIE: [" . @$_COOKIE['loc'] . "] -->\r\n";
			//echo "<!-- geo_location params: [" . @$params['loc'] . "] -->\r\n";
			$params['loc'] = @$params['loc'] ?: @$_COOKIE['loc'] ?: get_cookie('loc') ?: 'UK';
			if( $params['loc'] == 'GB' ){ $params['loc'] = 'UK'; }
			if( !empty($params['loc']) ){ $params['loc'] = strtoupper($params['loc']); }
			msg("Exception: setting loc to: [{$params['loc']}]", 'get_geo_location::');
			return;
		
		}

		//print_r($record); die;
		$params['loc'] 		= @$params['loc'] ?: @$record->country->isoCode ?: get_cookie('loc') ;
	if( $params['loc'] == 'GB' ){ $params['loc'] = 'UK'; }
		$params['country'] 	= $record->country->name;
		$params['city'] 	= $record->city->name;
		$params['zip'] 		= $record->postal->code;
		$params['region'] 	= $record->continent->code;
		
		//$params['region_code'] 	= $record->mostSpecificSubdivision->isoCode;		
		//$params['lat'] = $record->location->latitude;
		//$params['lng'] = $record->location->longitude;
			
		// N.B. do_session sets the Site_ini cookies from $params

		msg("loc:{$params['loc']}", 'get_geo_location::');
		msg("country:{$params['country']}", 'get_geo_location::');
		msg("city:{$params['city']}", 'get_geo_location::');
		msg("zip:{$params['zip']}", 'get_geo_location::');
		msg("region:{$params['region']}", 'get_geo_location::');
	  

	}
/***********************************************************************************/
	function is_banned($test) {
	 
		$TestRegexPattern = "(192\.168\.1|127\.0\.0\.1|10\.100\.|10\.0\.1\.|googlebot\/|Googlebot\-Mobile|Googlebot\-Image|Google favicon|Mediapartners\-Google|bingbot|slurp|java|wget|curl|Commons\-HttpClient|Python\-urllib|libwww|httpunit|nutch|phpcrawl|msnbot|jyxobot|FAST\-WebCrawler|FAST Enterprise Crawler|biglotron|teoma|convera|seekbot|gigablast|exabot|ngbot|ia_archiver|GingerCrawler|webmon |httrack|webcrawler|grub\.org|UsineNouvelleCrawler|antibot|netresearchserver|speedy|fluffy|bibnum\.bnf|findlink|msrbot|panscient|yacybot|AISearchBot|IOI|ips\-agent|tagoobot|MJ12bot|dotbot|woriobot|yanga|buzzbot|mlbot|yandexbot|purebot|Linguee Bot|Voyager|CyberPatrol|voilabot|baiduspider|citeseerxbot|spbot|twengabot|postrank|turnitinbot|scribdbot|page2rss|sitebot|linkdex|Adidxbot|blekkobot|ezooms|dotbot|Mail\.RU_Bot|discobot|heritrix|findthatfile|europarchive\.org|NerdByNature\.Bot|sistrix crawler|ahrefsbot|Aboundex|domaincrawler|wbsearchbot|summify|ccbot|edisterbot|seznambot|ec2linkfinder|gslfbot|aihitbot|intelium_bot|facebookexternalhit|yeti|RetrevoPageAnalyzer|lb\-spider|sogou|lssbot|careerbot|wotbox|wocbot|ichiro|DuckDuckBot|lssrocketcrawler|drupact|webcompanycrawler|acoonbot|openindexspider|gnam gnam spider|web\-archive\-net\.com\.bot|backlinkcrawler|coccoc|integromedb|content crawler spider|toplistbot|seokicks\-robot|it2media\-domain\-crawler|ip\-web\-crawler\.com|siteexplorer\.info|elisabot|proximic|changedetection|blexbot|arabot|WeSEE:Search|niki\-bot|CrystalSemanticsBot|rogerbot|360Spider|psbot|InterfaxScanBot|Lipperhey SEO Service|CC Metadata Scaper|g00g1e\.net|GrapeshotCrawler|urlappendbot|brainobot|fr\-crawler|binlar|SimpleCrawler|Livelapbot|Twitterbot|cXensebot|smtbot|bnf\.fr_bot|A6\-Indexer|ADmantX|Facebot|Twitterbot|OrangeBot|memorybot|AdvBot|MegaIndex|SemanticScholarBot|ltx71|nerdybot|xovibot|BUbiNG|Qwantify|archive\.org_bot|Applebot|TweetmemeBot|crawler4j|findxbot|SemrushBot|yoozBot|lipperhey|y!j\-asr|Domain Re\-Animator Bot|AddThis|YisouSpider|BLEXBot|YandexBot|SurdotlyBot|AwarioRssBot|FeedlyBot|Barkrowler|Gluten Free Crawler|Cliqzbot)";
	 
		return preg_match("/{$TestRegexPattern}/", $test);
	 
	}

/***********************************************************************************/

?>