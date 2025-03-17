<?php

/**	
*  	
*
*/

    $tt1 = microtime(true);
	$params = require __DIR__ . "/php.server.defaults.inc";
	require __DIR__ . "/php.main.functions.V3.inc";
	
	$params['qs'] = get_query_string(true);     
    $tt2 = microtime(true);
    $autoload_time =  "AutoLoad: [". number_format($tt2-$tt1, 4) ."] seconds";
	
	ini_set("zlib.output_compression", "On");
	ini_set("zlib.output_compression_level", "3");

    use MattyLabs\XMLAdapter\XMLAdapter;
	use MattyLabs\XMLAdapter\Logger\SimpleLogger;
	
    /*
     * SimpleLogging
     */
    $log = new SimpleLogger();
	$log::info($autoload_time, 'Autoload');
    $log::time('PAGE');
    $log::info('Beginning script', 'PAGE');

	// The Adapter needs 'elastic_client_config' as a minimum. Reset here OR (best) just pass thru php.server.defaults.inc::$params[] (loaded via mandatory.inc)
	/*
	$add_params = [
		//'idx' => "$indexes",	// will reset indexes to be searched
		//'dbm' => "{$params['sitename']}-main", //will reset DBM
		//'elastic_client_config' => $params['elastic_client_config'],	// resets hosts set i) php.server.defaults.php, ii) DBM
		
			],
			'basicAuthentication' => [ 'mattyp', 'Abc12345' ],	// => [ $params['username'], $params['password'] ],

		],
	];
	*/
	//$add_params = [];	// OR rely on XMLAdapter to try to work out the local cluster - OR set it via the DBM
	
	
    /**
     *  XMLAdapter carries out search
     *  @param request		[basically the url and querystring - usually picked up from the page querystring 
							 i.e. $_SERVER['QUERY_STRING']. If we want to accept POSTs then pass $_POST into 
							 the XMLAdapter as the first @param ]
	 *	@param add_params 	[params.script_filename|params.elastic_client_config]
     *
	 *	->search([raw|xml|json|json2|doc|html] 'raw' = PHP array(). ToDo xml1 + ONIX3
     */
	
	$xmla = new XMLAdapter('', $params);	// [ $params|$add_params|[] ]
	//print_r($xmla);
	$view = @$params['view'] ?: '';
	
	if( empty($view) or $view == 'xml'){
		
		header("Content-type: text/xml; charset=utf-8");
		echo $xmla->search('xml');
	
	}elseif($view == 'doc'){
		
		header("Content-type: text/xml; charset=utf-8");
		echo $xmla->search('doc');
		
	}elseif($view == 'json'){
		
		header('Content-Type: application/json; charset=utf-8');
		echo $xmla->search('json');
		
	}elseif($view == 'json2'){
		
		header('Content-Type: application/json; charset=utf-8');
		echo $xmla->search('json2');
	
		
	}elseif($view == 'raw'){
		
		header('Content-Type: text/plain; charset=utf-8');
		echo $xmla->search('raw');
		
	}elseif($view == 'html'){
		
		header('Content-Type: text/html; charset=utf-8');
		echo $xmla->search('html');
		
	}
		

    /**
     * 	Debug if you want to: Simple set &DEBUG=on in the query_string and pass as param
	 *	@param $_SERVER['QUERY_STRING'] or '&DEBUG=on,xmla,query,highlight,must,should,aggs,etc.'
     */
    
	$log::info("Script finished.", 'PAGE');
	$log::timeEnd('PAGE');
	echo $log::dump_to_string(@$_SERVER['QUERY_STRING']);


