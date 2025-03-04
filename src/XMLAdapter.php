<?php
    namespace MattyLabs\XMLAdapter;

    use MattyLabs\XMLAdapter\Common\ResultsInfoXML;
    use MattyLabs\XMLAdapter\Common\ResultsInfoJSON;
    use MattyLabs\XMLAdapter\Common\ResultsInfoJSON2;
    use MattyLabs\XMLAdapter\Common\ResultsInfoDoc;
    use MattyLabs\XMLAdapter\Common\ResultsInfoHTML;
    use MattyLabs\XMLAdapter\Exceptions\GeneralException;
    use MattyLabs\XMLAdapter\Helpers\SearchParser;
    use MattyLabs\XMLAdapter\Helpers\Arr;
    use MattyLabs\XMLAdapter\Helpers\HelperFunctions as hf;



    /**
 *  XMLAdapter Class
  *  Wrapper class for converting HTML querystring name-value pairs into Elasticsearch JSON arrays, submitting the search and
  *  returning search resultset as either JSON, XML or HTML. ToDo: XML1 ONIX3
  *
  *  dicis olet dico arridet:
  *
 */
    class XMLAdapter{

        /**
         * @var Config|false
         */
        protected Config $config;

        /**
         * @var Logger\SimpleLogger
         */
        protected Logger\SimpleLogger $log;


        /**
         * Local var for SearchParser
         * @var
         */
        protected Helpers\SearchParser $query;

        /**
         * Elasticsearch Client
         * @var
         */
        protected $client;


        /**
         * XMLAdapter: takes a standard HTML QueryString and returns Elasticsearch resultset
         * @param string|null $url
         * @param array|null $params
         */
        public function __construct(string $url = null, array $params = null)
        {

            $this->log = new Logger\SimpleLogger();

            $this->config = Config::instance();

            $this->config->init($url, $params);

        }


        /**
         * Output formats = [array()|xml|json]
         * @param $format string
         */
        public function search($format = null)
        {

        /*
         *  setHosts
         *  - read in server.defaults from $config
         *  - read in DBM
         *  - sniff available hosts
         */
            $this->setHosts();


        /*
         *  SearchParser
         *  - parses $url querystring and generates the query_params
         *
         */
            $this->query = new SearchParser();


            //print_r($this->query);die;
            $host_str =  $this->config->get('params.active_elastic_host');
            $this->log::info("active host: [$host_str]", get_class($this));
            $this->log::info("elastic_version: [{$this->config->get('params.elastic_version')}]", get_class($this));

            $this->log::info("search_query: [{$this->query->get('search_query')}]", get_class($this));
            $this->log::info("search_terms: [{$this->query->get('search_terms')}]", get_class($this));
            $this->log::info("search_fields: [{$this->query->get('search_fields')}]", get_class($this));

            if(!empty($this->config->get('params.idx'))){
                $idx = $this->config->get('params.idx');
                $this->log::info("searching index: [$idx] (params.idx)", get_class($this));
            }else{
                $idx = $this->config->get('dbm.dbm_index');
                $this->log::info("searching index: [$idx] (dbm.dbm_index)", get_class($this));
            }


        // Compile the final query..ToDo Check that this works properly for BDS [see get_xml() and xmla-api.php
            $query = [
                'index' => $idx,
                'preference' =>  ($this->config->get('dbm.shard_preference')) ?: '',
                'body' => [
                    'from' => $this->query->get('from'),
                    'size' => $this->query->get('size'),
                    '_source' => [
                        'includes' => $this->query->get('field_list_array')
                    ],
                    //'min_score'   => 10,  // removes tail from resultset
                    'explain' => false,
                    'profile' => false,
					'version' => true,
					//'seq_no_primary_term' => true,    // version 7+ only
                    'track_total_hits' => ($this->config->get('dbm.track_total_hits')) ?: true,
                    'query' => [
                        'bool' => [
                            'must' => $this->query->getMust(),
                            'should' => $this->query->getShould(),
                            'filter' => $this->query->getFilters(),
                        ]
                    ],
                    'aggs' => $this->query->getAggs(),
                    'collapse' => $this->query->getCollapse(),
                    'highlight' => $this->query->getHighlight(),
                    'rescore' => $this->query->getRescore(),
                    'sort' => $this->query->getSort(),
                    'suggest' => $this->query->getSuggest(),


                ]

            ];

            

        // We need to cautious about deleting blanks e.g. don't remove 'query' from should clause just because it is empty
            foreach(['aggs', 'collapse', 'highlight', 'rescore', 'sort', 'suggest'] as $key){
                if( empty($query['body'][$key])){
                    unset($query['body'][$key]);
                }
            }

            if( empty($query['body']['query']['bool']['must']) and !empty($query['body']['query']['bool']['filter']) ){
                unset($query['body']['query']['bool']['must']);
                unset($query['body']['query']['bool']['should']);
            }

        // Version compatibility checks:
            // AGGS
            if (intval($this->config->get('params.elastic_version')) >= 7 ){

                if(isset($query['body']['aggs'])){
                    $this->log::info("Version adjustment:: setting Aggs key '_key' in place of '_term' [v" . $this->config->get('params.elastic_version') .']', get_class($this));
                    $query['body']['aggs'] = Arr::replaceKeys('_term', '_key', $query['body']['aggs']);

                    $query['body']['aggs'] = Arr::replaceKeys('interval', 'calendar_interval', $query['body']['aggs']);

                }

            }else{

                if(isset($query['body']['aggs'])){
                    $this->log::info("Version adjustment:: setting Aggs key '_term' in place of '_key' [v" . $this->config->get('params.elastic_version') .']', get_class($this));
                    $query['body']['aggs'] = Arr::replaceKeys('_key', '_term', $query['body']['aggs']);

                    $query['body']['aggs'] = Arr::replaceKeys('calendar_interval', 'interval', $query['body']['aggs']);

                }

            }

        // type & track_total_hits & skip_duplicates
            if (intval($this->config->get('params.elastic_version')) < 7) {

                $this->log::info("Version adjustment:: setting 'type=doc' [v" . $this->config->get('params.elastic_version') .']', get_class($this));
                Arr::set($query, 'type', $this->config->get('dbm.dbm_type'));

                if (Arr::searchKeys($query, 'track_total_hits')) {
                    $this->log::info("Version adjustment:: deleting key track_total_hits:: track_total_hits", get_class($this));
                    Arr::del($query, "body.track_total_hits");
                }

                if( !empty($query['suggest']) ){
                    if (Arr::searchKeys($query['suggest'], 'skip_duplicates')) {
                        $this->log::info("Version adjustment:: deleting key skip_duplicates", get_class($this));
                        Arr::del($query['suggest'], "skip_duplicates");
                    }
                }


            }

        // LIKE Search: completely replaces the 'body.query'
            if (!empty($this->query->get('like'))) {

                Arr::set($query, 'body.query', $this->query->getLike());

            }

        // MOQ: getMyOwnQuery() - completely replaces the 'body.query'
            if (!empty($this->query->getMyOwnQuery())) {

                Arr::set($query, 'body.query', $this->query->getMyOwnQuery());

            }

        // Check we actually have something to search on!
            $query_test = Arr::filterBlanks($query);
            //print_r($query_test);//die;
            if(empty($query_test['body']['query']) and empty($this->query->get('sug')) ){

                $this->log::error('There\'s no query to run! Check your search syntax.', get_class($this));
                return;

            }

        // NOBOOL parameter: unset selected parts of the query
            if(!empty($this->config->get('url.qs_array.nobool'))){

                $nobool = $this->config->get('url.qs_array.nobool');
                if( preg_match('/must/', $nobool) ){
				
                    $this->log::info("NOBOOL: Setting [must] to null", get_class($this));
                    if( isset($query['body']['query']['bool']['must']) ){
                        unset($query['body']['query']['bool']['must']);
                    }
                    
                }

                if( preg_match('/should/', $nobool) ){
				
                    $this->log::info("NOBOOL: Setting [should] to null", get_class($this));
                    if( isset($query['body']['query']['bool']['should']) ){
                        unset($query['body']['query']['bool']['should']);
                    }
                    
                }

                if( preg_match('/filter/', $nobool) ){
				
                    $this->log::info("NOBOOL: Setting [filter] to null", get_class($this));
                    if( isset($query['body']['query']['bool']['filter']) ){
                        unset($query['body']['query']['bool']['filter']);
                    }
                    
                }
                
                if( preg_match('/highlight/', $nobool) ){
				
                    $this->log::info("NOBOOL: Setting [highlight] to null", get_class($this));
                    if( isset($query['body']['highlight']) ){
                        unset($query['body']['highlight']);
                    }
                    
                }

                if( preg_match('/aggs/', $nobool) ){
				
                    $this->log::info("NOBOOL: Setting [aggs] to null", get_class($this));
                    if( isset($query['body']['aggs']) ){
                        unset($query['body']['aggs']);
                    }
                    
                }
               
                if( preg_match('/rescore/', $nobool) ){
				
                    $this->log::info("NOBOOL: Setting [rescore] to null", get_class($this));
                    if( isset($query['body']['rescore']) ){
                        unset($query['body']['rescore']);
                    }
                    
                }

                if( preg_match('/suggest/', $nobool) ){
				
                    $this->log::info("NOBOOL: Setting [suggest] to null", get_class($this));
                    if( isset($query['body']['suggest']) ){
                        unset($query['body']['suggest']);
                    }
                    
                }

                if( preg_match('/query/', $nobool) ){
				
                    $this->log::info("NOBOOL: Setting [query] to null", get_class($this));
                    if( isset($query['body']['query']) ){
                        unset($query['body']['query']);
                    }
                    
                }


            }

        //print_r($query);//die;

        // DO THE SEARCH
            //$query = Arr::filterBlanks($query);
            $this->log::info('Running search query..');
            $this->log::time('SEARCH');

            $host = $this->config->get('params.active_elastic_host');
            $options = ['timeout' => 15000];
            $rurl = "$host/{$query['index']}/_search/";
            $json = hf::get_rest($rurl, json_encode($query['body']), $options);
            $results = json_decode($json, true);

        //check for REST API error (response not proper JSON)
            if(hf::json_error($explain)){
                $this->log::info("Error Search: $explain", get_class($this));
                return;
            }
                
        // check for CURL error (url wrong or timed out)
            if( @$results['errordetails']['error_rest'] == true){
                $this->log::info("Error CURL: ({$results['errordetails']['errorcode']}) {$results['errordetails']['errormessage']} ", get_class($this));
                return;
            }

            if( isset($results['hits']['total']['value']) ){
                $docs = $results['hits']['total']['value'];
            }else{
                $docs = $results['hits']['total'];
            }

            $this->log::info("..search query completed. Docs found [$docs]");
            $this->log::timeEnd('SEARCH');
            //print_r($results);//die;

            $this->logQueryDetails($query, 'Search Query');

        // DISPLAY THE RESULTS
            $this->log::info("ResultsInfo - start..", get_class($this));

            $format = $format ?: 'xml';
            $format = strtolower($format);

            if( $format == 'raw' ){

                $this->log::info("ResultsInfo - PHP.", get_class($this));
                $x = print_r($results, true);
                return $x; //$results; // todo - this is for testing only

            }elseif ($format == 'xml'){

                $res = new ResultsInfoXML($results, $this->query->getQueryParams());
                $this->log::info("ResultsInfo - XML.", get_class($this));
                return $res->create();

            }elseif ( $format == 'json' ){
                // this tracks Elastic output
                //$res = json_encode($results, JSON_PRETTY_PRINT);
                $res = new ResultsInfoJSON($results, $this->query->getQueryParams());
                $this->log::info("ResultsInfo - JSON.", get_class($this));
                return $res->create();

            }elseif ( $format == 'json2' ){
                // this tracks XML2
                $res = new ResultsInfoJSON2($results, $this->query->getQueryParams());
                $this->log::info("ResultsInfo - JSON2.", get_class($this));
                return $res->create();

            }elseif ( $format == 'doc' ){
                // i.e. a XML dump data file
                $res = new ResultsInfoDoc($results, $this->query->getQueryParams());
                $this->log::info("ResultsInfo - Doc.", get_class($this));
                return $res->create();

            }elseif ( $format == 'html' ){
                // i.e. a nightmare
                $res = new ResultsInfoHTML($results, $this->query->getQueryParams());
                $this->log::info("ResultsInfo - HTML.", get_class($this));
                return $res->create();

            }


        }

              /**
         *  setHosts()
         *  - You can pass in the default Elasticsearch client_config when creating a new XMLAdapter()
         *  - - if you want to set more than the hosts e.g. Authentication credentials etc. use this route
         *  - We only really need the hosts which can also be set (along with Cloud credentials) in the DBM
         *  - - 'dbm_elastic_hosts'
         *  - - 'dbm_elastic_cloud'
         *  v8.2 seems to require you set the port too!
         *
         */
        protected function setHosts()
        {

            //print_r($this->config);//die;
             $this->log::info("Read in the DBM", get_class($this));
             if (!empty($this->config->get('params.dbm'))) {

                $path = $this->config->get('params.site_root') . "" . $this->config->get('sitename') . "/include/config/{$this->config->get('params.dbm')}-dbm.inc";
                $this->log::info("Loading DBM >> config.dbm: [$path]", get_class($this));
                if(file_exists($path)){

                    $arr = require($path);
                    $this->config->set('dbm', $arr);
                    $this->log::info("DBM index: [{$this->config->get('dbm.dbm_index')}]", get_class($this));

                }else{

                    $this->log::error("Failed to load DBM [{$this->config->get('params.dbm')}]. Please check.", get_class($this));
                    return;
                }

            } else {

                $this->log::error("No DBM! You must set '&DBM=siteName-indexName'", get_class($this));
                return;

            }

            //print_r($this->config);die;

            $this->log::info("setHosts()", get_class($this));
            //$ecc = print_r($this->config->get('params.elastic_client_config'), true);  //$this->log::info("gak: [$ecc]", get_class($this));
            
            // N.B. this is the Server default and can be set initially in php.server.defaults::$params['elastic_client_config']
            if(!empty($this->config->get('params.elastic_client_config')) ){
                $this->log::info("..loading elastic_client_config (from php.server.defaults).", get_class($this));
                $client_config = $this->config->get('params.elastic_client_config');
            }else{
                $client_config = [
                    'hosts' => []
                ];
            }
        
            //$x = print_r($client_config, true); echo "<!-- 1: $x -->\r\n";
            // always override the server.default hosts with the specific dbm hosts
            if(!empty( $this->config->get('dbm.dbm_elastic_hosts'))){
                
                $string = implode('|', $this->config->get('dbm.dbm_elastic_hosts'));
                $this->log::info("..setting dbm_elastic_hosts (from DBM) [$string]", get_class($this));
                $client_config['hosts'] = $this->config->get('dbm.dbm_elastic_hosts');
            }
            
            // if still empty try the local server
            if(empty($client_config['hosts'])){

                $client_config['hosts'] = [
                    '127.0.0.1', // fallback
                    $this->config->get('params.this_server_ip'),    // \Config::setServerIP() [N.B.VPN may make LOCAL_ADDR inaccurate]
                ];
                $string = implode('|', $client_config['hosts']);
                $this->log::info("..setting fallback hosts [$string]", get_class($this));
                
            }
            
            // Clean up hosts from Server Defaults & DBM etc.
            $this->check_hosts_port($client_config);
            
            // Sniff for available nodes before doing the search. Default: 'dbm_sniff_hosts' = 'on'
            $sniff = @$this->config->get('params.sniff_hosts') ?: 'on';
            if(!empty($this->config->get('dbm.dbm_sniff_hosts') )){
                $sniff = $this->config->get('dbm.dbm_sniff_hosts') ;
            }
            if($sniff === 'on'){
                $this->check_hosts_avail($client_config); 
            }
            
            // Elastic v8.2.2 seems to require the port whereas previous versions did not!
            $this->check_hosts_port($client_config);
   
            if( !empty($client_config['hosts']) ){

                $hosts_string = implode('|', $client_config['hosts']);
                $this->log::info("Using available hosts: [$hosts_string]", get_class($this));
                //print_r($client_config);
                //print_r($this->config->get('dbm'));

            } else {

                $this->log::error("Unable to set Elastic hosts.", get_class($this));

            }

            if( !empty($this->config->get('dbm.dbm_elastic_cloud')) ){

                if( !empty( $this->config->get('dbm.dbm_elastic_cloud.elasticCloudId') )){
                $client_config['elasticCloudId'] = $this->config->get('dbm.dbm_elastic_cloud.elasticCloudId');
                }
               
                $client_config['basicAuthentication'] = [
                    $this->config->get('dbm.dbm_elastic_cloud.username'),
                    $this->config->get('dbm.dbm_elastic_cloud.password')
                ];

            }
            
            $this->config->set('params.active_elastic_config', $client_config);
            $this->config->set('params.active_elastic_host', reset($client_config['hosts']));
           
        }

        public function get_hosts()
        {

            $this->setHosts();
            
            $ret = [
                'active_host' => $this->config->get('params.active_elastic_host'),
                'active_config' => $this->config->get('params.active_elastic_config'),
                'active_version' => $this->config->get('params.elastic_version')
            ];

            return $ret;

        }

        /**
         * @param $hosts - checks cluster for available hosts and removes any that are 'down'
         * @return void
         */
        protected function check_hosts_avail(&$cfg)
        {
            
			$auth = '';
            $hosts = $cfg['hosts'];
			$avail_hosts = array();
            
            $this->log::info("Starting sniffer", get_class($this));
            if( !empty($this->config->get('params.elastic_client_config.basicAuthentication')) ){
                $auth = ( implode(':', $this->config->get('params.elastic_client_config.basicAuthentication')) );
                //$this->log::info("..using credentials: [$auth]", get_class($this));
            }

            foreach($hosts as $h){
            
                $arr = parse_url($h);
                $prot = @$arr['scheme'] ?: @$this->config->get('params.default_protocol') ?: "http";
                $host = @$arr['host'] ?: @$arr['path'] ?: '';
                $port = @$arr['port'] ?: 9200;
                
                if(!empty($auth)){
                    $rurl = "$prot://$auth@$host:$port/_nodes/_all/http";	
                } else {
                    $rurl = "$prot://$host:$port/_nodes/_all/http";	
                }
                
                $this->log::info("..sniffing host availability: [$rurl]", get_class($this));
				$connect_timeout = @$this->config->get('dbm.dbm_connect_timeout') ?: 250;
				$timeout = @$this->config->get('dbm.dbm_timeout') ?: 250;
                $options = [ 'connect_timeout' => $connect_timeout, 'timeout' => $timeout ]; // in milliseconds
				$json = hf::get_rest($rurl, '', $options);	
                $this->log::info("..sniffer done. ct[$connect_timeout] t[$timeout]", get_class($this));
                //echo "<!-- Adapter: [$json] -->\r\n";
                if(!empty($json)){
                    
                    $data = json_decode($json, true);

                    if( isset($data['errordetails']['error_rest']) ){ 
                        $string = implode('|',$data['errordetails'] );
                        $this->log::info("..sniffer failed to reach host:[$h] [$string]", get_class($this));
                        continue; 
                    }
					$avail_hosts = Arr::searchKeys($data, 'host');
                   
					foreach($avail_hosts as $idx=>$avail_host){
					
						$avail_hosts[$idx] = "$prot://$avail_host:$port";
						
					}
                    
                    $cfg['hosts'] = Arr::reOrder($avail_hosts, $hosts);
                    $string = implode('|', $cfg['hosts']);
                    $this->log::info("..sniffer found available hosts: [$string]", get_class($this));
                    $this->config->set('params.elastic_version',  @Arr::searchKeys($data, 'version')[0] ?: 'Unknown');
                    $this->log::info("..sniffer found elastic version: [{$this->config->get('params.elastic_version')}]", get_class($this));
                    return;
            
                } else {

                    $this->log::info("..sniffer could not reach host: [$h]", get_class($this));

                }
                
            }

            $this->log::error("..sniffer could not find any hosts!", get_class($this));
            
        }

        /**
         * @param $client_config - adds default port to host entries if not already supplied
         * @return void
         */
        protected function check_hosts_port(&$cfg){

            $hosts = $cfg['hosts'];
            $avail = array();

            foreach($hosts as $h){

                $arr = parse_url($h);
                // Send in the protocol - set it in the DBM - last resort http (most of our servers)
                $prot = @$arr['scheme'] ?: @$this->config->get('params.default_protocol') ?: "http"; // i.e. specify or it will default to this
               
                $this->config->set('params.default_protocol', $prot);  // ideally save what was sent in via DBM
                $host = @$arr['host'] ?: @$arr['path'] ?: '';
                $port = @$arr['port'] ?: 9200;
                if(empty($host)){
                    $this->log::error("Unable to parse host string: [$h]", get_class($this));
                }else{
                     $avail[] = "$prot://$host:$port";
                }
               

            }

            $cfg['hosts'] = $avail;

        }


        /**
         * Set debug=on,query,xmla etc. to return the $log
         * @param $query
         * @param $msg
         */
        public function logQueryDetails($query, $msg){

            if($this->query->get('debug')){

                $this->log::info("Debug requested: [{$this->query->get('debug')}]");
                $debugs = explode(',', $this->query->get('debug'));
                foreach($debugs as $debug){

                    if($debug == 'all' or $debug == 'query'){
                        $this->log::debug(json_encode($query, JSON_PRETTY_PRINT), $msg);
                        break;
                    }

                    if( !empty(Arr::val($query, $debug)) ){
                        $tmp[$debug] = Arr::val($query, $debug);
                        $this->log::debug(json_encode($tmp, JSON_PRETTY_PRINT), $msg);
                        unset($tmp);
                    }

                }
            }


        }

        public function viewLog(){

            $log = $this->log::dump_to_string();
            $this->log::clearLog();
            return $log;

        }
}