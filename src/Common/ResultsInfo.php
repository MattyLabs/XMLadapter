<?php

    namespace MattyLabs\XMLAdapter\Common;

    use MattyLabs\XMLAdapter\Helpers\Arr;

    class ResultsInfo{

        protected $output;

        protected $data;

        protected $params;

        public function __construct($data, $params){

            $this->data = $data;
            $this->params = $params;

            //print_r($data);die;

        }

        public function getResultsInfo(){

            //print_r($this->params);//die;
            $this->output = [
                    'search_terms'      => $this->params['search_terms'] ,
                    'search_filters'    => $this->getFilters(),
                    'search_query'      => $this->params['search_query'] . " " . $this->getFilters('range'),
                    'suggest_names'     => $this->getSuggestions('suggest-name'),
                    'suggest_titles'    => $this->getSuggestions('suggest-title'),
                    'collapse_info'     => $this->getCollapseInfo(),
                    'max_score'         => $this->data['hits']['max_score'],
                    'time_taken'        => $this->data['took']/1000,

            ];
            $this->output += $this->getPagination();
            if( !empty($this->data['aggregations']) ){
                $this->output['distinct_fields'] = $this->getAggs($this->data['aggregations']) ?: '';
            }else{
                $this->output['distinct_fields'] = '';
            }

            return Arr::filterBlanks($this->output);

        }

        private function getTotalHits(){

            $ret = '';
            if( isset($this->data['hits']['total']['value']) ){
                // v.7+
                $ret = $this->data['hits']['total']['value'];

            }elseif(isset($this->data['hits']['total'])){

                $ret = $this->data['hits']['total'];

            }

            return $ret;

        }

        protected function getDocumentCount(){

            if( Arr::searchKeys($this->data,"inner_hits") ){

                return Arr::val($this->data, 'aggregations.total_collapsed_docs.value');

            }else{

                return $this->getTotalHits();

            }

        }

        private function getCollapseInfo(){

            $ret = [];
            //print_r( Arr::searchKeys($this->data,"inner_hits") );die;
            if( Arr::searchKeys($this->data,"inner_hits")){

                $ret = [
                        'collapse_count' => Arr::val($this->data, 'aggregations.total_collapsed_docs.value'),
                        'total_count' => $this->getTotalHits()
                ];

            }

            return $ret;

        }

        /**
         * @return array
         */
        protected function getPagination(): array
        {
            $arr = [];
            if(!empty($_SERVER['HTTP_HOST'])){
                $basepath = 'https://' . $_SERVER['HTTP_HOST'] . '' . $_SERVER['PHP_SELF'] . '';
            }else{
                $basepath = '';
            }

            $pl = $this->params['size'];
            $dc = $this->getDocumentCount();

            if($pl > $dc){$pl = $dc;}
            $doc_start = $this->params['from']+1;
            $doc_end = $doc_start + ($pl-1);
            if($doc_end > $dc){$doc_end = $dc;}
            $next_page = ($doc_end >= $dc) ? '' : $doc_start + $pl;
            $prev_page = (($doc_start-$pl) <= 0) ? 1 : $doc_start - $pl;

            // build the array we want
            $arr['pagelength'] = $pl;
            $arr['documentcount'] = $dc;
            $arr['documentstart'] = $doc_start;
            $arr['documentend'] = $doc_end;
            $arr['nextpage'] = $next_page;
            $arr['previouspage'] = $prev_page;
            $arr['lastpage'] = $this->pageLast($dc,$pl);

            $qs_array = $this->params['qs_array'];
            $qs_array['m'] = 1;
            $qs = http_build_query($qs_array);
            $arr['firstpage_url'] = ($doc_start > 1) ? ["@cdata" => "$basepath?$qs"] : '';

            $qs_array['m'] = $prev_page;
            $qs = http_build_query($qs_array);
            $arr['previouspage_url'] = ($doc_start > 1) ? ["@cdata" => "$basepath?$qs"] : '';

            $qs_array['m'] = $next_page;
            $qs = http_build_query($qs_array);
            $arr['nextpage_url'] = ($doc_end < $dc) ? ["@cdata" => "$basepath?$qs"] : '';

            $qs_array['m'] = $this->pageLast($dc, $pl);
            $qs = http_build_query($qs_array);
            $arr['lastpage_url'] = ($doc_end < $dc) ? ["@cdata" => "$basepath?$qs"] : '';

            return $arr;

        }

        protected function getSuggestions($suggest_field, $source_field = 'text'){

            if( !empty( Arr::val($this->data, "suggest.$suggest_field.0.options") )){

                $suggests = array();
                $options = Arr::val($this->data, "suggest.$suggest_field.0.options");
                if(!empty($options)){

                    foreach($options as $arr){

                        if(!empty( Arr::val($arr, $source_field) )){
                            $test = Arr::val($arr, $source_field);
                            if(is_array($test)){
                                // contriubutor field can be either a pipe separated list or an array of values and can also be not the reason for the match!
                                foreach($test as $val){
                                    $suggests[] =  $val;
                                }
                            } else {
                                $suggests[] =  Arr::val($arr, $source_field);
                            }

                        }

                    }
                }

                $suggests = array_unique($suggests, SORT_STRING); // for pre v7.0

            }

            return !empty($suggests) ? implode('|', $suggests) : '';

        }

        /**
         * @param $arr
         * @param string $separator
         * @return array
         */
        private function getAggs($arr, $separator = '|'){

            $res = array();

            if( !empty($arr) ){

                foreach ($arr as $key=>$val) {

                    if(!empty($val['buckets']) ){

                        foreach ($val['buckets'] as $k=>$v) {

                            if ($key == 'average_rating') {
                                @$res[$key] .= '<' . $v['key'] . ' data-count="' . $v['doc_count'] . '">' . $v['avg_rating']['value'] . '</' . $v['key'] . '>';
                            } else {
                                @$res[$key] .= ($v['key_as_string'] ?? $v['key']) . ' [' . $v['doc_count'] . ']' . $separator;
                            }

                        }
                        $res[$key] = rtrim($res[$key], "$separator");

                    }
                    // should be just a value count
                    if(!empty($val['value']) ){

                        $res[$key] = ' [' . $val['value'] . ']' ;

                    }
                }

            }

            return $res;

        }


        /**
         *
         * @param null $filter  [|range]
         * @return string       e.g. (thema_tree:(G))|(sort_date:(20220428~20210821))
         */
        private function getFilters($filter = null){

            $filter_str = '';
            if(empty($this->params['search_filters_array'])){ return null;}
            foreach( Arr::array_dot($this->params['search_filters_array']) as $key=>$value ){

                $type = explode('.', $key)[1];

                if($type == 'range'){

                    preg_match("/$type\.(.*?)\./", $key, $matches);
                    //$range_field = $matches[1] ?? '';
                    $range_field = @$matches[1] ?: '';
                    if(preg_match("/lte/", $key)){ $lte = $value;}
                    if(preg_match("/gte/", $key)){ $gte = $value;}

                }else{

                    $field = explode("$type.", $key)[1];
                    $val = $value;
                    $filter_str .= "($field:($val))|";
                }

            }

            if($filter == 'range' and !empty($range_field)){
                return "($range_field:($lte~$gte))";
            }else{
                return trim($filter_str, "[|]");
            }

        }


        /**
         * @param $doc_count
         * @param $pagelength
         * @return int
         */
        private function pageLast($doc_count, $pagelength){

            if ($doc_count == 0 ) {
                return 0;
            }
            if ($pagelength == 0 ) {
                return 0;
            }
            $mod_pages = intval($doc_count) % intval($pagelength);
            if ($mod_pages == 0) {
                $mod_pages = $pagelength;
            }

            return intval(($doc_count - $mod_pages) + 1);

        }


    }