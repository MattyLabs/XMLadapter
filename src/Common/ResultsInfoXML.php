<?php

    namespace MattyLabs\XMLAdapter\Common;

    use MattyLabs\XMLAdapter\Logger\SimpleLogger;
    use MattyLabs\XMLAdapter\Helpers\Array2XML;


    class ResultsInfoXML extends ResultsInfo
    {

        private $output_array;

        /**
         * @var SimpleLogger
         */
        protected  $log;

        public function create(){

            //print_r( $this->data );
            //print_r( $this->params );
            //print_r( $this->getSource() );

            $this->log =  new SimpleLogger();

            if($this->getDocumentCount() == 0){
                return $this->throwError("Search didn't find any matches", "EL666");
            }

            $this->output_array = [
                'resultsetinformation' => $this->getResultsInfo(),
                'resultfields' => $this->getSource()
            ];

            //print_r($this->output_array);//die;
            $xml = Array2XML::createXML('resultscollection', $this->output_array);
            $output = $xml->saveXML();
            $output = str_replace('&amp;#', '&#', $output); // this really shouldn't be needed!
            /* $output = str_replace('<?xml version="1.0" encoding="utf-8" standalone="no"?>', '', $output); */
            $output = str_replace('<?xml version="1.0" encoding="utf-8" standalone="no"?>', '<?xml version="1.0" encoding="UTF-8"?>', $output);

            return $output;

        }

        private function getSource(){

            $page = [];
            //todo:: see what's mawkes sense to set $basepath to
            if(!empty($_SERVER['HTTP_HOST'])){
                $basepath = 'https://' . $_SERVER['HTTP_HOST'] . '' . $_SERVER['PHP_SELF'] . '';
            }else{
                $basepath = '';
            }

            // record loop
            foreach( $this->data['hits']['hits'] as $key=>$record){

                $source = []; $add = [];
                $log = $this->log;
                ksort($record['_source']);
                // field loop
                foreach($record['_source'] as $k=>$v){

                // By default real field names are prefixed "fv_" in the XML output
                    if(!empty($this->params['default_field_prefix'])){
                        $field_key = "fv_$k";
                    }else{
                        $field_key = $k;
                    }
                    $source[$field_key] = $v;

                // Preserve CDATA Sections that may NOT have been indexed as a nested field
                    if(isset($this->params['default_cdata_output'])){

                        if(in_array($k, $this->params['default_cdata_output'])){

                            if( isset($source[$field_key]['@cdata']) ){
                            // don't add it back - issue a warning
                                $log::warning("Check your indexing: [$field_key] indexed with CDATA section and DBM config adds back CDATA via default_cdata_output param.", get_class());

                            }else{

                                $source[$field_key] = ['@cdata' => $v];

                            }

                        }

                    }

                }   // end fields loop


            // ID
                $add['id'] = $this->params['from']+1 + $key;
                // Score %
                if ($record['_score'] > 0 && $this->data['hits']['max_score'] > 0) {
                    $score = number_format(($record['_score']/$this->data['hits']['max_score'])*100);
                } else {
                    $score = 0;
                }

                $add['score'] = $score . '%';

            // Add Title Detail Link, id & score as %
                $qs_array = $this->params['qs_array'];
                $qs_array['k'] = $source['fv_ref_no'];
                $qs = http_build_query($qs_array);
                $add['titleurl'] = ['@cdata' => "$basepath?$qs" ];

            // Highlights
                if ( !empty($record['highlight']) )  {

                    //$separator = $this->params['elastic_highlight_separator'] ?? '<span class="highlight-separator"> ... </span>';
                    $separator = @$this->params['elastic_highlight_separator'] ?: '<span class="highlight-separator"> ... </span>';

                    foreach ( $record['highlight'] as $hk=>$hv ) {

                        $nk = str_replace('.@cdata', '', $hk);
                        $hv = str_replace( array("'", '&apos', '’', '•'), '', $hv);
                        $add['fv_highlights'][$nk]['@cdata'] = implode($separator, $hv);

                    }

                }

            // Inner Hits
                if( !empty($record['inner_hits']) ){

                    $inner_hits = $record['inner_hits']['related_titles']['hits']['hits'];
                    foreach($inner_hits as $inner_hit){

                        ksort($inner_hit['_source']);
                        $add['fv_inner_hits'][] = $inner_hit['_source'];

                    }

                }

                //print_r($add);//die;
                //print_r($source);die;
                $page[] = array_merge($add, $source);

            }   // end record loop

            return $page;

        }


        private function throwError($msg, $error_code){
//print_r($this->params);//die;

            $error_array = [
                'errordetails' => [
                    'errorcode'     => $error_code ?: '',
                    'errormessage'  => $msg ?: "Search error",
                    'documentcount' => $this->getDocumentCount(),
                    'dbm_index'     => @$this->params['dbm'] ?: "DBM missing!",
                    'search_terms'  => $this->params['search_terms'],
                    'search_query'  => $this->params['search_query'],
                    'suggest_names' => $this->getSuggestions('suggest-name'),
                    'suggest_titles' => $this->getSuggestions('suggest-title'),
                ]
            ];

            return Array2XML::createXML('recordcollection', $error_array)->saveXML();

        }

    }