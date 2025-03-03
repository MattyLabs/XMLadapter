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
         * @return string
         * 
         * N.B. For backwards compatibility with v5.6 $options is a nullable array 
         * [?array $options (v7,1+) instead of $options = null (v5.6)]
         */
        public static function get_rest($rurl, $body, array $options)
        {

            $ct = @$options['connect_timeout'] ?: 1000; // N.B. send "0" for unlimited timeout
		    $to = @$options['timeout'] ?: 1000;
		    $rq = @$options['request_type'] ?: "GET";	// GET|POST
            //print_r($body); die;

            if( !empty($options['header']) ){
				
                $header = $options['header'];
                
            }else{
            
                $header = [
                    //"Authorization: Basic {$options['auth']}",
                    "Content-Type: application/json",
                    "Connection: Close"
                ];
                
                if(!empty($options['auth'])){
                    $auth = base64_encode($options['auth']);
                    array_unshift($header, "Authorization: Basic $auth");
                }
                
            }
            
            $curl_options = [
    
                CURLOPT_CUSTOMREQUEST => "$rq",
			    CURLOPT_POSTFIELDS => "$body",
                CURLOPT_URL	=> $rurl,
                CURLOPT_HEADER => false,	// 0 removes headers from response leaving just the JSON
                CURLOPT_HTTPHEADER => $header,
                CURLOPT_CONNECTTIMEOUT_MS => $ct,
				CURLOPT_TIMEOUT_MS => $to,
                CURLOPT_RETURNTRANSFER => true, // sets curl_exec() to return request body e.g. as $response
                CURLOPT_FAILONERROR => false,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_ENCODING => '',	
                
                CURLOPT_VERBOSE => false,
                //CURLOPT_CAINFO => "",
                CURLOPT_SSL_VERIFYPEER => false,	// 0 for testing :)
                CURLOPT_SSL_VERIFYHOST => false,
                //CURLOPT_STDERR => ($f = fopen("d:/temp/curl-client.txt", "a")),
                
            ];
            
            //print_r($curl_options); //die;
            $curl = curl_init();
            curl_setopt_array($curl, $curl_options);	
            $response = curl_exec($curl);
            //print_r("[$response]");
            $info = curl_getinfo($curl);
            //$x = print_r($info, true); echo "<!-- $x -->\r\n"; die;

            if( $info['http_code'] == 200 ){
                // return $response
                curl_close($curl);
                return $response;
            }
                
            if( $info['http_code'] >= 400 ){
                // return $response // make sure CURLOPT_FAILONERROR = false so we can read the Elastic API response
                curl_close($curl);
                return $response;
                
            }
           
            if (curl_errno($curl)){
                
                $cerr = curl_errno($curl);
                $x = print_r($info, true); //echo "<!-- cUrl ERR ($cerr): getinfo: $x --\r\n"; //die;
                
                if(curl_errno($curl) == '28'){
                    $emsg = "REST request ($cerr) timed out. ct[$ct] to[$to]";
                }elseif(curl_errno($curl) == '22'){
                    $emsg = "BAD REST request ($cerr) (http:400)";
                }else{
                    $emsg = "CURL Err $x";
                }

                $error_array = [
                    'errordetails' => [
                        'error_rest'    => true,
                        'errorcode'     => $cerr,
                        'errormessage'  => "CURL Error: $emsg",
                    ]
                ];
               
                $response = json_encode($error_array);
                curl_close($curl);
                return $response;
                
            } else {

                if( $info['http_code'] == 0 or $info['size_download'] < 1){

                    $emsg = "No data received: [{$info['url']}]";
                    $error_array = [
                        'errordetails' => [
                            'error_rest'    => true,
                            'errorcode'     => curl_errno($curl),
                            'errormessage'  => "Unknown Error: $emsg",
                        ]
                    ];
                   
                    $response = json_encode($error_array); 
                    curl_close($curl);
                    return $response;
                }
              //$skip = intval(curl_getinfo($curl, CURLINFO_HEADER_SIZE)); 
              //$responseHeader = substr($response, 0, $skip);
              //$response = substr($response,$skip);

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
                $xml = HelperFunctions::ReturnPtrnMatches($xml, $ptrn, $tags, $delimiter, $p);

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
         * @param $explain array
         *
         */
        public static function json_error(&$explain)
        {
            
            $explain = '';
        
            switch (json_last_error()) {
                
                case JSON_ERROR_NONE:
                    $explain = 'No JSON errors';
                    return false;
                break;
                
                case JSON_ERROR_DEPTH:
                    $explain = ' - Maximum stack depth exceeded';
                    return true;
                break;
                
                case JSON_ERROR_STATE_MISMATCH:
                    $explain =  ' - Underflow or the modes mismatch';
                    return true;
                break;
                
                case JSON_ERROR_CTRL_CHAR:
                    $explain =  ' - Unexpected control character found';
                    return true;
                break;
                
                case JSON_ERROR_SYNTAX:
                    $explain =  ' - Syntax error, malformed JSON';
                    return true;
                break;
                
                case JSON_ERROR_UTF8:
                    $explain =  ' - Malformed UTF-8 characters, possibly incorrectly encoded';
                    return true;
                break;
                
                default:
                    $explain =  ' - Unknown error';
                    return true;
                break;
                
            }

        }

        /**
         * @param $xml
         * @param $pattern
         * @param $tags
         * @param $delimiter
         * @param $fn
         * @return false|string
         */
        private static function ReturnPtrnMatches($xml, $pattern, $tags, $delimiter, $fn){

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
