<?php
    namespace MattyLabs\XMLAdapter;

    use HTMLPurifier;
    use MattyLabs\XMLAdapter\Common\BaseConfig;
    use MattyLabs\XMLAdapter\Logger\SimpleLogger;
    use MattyLabs\XMLAdapter\Helpers\Arr;
    use MattyLabs\XMLAdapter\Helpers\HelperFunctions as hf;
    use HTMLPurifier_Config;


/**
 * Config: initialises everything and picks up various settings. Settings are for the most part derived from the url
 * querystring passed on creation but for reasons of backwards compatibility are also stored in several files.
 * - Creates a Config instance for the life of the page
 * - Creates a SimpleLogger instance
 * - Loads and parses URL >> config.url (if NOT running within a web page you will need to pass in the SCRIPT_FILENAME)
 * - Optionally loads and cleans (HTMLPurifier) $_REQUEST >> config.url.qs_array
 * and saves everything to instance of Config (usually called $config) for the life of the page.
 *
 */

    class Config extends BaseConfig {

        /**
         * @var array
         */
        protected $config = array();

        /**
         * @var SimpleLogger
         */
        protected $log;

        /**
         * Loads the config array with site settings
         *
         * @param array $parameters
         */
        public function add(array $parameters = array()){

            $this->config = array_replace_recursive($this->config, $parameters);

        }


        /**
         * Set an individual $config[$key] = $val pair
         * or using dot notation
         *
         * @param $key
         * @param $val
         */
        public function set($key, $val){

            Arr::set($this->config, $key, $val);

        }


        /**
         * Retrieve a particular value back from the config array
         * use dot notation if required
         * @param $key
         * @param null $default
         * @return mixed|null
         */
        public function get($key, $default = null)
        {
            $val = '';

            if( strpos($key, '.') === false ){

                //$val = $this->config[$key] ?? $default;
                $val = @$this->config[$key] ?: $default;


            } else {

                //$val =  Arr::val($this->config, $key) ?? $default;
                $val = Arr::val($this->config, $key);

            }

            return $val;
        }

        /**
         * @param $key
         * @return mixed|string
         */
        public function find($key){
            return Arr::search($this->config, $key)['value'];
        }


        /**
         *  Sets as many $config->get('params') as possible
         *  - also attempts tp load and clean the QueryString if available
         *  - sets some useful / vital ENVironment vars too :)
         *
         * @param null $querystring
         * @param null $params
         */
        public function init($querystring = null, $params = null)
        {
            $this->log =  new Logger\SimpleLogger();
            $log = $this->log;
              /* VERSION: 1.~ for PHP v5.6+. 2.~ for PHP v7+ */ 
            /* - 1.0.16: Fix for setting dbm_elastic_cloud */ 
            $log::info("Initialising Config: [XMLAdapter v1.0.15]", get_class());

        // Load $params
            if($params){
                $log::info("setting params via Config:init()", get_class());
                self::set('params', $params);
            }

        // Can we find this Server IP?
            if( empty(self::get('params.this_server_ip')) ){
                self::set('params.this_server_ip', $this->getServerIP());
            }

        /** QUERYSTRING: Attempt to parse either the passed url or the web page's url
        *   e.g. "https://mattyp/mattylabs/page/results/?DEBUG=on&DBM=mattylabs-main&SF1=keyword&ST1=ref_no";
        */
            if($querystring) {

                $log::info("QueryString passed via param", get_class());
                if(strpos($querystring, '?')){

                    self::set('url.path', explode('?', $querystring)[0]) ;
                    self::set('url.qs', urldecode(explode('?', $querystring)[1]));

                }else{

                    self::set('url.qs', urldecode($querystring));

                }

            }else {

                $log::info("QueryString got from page", get_class());
                self::set('url.path', urldecode($_SERVER['SCRIPT_NAME']));
                self::set('url.qs', urldecode($_SERVER['QUERY_STRING']));

            }

            if( empty(self::get('url.qs')) ){

                $log::error("Querystring is empty. Nothing to do!.", get_class());
                $this->throwError();

            }else{

                $log::info("loading HTML QueryString >> 'config.url.qs_array'", get_class());
                self::set('url.qs_array', array_change_key_case(hf::parseQueryStr(self::get('url.qs'))));

            }

            // DBM: We need this to find the DB config file
            //print_r($this->config);
            $dbm = self::get('url.qs_array.dbm') ?: self::get('params.dbm') ?: '';
            $dbm = strtolower($dbm);
            if( empty($dbm) ){

                $log::error("Error. DBM not found.", get_class());
                $this->throwError();

            }else{

                $log::info("DBM::[$dbm]", get_class());
                self::set('params.dbm', $dbm);
                self::set('params.sitename', explode('-', $dbm)[0]);
                $log::info("Sitename::[" . self::get('params.sitename') . "]", get_class());

            }

        // SITE ROOT: we need this to locate the site config files [php.server.defaults.php, site_ini.php and site-db-DBM.inc]
            //$script_filename = self::get('params.script_filename') ?? $_SERVER['SCRIPT_FILENAME'] ?? '';
            if(!empty(self::get('params.script_filename'))){
                $script_filename = self::get('params.script_filename');
            }elseif(isset($_SERVER['SCRIPT_FILENAME'])){
                $script_filename = strtolower($_SERVER['SCRIPT_FILENAME']);
            }else{
                $script_filename = '';
            }

            if(!empty($script_filename)){

                $script_filename = str_replace( "\\", "/", $script_filename);
                $sitename = strtolower(self::get('params.sitename'));
                if( preg_match("/$sitename/i", $script_filename) ){
                    $site_root = explode($sitename, $script_filename)[0] . '' . $sitename;
                }else{
                    $site_root = self::get('params.www_root') . "/$sitename";
                }
                if( empty(self::get('params.site_root')) ){
                    self::set('params.site_root', $site_root );
                }


            } else {

                $log::error("Error. Cannot locate script path.", get_class());
                $this->throwError();

            }

            if(self::get('params.htmlpurifier_purify') == true) {
                $this->purifyQueryString( self::get('url.qs_array'));
            }
            //print_r($this->config);die;
        }


        /**
         *  Returns the current Config object
         *
         * @param bool $sort
         * @return array
         */
        public function getConfigArray($sort = true)
        {
            if($sort){
                ksort($this->config);
            }
            return $this->config;
        }

        /**
         *  Returns URLencoded QueryString ready for use
         *
         * @return string
         */
        public function getQS()
        {
            $qs_arr = Arr::filterBlanks($this->config['url.qs_array']);
            return http_build_query($qs_arr);
        }

        private function getServerIP(){

            //$ip = $_SERVER['LOCAL_ADDR'] ?? $_SERVER['SERVER_ADDR'] ?? '';
            $ip = @$_SERVER['LOCAL_ADDR'] ?: @$_SERVER['SERVER_ADDR'] ?: '';

            if(empty($ip)){
                $host= gethostname();
                $ip = gethostbyname($host);
            }

            return $ip;

        }

        /**
         * @param $qs_array
         */
        protected function purifyQueryString($qs_array)
        {
            $log= $this->log;
            $log::info('HTMLPurify', get_class());
            if(class_exists('HTMLPurifier_Config')){

                $log::info('HTMLPurifying:keys', get_class());
                $config = HTMLPurifier_Config::createDefault();
                $purifier = new HTMLPurifier($config);
                $log::info("HTMLPurifier:[v.$config->version]", get_class());

                // might just cause problems purifying passwords See Site_ini: expects simple array of excluded keys e.g. ['ds', '_password']
                $restricted_keys = self::get('params.htmlpurifier_exclude_keys') ?: [];
                $restricted_keys = array_fill_keys($restricted_keys, null);
                $filtered_keys = array_diff_key($qs_array, $restricted_keys);

                foreach($filtered_keys as $key => $val){
                    if( is_array($val)){
                        // You've got 2 url params with the same name like "ST1=plip&ST1=plop"
                        $log::error("Please check the urlQueryString params for duplicates.", get_class());
                        $this->throwError();

                    }
                    $filtered_keys[$key] = $purifier->purify($val) ;
                }
                self::set('url.qs_array', $filtered_keys);

            }else{
                $log::info('HTMLPurify: installed but not configured.', get_class());
            }
        }

        /**
         * Set debug=on to return the $log
         * $array = ['error_msg' => '', 'error_code' => [0|1], 'rid' => '', 'data => ''];
         * @param $array
         */
        protected function throwError($array = null){

            $log = $this->log;
            if($array){
                $log::error(json_encode($array, JSON_PRETTY_PRINT), get_class() );
            }

            echo $log::dump_to_string();

            exit;

        }
    }
