<?php

    namespace MattyLabs\XMLAdapter\Common;

    class ResultsInfoJSON extends ResultsInfo
    {

        private $output_array;

        public function create(){

            if($this->getDocumentCount() == 0){
                return $this->throwError("Search didn't find any matches", "EL666");
            }

            $this->output_array['resultsetinformation'] = $this->getResultsInfo();
            if(method_exists($this->data, 'asArray')){
                $this->output_array += $this->data->asArray();
            }else{
                $this->output_array += $this->data;
            }

            $json = json_encode($this->output_array, JSON_PRETTY_PRINT);

            return $json;

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

            return json_encode($error_array, JSON_PRETTY_PRINT);

        }

    }