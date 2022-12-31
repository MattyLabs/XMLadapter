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

	use \Elastic\Elasticsearch\ClientBuilder;
	use \Symfony\Component\HttpClient\Psr18Client;


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

            //print_r($this->config);die;
            if (!empty($this->config->get('params.dbm'))) {

                $path = $this->config->get('params.site_root') . "" . $this->config->get('sitename') . "/include/config/{$this->config->get('params.dbm')}-dbm.inc";
                $this->log::info("Loading DBM >> config.dbm: [$path]", get_class());
                if(file_exists($path)){

                    $arr = require($path);
                    $this->config->set('dbm', $arr);
                    $this->log::info("DBM index: [{$this->config->get('dbm.dbm_index')}]", get_class());

                }else{

                    $this->log::error("Failed to load DBM [{$this->config->get('params.dbm')}]. Please check.", get_class());
                    return;
                }

            } else {

                $this->log::error("No DBM! You must set '&DBM=siteName-indexName'", get_class());
                return;

            }

        /*
         *  SearchParser
         *  - parses $url querystring and generates the query_params
         *
         */
            $this->query = new SearchParser();

        // ELASTIC CLIENT
            $this->log::info('Elastic Client initialise..', get_class());
            try {

            //print_r( $this->config->get('params.elastic_client_config.hosts') );die;
            //print_r( $this->query->get('elastic_client') ); die;
                $this->client = ClientBuilder::fromConfig( $this->query->get('elastic_client') );
				//print_r($this->client->info());die;
				//$this->client = ClientBuilder::create()
				//	->setHosts($this->config->get('params.elastic_client_config.hosts'))
				//	->setHttpClient(new Psr18Client)
				//	->build();
				//$this->client->setHttpClient(new Psr18Client);

            } catch ( \Elastic\Elasticsearch\Exception\NoNodesAvailableException $e ){

                $m = $e->getMessage();
                $m = json_encode(json_decode($m,true), JSON_PRETTY_PRINT);
                $this->log::error($m, 'Search Exception');
                return;

            }


            // Quicker to get the version from the client than via REST call to host

            $info = $this->client->info();
            $this->query->set('elastic_version', $info['version']['number']);
            $this->log::info("elastic_version: [{$info['version']['number']}]", get_class());

            // Get All QueryParams
            //print_r($this->query->getQueryParams()); //die;
            //print_r($this->config);die;
            //print_r($this->query->get('params.idx') );die;

            $this->log::info("search_query: [{$this->query->get('search_query')}]", get_class());
            $this->log::info("search_terms: [{$this->query->get('search_terms')}]", get_class());
            //$x = print_r($this->query->get('search_array'), true); $this->log::info("search_array: [$x]", get_class());
            $this->log::info("search_fields: [{$this->query->get('search_fields')}]", get_class());

            if(!empty($this->config->get('params.idx'))){
                $idx = $this->config->get('params.idx');
                $this->log::info("searching index: [$idx] (params.idx)", get_class());
            }else{
                $idx = $this->config->get('dbm.dbm_index');
                $this->log::info("searching index: [$idx] (dbm.dbm_index)", get_class());
            }


        // Compile the final query..ToDo Check that this works properly for BDS [see get_xml() and xmla-api.php
            $query = [
                'index' => $idx,
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
            if (intval($this->query->get('elastic_version')) >= 7 ){

                if(isset($query['body']['aggs'])){
                    $this->log::info("Version adjustment:: setting Aggs key '_key' in place of '_term' [v" . $this->query->get('elastic_version') .']', get_class());
                    $query['body']['aggs'] = Arr::replaceKeys('_term', '_key', $query['body']['aggs']);

                    $query['body']['aggs'] = Arr::replaceKeys('interval', 'calendar_interval', $query['body']['aggs']);

                }

            }else{

                if(isset($query['body']['aggs'])){
                    $this->log::info("Version adjustment:: setting Aggs key '_term' in place of '_key' [v" . $this->query->get('elastic_version') .']', get_class());
                    $query['body']['aggs'] = Arr::replaceKeys('_key', '_term', $query['body']['aggs']);

                    $query['body']['aggs'] = Arr::replaceKeys('calendar_interval', 'interval', $query['body']['aggs']);

                }

            }

        // type & track_total_hits & skip_duplicates
            if (intval($this->query->get('elastic_version')) < 7) {

                $this->log::info("Version adjustment:: setting 'type=doc' [v" . $this->query->get('elastic_version') .']', get_class());
                Arr::set($query, 'type', $this->config->get('dbm.dbm_type'));

                if (Arr::searchKeys($query, 'track_total_hits')) {
                    $this->log::info("Version adjustment:: deleting key track_total_hits:: track_total_hits", get_class());
                    Arr::del($query, "body.track_total_hits");
                }

                if( !empty($query['suggest']) ){
                    if (Arr::searchKeys($query['suggest'], 'skip_duplicates')) {
                        $this->log::info("Version adjustment:: deleting key skip_duplicates", get_class());
                        Arr::del($query['suggest'], "skip_duplicates");
                    }
                }


            }

        // LIKE Search: completely replaces the 'body.query'
            if (!empty($this->query->get('like'))) {

                Arr::set($query, 'body.query', $this->query->getLike());

            }

        // Check we actually have something to search on!
            $query_test = Arr::filterBlanks($query);
            //print_r($query_test);//die;
            if(empty($query_test['body']['query']) and empty($this->query->get('sug')) ){

                $this->log::error('There\'s no query to run! Check your search syntax.', get_class());
                return;

            }

        // NOBOOL parameter: unset selected parts of the query
            if(!empty($this->config->get('url.qs_array.nobool'))){

                $nobool = $this->config->get('url.qs_array.nobool');
                if( preg_match('/must/', $nobool) ){
				
                    $this->log::info("NOBOOL: Setting [must] to null", get_class());
                    if( isset($query['body']['query']['bool']['must']) ){
                        unset($query['body']['query']['bool']['must']);
                    }
                    
                }

                if( preg_match('/should/', $nobool) ){
				
                    $this->log::info("NOBOOL: Setting [should] to null", get_class());
                    if( isset($query['body']['query']['bool']['should']) ){
                        unset($query['body']['query']['bool']['should']);
                    }
                    
                }

                if( preg_match('/filter/', $nobool) ){
				
                    $this->log::info("NOBOOL: Setting [filter] to null", get_class());
                    if( isset($query['body']['query']['bool']['filter']) ){
                        unset($query['body']['query']['bool']['filter']);
                    }
                    
                }
                
                if( preg_match('/highlight/', $nobool) ){
				
                    $this->log::info("NOBOOL: Setting [highlight] to null", get_class());
                    if( isset($query['body']['highlight']) ){
                        unset($query['body']['highlight']);
                    }
                    
                }

                if( preg_match('/aggs/', $nobool) ){
				
                    $this->log::info("NOBOOL: Setting [aggs] to null", get_class());
                    if( isset($query['body']['aggs']) ){
                        unset($query['body']['aggs']);
                    }
                    
                }
               
                if( preg_match('/rescore/', $nobool) ){
				
                    $this->log::info("NOBOOL: Setting [rescore] to null", get_class());
                    if( isset($query['body']['rescore']) ){
                        unset($query['body']['rescore']);
                    }
                    
                }

                if( preg_match('/suggest/', $nobool) ){
				
                    $this->log::info("NOBOOL: Setting [suggest] to null", get_class());
                    if( isset($query['body']['suggest']) ){
                        unset($query['body']['suggest']);
                    }
                    
                }

                if( preg_match('/query/', $nobool) ){
				
                    $this->log::info("NOBOOL: Setting [query] to null", get_class());
                    if( isset($query['body']['query']) ){
                        unset($query['body']['query']);
                    }
                    
                }


            }

        //print_r($query);die;

        // DO THE SEARCH
            //$query = Arr::filterBlanks($query);
            $this->log::info('Running search query..');
            $this->log::time('SEARCH');

            try {

                $results = $this->client->search($query);
                //print_r($results->asArray());die;
                //Elastic\Elasticsearch\Exception\ClientResponseException
                //\Elasticsearch\Common\Exceptions\BadRequest400Exception|\Elasticsearch\Common\Exceptions\Missing404Exception|\Elasticsearch\Common\Exceptions\ServerErrorResponseException
            } catch ( \Elastic\Elasticsearch\Exception\ClientResponseException $e) {

                $m = $e->getMessage();
                $m = '{' . explode('{', $m, 2)[1]; // v8.2 !! Exceptions used to be JSON now they have e.g.404 Not Found: { json }
                //print_r($e);
                $m = json_encode(json_decode($m,true), JSON_PRETTY_PRINT);
                $this->log::error($m, 'Search Exception', get_class());
                $this->logQueryDetails($query, 'Search query');
                return; // ToDo: return XML Error message

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
            $this->log::info("ResultsInfo - start..", get_class());

            $format = $format ?: 'xml';
            $format = strtolower($format);

            if( $format == 'raw' ){

                $this->log::info("ResultsInfo - PHP.", get_class());
                $x = print_r($results, true);
                return $x; //$results; // todo - this is for testing only

            }elseif ($format == 'xml'){

                $res = new ResultsInfoXML($results, $this->query->getQueryParams());
                $this->log::info("ResultsInfo - XML.", get_class());
                return $res->create();

            }elseif ( $format == 'json' ){
                // this tracks Elastic output
                //$res = json_encode($results, JSON_PRETTY_PRINT);
                $res = new ResultsInfoJSON($results, $this->query->getQueryParams());
                $this->log::info("ResultsInfo - JSON.", get_class());
                return $res->create();

            }elseif ( $format == 'json2' ){
                // this tracks XML2
                $res = new ResultsInfoJSON2($results, $this->query->getQueryParams());
                $this->log::info("ResultsInfo - JSON2.", get_class());
                return $res->create();

            }elseif ( $format == 'doc' ){
                // i.e. a XML dump data file
                $res = new ResultsInfoDoc($results, $this->query->getQueryParams());
                $this->log::info("ResultsInfo - Doc.", get_class());
                return $res->create();

            }elseif ( $format == 'html' ){
                // i.e. a nightmare
                $res = new ResultsInfoHTML($results, $this->query->getQueryParams());
                $this->log::info("ResultsInfo - HTML.", get_class());
                return $res->create();

            }


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