<?php
    namespace MattyLabs\XMLAdapter\Helpers;

    use DateTime;
    use MattyLabs\XMLAdapter\Helpers\Arr as Arr;

/**
 *  What a lot of Emperor's new clothes this class stuff is - apparently if I do something simple a lot of times like padding digits I have to make this a trait of method of an extended sub-something so that no-one can find out what its doing!
 *  or lets just dump the simple stuff here :)
 *
 *
 */
    class HelperFunctions{

        /**
         * @param $s
         * @param $n
         * @return string
         */
        public static function padz($s, $n){

            $s = str_pad($s, $n, "0", STR_PAD_LEFT);
            return $s;

        }

        /**
         *
         *  New & improved! Returns Date or DateTime in the specified format - combination of DisplayDate and rdate
         *  e.g. displayDate() > 'yyyymmdd' - today
         *  e.g. displayDate('', 20) > 'yyyymmdd' + 20 days
         *  e.g. displayDate("l, jS F Y", 0, '20250101') > format specific date
         *  e.g. displayDate('l, jS F Y') - 'Monday, 8th April 2013'
         *
         * @param string $date
         * @param string $format
         * @param int $offset
         * @return mixed
         */
        public static function displayDate($format = 'Ymd',  $offset = 0, string $date = null) {

            date_default_timezone_set('Europe/London');
            if(!empty($date)){

                if( self::isDate($date) ){

                    $oDate = new DateTime($date);
                    if(!empty($offset)){
                        $oDate->modify($offset.' day');
                    }

                    return $oDate->format($format);

                } else {
                    //it's not a proper date
                    return $date;
                }

            }else{

                $date = new DateTime('NOW');
                if(!empty($offset)){
                    $date->modify($offset.' day');
                }

                return $date->format($format);

            }
        }


        /**
         * @param $value
         * @return bool
         */
        public static function isDate($value) {

            if (!$value) {
                return false;
            } else {
                $date = date_parse($value);
                if($date['error_count'] == 0 && $date['warning_count'] == 0){
                    return checkdate($date['month'], $date['day'], $date['year']);
                } else {
                    return false;
                }
            }

        }


        /**
         * @param $date
         * @param string $format
         * @return bool
         */
        public static function validDates($date, $format = 'Ymd'){

            $ret = true;
            $parts = explode(':', $date);
            foreach($parts as $p){

                if (($timestamp = strtotime($p)) === false) {
                    $ret = false;
                }

            }

            return $ret;

        }

        /**
         *
         *
         * @param $txt
         * @param $StartTag
         * @param $StopTag
         * @param boolean $tags
         * @param string $FieldDelimiter
         * @return false|string
         */
        public static function getDelimStr($txt, $StartTag, $StopTag, $tags, $FieldDelimiter = '|'){

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

        /**
         * returns an XML style attribute value e.g.['something' from <field attr1='else' attr2="something">]
         * @param $txt
         * @param $attr
         * @return false|string|void
         */
        public static function getAttribute($txt, $attr){

            $txt = preg_replace('@\s+([=])\s+@', '$1', $txt);

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

        /**
         * @param $str
         * @return array
         */
        public static function parseQueryStr($str) {
            # result array
            $arr = array();

            if( strpos($str, '?')){
                $str = explode('?', $str)[1];
            }

            # split on outer delimiter
            $pairs = explode('&', $str);

            # loop through each pair
            foreach ($pairs as $pair) {

                # split into name and value
                if( strpos($pair, "=") ){
                    list($name,$value) = explode('=', $pair, 2);
                    $arr[strtolower($name)] = $value;   //ToDo what is value if empty?
                }

            }

            # return result array
            return $arr;
        }

        /**
         * @param $rurl
         * @param array|null $options
         * @return false|string
         */
        public static function getRest($rurl, array $options = Null){

                // Defaults
                $options['method'] 			= @$options['method'] ?: 'GET';
                $options['header'] 			= @$options['header'] ?: 'Content-Type: text/html; charset=UTF-8';
                $options['timeout'] 		= @$options['timeout'] ?: 1;
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
                    ]
                ];

                $context = stream_context_create($opts);
                $response = @file_get_contents($rurl, false, $context);

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

                return $response;

        }

        /**
         * If you are accessing Elasticsearch in the cloud you will need to supply your username:password as a Base64 encoded string
         *  N.B. Quicker to get the version from the client than via REST call to host
         *
         * @param array $hosts
         * @param string $key
         * @param string $base64auth
         * @return string
         */
        public static function getElasticVersion(array $hosts, $key = 'version.number', string $base64auth = ""){

            $h = reset($hosts);
            $h = str_replace(['http://','https://',':9200'], '', $h);

            if( !empty($base64auth) ){

               $options = [
                  'header' => ["Authorization: Basic $base64auth"]
               ];
                $v = self::getRest("http://$h:9200", $options);
                return Arr::val(json_decode($v, true), $key);

            } else {

               return '7.14.99';

            }



        }

        /**
         * @param $xml
         * @param $name
         * @param false $tags
         * @param string $delimiter
         * @return array|mixed|string|string[]
         */
        public static function xmlfield($xml, $name, $tags = false, $delimiter = '' ){

            $delimiter = $delimiter ?: chr(29);
            $name = str_replace("\\", "\/", $name);
            $name = preg_quote($name); // this escapes things that need escaping :)
            $parts = explode("/", $name);
            foreach($parts as $p){

                $ptrn = "@<" . $p . "(?:>| [\s\S]+?>)(|[\s\S]+?)(?:<\/" . $p . ">)@";
                $xml = ReturnPtrnMatches($xml, $ptrn, $tags, $delimiter, $p);

            }

            if($tags == false){
                $xml = str_replace('&#60;', '<', $xml);
                $xml = str_replace('&#62;', '>', $xml);
                $xml = str_replace('&lt;', '<', $xml);
                $xml = str_replace('&gt;', '>', $xml);
                $xml = str_replace ('<![CDATA[', '',$xml);
                $xml = str_replace(']]>', '', $xml);
            }

            return $xml;
        }


        /**
         * @param $xml
         * @param $pattern
         * @param $tags
         * @param $delimiter
         * @param $fn
         * @return false|string
         */
        private function ReturnPtrnMatches($xml, $pattern, $tags, $delimiter, $fn){

            preg_match_all($pattern, $xml, $matches, PREG_PATTERN_ORDER);

            if(count($matches[0]) == 0){
                // try adding 'fv_'
                $pattern = str_replace($fn, 'fv_'.$fn, $pattern);
                preg_match_all($pattern, $xml, $matches, PREG_PATTERN_ORDER);
            }
            // N.B. $matches is an array[0,1] containing an array of all the matches
            //printHash($matches[0]); // returns all matches with their tags
            //printHash($matches[1]); // returns all matches withOUT tags
            $returnStr = "";
            if($tags == true){
                foreach($matches[0] as $key=>$value){
                    $returnStr .= $value.$delimiter;
                }
            }else{
                foreach($matches[1] as $key=>$value){
                    $returnStr .= $value.$delimiter;
                }
            }
            //echo $returnStr;
            if(substr($returnStr, -1) == $delimiter){
                //remove trailing pipe
                $returnStr = substr($returnStr, 0, strlen($returnStr)-1);
            }

            return $returnStr;
        }

    }
