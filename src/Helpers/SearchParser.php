<?php
    namespace MattyLabs\XMLAdapter\Helpers;

    use MattyLabs\XMLAdapter\Config;
    use MattyLabs\XMLAdapter\Logger\SimpleLogger;
    use MattyLabs\XMLAdapter\Helpers\HelperFunctions as hf;

    class SearchParser
    {

        /**
         * @var Config|false
         */
        protected  $config;

        /**
         * @var SimpleLogger
         */
        protected  $log;

        /** Contains all the search components needed to construct the final query
         *  You can access values using SearchParser->get($key)
         * @var array|mixed|null
         */
        protected $query_params;


        /**
         * @param null $qs_array
         */
        public function __construct($qs_array = null)
        {
            // loadup all the page's $params
            $this->config = Config::instance();

            $this->log = new SimpleLogger();
            $log = $this->log;

            $this->query_params = $qs_array ?: $this->config->get('url.qs_array');

            $log::info('SearchParser initialised..', get_class());
            //$pr = print_r($this->query_params, true);$log::info("$pr", get_class());
            //$cg = print_r($this->config, true);$log::info("$cg", get_class());
            //echo $log::dump_to_string(); die;

            $this->cleanParams();
            $this->cleanSearchParams();
            $this->initSearchParams();
            $this->formatQuickCodes();
            $this->formatSearchParams();
            $this->buildQueryString();
            $this->compileFieldList();
            $this->setPageCount();
            $this->setPageSize();
            $this->setMinShouldMatch();
            $this->setHosts();

            //$log::info("search_query:[{$this->query_params['search_query']}]", get_class());
            //$pr = print_r($this->query_params, true);$log::info("$pr", get_class());

        }


        /**
         *  Return any of the calculated values
         *  e.g. search_terms, search_fields, search_array[fields => vals], search_query (Elasticsearch query_string Query syntax :)
         *
         * @param $key
         * @return mixed
         */
        public function get($key){

            // return $this->query_params[$key] ?? null;
            if(isset($this->query_params[$key])){
                return $this->query_params[$key];
            }else{
                return null;
            }
        }


        /**
         * @param $key
         * @param $value
         */
        public function set($key, $value){
           $this->query_params[$key] = $value;
        }


        /**
         *  Attempts to standardise historically valid name=value pairs
         *  also attempts to clean some params - particularly &amp;
         *
         */
        protected function cleanParams()
        {

            $params = $this->query_params;
            $clean_params = [];

            foreach($params as $key => $val) {

                $val = str_replace('&amp;', '&', $val);

                if (($key == 'sort') || ($key == 'form_ob')) {
                    $clean_params['sort'] = $val;
                }
                elseif (($key == 'aub') || ($key == 'author')) {
                    $clean_params['aub'] = $val;
                }
                elseif (($key == 'adv') || ($key == 'new') || ($key == 'fut')) {
                    $clean_params['adv'] = $val;
                }
                elseif (($key == 'asl') || ($key == 'stoplimit')) {
                    $clean_params['autostoplimit'] = $val;
                }
                elseif (($key == 'bic') || ($key == 'subject') || ($key == 'sub'))	{
                    $clean_params['bic'] = $val;
                }
                elseif (($key == 'kyt') || ($key == 'kwd') || ($key == 'keyword'))	{
                    $clean_params['kyt'] = $val;
                }
                elseif (($key == 'k') || ($key == 'key')) {
                    $clean_params['k'] = $val;
                }
                elseif (($key == 'mf') || ($key == 'maxf'))	{
                    $clean_params['maxfilestoretrieve'] = $val;
                }
                elseif (($key == 'dc') || ($key == 'fc')){
                    $clean_params['document_count']	= $val;
                }
                elseif (($key == 'm') || ($key == 'doc') || ($key == 'docid'))	{
                    $clean_params['m'] = $val;
                }
                elseif (($key == 'pl') || ($key == 'pagelength') || ($key == 'page')){
                    $clean_params['pagelength']	= $val;
                }
                elseif (($key == 'q') || ($key == 'query'))	{
                    $clean_params['q'] = $val;
                }
                elseif (($key == 'tit') || ($key == 'title')) {
                    $clean_params['tit'] =	$val;
                }
                elseif (($key == 'v') || ($key == 'view')) {
                    $clean_params['view'] =	$val;
                }
                elseif (($key == 'f') || ($key == 'fields')) {
                    $clean_params['field_list']	= $val;
                }
                elseif (($key == 'sqf') || ($key == 'filter')){
                    $clean_params['sqf']	= $val;
                }
                else {
                    $clean_params[$key]	= $val;
                }

            }

            $this->query_params = $clean_params;

        }

        /**
         *  Really old versions permitted query params such as search_field_01 etc.
         *  - Now standardised to SF1=x&ST2=y&SP=z
         *
         */
        protected function cleanSearchParams(){

            $params = $this->query_params;
            $params_to_ignore	=	['stem', 'start', 'stoplimit'];
            $cleaned_params		=	[];

            foreach($params as $key => $val)
            {

                if (in_array($key, $params_to_ignore))
                {
                    $cleaned_params[$key] = $val;
                }
                else
                {
                    $key		=	str_replace(array('search_field', 'search_text', 'search_operator'), array('sf', 'st', 'sp'), $key);
                    $s_key		=	substr($key, 0, 2);

                    if (($s_key == 'sf')||($s_key == 'st')||($s_key == 'sp'))
                    {
                        $s_key		=	str_replace(array('sf', 'st', 'sp'), array('sf_', 'st_', 'sp_'), $key);

                        $s_key		=	preg_replace('/_{1,}/',	'_',	$s_key);
                        $s_key		=	preg_replace('/[^a-z_]/i',	'',		$s_key);
                        $k_idx		=	preg_replace('/[a-z_]/i',	'',		$key);

                        if (empty($k_idx)) { $k_idx	= 1; }

                        $k_idx		=	hf::padz($k_idx, 2);

                        $key		=	$s_key . $k_idx;
                        $cleaned_params[$key]	=	$val;

                    }
                    else
                    {
                        $cleaned_params[$key] = $val;
                    }
                }
            }

            $this->query_params = $cleaned_params;

        }

        /**
         * Search params are re-numbered but kept in sequence
         *
         */
        protected function initSearchParams(){

            $params = $this->query_params;
            $initialised_params = [];
            $counter = 0;

            foreach($params as $key => $val)
            {
                $s_key			=	substr($key, 0, 3);
                $k_idx			=	preg_replace('/[a-z_]/i',	'',		$key);
                $k_idx			=	hf::padz($k_idx, 2);

                if (($s_key == 'sf_') || ($s_key == 'st_') || ($s_key == 'sp_'))
                {
                    if (!empty($params['st_' . $k_idx]))
                    {
                        $counter++;
                        $p_counter		=	hf::padz($counter, 2);
                        $initialised_params['total_field_count'] = $p_counter;
                        $initialised_params['search_text_'		. $p_counter] =	@$params['st_' . $k_idx];
                        $initialised_params['search_field_'		. $p_counter] =	@$params['sf_' . $k_idx];
                        $initialised_params['search_operator_'	. $p_counter] =	@$params['sp_' . $k_idx];

                        // set the default operator
                        if (empty($initialised_params['search_operator_'	. $p_counter])) {

                            $initialised_params['search_operator_' . $p_counter] = $this->config->get('dbm.default_search_operator');

                        }


                        // handle 'SF1=ctitle,contributor' - the logic is the operator_02 describes treatment for SF_02 & ST_02
                        if ( strpos($initialised_params['search_field_'	. $p_counter], ',') > 0 ){

                            $fields = explode(',', $initialised_params['search_field_'	. $p_counter]);
                            $current_count = $p_counter;

                            if(count($fields) > 1) {

                                // reset the 'default_search_operator to 'OR'
                                $initialised_params['search_field_'	. $p_counter] = $fields[0];
                                $initialised_params['search_operator_'	. $p_counter] = 'OR';

                            }

                            for ($i = 1; $i < count($fields); $i++) {

                                $counter++;
                                $p_counter = hf::padz($counter, 2);

                                $initialised_params['search_field_'	. $p_counter] = $fields[$i];
                                $initialised_params['search_text_'	. $p_counter] = $initialised_params['search_text_'	. $current_count];
                                $initialised_params['search_operator_'	. $p_counter] = 'OR';

                            }

                        }


                        unset($params['st_' . $k_idx]);
                        unset($params['sf_' . $k_idx]);
                        unset($params['sp_' . $k_idx]);
                    }
                }
                else
                {
                    $initialised_params[$key]	=	$val;
                }
            }

            $this->query_params = $initialised_params;
        }

        /**
         *  There are a number of shorthand query params that need to be handled
         *
         */
        protected function formatQuickCodes(){

            $params = $this->query_params;
            $total_field_count = @$params['total_field_count'] ?: 0;
       
            //	Handle action codes
            foreach($params as $key => $val) {

                if (($key == 'bic'))
                {
                    //	This is a subject search :: Rewrite the search field to BIC
                    $total_field_count++;
                    $num = hf::padz($total_field_count, 2);
                    $params['total_field_count'] = $num;
                    $params['search_field_' . $num] = $this->config->get('dbm.short_code_bic');
                    $params['search_text_'  . $num] = $val;
                    unset($params[$key]);
                }
                elseif ($key == 'isb')
                {
                    //	This is an identifier search :: Rewrite the search field to IDENTIFIER
                    $total_field_count++;
                    $num = hf::padz($total_field_count, 2);
                    $params['total_field_count'] = $num;
                    $params['search_field_' . $num]	= $this->config->get('dbm.short_code_isb');
                    $params['search_text_'  . $num]	= $val;
                    unset($params[$key]);
                }
                elseif ($key == 'ehcat')
                {
                    //	This is a publishers subject search :: Rewrite the search field to CAT_CLASS
                    $total_field_count++;
                    $num = hf::padz($total_field_count, 2);
                    $params['total_field_count'] = $num;
                    $params['search_field_' . $num]	= $this->config->get('dbm.short_code_ehcat');
                    $params['search_text_'  . $num]	= $val;
                    unset($params[$key]);
                }
                elseif ($key == 'since')
                {
                    //	This is a date range search, gives us all books published from <n> days - set the $qs['dateRange_'] params
                    $this->dateRange($val, $key, $params);

                }
                elseif (($key == 'adv'))
                {
                    //	This is a date range search, gives us all books published within <n> days
                    $this->dateRange($val, $key, $params);

                }
                elseif ($key == 'dtspan')
                {
                    // should be &dtspan=30:30 sort of thing

                    $this->dateRange($val, $key, $params);


                }
                elseif (($key == 'kyt'))
                {
                    //	This is a keyword search
                    $total_field_count++;
                    $num = hf::padz($total_field_count, 2);
                    $params['total_field_count'] = $num;
                    $params['search_field_' . $num]	= 'keyword';
                    $params['search_text_'  . $num]	= $val;
                    unset($params[$key]);
                }
                elseif (($key == 'aub'))
                {
                    //	This is an author search
                    $total_field_count++;
                    $num = hf::padz($total_field_count, 2);
                    $params['total_field_count'] = $num;
                    $params['search_field_' . $num]	= $this->config->get('dbm.short_code_aub');
                    $params['search_text_'  . $num]	= $val;
                    unset($params[$key]);
                }
                elseif ($key == 'tit')
                {
                    //	This is a title search
                    $total_field_count++;
                    $num = hf::padz($total_field_count, 2);
                    $params['total_field_count'] = $num;
                    $params['search_field_' . $num]	= $this->config->get('dbm.short_code_tit');
                    $params['search_text_'  . $num]	= $val;
                    unset($params[$key]);
                }

            }

            ksort($params);
            $this->query_params = $params;

        }

        /**
         *
         */
        protected function formatSearchParams(){

            $params = $this->query_params;
            $search_terms = '';
            $search_fields = '';

            foreach($params as $key => $val) {

                if(preg_match('/^search_text/', $key)) {

                    // assume that the default_operator is OR (i.e. any term)
                    // permit search terms to contain Elastic operators
                    // only interpret the rest :)

                    // Aaargh! quotes: attempt to extract legitimate single quotes
                    $val = preg_replace('/([A-Za-z])\'([A-Za-z])/', "$1".chr(29)."$2", $val);	// O'Malley >> O[chr(29)]Malley etc.
                    $val = str_replace("' ", ' ', $val);	// e.g. "students' pens" >> "students pens"
                    $val = str_replace("'", '"', $val);	// i.e. preserve 'phrase searches' using either single or "double quotes"
                    $val = str_replace("<", '&lt;', $val);
                    $val = str_replace(">", '&gt;', $val);
                    $val = str_replace("<=", '&lte;', $val);
                    $val = str_replace(">=", '&gte;', $val);
					$val = str_replace("{63}", '?', $val);

                    if(substr_count($val, '"') % 2 != 0){
                        // if there are an odd number of quotes now, get rid of them all!
                        $val = str_replace('"', '', $val);
                    }
                    $val = str_replace(chr(29), "'", $val);	// now should be safe to pop back in apostrophes
                    $terms 	= $val;
                    //print_r($terms); die;

                    $terms = str_replace('*', '\*', $terms);	// where someone just enters a '*' operator - messes things up a bit.
                    $terms = str_replace('?', '\?', $terms);
                    $terms = str_replace('/', '\/', $terms);

                    if(!preg_match("/$terms/", $search_terms)){
                        $search_terms 	.= $terms . ' ';
                    }
                    $single_terms 	= $val;
                    $clean_p 		= '';

                    // remove [square brackets] - plays havoc with the arrays!
                    $val 			= str_replace('[', '', $val);
                    $val 			= str_replace(']', '', $val);

                    // remove commas - AND - OR - NOT from phrases
                    $parts = hf::getDelimStr($val, '"', '"', true);

                    foreach(explode('|', $parts) as $p) {

                        // save array of all terms NOT part of a phrase
                        $single_terms = str_replace($p, '', $single_terms);

                        // remove commas and bool ops from phrases [probably not necessary]
                        $clean_p = preg_replace('/,| and | or | not /i', ' ', $p);

                        // add default proximity to phrases unless already present
                        if(!preg_match('/([\^~]\d)/', $terms)) {
                            $clean_p = $clean_p . $this->config->get('dbm.default_phrase_proximity');
                        }

                        //$clean_p = ''. $clean_p . '';
                        //echo("$p, $clean_p, $val\r\n");
                        $val = str_replace($p, $clean_p, $val);

                    }

                    // if fuzzy is set add trailing~ to each single term
                    $single_terms = preg_replace('/,|( and )|( or )|( not )|( to )|([\s\W]+)/i', ' ', $single_terms);
                    $singles = explode(' ', $single_terms);
                    foreach($singles as $s) {
                        $s = trim($s);
                        if(!empty($params['fuzzy'])) {		//N.B. '0' is treated as empty and '&FUZZY=false' will be treated as 'on'

                            $fuzziness = $params['fuzzy'] ?:  $this->config['dbm.default_fuzziness'];

                            switch ($fuzziness) {
                                case 1:
                                    $fuzz = "~";
                                    break;
                                case 2:
                                    $fuzz = "~$fuzziness";
                                    break;
                                default:
                                    $fuzz = '~';
                            }

                            $val = str_replace($s, $s . $fuzz, $val);
                            $val = str_replace("~~", "~", $val);	// e.g. where the query contains the same term twice!
                            $val = str_replace("~2~2", "~2", $val);	// e.g. where the query contains the same term twice!
                            $fuzz = '';

                        }
                    }

                    /* EEK! Converting commas to ' OR ' is generally a confusing/bad thing, but ISBN lists separated
                        by commas need to be permitted - best option is to ensure there are spaces between each ISBN
                        AND don't forget msm=0
                    */
                    $val = str_replace(',', ' ', $val);

                    //tidy spaces and brackets
                    $val = preg_replace('/\s+/', ' ', $val);
                    $val = preg_replace('/\(+/', '(', $val);
                    $val = preg_replace('/\)+/', ')', $val);

                    $params[$key] = $val;
                }

                // add boost to selected fields.. sadly this syntax doesn't work with search_query but will work with multi_match queries [Failed to parse query [(contributor^3:matthew pollock)]
                if(preg_match('/^search_field/', $key)) {

                    if(array_key_exists($val, $this->config->get('dbm.default_field_boost'))) {
                        $val = $val . '^' . $this->config->get('dbm.default_field_boost')[$val];
                    }

                    $search_fields .= $val . ',';

                }

                $params['search_terms'] = trim($search_terms);
                $params['search_fields'] = trim($search_fields, " ,");

            }

            $this->query_params = $params;

        }

        /**
         *
         */
        protected function buildQueryString(){

            $qs = '';
            $st = array();
            $params = $this->query_params;

            foreach($params as $key => $val) {

                if(preg_match('/^search_field/', $key)) {

                    $c = explode('_', $key);
                    $count = end($c);
                    $operator = ' '. trim(strtoupper( (@$params['search_operator_'.$count] ?: '') )) . ' ';
                    if(preg_match('/ AND | OR | NOT /', $operator)) {
                        $op = $operator;
                    } else {
                        $op = ' ';
                    }
                    /* 	We ignore the first operator: 'SF1=ctitle&ST1=war&SP1=OR'.
                        We apply the operator to the search phrase with the same number:
                        'SF1=ctitle&ST1=war&SP1=OR&SF2=series&ST2=trenches&SP2=AND' >> (ctitle:war) AND (series:trneches)
                        the initial operator is effectively ignored

                    */
                    if(empty($qs)){ $op = '';}

                    if (!empty ($params['search_text_'.$count]) ) {

                        if ($val == 'keyword') {

                            $qs .= $op . '(' . $params['search_text_'.$count] . ')';
                            $st['keyword'] = $params['search_text_'.$count];


                        } elseif ( strpos($params['search_text_'.$count], ':')  > 0 ) {

                            // catch someone doing a range search on another date field [N.B. As this is indistinguishable form another type of query - you will need to couple it with SFx=keyword&STx=ref_no (as I can't see how to delete the must/should clauses when the date range filter is on its own but not using the sort_date field ;0)
                            //$this->dateRange($params['search_text_'.$count], $val, $params);
                            //$this->config->set('dbm.short_code_date', $val);	// temporarily reassign the short_code_date with the actual field used.
                            $qs .= $op . $val . ':[' . str_replace(':', ' TO ', $params['search_text_'.$count]) . ']';

                        } elseif (!empty($this->config->get('dbm.default_force_quotes')) ) {

                            if (in_array($val, $this->config->get('dbm.default_force_quotes'))) {
                                // add quotes to increase precision: you will want to set 'default_phrase_slop' = 10 or more
                                $qs .= $op . '(' . $val . ':("' . $params['search_text_' . $count] . '"))';
                                // remove double-double quotes if already present :)
                                $qs = str_replace('""', '"', $qs);
                                $st[$val] = $params['search_text_' . $count];
                            }

                        }else {

                            $qs .= $op . '(' . $val . ':(' . $params['search_text_'.$count] . '))';
                            $st[$val] = $params['search_text_'.$count];

                        }
                    }
                }
            }

            // Preserve '>' & '<' search operators
            $qs = str_replace('&gt;', '>', $qs);
            $qs = str_replace('&lt;', '<', $qs);
            $qs = str_replace('&gte;', '>=', $qs);
            $qs = str_replace('&lte;', '<=', $qs);
            /* 	Lone ampersands can be searched as %26 which are escaped as {38} and
                finally converted back to '&', but the CMS stores them as &#38;
                The following works but may cause side-effects:..
            */
            //$qs = str_replace(' & ', ' &#38; ', $qs);

            if (!empty($params['k'])) {

                $qs = '' . $this->config->get('dbm.short_code_key') . ':(' . $params['k'] . ')';
                $st[$this->config->get('dbm.short_code_key')] = $params['k'];

            }

            if ($this->config->get('dbm.permit_q_search')) {

                if( !empty($this->config->get('dbm.site_q_search_filter')) ){

                    //$p = print_r($params, true); echo "<1-- $p -->\r\n";

                    // This is so we can see all relevant titles in the CMS
                    if( @$params['nqf'] == 'true' || @$params['nqf'] == 'y'){

                        $qsf = @$params['q']; // i.e. manual override in url

                    } elseif( !empty($params['k']) || preg_match('/_id|identifier|ref_no/', $params['search_fields']) ){

                        $qsf = '';

                    } else {

                        $qsf = @$params['q'] ?: @$this->config->get('dbm.site_q_search_filter') ?: '';

                    }

                } else {

                    $qsf = @$params['q'] ?: '';

                }

                if(!empty($qsf)){ 
                    $qsf = str_replace('+', ' ', $qsf); 
                    $qsf = str_replace('%20', ' ', $qsf); 
                } 


                //echo "<!-- $qsf -->\r\n";

                /* 	Near magical fix for grouping OR searches by adding brakets around ( x OR y OR z) the OR searches
                    e.g. where you search on: [&SF1=bic_subject,bic_qual,thema_qual&ST1=astronomy]
                    (
                        (bic_subject:(astronomy)) OR (bic_qual:(astronomy)) OR (thema_qual:(astronomy))

                    ) AND (

                        (iain)

                    ) AND (

                        (format_code:(BB OR BC))

                    )

                */
                //echo "<!-- [a] [[$qs]] -->\r\n";
                if(!empty($qs)){
                    $qs = '(' . str_replace(' AND ', ') AND (', $qs) . ')';
                    $qs = Trim($qs . ' ' . $qsf);
                } else {
                    $qs = $qsf;
                }
                //echo "<!-- [qsf] $qsf -->\r\n";
                //echo "<!-- [qs] [[$qs]] -->\r\n";

            }

            $params['search_array'] = $st;
            $params['search_query'] = $qs;
            $this->query_params = $params;

        }


        /**
         * Search Filters are presented in the QueryString as follows:
         * - e.g. &SQF=/n:val1 val2/n:val1 val2/ etc. where n=[0-9] OR field_name
         *
         * if numeric then the field_name mapping is set in -dbm.inc 'sqf_filters'
         * - e.g. 'sqf_filters' => ['null', 'psc.raw', 'pub_month', 'primary_name', 'format', 'primary_price', 'imprint', 'series', 'primary_avail'],
         * - N.B. SQF Filters always number from 1
         *
         * Filters can be set as Prefix Filters (i.e. the equivalent of 'Her maj*') in the DBM: $this->config['dbm.prefix_filters']
         * N.B. All Filter fields need a type=keyword (.raw) mapping
         *
         * - added to search as filter in the form
         *  'query' => [
         *      'bool'  => [
         *          'must' => [],
         *          'should => [],
         *          'filter'  => [
         *               'prefix' => [ 'psc.raw' => 'humanities history' ],
         *                'match' => [ 'facet' => 'NBD' ],
         *                 'term' => [ 'facet' => 'NBD' ],
         *          ]]]
         *
         * @return array|null
         */
        public function getFilters(){

            $search_filters = array();
            $sfa = array();
            $tmp = '';
            $log = $this->log;

            if(!empty($this->query_params['sqf']) ) {

                $log::info('Search Filters initialising...', get_class());
                $sf = trim($this->query_params['sqf'], '[/]');
                $sf = str_replace('&', '{38}', $sf);	// save any '&' in the query
                $sf = str_replace('/', '&', $sf);	// maybe we use ~ or ^ or | or `
                $sf = str_replace(':', '=', $sf);
                //$sf = str_replace(';', '.', $sf);   // Some old sites used ';' in place of '.' for field.raw etc.
                $log::info("search filter: [$sf]", get_class());

                $sfa = hf::parseQueryStr($sf); // N.B. parse_str converts '.' into '_' so lets not use it :)

                foreach($sfa as $key=>$val){

                    $val = str_replace('{38}', '&', $val);
                    // here's where we map back the field names from the SQF=/1: numbered params
                    if(is_numeric($key)){
                        $sfa[$this->config->get('dbm.sqf_filters')[$key]] = $val;
                        unset($sfa[$key]);
                    }else {
                        $sfa[$key] = $val;
                    }

                }
            }

            if (!empty($sfa) ) {

                foreach($sfa as $key=>$val){

                    $tmp .= $key . ':' . $val . '/';
                    // set prefix filter fields in $this-config['sqf_filters'])'
                    if(!empty($this->config->get('dbm.prefix_filters')[$key]) ){

                        $search_filters[] = [
                            'prefix' => [
                                $this->config->get('dbm.prefix_filters')[$key] => $val
                            ]
                        ];

                    } else {

                        if (strpos($val, ',') > 0 ) {
                            // e.g. '&SQF=facet:UK12,NBD' => comma treated as either/or (i.e.'should')
                            $filters = explode(',', $val);
                            foreach ($filters as $filter) {

                                $search_filters[] = [
                                    'term' => [$key => $filter]
                                ];
                            }

                        }elseif (strpos($val, '~') > 0 ){
                            $filters = explode('~', $val, 2);
                            $search_filters[] = [
                                'range' => [
                                    $key => [
                                        'gte' => $filters[0],
                                        'lte' => $filters[1]
                                    ]
                                ]
                            ];

                        }elseif (strpos($val, '-') > 0 ){
                            $filters = explode('-', $val, 2);
                            $search_filters[] = [
                                'range' => [
                                    $key => [
                                        'gt' => $filters[0],
                                        'lt' => $filters[1]
                                    ]
                                ]
                            ];

                        } else {
                            // 'SQF=/1:arts/2:UK3' or 'SQF=psc:arts/facet:UK3'
                            $search_filters[] = [
                                'term' => [$key => $val]
                            ];

                        }

                    }

                }

            } else {

                $search_filters = null;

            }

            if( !empty($this->getDateRangeFilter()) ){
                $search_filters[] = $this->getDateRangeFilter();
            }

            if(!empty($search_filters) ){

                // Use SearchParser::get($key): this is the friendly version of the filters
                $this->query_params['search_filters'] = rtrim($tmp, '[/]');
                // This is the Elasticsearch ready version of the filters
                $this->query_params['search_filters_array'] = array_filter($search_filters);
                $log::info('Search Filters done.', get_class());
                return array_filter($search_filters);

            }else{

                $log::info('Search Filters: None.', get_class());

            }

        }

        /**
         * @param $days
         * @param $key
         * @param $params
         */
        protected function dateRange($days, $key, &$params){

            switch ($key) {

                case 'since':
                    if(!empty($days) ){
                        $params['date_range_from'] = hf::displayDate( 'Ymd', -($days));
                        $params['date_range_to'] = hf::displayDate('Ymd', 0);
                    }
                    break;

                case 'adv':
                    if(!empty($days) ){
                        $params['date_range_from'] = hf::displayDate('Ymd', 0);
                        $params['date_range_to'] = hf::displayDate('Ymd', $days);
                    }
                    break;

                case 'dtspan':
                    $parts = explode(':', $days);
                    if(count($parts) == 2){
                        $params['date_range_from'] = hf::displayDate('Ymd', -($parts[0]));
                        $params['date_range_to'] = hf::displayDate('Ymd', $parts[1]);
                    }
                    break;

                // case we done :)
                default:

                    if( hf::validDates($days) ){
                        $parts = explode(':', $days);
                        $params['date_range_from'] = $parts[0];
                        $params['date_range_to'] = $parts[1];

                    } else {
                        $params['date_range_from'] = '';
                        $params['date_range_to'] = '';

                    }
                    break;

            }

        }

        /**
         *  compileFieldList()
         *  We need to list which _source fields we want to return in the search results
         *  - sets $this->query_params['field_list_array']
         */
        protected function  compileFieldList(){

            if( !empty($this->query_params['field_list']) ){

                $fields_url = explode(',', $this->query_params['field_list']);
                $fields_default = explode(',', $this->config->get('dbm.default_fieldlist'));

                if( in_array('default', $fields_url)){

                    if (($key = array_search('default', $fields_url)) !== false) {
                        unset($fields_url[$key]);
                    }
                    $field_list = array_merge($fields_default, $fields_url);

                }else{

                    $field_list = $fields_url;
                }

            }else{

                $field_list = explode(',', $this->config->get('dbm.default_fieldlist'));

            }

            // make sure we have ref_no
            if( !in_array('ref_no', $field_list) && !in_array('*', $field_list)){
                array_push($field_list, 'ref_no');
            }

            $this->query_params['field_list_array'] = $field_list;

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
        protected function setHosts(){

            $log = $this->log;
            $log::info("setHosts()", get_class());
            //$ecc = print_r($this->config->get('params.elastic_client_config'), true);  $log::info("gak: [$ecc]", get_class());
            // N.B. this is the Server default and can be set initially in php.server.defaults::$params['elastic_client_config']
            if(!empty($this->config->get('params.elastic_client_config')) ){
                $this->log::info("..loading elastic_client_config (from php.server.defaults).", get_class());
                $client_config = $this->config->get('params.elastic_client_config');
            }else{
                $client_config = [];
            }
           
            // always override the server.default hosts with the specific dbm hosts
            if(!empty($this->config->get('dbm.dbm_elastic_hosts'))){
                
                $string = implode('|', $this->config->get('dbm.dbm_elastic_hosts'));
                $this->log::info("..setting dbm_elastic_hosts (from DBM) [$string]", get_class());
                $client_config['hosts'] = $this->config->get('dbm.dbm_elastic_hosts');
            }
           
            // if still empty try the local server
            if(empty($client_config['hosts'])){

                $client_config['hosts'] = [
                    '127.0.0.1', // fallback
                    $this->config->get('params.this_server_ip'),    // \Config::setServerIP() [N.B.VPN may make LOCAL_ADDR inaccurate]
                ];
                $string = implode('|', $client_config['hosts']);
                $this->log::info("..setting fallback hosts [$string]", get_class());
                
            }

            // Sniff for available nodes before doing the search. Default is true
            if($this->config->get('dbm.dbm_sniff_hosts') !== false){
                $this->check_hosts_avail($client_config); 
            }
            
            // Elastic v8.2.2 seems to require the port whereas previous versions did not!
            $this->check_hosts_port($client_config);

            if( !empty($client_config['hosts']) ){

                $hosts_string = implode('|', $client_config['hosts']);
                $this->log::info("Using Elastic hosts: [$hosts_string]", get_class());
                //print_r($client_config);
                //print_r($this->config->get('dbm'));

            } else {

                $log::error("Unable to set Elastic hosts.", get_class());

            }

            if( !empty($this->config->get('dbm.dbm_elastic_cloud.elasticCloudId')) ){

                $client_config['elasticCloudId'] = $this->config->get('dbm.dbm_elastic_cloud.elasticCloudId');
                $client_config['basicAuthentication'] = [
                    $this->config->get('dbm.dbm_elastic_cloud.username'),
                    $this->config->get('dbm.dbm_elastic_cloud.password')
                ];

            }

            $this->query_params['elastic_client'] = $client_config;

        }

        /**
         * @param $hosts - checks cluster for available hosts and removes any that are 'down'
         * @return void
         */
        protected function check_hosts_avail(&$cfg)
        {
            
            $hosts = $cfg['hosts'];

            foreach($hosts as $h){
            
                $arr = parse_url($h);
                $prot = @$arr['scheme'] ?: @$this->config->get('dbm.dbm_default_protocol') ?: "http";
                $host = @$arr['host'] ?: @$arr['path'] ?: '';
                $port = @$arr['port'] ?: 9200;
                
                $rurl = "$prot://$host:$port/_nodes/_all/http";	
                $options['timeout'] = 1;
                $this->log::info("..sniffing host availability: [$rurl]", get_class());
                $json = hf::getRest($rurl, $options);	
                
                if(!empty($json)){
                    
                    $data = json_decode($json, true);
                    $cfg['hosts'] = array_values(Arr::searchKeys($data, 'host'));
                    $string = implode('|', $cfg['hosts']);
                    $this->log::info("Available hosts found: [$string]", get_class());
                    return;
            
                }
                
            }

            $this->log::error("No available hosts found!", get_class());
            
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
                $prot = @$arr['scheme'] ?: @$this->config->get('dbm.dbm_default_protocol') ?: "http"; // i.e. specify or it will default to this
               
                $this->config->set('dbm.dbm_default_protocol', $prot);  // ideally save what was sent in via DBM
                $host = @$arr['host'] ?: @$arr['path'] ?: '';
                $port = @$arr['port'] ?: 9200;
                if(empty($host)){
                    $this->log::error("Unable to parse host string: [$h]", get_class());
                }else{
                     $avail[] = "$prot://$host:$port";
                }
               

            }

            $cfg['hosts'] = $avail;

        }
        /**
         *  checkISBNQuery()
         *  - we want to know if the search is for a single unique id as this affects other parts of the query
         */
        protected function checkISBNQuery()
        {

            if (
                !empty($this->query_params['k']) or
                !empty($this->query_params['isb']) or
                preg_match('/identifier/', $this->query_params['search_fields']) or
                preg_match('/ref_no/', $this->query_params['search_fields']) or
                preg_match('/978\d{10}/', $this->query_params['search_terms'])
            ){
                return true;
            } else {
                return false;
            }
        }


        /**
         *  setPageCount()
         *  - url's store the current recordset index as 'm'
         *  - 'm' is adjusted to start at 1 (not 0) and incremented by 'pl' (pagelength) see setPageSize()
         *
         */
        protected function setPageCount(){

            if( !empty($this->query_params['k'])  ){
                // For k=ref_no we need to ignore the value of m, as there is only one matching document and it is numbered 0
                $this->query_params['from'] = 0;

            } else {
                if( empty($this->query_params['m'])  ){
                    $this->query_params['m'] = 0;
                }
                $this->query_params['from'] = (intval(@$this->query_params['m']-1) >= 0) ? intval(@$this->query_params['m']-1) : 0;
            }

        }

        /**
         *  Most params can be set in the DBM ($this->>config[]) and overwritten via the urlQueryString ($this->>query_params[])
         *  For an AGGS only search you need to set PL=0
         */
        protected function setPageSize(){

            if( !empty($this->query_params['pagelength']) ){
                $this->query_params['size'] = $this->query_params['pagelength'];
            } else {
                $this->query_params['size'] = $this->config->get('dbm.pagelength');
            }

        }

        /**
         *  Most params can be set in the DBM ($this->>config[]) and overwritten via the urlQueryString ($this->>query_params[])
         *  For an AGGS only search you need to set PL=0
         */
        protected function setMinShouldMatch(){

        // Start with DBM Default
            if(!isset($this->query_params['msm']) ){
                //$msm = $this->config->get('dbm.default_min_should_match') ?? '';
                if($this->config->get('dbm.default_min_should_match') ){
                    $msm = $this->config->get('dbm.default_min_should_match');
                }else{
                    $msm = '';
                }

            }else{
                // reset or change $msm via the url
                if( empty($this->query_params['msm']) or $this->query_params['msm'] == 0 or $this->query_params['msm'] == 'off' ){
                    $msm = '';
                } else {
                    $msm = $this->query_params['msm'];
                }
            }

        /* Some search params bear on whether MSM is allowed */
            // ISBN searches can't have msm
            if( $this->checkISBNQuery() == true) {
                $msm = '';
            }

            $msm = str_replace('&lt;', '<', $msm);
            $msm = str_replace('&gt;', '>', $msm);
            $msm = str_replace('%25;', '%', $msm);

            $this->query_params['msm'] = $msm ?: '';

        }


        /**
         * @param int $prefixLength
         * @param int $boost
         * @return array[]|float[][]
         */
        public function getMust($prefixLength = 2, $boost = 1.0){

        // Filter ONLY Search
            $log = $this->log;

            if( empty($this->query_params['search_terms']) and empty($this->query_params['k']) and empty($this->query_params['q'])){
                $log::info("skipping MUST query. No search terms", get_class()); 
                return [];
            }
            
            if( !empty($this->query_params['must']) and ($this->query_params['must'] == 'false' or $this->query_params['must'] == 'off') ){
                $log::info("MUST query switched off..", get_class());
                return [];
            }

            $log::info("Processing MUST query..", get_class());

        // What's all the fuzz
            $fuzz =  $this->config->get('dbm.default_fuzziness');
            if( isset($this->query_params['fuzzy'])){
                if(empty($this->query_params['fuzzy'])){
                    $fuzz = 0;
                }else{
                    $fuzz = $this->query_params['fuzzy'];
                }
            }
            //echo "[$fuzz]";die;


        // "SF1=keyword&ST1=ref_no&SF2=&ST2=" means match all OK!
			if(Arr::val($this->query_params, 'search_array.keyword')){
				
				if( preg_match('/ref_no/', Arr::val($this->query_params, 'search_array.keyword'))  ){

					if(count($this->query_params['search_array']) == 1 ) {

						$must = [
							'match_all' => ['boost' => 1.0]
						];

						return $must;

					}elseif (count($this->query_params['search_array']) > 1 and empty($this->query_params['k'])) {

					// then we have "SF1=keyword&ST1=ref_no&SF2=contributor&ST2=matty" i.e. match_all BUT don't match_all!
						$log::error("Incorrect use of 'SF1=keyword&ST1=ref_no' This means match_all so you can't then add more search terms!", get_class());
						echo $log::dump_to_string();
						exit;

					}

				}
			}

        // QUERY_STRING Query Syntax indicated
            if( strpos($this->query_params['search_query'], ':') > 0 && empty($this->query_params['fmm']) ){

                $must = [

                    'query_string' => [

                        'query' => $this->query_params['search_query'],
                        'default_operator' =>  $this->config->get('dbm.default_search_operator') ?: 'AND',
                        'boost' => 1.0,
                        'fuzziness' => $fuzz,	// Seems to be OK with ~1|2 on each term as well
                        'phrase_slop' => $this->config->get('dbm.default_phrase_slop'),

                    ]

                ];

            // Minimum Should Match
                if( !empty($this->query_params['msm'])){
                    Arr::set($must,'query_string.minimum_should_match', $this->query_params['msm']);
                }

            // ISBN Query
                if( $this->checkISBNQuery() == true ){
                    Arr::set($must,'query_string.default_operator', 'OR');
                }

                return $must;
            }

        // Last resort Multi Match Query
            $must = [

                'multi_match' => [

                    'query' => $this->query_params['search_terms'],
                    'type' => $this->config->get('dbm.default_search_type') ?: 'best_fields',
                    'fields' => $this->config->get('dbm.default_keyword_fields') ?: 'cindex',
                    'slop' => $this->config->get('dbm.default_phrase_slop'),
                    'fuzziness' => $fuzz,		//yes its a permitted parameter but no it doesn't work with query_string!! you need to add '~1' etc.
                    'prefix_length' => $prefixLength,	// no fuzziness where type = phrase or phrase_prefix
                    'boost' => $boost,
                    //'operator' => $this->config->get('dbm.default_search_operator'),

                ]

            ];

            if( !empty($this->query_params['msm'])){
                Arr::set($must,'multi_match.minimum_should_match', $this->query_params['msm']);
            }

            return $must;

        }

        /**
         *  getShould()
         *  - the 'elastic_should' block is stored in the DBM for any given index
         *  - a limited number of replacements are permitted;
         *  - 'search_terms', 'search_query', 'yymmdd[n]' where n = +/- days from today
         *  - by default we should be indexing date fields (like 'sort_date') with format 'yyyymmdd'
         *
         */
        public function getShould()
        {
            $log = $this->log;
            // Filter ONLY Search
            if( empty($this->query_params['search_terms']) and empty($this->query_params['k'])){
                $log::info("SHOULD query - no terms to process.", get_class());
                return [];
            }

			 if( !empty($this->query_params['should']) and ($this->query_params['should'] == 'false' or $this->query_params['should'] == 'off') ){
                $log::info("SHOULD query switched off..", get_class());
                return [];
            }
        
            $log::info("Processing SHOULD query.", get_class());
            $should = $this->config->get('dbm.elastic_should');
           
            $flat_should = json_encode($should, JSON_PRETTY_PRINT);
            $flat_should = str_replace('search_terms', addslashes( $this->query_params['search_terms']), $flat_should);
            $flat_should = str_replace('search_query', addslashes( $this->query_params['search_query']), $flat_should);
            $flat_should = str_replace("\\'", "'", $flat_should);   // single quotes inside JSON sting don't need to be escaped!

            preg_match_all('/yyyymmdd\[(.*)?\]/',$flat_should, $matches);
            foreach($matches[0] as $key=>$val){

                $d = $matches[1][$key];
                $dte = hf::displayDate('Ymd', $d);
                $flat_should = str_replace($val, $dte, $flat_should);
            }

            $should = json_decode($flat_should, true);
  
            return $should;

        }


        /**
         *  getLike()
         *  - the 'elastic_like' block is stored in the DBM for any given index
         *  - a limited number of replacements are permitted;
         *  - 'elastic_like_index' is substituted with the current index
         *  - 'elastic_like_id' passed via the url as '&LIKE=<ref_no>'
         *
         */
        public function getLike()
        {

            $log = $this->log;
            if( !empty($this->query_params['like']) ){

                $log::info("Like is on.", get_class());
                $like = $this->config->get('dbm.elastic_like');

                $flat_like = json_encode($like, JSON_PRETTY_PRINT);
                $flat_like = str_replace('elastic_like_index', $this->config->get('dbm.dbm_index'), $flat_like);
                $flat_like = str_replace('elastic_like_id', $this->query_params['like'], $flat_like);

                $like = json_decode($flat_like, true);
                $like = Arr::filterBlanks(($like));

            }

            //return $like ?? null;
            if($like){
                return $like;
            }else{
                return null;
            }


        }


        /**
         *  getAggs()
         *  - The default Aggregations are configured in the DBM [ 'elastic_aggregations_inc' => 'aggs1, agg2, etc' ]
         *  - and are triggered by setting url param '&FIELDS=distinct'
         *  - they can also be specified via the url param '&AGGS=aggs1,aggs3,etc.'
         *  - include filters can also be added via DBM 'elastic_aggs_tree_filter'
         *  - and in the case of tree filters coupled with the search_filters;
         *  - e.g. url: '&SQF=/thema_tree:F'    // N.B. configure thema_tree as a 'prefix_filter' & an 'sqf_filter'
         *  - - url: '&AGGS=thema_tree'         // OR set as DBM default: 'elastic_aggregations_inc'
         *  - - DBM: 'elastic_aggs_tree_filter'	=> ['thema_tree' => '[A-Z0-9]{1}', ]    // i.e. just show me the next letter in the code tree
         *  - Collapsed searches acquire their own aggregation for the 'total_collapsed_doc_count'
         */
        public function getAggs(){

            $aggs = ''; $aggregations = [];
            $log = $this->log;
            if( !empty($this->query_params['aggs']) ){
                $aggs = $this->query_params['aggs'];
            }else{
                if( in_array('distinct', $this->query_params['field_list_array']) || in_array('distinct_fields', $this->query_params['field_list_array']) ){
                    $aggs = $this->config->get('dbm.elastic_aggregations_inc');
                }
            }

            if(!empty($aggs)){

                $log::info("Aggregations are on: [$aggs]", get_class());
                $aggs = explode(',', $aggs);
                foreach($aggs as $a){

                    if(Arr::val(@$this->query_params['search_filters_array'] , $a) ){
                        $include_filter = Arr::val(@$this->query_params['search_filters_array'] , $a);
                    }else{
                        $include_filter = '';
                    }


                    if( !empty( $this->config->get("dbm.elastic_aggregations.$a")) ){

                        $aggregations[$a] = $this->config->get("dbm.elastic_aggregations.$a");

                        if( !empty($include_filter) && !empty($this->config->get("dbm.elastic_aggs_tree_filter.$a")) ){

                            $aggregations[$a]['terms']['include'] = $include_filter.";".$this->config->get("dbm.elastic_aggs_tree_filter.$a");

                        } elseif(empty($include_filter) && !empty($this->config->get("dbm.elastic_aggs_tree_filter.$a")) ){

                            $aggregations[$a]['terms']['include'] = $include_filter.$this->config->get("dbm.elastic_aggs_tree_filter.$a");

                        }

                    }

                }

            }

            // Collapsed Searches
            $collapse_field = @$this->query_params['collapse'] ?: $this->config->get('dbm.collapse') ?: '';
            if( !empty($collapse_field) ){

                $aggregations['total_collapsed_docs'] = [
                    'cardinality' => [
                        'field' => $collapse_field
                    ]
                ];

            }

            return $aggregations;

        }


        /**
         * Collapse on a field of type=keyword. Every record needs to have the collapse key.
         * e.g. work_id = good, book titles can be a bit erratic
         * N.B. If Collapse is set then Rescore will be ignored (you can't have them both)
         *
         * @return array
         */
        public function getCollapse(){

            $collapse = [];
            $collapse_field = @$this->query_params['collapse'] ?: $this->config->get('dbm.collapse') ?: '';
            $log = $this->log;

            if( !empty($collapse_field) and empty($this->query_params['nqf']) and $collapse_field != 'off' and $this->checkISBNQuery() == false ){

                $log::info("Collapse is on. Field: [$collapse_field]", get_class());
                $collapse = [
                    'field' => $collapse_field,
                    'inner_hits' => [
                        'name' => 'related_titles',
                        '_source' => [
                            'includes' => ($this->config->get('dbm.collapse_inner_fields')) ?: ['ref_no']
                        ],
                        'size' => ($this->config->get('dbm.collapse_fields_size')) ?: 5,
                        'sort' => ($this->config->get('dbm.collapse_fields_sort')) ?: [],
                    ]
                ];


            }

            return $collapse;

        }

        /**
         *  getRescore()
         *  - the rescore block is configured in the DBM
         */
        public function getRescore(){

            $rescore = null;
            $log = $this->log;
			 if( !empty($this->query_params['rescore']) and ($this->query_params['rescore'] == 'false' or $this->query_params['rescore'] == 'off') ){
                $log::info("RESCORE query switched off..", get_class());
                return [];
            }
            $collapse_field = @$this->query_params['collapse'] ?: $this->config->get('dbm.collapse') ?: '';
            if($this->config->get('dbm.elastic_rescore_show') and $this->checkISBNQuery() == false and empty($collapse_field) and empty($this->query_params['sort']) ){

                $log::info("Rescore is on.", get_class());
                //$rescore = $this->config->get('dbm.elastic_rescore') ?? [];
                if($this->config->get('dbm.elastic_rescore')){
                    $rescore = $this->config->get('dbm.elastic_rescore') ;
                }else{
                    $rescore = [];
                }


                $flat_rescore = json_encode($rescore, JSON_PRETTY_PRINT);
                $flat_rescore = str_replace('rescore_window_size',  $this->config->get('dbm.rescore_window_size'), $flat_rescore);

                preg_match_all('/yyyymmdd\[(.*)?\]/',$flat_rescore, $matches);
                foreach($matches[0] as $key=>$val){

                    $d = $matches[1][$key];
                    $dte = hf::displayDate('Ymd', $d);
                    $flat_rescore = str_replace($val, $dte, $flat_rescore);
                }

                $rescore = json_decode($flat_rescore, true);

            }

           //return $rescore ?? null;
            return $rescore;

        }

        /**
         *  getSort()
         *
         */
        public function getSort(){

            $sort = null;
            $log = $this->log;
            if( !empty($this->query_params['sort']) ){

                $log::info("Sort is on: [{$this->query_params['sort']}]", get_class());
                $sorts = explode(',', $this->query_params['sort']);
                foreach($sorts as $s){

                    $sort_field = explode('/', $s)[0];
                    $sort_direction = ((stripos($s, '/d') > -1) ? '/d' : '');
                    $sort_order = ($sort_direction == '/d') ? 'desc' : 'asc';
                    $sort_type = $this->checkSortType($sort_field);
                    $sort[$sort_field.$sort_type] = ['order' =>  $sort_order, 'mode' => 'min', 'missing' => '_last' ];

                }

            }

            //return $sort ?? null;
            return $sort;
        }

        /**
         * getDateRange()
         * e.g. url params: '&DTSPAN=10:10'
         * e.g. url params: '&ADV=10'   (i.e. 0-10 days ahead)
         * e.g. url params: '&SINCE=10' (i.e. -10-0 days ago)
         *
         * this provides an opportunity to add filters to the parseFilters() operation
         *
         */
        public function getDateRangeFilter(){

            $range = null;
            $log = $this->log;
            if(!empty($this->query_params['date_range_from']) ){

                $log::info("Date Range is on: [{$this->query_params['date_range_from']}:{$this->query_params['date_range_to']}]", get_class());
                $range = [
                    'range' => [
                        $this->config->get('dbm.short_code_date') => [
                            'gte' => $this->query_params['date_range_from'],
                            'lte' => $this->query_params['date_range_to']
                        ]
                    ]

                ];
            }

            //return $range ?? null;
            return $range;
        }


        /**
         * Attempt to find the mapping type for the field from the DBM or return a default best guess
         * - a hangover from previous versions ALL Mappings should have a .raw field of type = keyword (if you want to sort)
         * - except sort_|_code|_exact|_rank which can be sorted without .raw
         *
         * @param string $field
         */
        protected function checkSortType($field){

            $ret = '';
            $log = $this->log;
            $sort_map = Arr::search($this->config->get('dbm.elastic_index_config.body.mappings'), $field)['value'];
            if(is_array(($sort_map) and !empty($sort_map))){

                $type = Arr::val($sort_map, 'type'); 
                if( preg_match('/text/', $type) ){ 
                    $log::info("Sort Type: [$type] adding .raw", get_class());
                    $ret = '.raw'; 
                }else{ 
                    $log::info("Sort Type: [$type] leave as is", get_class());
                } 

            }else{
                // it didn't have to be in the DBM mapping, try to get it from the field name 
                $ret = (preg_match('/sort_|_exact|_code|_rank/', $field)) ? '' : '.raw';
            }

            $log::info("Checking Sort field: [$field] = [$ret]", get_class());

            return $ret;

        }


        /**
         *  getSuggest()
         *  - the Suggestion block is configured in the DBM
         *  - N.B. You will need to ensure you index suggestion fields with type = 'completion'
         *  - See the elkastic-indexer for example as to how to index
         *  - If DBM 'elastic_suggestions_show' = true then set suggestions from the search, use search_terms 
         *  - If &SUG=xyz - then set suggestions=xyz - this should override search 
         */ 
        public function getSuggest(){

            $log = $this->log;
            $suggest = null;
            $search_terms = $this->query_params['search_terms']; 
            $search_query = $this->query_params['search_query']; 
           
            //$show_suggest = $this->config->get('dbm.elastic_suggestions_show') ?? false;
            if($this->config->get('dbm.elastic_suggestions_show')){

                $show_suggest = (bool)$this->config->get('dbm.elastic_suggestions_show'); 
                $log::info("Suggest show (DBM) [$search_terms]", get_class()); 

            }else{

                $show_suggest = false;

            }

            if( isset($this->query_params['sug']) and !empty($this->query_params['sug']) ){ 
                
                $show_suggest = true; 
                $search_terms = $this->query_params['sug']; 
                $this->config->set('url.qs_array.nobool', 'query,highlight,aggs'); 
                $log::info("Suggest show (SUG) [$search_terms]", get_class()); 
 
            } 

            if(($show_suggest === true) and empty($this->checkISBNQuery())){

                if( $this->config->get('dbm.elastic_suggest') ){ 
                    $suggest = $this->config->get('dbm.elastic_suggest'); 
                }else{ 
                    $suggest = null; 
                } 


                if($suggest){
                    Arr::del($suggest, "suggest-name.completion.skip_duplicates");
                    Arr::del($suggest, "suggest-title.completion.skip_duplicates");
                    $flat_suggest = json_encode($suggest, JSON_PRETTY_PRINT);
                    $flat_suggest = str_replace('search_terms', addslashes( $search_terms ), $flat_suggest); 
                    $flat_suggest = str_replace('search_query', addslashes( $search_query ), $flat_suggest); 

                    preg_match_all('/yyyymmdd\[(.*)?\]/',$flat_suggest, $matches);
                    foreach($matches[0] as $key=>$val){

                        $d = $matches[1][$key];
                        $dte = hf::displayDate('Ymd', $d);
                        $flat_suggest = str_replace($val, $dte, $flat_suggest);
                    }

                    $suggest = json_decode($flat_suggest, true);

                }

            }

            return $suggest;
        }


        /**
         *  getHighlight()
         *  - the highlight block is configured in the DBM
         */
        public function getHighlight(){

            $highlight = null;
            if($this->config->get('dbm.elastic_highlight_show') and $this->checkISBNQuery() == false){

                $log = $this->log;
                $log::info("Highlight is on.", get_class());
                $highlight = $this->config->get('dbm.elastic_highlight');

            }

            return $highlight;

        }



        /** Returns the whole SearchParser generated array
         * @return mixed
         */
        public function getQueryParams(){

            // Easiest qway to pass any config params thru to params
            //print_r($this->config);die;
            $this->set('qs_array', $this->config->get('url.qs_array'));
            $this->set('default_field_prefix', $this->config->get('dbm.default_field_prefix'));
            $this->set('default_cdata_output', $this->config->get('dbm.default_cdata_output'));
            $this->set('elastic_highlight_separator', $this->config->get('dbm.elastic_highlight_separator'));


            return $this->query_params;

        }
        
    }

