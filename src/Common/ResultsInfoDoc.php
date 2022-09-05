<?php

    namespace MattyLabs\XMLAdapter\Common;

    use MattyLabs\XMLAdapter\Config;
    use MattyLabs\XMLAdapter\Helpers\Arr;
    use MattyLabs\XMLAdapter\Helpers\Array2XML;

    class ResultsInfoDoc extends ResultsInfo
    {

        /**
         * @var Config|false
         */
        protected $config;

        public function create(){

            $this->config = Config::instance();

            if($this->getDocumentCount() == 0){
                return $this->throwError("Search didn't find any matches", "EL666");
            }elseif (($this->getDocumentCount() > 1) ){
                /* or we jsut show the first one */
                //return $this->throwError("VIEW=doc rturns only 1 Doc at a time", "EL999");
            }

            //print_r($this->data);die;
            $record = $this->data['hits']['hits'][0];
            $source = $record['_source'];
            $source = Arr::filterBlanks($source);

            ksort($source);

            if(!empty($this->config->get('dbm.default_cdata_output')) ){
                foreach($source as $k=>$v){
                    if(in_array($k, $this->config->get('dbm.default_cdata_output')) ) {

                        $source[$k] = ['@cdata' => $v];
                    }
                }
            }
            $xml = Array2XML::createXML('record', $source);
            $output = $xml->saveXML();
            $output = str_replace('&amp;#', '&#', $output); // this really shouldn't be needed!
            /*  $output = str_replace('<?xml version="1.0" encoding="utf-8" standalone="no"?>', '', $output);*/
           $output = str_replace('<?xml version="1.0" encoding="utf-8" standalone="no"?>', '<?xml version="1.0" encoding="UTF-8"?>', $output);

            return $output;

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
                    'suggest_titles' => $this->getSuggestions('suggest-title', '_source.ctitle'),
                ]
            ];

            return Array2XML::createXML('recordcollection', $error_array)->saveXML();

        }

    }