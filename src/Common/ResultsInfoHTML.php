<?php

    namespace MattyLabs\XMLAdapter\Common;

    use MattyLabs\XMLAdapter\Config;
    use MattyLabs\XMLAdapter\Helpers\Arr;
    use MattyLabs\XMLAdapter\Helpers\Array2XML;
    use MattyLabs\XMLAdapter\Logger\SimpleLogger;
    use MattyLabs\XMLAdapter\Helpers\HelperFunctions as hf;


    class ResultsInfoHTML extends ResultsInfo
    {

        /**
         * @var Config|false
         */
        protected Config $config;

        /**
         * @var SimpleLogger
         */
        protected SimpleLogger $log;


        public function create($tpl = ""){

            //print_r( $this->data );
            //print_r( $this->params );
            //print_r( $this->getSource() );
            $this->config = Config::instance();
            $this->log = new SimpleLogger();
            $results_info = $this->getResultsInfo();
            $results =  $this->getSource();

            if(empty($tpl)){
                $tpl = file_get_contents($this->config->get('params.site_root') . "/lib/elastic/test/templates/" . $this->config->get('dbm.html_output_tpl'));
            }

            $repeats = '';
            $repeats_block = hf::xmlfield($tpl, "fi_list", true, '');
            $repeats_item = hf::xmlfield($tpl, "fi_list", false, '');

            // Find out what fields & functions (<fv_> and <fn_>) the tpl contains
            $fields = explode('|', hf::getDelimStr($tpl, '<fv_', '>', false));
            $fn = preg_match_all('/(<fn_.*?>.*?<\/fn_.*?>)/', $tpl, $functions);

            // Interpolate Results List with <fv_> placeholders
            foreach($results as $record) {

                // Add any required fields
                $record['fv_dbm'] = $this->config->get('params.dbm');
                $record['fv_score'] = $record['score'];
                $record['fv_id'] = $record['id'];
                $repeat = $repeats_item;

                // interpolate all <fv_ fields with their values
                foreach ($fields as $f) {

                    $fval = Arr::val($record, "fv_$f");
                    if( is_array($fval)){

                        if ($f == "highlights"){
                            $txt = '';
                            foreach($fval as $k=>$v){
                                $txt.= "<!-- $k --> {$v['@cdata']} ";
                            }
                            $fval = $txt;

                        }else{
                            $fval = implode('|', $fval);
                        }

                    }
                    $repeat = str_replace("<fv_$f>", $fval, $repeat);

                }

                // run any <fn_functions></fn_functions>
                foreach($functions[0] as $fn){

                    preg_match('/<fn_(.*?)[ >]/', $fn, $matches);
                    $fn_name = $matches[1];
                    $fn_tag = hf::xmlfield($repeat, "fn_$fn_name", true, '|');

                    if(method_exists($this, $fn_name )){
                        $repeat = str_replace($fn_tag, $this->$fn_name($fn_tag), $repeat);
                    }else{
                        $repeat = str_replace($fn_tag,"<!-- missing <fn_function> [$fn_name] -->\r\n", $repeat);
                    }

                }

                $repeats .= $repeat;

            }

            $html = str_replace($repeats_block, $repeats, $tpl);

            // Interpolate the Results Info <fi_> placeholders. These appear outwith the repeating elements
            foreach($fields as $f){
                $html = str_replace('<fv_'.$f.'>', Arr::val($results_info, $f), $html);
            }

            return $html;


        }

        private function clean_pipes($tag){

            $val = hf::xmlfield($tag, "fn_clean_pipes", false);
            return str_replace('|', ', ', $val);

        }

        private function format_date($tag){

            $val = hf::xmlfield($tag, "fn_format_date", false);
            $att = hf::getAttribute($tag, 'fmt') ?: 'Y-M-d';
            return hf::displayDate($att, '', $val);

        }

        private function highlights($tag){

            $val = hf::xmlfield($tag, "fn_highlights", false);
            return $val;

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
                                $this->log::warning("Check your indexing: [$field_key] indexed with CDATA section and DBM config adds back CDATA via default_cdata_output param.", get_class());

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

            $error_array = [
                'errordetails' => [
                    'errorcode'     => $error_code ?: '',
                    'errormessage'  => $msg ?: "Search error",
                    'documentcount' => $this->getDocumentCount(),
                    'dbm_index'     => $this->params['dbm'],
                    'search_terms'  => $this->params['search_terms'],
                    'search_query'  => $this->params['search_query'],
                    'suggest_names' => $this->getSuggestions('suggest-name'),
                    'suggest_titles' => $this->getSuggestions('suggest-title'),
                ]
            ];

            return Array2XML::createXML('recordcollection', $error_array)->saveXML();

        }

    }