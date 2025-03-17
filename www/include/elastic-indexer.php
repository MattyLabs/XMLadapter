<?php
/**
	
*/
	

	$params = require_once __DIR__ . '/php.server.defaults.inc';
	require_once __DIR__ . "/php.main.functions.V3.inc";
	require_once __DIR__ . "/xml2array.php";
	require_once __DIR__ . "/array2xml.php";

	set_time_limit(0);
	$tt1 = timer();
	
/*	Get args from CMD line or URL..
	- you can override the defaults (<sitename>-config.inc) via the command line args [ -- key_fields=plop delimiter=plap ]
 	- you can also run the script direct from the web using &name=value pairs :)
*/
	if(!empty($argv)){ parse_str(implode('&', $argv), $args); }
	
	if(!empty($_REQUEST)){ 	
	
		$args = $_REQUEST;	
		$args = array_change_key_case($args);
		
	}

	if(!empty($args)){
	
		$params = array_merge($params, $args);
		
	}
	
// LOGGING
	$params['log_folder'] = "{$params['www_root']}/{$params['sitename']}/logs";	// Sets where elastic-index.php will log to

// If script started by Web page, it will pass in log_file - otherwise name = DBM_AX_ - we need to add the date as expected..
	//if(empty($params['log_file'])){ $params['log_file']	= @$params['dbm'] .'_' . $params['ax']; }
	//if(empty($_REQUEST)){ $params['log_file']	= @$params['dbm'] .'_' . @$params['ax']; }
	if(empty($params['log_file'])){ $params['log_file']	= @$params['dbm'] .'_' . @$params['ax']; }
	$params['log_file'] = strtoupper($params['log_file'] . '_' . DisplayDate(rdate(0), 'Ymd' )) .'.log';
	$params['log_path']	= $params['log_folder'] . '\\' . $params['log_file'];
	
	//$x = print_r($params,true); echo $x;
	//logit('eek', $x);die;
	
	
// Lets get started..
	$response = response_init(); $www_debug = "\r\n\r\n";
	logit();logit('STARTING SCRIPT..', strtoupper( (@$argv[0]?:'') ));logit();
	logit('Script version:', '3.0.0');
	logit('log_file', $params['log_file']);
	
	if(@$params['dbm']) {
	
		$dbm_config = include("{$params['www_root']}/{$params['sitename']}/include/config/{$params['dbm']}-dbm.inc");
		
	} else {
		
		logit('WARNING!', 'No DBM supplied. DBM.inc config file NOT loaded.');
		logit('-', 'This is OK for cat and alias');
		
	}

	if(!empty($dbm_config) && is_array($dbm_config) ) {
	
		$params = array_merge($params, $dbm_config);
		logit('Config loaded', 'Index: [' . $params['dbm_index'] . '] Type: [' . $params['dbm_type'] . ']');
		
	} else {
	
		logit('N.B. No DBM.inc config loaded');
		$params['dbm'] = ''; $params['dbm_index'] = '';
		
	}
	
	if(!empty($args)){
	
		$params = array_merge($params, $args);
		
	}
	
	//print_r($params['dbm_elastic_hosts']);
	//print_r( json_encode($params['elastic_client_config']));die;
	if( !empty($params['hosts'])){
		// it came thru as JSON via CMD prompt
		$hosts = str_replace("'", '"', $params['hosts']);
		logit('hosts override:', $hosts);
		$hosts = json_decode($hosts, true);
		$x = json_error($explain);
		logit('..checking hosts param', $explain);
		$params['dbm_elastic_hosts'] = $hosts['dbm_elastic_hosts'];
	}
	
	
	if(!empty($params['dbm_elastic_hosts']) && !is_array($params['dbm_elastic_hosts'])){
	// make it into an array
		$tmp = $params['dbm_elastic_hosts'];
		$params['dbm_elastic_hosts'] = [ $tmp ];
	}
	
	if(!empty($params['elastic_client_config']) ) {
	// load the default elastic_client_config from php.server.defaults.inc [used to be stored in elastic-config.inc]
	// sets default hosts and basicAuthentication
		$config = $params['elastic_client_config'];
		
	// Update the elastic hosts: First if specified in DBM, otherwise keep from default elastic_client_config
		if(!empty($params['dbm_elastic_hosts'])){
			$config['hosts'] = $params['dbm_elastic_hosts'];
		}
		$params['active_hosts'] = implode(',', $config['hosts']);
		logit('Default hosts:', $params['active_hosts']);
		
	// Update LIVE indexes via the elastic hosts
		if(empty($params['ax']) ){
			
			logit('Action code missing [ax=]. Quitting');
			$response['errorcode']	=	98;
			$response['errormsg']	=	'';
			echo response_write($response);
			show_help();
			exit;
			
		}elseif( preg_match('/-live$/', $params['ax']) ){
			
		// Override $config['hosts']	
			if( !empty($params['elastic_indexer_live_hosts']) ){
				// Set $params['elastic_indexer_live_hosts'] in the DBM
				$params['active_hosts'] = implode(',', $params['elastic_indexer_live_hosts']);
				logit('Switching indexer to LIVE hosts:', $params['active_hosts']);
				// !!! Some old DBMs don't specify a protocol; which will default to HTTPS now
				$config['hosts'] =  $params['elastic_indexer_live_hosts'];
				
			} else {
				
				logit('Elastic-Indexer Live Hosts not configured. Quitting');
				$response['errorcode']	=	98;
				$response['errormsg']	=	'Live hosts not set. Check DBM: [elastic_indexer_live_hosts]';
				echo response_write($response);
				show_help();
				exit;
				
			}
			
		}
		
	// Check the cluster for available hosts and update the hosts [Or just use clean_hosts_port($config)]
		check_hosts_avail($config);	
		
	// FIND OUT WHICH VERSION OF ELASTIC IS IN PLAY =>> $params['es_version']:: SET this in php.Server.Defaults or the DBM
		logit("active hosts: ", $params['active_hosts']);
		
		$params['es_version'] = get_version();
		logit('Elasticsearch version mumber', $params['es_version']);
		
		
		if(!empty($params['dbm_elastic_cloud']['elasticCloudId']) ){
			
			$config['elasticCloudId'] = $params['dbm_elastic_cloud']['elasticCloudId'];
			$config['basicAuthentication'] = [ $params['dbm_elastic_cloud']['username'], $params['dbm_elastic_cloud']['password'] ];
			//print_r($config); die;
			
		}
		//print_r($config); die;
		
		
	

	}
	

// LOAD any maps you need e.g. see BIC/THEMA etc for make_xml2() - set them in the -DBM.inc file
	if(!empty($params['require_map_hashes'])){
		foreach( $params['require_map_hashes'] as $k=>$v){
			logit($v);
			$$k = require_once($v);
		}
	}

	if(!empty($_SERVER)){ 	
	
		logit("Requested by:", @$_SERVER['REMOTE_ADDR'] );
		// ToDo: ONLY to permit local requests..
		check_request_authorised('START');
		
	}
	
	
	//ksort($params);
	//print_r($params['data']); die;
	$params['success_count'] = 0;
	
	
	switch (strtolower(@$params['ax']))	{
	
		case 'indexdelete':
		case 'index-delete':
		case 'index-delete-live':
			$x = fnIndexDelete();
			echo response_write($response);
			break;

		case 'indexcreate':
		case 'index-create':
		case 'index-create-live':
			$x = fnIndexCreate();
			echo response_write($response);
			break;
			
		case 'index-rebuild':
		case 'index-rebuild-live':
			$x = fnIndexDeleteCreate();
			echo response_write($response);
			break;
			
		case 'index-dump':
		case 'index-dump-live':
			$x = fnIndexDump();
			if(@$params['path'] == 'return'){
				
				/* special case for dumping 1 record from the index and returning to requester
					e.g. [/lib/elastic/elastic-indexer.php?dbm=bds-main&ax=index-dump&data=9780857834607&path=return&format=json]
				*/
				echo $x;
				
			} else {
				
				echo response_write($response);
				
			}
			break;
			
		case 'indexupdate':
		case 'index-update':
		case 'index-update-live':
			$x = fnIndexUpdate();
			echo response_write($response);
			break;
			
		case 'indexupdatethis':
		case 'index-update-this':
		case 'index-update-this-live':
			//logit("Index-Update-This", "DBM=site-db AX=index-update-this DATA=9781234567890 ");
			$x = fnIndexUpdateThis();
			echo response_write($response);
			break;

	// Index a list of full file paths (expects XML2)
		case 'indexfiles':
		case 'index-files':
			$x = fnIndexFilesByRefXML2();
			echo response_write($response);
			break;
			
		case 'indexthis':
		case 'index-this':
		case 'index-this-live':
			$x = fnIndexThis();
			echo response_write($response);
			break;
		
		case 'index-upd-query':
			
			$x = fnUpdateByQuery();
			echo response_write($response);
			break;
			
		case 'indexpartial':
		case 'index-partial':
		case 'index-csv':
			$x = fnIndexPartialUpdate();
			echo response_write($response);
			break;
			
		case 'indexpartialxml2':
		case 'index-partialxml2':
			$x = fnIndexPartialUpdateXML2();
			echo response_write($response);
			break;
			
		case 'filedelete':
		case 'delete-file':
			$x = fnFileDelete();
			echo response_write($response);
			break;
		
		case 'delete-this':
		case 'delete-this-live':
			$x = fnDeleteThis();
			echo response_write($response);
			break;
			
		case 'getsettings': 
			$x = fnGetSettings();
			echo response_write($response);
			break;
		
		case 'stats': 
			$x = fnStats();
			
			echo response_write($response);
			break;	

		case 'cat':
		case 'cat-aliases':		
			$x = fnCat();
			
			echo response_write($response);
			break;		
		
		case 'analyze': 
			$x = fnAnalyze();
			
			echo response_write($response);
			break;		
			
		case 'getmapping': 
		case 'get-mapping': 
		case 'getmapping-live': 
			$x = fnGetMapping();
			echo response_write($response);
			break;		
		
		case 'settings': 
		case 'settings-live': 
		case 'settings-put': 
		case 'settings-put-live': 
			$x = fnPutSettings();
			echo response_write($response);
			break;	
			
		case 'cluster-stats': 
		case 'cluster-settings': 
			$x = fnCluster();
			echo response_write($response);
			break;		
			
		case 'alias-add': 
		case 'alias-get': 
		case 'alias-remove': 
		case 'alias-repoint': 
		case 'alias-add-live': 
		case 'alias-remove-live': 
		case 'alias-repoint-live': 
			$x = fnAlias();
			echo response_write($response);
			break;	


		case 'refresh':
			$x = fnRefresh();
			echo response_write($response);
			break;	
			

	// case we done :)
		default: 
			$response['errorcode']	=	99;
			$response['errormsg']	=	'Invalid action code: ax';
			echo response_write($response);
			show_help();
			break;
	}

	// EMAIL LOG...N.B. see function for mailing list
	if(!empty($params['log_path'])) {
		if(strpos($params['log_path'], 'MAIN_INDEX')) {
			//email_out($params['log_path']);
		}
		
	}

	$tt2 = timer();
	logit();
	logit('total time (secs):', number_format(($tt2-$tt1),0));
	logit('script finished successfully');

/*******************************************************************************/
	if (@$params['debug'] == 'on') { 

		if(!empty($_REQUEST)){ 
		
			$www_debug .= "\r\n" . sprint_hash($params);
			echo "\r\n<!-- " . $www_debug . ' -->';
			
		} else {
			
			logit(); logit();
			echo sprint_hash($params);
			
		}
		
	}
/*******************************************************************************/
/*
	$params[]
	- dbm=sitename-db
	- dump-path=d:/dtsearch/bds/acs-db/data	[defaults to $params['data_source_path']/data from DBM file|'return'|If  csv then defaults to /Admin/FTP/download/]
	- dump-format=xml|json|csv				[defaults to XML so's we can re-index via the CMS's]
	- dump-data								[send in a ref_no to dump a single record. Set path = return]
	- dump-date-range						[days before:after today e.g. 90:0 - (depends on sort_date field format: yyyymmdd)]	
	- dump-query							[query_string query syntax search query]
	- dump-fields							[csv only. comma separated list of fields to export]
	- dump-csv-format						[csv|tsv|ansi - defaults to tsv]
	- dump-size								[defaults to 1000]
	- dump-sort								[sort field e.g. 'sort_date/d' - ONLY with a dump-query]
	
	e.g. php elastic-indexer.php dbm=sitename-main ax=index-dump data="9781783304745 OR 9781783304752" path="d:/temp" [|date-range=90:0]
	
	e.g. php elastic-indexer.php dbm=sitename-main ax=index-dump dump-format=csv dump-csv-format=tsv dump-fields=ref_no,ctitle,contributor,format,uk_vat_price,descrip,bic_subject,bic_subj_code 

*/
	function fnIndexDump(){
		
		global $config, $params, $response;
		$params['success_count'] = 0;
		$params['failed_count'] = 0;
		$dump_count = 0;
		$dump_size = @$params['dump-size'] ?: 1000;
		
	// @params updated with 'dump-' prefix for saving defaults to DBM
		$data = @$params['dump-data'] ?: @$params['data'] ?: '';
		$date_range = @$params['dump-date-range'] ?: @$params['date-range'] ?: '';
		//$fields = @$params['dump-fields'] ?: @$params['fields'] ?: 'ref_no';
		//$format = @$params['dump-format'] ?: @$params['format'] ?: 'xml';
		$path = @$params['dump-path'] ?: @$params['path'] ?: "{$params['data_source_path']}/data";
		$dump_query = @$params['dump-query'] ?: @$params['query'] ?: '';
		
		
		if( !empty($data) ){
			
			$query = [ 
				"query" => [
					"query_string" => [
					  "query" => "(ref_no :($data))"
					]
				]
			];
		
		} elseif( !empty($date_range) ){
			
			if( strpos($date_range, ':') !== false ){
			
				$gte = 0 - (explode(':', $date_range)[0]);
				$lte = (explode(':', $date_range)[1]) + 1;
				logit("Dumping records within range:", DisplayDateTime('jS F Y', $gte) . " <> " . DisplayDateTime('jS F Y', $lte) );
				
			} else {
				
				$response['errorcode']	=	99;
				$response['errormsg']	=	'Invalid date range e.g. [date-range=30:0]';
				echo response_write($response);
				return;
				
			}
			
			$query = [
				"query" => [
					"range" => [
						"sort_date" => [
							"lte" => DisplayDateTime("Ymd", $lte),
							"gte" => DisplayDateTime("Ymd", $gte),
						]
					]
				]
			];
		} elseif( !empty($dump_query) ){	
		// we just expecting the "(ctitle:( terms ))" bit - use query_string query syntax.
		
			$sort = Array();
			if( !empty($params['dump-sort']) ){
				$d = (preg_match('/\/d/', $params['dump-sort'])) ? 'desc' : 'asc';
				$s = explode('/', $params['dump-sort'])[0];
				$sort = [
					"sort" => [
						"$s" => [
							"order" => "$d",
							"mode" => "min",
							"missing" => "_last"
						]
					]
				];
			}
			
			$query = [ 
				"query" => [
					"query_string" => [
					  "query" => $dump_query,
					  "default_operator" => "AND"
					]
				],
				"sort" => @$sort['sort'],
			];
			
			if( empty($sort) ){ $query['sort'] = "_doc";}
			//print_r($query);die;
		
		} else {
			
			$query = [
				"query" => [
					"match_all" => new \stdClass()
				],
				"sort" => "_doc",
			];
		}
		
		
		
		$batch = [
			"size" => $dump_size, 
			"query" => $query['query'],
			"sort" => $query['sort']
		];

		
	// prepare json for submission:
		$batch_json = json_encode($batch, JSON_PRETTY_PRINT);
		$batch_json = "$batch_json\n";
		//$x = print_r($batch_json, true); echo "<!-- $x -->\r\n"; die;
				
	// submit the query
		$host = reset($config['hosts']);
		$rurl = "$host/{$params['dbm_index']}/_search?scroll=90s";	// 90s may be too long
		$options = ['request_type' => 'POST', 'timeout' => 30000];
		$json = getRest($rurl, $batch_json, $options);
		$resp = json_decode($json, true);
		//$x = print_r($resp, true); echo "<!-- $x -->\r\n"; //die;
		
	// check for REST API error
		if(json_error($explain)){
			logit("Error bulk_update: ", $explain);
			//return;
		}	
		
	// check for CURL error
		if(arr_val($resp, 'error_rest') == true){
			logit('CURL Error' . ' (' .arr_val($resp, 'errorcode') . ')', arr_val($resp, 'errormessage')  );
			//die;
			
		}

		// Now we loop until the scroll "cursors" are exhausted
		while (isset($resp['hits']['hits']) && count($resp['hits']['hits']) > 0) {

			// **
			// Do your work here, on the $resp['hits']['hits'] array
			// **
			//echo "eeek\r\n";
			//print_r($resp);
			$dump_count++;
			$dump_data = dump_records($resp);
			logit('..dumping', ($dump_count * $dump_size) );
			// When done, get the new scroll_id
			// You must always refresh your _scroll_id!  It can change sometimes
			$batch = [
				"scroll" => "90s",	// 90s may be too long
				"scroll_id" => $resp['_scroll_id']
			];
				

			// Execute a Scroll request and repeat
			$rurl = "$host/_search/scroll";	
			$batch = json_encode($batch, JSON_PRETTY_PRINT);
			$batch = "$batch\n";
			$options = ['request_type' => 'POST', 'timeout' => 30000];
			$json = getRest($rurl, $batch, $options);
			$resp = json_decode($json, true);
		
		}
		
		if( $path == 'return'){

			return $dump_data;
			
		}
		logit("Dumped success count", $params['success_count']);
		logit("Dumped failed count", $params['failed_count']);
		$response['errorcode'] = 0 ;
		$response['errormsg'] = "Dumped success count: [{$params['success_count']}]. Errors: [{$params['failed_count']}]";
		$response['logfile'] = $params['log_file'];
		$response['responsetext'] = "Script finished successfully. {$params['dbm_index']} records dumped: (" .$params['success_count'] . ') to: (' . $path .')';

	}
	
/*******************************************************************************/
	function dump_records($dump){
	
		global $params, $response;
		
		$records = $dump['hits']['hits'];

	// @params updated with 'dump-' prefix for saving defaults to DBM
		$fields 	= @$params['dump-fields'] ?: @$params['fields'] ?: '*';
		$format = @$params['dump-format'] ?: @$params['format'] ?: 'xml';
		$path = @$params['dump-path'] ?: @$params['path'] ?: "{$params['data_source_path']}";
		$csv_format = @$params['dump-csv-format'] ?: 'tsv';
		$xml = ''; $json = ''; $csv = '';
		//print_r($records);
		//logit("dumping to folder", $path);
		logit('fields', $fields);
		
		foreach($records as $idx=>$record){
			
			$ref_no = $record['_id'];
			$data = $record['_source'];
			$data = arr_filter_blanks($data);
			if( !empty($params['data_folder_num']) ){
				$sub_folder = "data/" . padz(substr($ref_no, -$params['data_folder_num']), $params['data_folder_num']+1);
			}else{
				$sub_folder = "data";
			}
			if(!file_exists("$path/$sub_folder")){mkdir("$path/$sub_folder", 0777, true);}
						
			//ksort($data); print_r($data);die;
			if( strtolower($format) == 'xml'){
				
				ksort($data);
				if(isset($params['default_cdata_output'])){
					foreach($data as $k=>$v){
						if(in_array($k, $params['default_cdata_output']) ) {
							
							$data[$k] = ['@cdata' => $v];
						}
					}
				}
				
				$dest = "$path/$sub_folder/$ref_no.xml";
				if($idx == 0){
					logit("writing files", $dest);
				}
				
				$xml = Array2XML::createXML($params['updates_node_in'], $data);
				$xml = $xml->saveXML();
				if($path == 'return'){
					return $xml;
				} else {
					$x = (file_put_contents($dest, $xml)) ? $params['success_count']++ : $params['failed_count']++;
				}
				
				
			} elseif( strtolower($format) == 'json'){
				
				$dest = "$path/$ref_no.json";
				//logit("writing file", $dest);
				$json = json_encode($data);
				if( $path == 'return'){
					return $json;
				} else {
					$x = (file_put_contents($dest, $json)) ? $params['success_count']++ : $params['failed_count']++;
				}
				
			} elseif( strtolower($format) == 'csv'){
				
				$delimiter = @$params['dump-delimiter'] ?: "\t";
				$path = @$params['dump-path'] ?: @$params['path'] ?: "{$params['www_root']}/{$params['sitename']}/admin/FTP/download";
				$dte = DisplayDateTime('Y-m-d');
				$filename = @$params['dump-filename'] ?: "DUMP-{$params['dbm_index']}-$dte.tsv";
				$dest = "$path/$filename";
				$keys = explode(',', $fields);
				$tmp = array();
				if(@$params['dump-modify'] == 'on'){
					modify_fields($data);
				}
				
				foreach($keys as $k){
					
				// Google and Facebook Merchant stores don't like empty descriptions or empty prices
					if(preg_match('/google|facebook/i', $filename)){
						
						if(empty(arr_val($data, 'description')) or empty(arr_val($data, 'price'))){
							// do not export
						}else{
							$tmp[] = str_replace("\t", '', arr_val($data, $k));
						}
						
					}else{
						
						$tmp[] = str_replace("\t", '', arr_val($data, $k));
						
					}
					
				}
				//print_r($tmp); die;
				
				if($csv_format == 'tsv'){	// THE DEFAULT: TAB DELIMITED
					
					$line = implode($delimiter, $tmp);
				// See php.main.functions.V3.inc for this one		
					$line = entities_to_unicode($line) . "\r\n";
										
				} elseif($csv_format == 'csv'){ 
				
					$dest = str_replace('.tsv', '.csv', $dest);
					$line = array2csv($tmp);
					
				} elseif($csv_format == 'ansi'){
				
					$line = implode($delimiter, $tmp);
				// Excel can't do UTF8 - needs ANSI	
					$line = iconv("UTF-8", "ISO-8859-1//TRANSLIT", $line) . "\r\n";
					
				} else {
				// simple clean tab separated
					$line = implode($delimiter, $tmp) . "\r\n";
					
				}
			
				//logit('line', $line); die;
				$csv .= $line;
				$params['success_count']++;
				$line = '';
				
			} 
			
		}
		
		if( strtolower($format) == 'csv'){
		// load all 500 into the csv output file in one go 
			if(!file_exists($dest)){
				
				$header_line = explode(',', $fields);
				$header_line = implode($delimiter, $header_line). "\r\n";
				logit("writing to file", $dest);
				$x = ( file_put_contents($dest, $header_line . $csv) );
				logit("writing file header", $fields);
				
			} else {
				
				logit("writing to file", $dest);
				$x = (file_put_contents($dest, $csv, FILE_APPEND)) ? $params['success_count'] : $params['failed_count'];
				$csv = '';
				
			}
			
		}


	}
// *************************************************************************** //	
	function modify_fields(&$data){
	
		global $params;
		
		
	/* Google Merchant Additional Links */
	// Re-Name fields (for the header line)
		$data['id'] 			= $data['ref_no'];
		$data['title'] 			= @$data['ctitle'] ?: '';
		$data['description'] 	= arr_val($data, 'descrip') ?: arr_val($data, 'description') ?: '';	// lenth restrictions
		$data['gtin'] 			= $data['barcode'];
		$data['price'] 			= @$data['argosy_eu_vat_price']  . ' EUR';
		$data['mpn'] 			= '';	// not required for books
		$data['brand'] 			= $data['barcode'];	// not required for Google but required for Facebook
		$data['condition']		= 'new';
		
	// Availability
		$avail_code = @$data['argosy_availability'] ?: '';
		if( preg_match("/$avail_code/", "10 12 20 21 22 23") ){ 

			if( preg_match('/facebook/i', $params['dump-filename']) ){
				
				$data['availability'] = 'in stock';
				
			}else{

			$data['availability'] = 'in_stock';

			}

		}else{
			
			if( preg_match('/facebook/i', $params['dump-filename']) ){
				
				$data['availability'] = 'out of stock';
				
		}else{

			$data['availability'] = 'out_of_stock';	
			
		}
		
			
			
		}
		
	// Product Link
		$data['link'] 		= "{$params['live_server_url']}/page/detail/?k={$data['ref_no']}";
		
	// Image link
		$sub_folder = substr($data['barcode'], 0, 6);
		$ImgSrcRef = $params['image_server_url'] . "/bds-images/l/$sub_folder/{$data['barcode']}.jpg";
		$data['image_link'] = $ImgSrcRef;
		
		//print_r($data);die;
		return $data;

	}
/*******************************************************************************/		
	function array2csv($data, $delimiter = ',', $enclosure = '"', $escape_char = "\\")
	{
		
		$f = fopen('php://memory', 'r+');
		fputcsv($f, $data, $delimiter, $enclosure, $escape_char);
		rewind($f);
		return stream_get_contents($f);
		
	}	
/*******************************************************************************/	
	function fnFileDelete(){
	/*
		This is configured for an NBD file containing a list of <ref_no>'s one per line 
	*/

		global $config, $params, $response;
		$success_count = 0;
		$failed_count = 0;
		$total_count = 0;
		$skipped_count = 0;
		$err = 0;

		logit('Delete records..', 'Index: ('.$params['dbm_index'] . ') Type: (' . $params['dbm_type'] .')');
		logit('..N.B. ', 'Records are deleted from the index but not the XML /data/ files');
		
		// Check request came from DEV server
		check_request_authorised('DELETE');
		
		logit('finding files..', $params['delete_files_folder']);
		
		$files = glob($params['delete_files_folder'] . '/*' );
		$files = preg_grep('@'. $params['delete_files_pattern']	.'@i', $files);
		
		logit('files found for processing', Count($files));

		foreach($files as $f){
		// process each file in sequence..
			
			$f = str_replace('\\', '/', $f);
			$lcount = 0;
			$batch = array();
			
			logit('processing file', $f);
				
		// read large files line by line
			$handle = fopen($f, "r");
			if ($handle) {
				
				while (($line = fgets($handle)) !== false) {
				// process the line read.
					$lcount ++;
					
					// We don't really want the double-quotes excel insists on
					$line = str_replace('"', '', $line);
					$key = trim($line);	// important to remove the line feeds too!	
					
					if($params['es_version'] >= 7){
						
						$batch['body'][] = [
							'delete' => [
								'_index' 	=> $params['dbm_index'],
								'_id'		=> $key
							]
						];	

					} else {
						
						$batch['body'][] = [
							'delete' => [
								'_index' 	=> $params['dbm_index'],
								'_type'		=> $params['dbm_type'],
								'_id'		=> $key
							]
						];		
						
					}
					
					if($lcount % $params['delete_files_chunk_count'] == 0) {
						
						logit('sending deletes', $lcount ); 
						
						// prepare ndjson for submission:
						$batch_json = get_ndjson($batch['body']);
						$batch_json = "$batch_json\n";
						//$x = print_r($batch_json, true); echo "<!-- $x -->\r\n"; die;
							
						$host = reset($config['hosts']);
						$options = ['request_type' => 'PUT', 'timeout' => 10000];
						$rurl = "$host/_bulk/";
						$json = getRest($rurl, $batch_json, $options);
						$resp = json_decode($json, true);
						//$x = print_r($resp, true); echo "<!-- $x -->\r\n"; //die;
						
						// check for REST API error
						if(json_error($explain)){
							logit("Error delete_records: ", $explain);
							//return;
						}	
						
						// check for CURL error
						if(arr_val($resp, 'error_rest') == true){
							logit('CURL Error' . ' (' .arr_val($resp, 'errorcode') . ')', arr_val($resp, 'errormessage')  );
							//die;
							
						}
						
						$report = parse_bulk_delete_resp($resp);
						$success_count += @$report['deleted'];
						$failed_count += @$report['not_found'];
							
						$batch = array();
						
					}
											
				}	//End Lines loop
				
				

			} else {
				// error opening the file.
				logit("error opening file", $f);
			} 
			fclose($handle);
			
			if($params['delete_files_archive']) {
				$archive = str_ireplace($params['delete_files_folder'], $params['index_files_archive_folder'], $f);
				logit('..archiving file', $f);
				rename($f, $archive);
			}
			
			
		}	//End File Loop
		
		// don't forget any remaining data!!
		if(!empty($batch)) {
			
			logit('sending final deletes', Count($batch['body']) ); 
			//print_r($batch); 
			
			// prepare ndjson for submission:
			$batch_json = get_ndjson($batch['body']);
			$batch_json = "$batch_json\n";
			//$x = print_r($batch_json, true); echo "<!-- $x -->\r\n"; die;
				
			$host = reset($config['hosts']);
			$options = ['request_type' => 'PUT', 'timeout' => 10000];
			$rurl = "$host/_bulk/";
			$json = getRest($rurl, $batch_json, $options);
			$resp = json_decode($json, true);
			//$x = print_r($resp, true); echo "<!-- $x -->\r\n"; //die;
			
			// check for REST API error
			if(json_error($explain)){
				logit("Error delete_records: ", $explain);
				//return;
			}	
			
			// check for CURL error
			if(arr_val($resp, 'error_rest') == true){
				logit('CURL Error' . ' (' .arr_val($resp, 'errorcode') . ')', arr_val($resp, 'errormessage')  );
				//die;
				
			}
				
			$report = parse_bulk_delete_resp($resp);
			$success_count += @$report['deleted'];
			$failed_count += @$report['not_found'];
	
			$batch = array();
			//print_r($err); 
		}
		
		logit('deleted: ', $success_count);
		logit('not found: ', $failed_count);
		
		$response['errorcode'] = (empty($err)) ? 0 : 1 ;
		$response['errormsg'] = (empty($err)) ? "Deleted success count: [$success_count] Not found: [$failed_count]": "Errors: view log";
		$response['logfile'] = $params['log_file'];
		$response['responsetext'] = "Script finished successfully. Index Updated (" .$params['dbm_index'] . ') Type: (' . $params['dbm_type'] .')';
		
		
	}

	/*******************************************************************************/
/*
	The idea is to send thru via GET or POST data for deleting a single record - dynamicall
	- data only needs to be the ref_no's - comma separated
	
	- e.g. php elastic-indexer.php dbm=<sitename>-cms ax=delete-this data=9871234567890	
	- e.g. 127.0.0.1/<sitename>/lib/elastic/elastic-indexer.php?dbm=bds-cms&ax=delete-this&data=9781234567890

*/
	function fnDeleteThis() {
	
		global $config, $params, $response, $www_debug;
		$data = array();
		$success_count = 0;
		$failed_count = 0;
		$total_count = 0;
		$skipped_count = 0;
		
		logit('Delete records..', 'Index: ('.$params['dbm_index'] . ') Type: (' . $params['dbm_type'] .')');
		logit('..N.B. ', 'Records are deleted from the index but not the XML /data/ files');

	// Check request came from DEV server
		check_request_authorised('DELETE');
		
	//print_r($www_debug); die;	
	// OK what have we been sent..
		if(!empty($params['data'])){
		
			$data = explode(',', $params['data']);

		} else {	
		
			logit('Error: no ref_no\'s');
			exit;
			
		}
	
	// OK ready to delete :)
		foreach ($data as $key){
			
			if($params['es_version'] >= 7){
					
				$batch['body'][] = [
					'delete' => [
						'_index' 	=> $params['dbm_index'],
						'_id'		=> $key
					]
				];	

			} else {
				
				$batch['body'][] = [
					'delete' => [
						'_index' 	=> $params['dbm_index'],
						'_type'		=> $params['dbm_type'],
						'_id'		=> $key
					]
				];	
				
			}
			
		}
				
		// prepare ndjson for submission:
		$batch_json = get_ndjson($batch['body']);
		$batch_json = "$batch_json\n";
		//$x = print_r($batch_json, true); echo "<!-- $x -->\r\n"; die;
						
		//print_r($batch);die;
		$host = reset($config['hosts']);
		$options = ['request_type' => 'PUT'];
		$rurl = "$host/_bulk/";
		$json = getRest($rurl, $batch_json, $options);
		$resp = json_decode($json, true);
		//$x = print_r($resp, true); echo "<!-- $x -->\r\n"; //die;
		
		// check for REST API error
		if(json_error($explain)){
			logit("Error delete_records: ", $explain);
			//return;
		}	
		
		// check for CURL error
		if(arr_val($resp, 'error_rest') == true){
			logit('CURL Error' . ' (' .arr_val($resp, 'errorcode') . ')', arr_val($resp, 'errormessage')  );
			//die;
			
		}
			
		$report = parse_bulk_delete_resp($resp);
		$success_count += @$report['deleted'];
		$failed_count += @$report['not_found'];
			
		$batch = array();	

		logit('deleted: ', $success_count);
		logit('not found: ', $failed_count);
		logit();
		//logit('Load files processed', $fcount);
		//logit('Lines submitted: ', $total_count);
		//logit('Lines skipped (No Ref_No):',	$skipped_count);
		//logit('Files rejected (reject_check()):',$rejected_count);
		//logit('File Exceptions (XML read):',	$exception_count);
		//logit('Index success count:', @$params['success_count']);
		
		 
		$response['errorcode'] = (empty($code)) ? 0 : 1 ;
		$response['errormsg'] .= (empty($code)) ? "Deletes success count: [" .  $success_count . ']' : "Errors: Not found [$failed_count] view log";
		$response['logfile'] = $params['log_file'];
		$response['responsetext'] = "Script finished successfully. Records deleted [$success_count] (" .$params['dbm_index'] . ') Type: (' . $params['dbm_type'] .')';
		
		logit('script finished successfully');			
	
	}
	
/*******************************************************************************/
/*******************************************************************************/
	function fnIndexPartialUpdate(){
	/*
		The idea here is to take a simple CSV file e.g. to load prices, sales_rank etc.
		Defaults to: ref_no	\t	fields	\t 	etc. with line 1 as header line
	*/

		global $params, $response;
		$success_count = 0;
		$failed_count = 0;
		$total_count = 0;
		$skipped_count = 0;
		$fcount = 0;
		$err = 0;

		logit('Partial Update..', 'Index: ('.$params['dbm_index'] . ') Type: (' . $params['dbm_type'] .')');
		logit('finding files..', $params['partial_updates_folder']);
		
		$files = glob($params['partial_updates_folder'] . '/*' );
		$files = preg_grep('@'. $params['partial_updates_pattern']	.'@i', $files);
		
		logit('files found for processing', Count($files));

		$params['updates_method'] = @$params['partial_updates_method'] ?: $params['updates_method'] ;
		$params['doc_as_upsert'] = @$params['partial_updates_doc_as_upsert'];
		if(!isset($params['doc_as_upsert'])){ $params['doc_as_upsert'] = $params['updates_doc_as_upsert'];}
		logit('running fnIndexPartialUpdate()', 'DBM Defaults: update/upsert=false');
		logit('..updates_method', $params['updates_method']);
		logit('..doc_as_upsert', ($params['doc_as_upsert'] ? 'true' : 'false') );

		foreach($files as $f){
		// process each file in sequence..
			
			$f = str_replace('/', '\\', $f);
			$lcount = 0;
			$fcount ++;
			$data = array();
			
			logit('processing file', $f);
				
		// read large files line by line
			$handle = fopen($f, "r");
			if ($handle) {
				
				while (($line = fgets($handle)) !== false) {
				// process the line read.
					$lcount ++;
					//echo $line;
					// We don't really want the double-quotes excel insists on
					$line = str_replace('"', '', $line);
					$line = trim($line, " \n\r\0\x0B");	// important to remove the line feeds too! BUT keep tabs!

					// first line should be the header
					if ($lcount == 1) {
						
						$header = preg_split('/'.$params['partial_updates_delimiter'].'/', $line);
						
					}
					
					if( $lcount >= ($params['partial_start_on_line']) ) {
					// load input data into $blob until </record>
						
						$cols = preg_split('/'.$params['partial_updates_delimiter'].'/', $line);
						
						// If they enter e.g. 2 headers but supply 3 cols of data
						if(count($cols) > count($header)) {
						
							//logit('truncating columns', count($cols) . '>>' .count($header));
							$x = (count($cols) - count($header));
							$y = (count($cols) - $x);
							$z = (count($cols)+1);
							
							//logit($x . '/'. $y . '/'. $z );
							for ($i=$y; $i <= $z; $i++) {
								//logit($i, 'i');
								unset($cols[$i]);
							}
							//logit('col check:', count($cols) .'<>'. count($header));
						}
						
						if(count($cols) == count($header) ) {
							try {
								
								$field_vals = array_combine($header, $cols);
								
								foreach($field_vals as $field=>$val){
									
									if( in_array($field, $params['default_force_array']) ){
						
										if(!is_array($field) && strpos($val, '|') > 0  ) {
											$field_vals[$field] = explode('|', $val);				
										}else{
											$field_vals[$field] = [ $val ];
										}
										
									}
								
									if( in_array($field, $params['default_cdata_elements']) ){
					
										if( !isset($field_vals[$field]['@cdata']) ){
											
											unset($field_vals[$field]);
											$field_vals[$field]['@cdata'] = $val;
											
										}
									
									}
								
								}
								
								if(!empty($field_vals[$params['partial_ref_no']])){
									
									$ref_no = $field_vals[$params['partial_ref_no']];
									$field_vals[$params['partial_ref_no']] = $ref_no;
									
								} else {
									
									$ref_no = create_unique_key($lcount);
									$field_vals[$params['partial_ref_no']] = $ref_no;
									
								}
																		
								ksort($field_vals);								
								$data[$ref_no] = $field_vals;
								unset($field_vals);
									
								if($lcount % $params['partial_chunk_count'] == 0) {
									//run a bulk update - set doc_as_upsert to false - we only want to update existing records (not make new ones!)
									logit('sending bulk update', $lcount); 
									//print_r($data); //die;
									
									$err = bulk_update($data);
									$data = array();
									
								}
									
								
								
							} catch (Error $e) {
								
								logit('header:['. count($header) . '] cols:[' . count($cols) . ']' );
								logit('Error: ', $e);
								
							}
							
						} else {
							
							$cc = count($cols); $hc = count($header);
							if($cc > 1){
								logit("Line: [$lcount] cols ($cc) dont't match cuffs ($hc)", $line);
							}
							$skipped_count++;
							
						}
						
					} 
					
				}	//End Lines loop
				//print_r($data);die;
				// don't forget any remaining data!!
				if(!empty($data)) {
					logit('sending final batch');
					
					$err = bulk_update($data);
					
				}
				$total_count += $lcount;
				$lcount = 0;

			} else {
				// error opening the file.
				logit("error opening file", $f);
			} 
			fclose($handle);
			
			
		}	//End File Loop
		
		logit();
		logit('Load files processed', $fcount);
		logit('Lines submitted: ', $total_count);
		logit('Lines skipped (No Ref_No or bad CSV):',	$skipped_count);
		//logit('Files rejected (reject_check()):',$rejected_count);
		//logit('File Exceptions (XML read):',	$exception_count);
		logit('Index success count:', @$params['success_count']);
		
		$response['errormsg'] = 'Index updated: ('.$params['dbm_index'] . ') Type: (' . $params['dbm_type'] .')';
		$response['errorcode'] = $err ;
		$response['errormsg'] = (empty($err)) ? "Script finished successfully" : "Errors: view log";
		$response['logfile'] = $params['log_file'];
		$response['responsetext'] = "Script finished successfully. Index Updated (" .$params['dbm_index'] . ').  Success count: (' . @$params['success_count'] . '/'. $total_count .')';
		
	}
/*******************************************************************************/
	function fnIndexPartialUpdateXML2(){
	/*
		The idea here is to take a folder of XML2 data files e.g. to load selected nodes, fields: 
		e.g. prices, sales_rank etc. (i.e. selected fields from the XML2)
		Settings etc in the config file as usual
		- v useful for partially indexing the Order xml files - just pick the data we want.
		
		ToDo: consider adding handling for multi fields (with |||| pipes), Attributes and CDATA (see fn_make_xml2)
		
	*/

		global $params, $response;
		$success_count = 0;
		$failed_count = 0;
		$total_count = 0;
		$skipped_count = 0;
		$fcount = 0;
		$data = array(); 
		
		logit('Partial Update From XML2..', 'Index: ('.$params['dbm_index'] . ') Type: (' . $params['dbm_type'] .')');
		logit('- flatten fields', ($params['partial_xml2_flatten_fields'])? 'On':'Off');
		logit('finding files..', $params['partial_xml2_data']);
		
		$params['updates_method'] = @$params['partial_xml2_updates_method'] ?: 'update' ;
		$params['doc_as_upsert']  = @$params['partial_xml2_doc_as_upsert'] ;
		if(!isset($params['doc_as_upsert'])){ $params['doc_as_upsert'] = false ;}
		logit('running fnIndexPartialUpdateXML2()', 'DBM Defaults: update/upsert=false');
		logit('..updates_method', $params['updates_method']);
		logit('..doc_as_upsert', ($params['doc_as_upsert'] ? 'true' : 'false') );
		
		$files = glob($params['partial_xml2_data'] . '/*' );
		$files = preg_grep('@'. $params['partial_xml2_pattern']	.'@i', $files);
		
		logit('files found for processing', Count($files));

		foreach($files as $f){
		// process each file in sequence..
			$fcount++;
			$f = str_replace('/', '\\', $f);
			
			$tmp = array(); $new = array();
			
			//logit('processing file', $f);
			
			$xml = file_get_contents($f);
			try {
					
				//logit($f);
				$tmp = XML2Array::createArray($xml);
				
			} catch (Exception $e) {
			
				logit($e->getMessage(), @$dir . '/' . xmlfield($xml, 'ref_no'));
				@$exception_count ++;
				continue;
			
			}
			
			
			$tmp  = $tmp[$params['partial_xml2_node_in']];	// trims off the <record> parent tag
			$path = arr_search($tmp, $params['partial_xml2_ref_no'])['path'];
			if(!empty( $path )){
							
				$ref_no = arr_get($tmp, $path);
				$new = array();
				
				foreach($params['partial_xml2_fields'] as $field){					
				/*	SO, we are assuming that the source file is decent XML2 (or the bits we want are)
					- $params['partial_xml2_fields'] should contain an array of the fields / nodes that we want to load into the index
					- e.g. 'ehaus' will load the whole node
					- e.g. 'ehaus.eh_dcode' a single field
					- e.g. most fields are directly under the <record> node e.g. 'ctitle' 
		
				*/
				
					$ats = arr_search($tmp, $field);
					
					if(isset($ats['value'])){
						
						if( isset($params['partial_xml2_flatten_fields']) & $params['partial_xml2_flatten_fields'] == true){
							
							
							if( is_array($ats['value'])){
								//print_r($ats['value']);
								$new = array_merge($new, $ats['value']);
								//print_r($new);
							} else {
								
								$f = explode('.', $ats['path']);
								$fn = array_pop($f);
								$new[$fn] = $ats['value'];
								
							}
							
						} else {
						
							$new[$field] = $ats['value'];
						}

					}	// end isset $ats
					unset($ats);
					
				}// end fields loop
				$data[$ref_no] = $new;
				unset($new);
				unset($tmp);
				//print_r($data); die;
				
				if($fcount % $params['partial_xml2_chunk_count'] == 0) {
					//run a bulk update
					logit('sending bulk update', $fcount); 
					//print_r($data); 
					$err = bulk_update($data);
					$data = array();
					
				}
									
			} else {
				
				$skipped_count++;
				
			}
			
		}
		
		// don't forget any remaining data!!
		if(!empty($data)) {
			
			logit('sending final batch', $fcount); 
			//print_r($data); 
			$err = bulk_update($data);
			
		}
		
		logit();
		logit('Load files processed', $fcount);
		//logit('Lines submitted: ', $total_count);
		logit('Lines skipped (No Ref_No):',	$skipped_count);
		//logit('Files rejected (reject_check()):',$rejected_count);
		//logit('File Exceptions (XML read):',	$exception_count);
		logit('Index success count:', @$params['success_count']);
		
		 
		$response['errormsg'] = 'Index updated: ('.$params['dbm_index'] . ') Type: (' . $params['dbm_type'] .')';
		$response['errorcode'] = $err ;
		$response['errormsg'] = (empty($err)) ? "Script finished successfully" : "Errors: view log";
		$response['logfile'] = $params['log_file'];
		$response['responsetext'] = "Script finished successfully. Index Updated (" .$params['dbm_index'] . ').  Success count: (' . @$params['success_count'] . '/'. $total_count .')';

										
		
	}
	
/*******************************************************************************/
/*
	Reads a source file containing the full path of all XML2 files to be updated
	- reads the XML2 files and then bulk updates the specified index
	- archives the source file and writes a log 
	- choose either to do a partial update or an overwrite (see the config file)
	
*/
	function fnIndexFilesByRefXML2(){
	
		global $params, $response;
		
		$success_count = 0;
		$failed_count = 0;
		$total_count = 0;
		$skipped_count = 0;
		$exception_count = 0;
		$rejected_count = 0;
		$fcount = 0;
		
		// I think we should enforce use of BDSLive-Main-dbm.inc for BDSLive updates
		if( empty($params['dbm']) ){
			logit('you need to set: dbm');
			exit;
		}
		
		logit('Indexing files from list..', '');
		logit('searching for files..', $params['index_files_source']);
		
		$params['updates_method'] = @$params['index_files_method'] ?: @$params['updates_method'] ;
		$params['doc_as_upsert'] = @$params['index_files_doc_as_upsert'] ;
		if(!isset($params['doc_as_upsert'])){ $params['doc_as_upsert'] = $params['updates_doc_as_upsert'];}
		logit('running fnIndexFilesByRefXML2()', 'DBM Defaults: update/upsert=true');
		logit('..updates_method', $params['updates_method']);
		logit('..doc_as_upsert', ($params['doc_as_upsert'] ? 'true' : 'false') );
		
		$files = glob($params['index_files_source'] . '/*' );
		$files = preg_grep('@'. $params['index_files_source_pattern']	.'@i', $files);
		
		logit('files found for processing', Count($files));

		foreach($files as $f){
			
			$fn = stringfrom($f, '/');
		// IF we on BDSLive then process each source file in sequence..find out which index we are updating..set $params['dbm_index'],$params['dbm_type'],
			if(preg_match('/DBBOOK/i', $f)){
				$params['dbm_index'] = @$params['current_live_index_book'];
				$params['dbm_type']  = 'doc';
				
			} elseif (preg_match('/DBMUSIC/i', $f)) {
				$params['dbm_index'] = @$params['current_live_index_music'];
				$params['dbm_type']  = 'doc';
			
			} elseif (preg_match('/DBDVD/i', $f)) {
				$params['dbm_index'] = @$params['current_live_index_film'];
				$params['dbm_type']  = 'doc';
			
			} elseif (preg_match('/DBGAME/i', $f)) {
				$params['dbm_index'] = @$params['current_live_index_games'];
				$params['dbm_type']  = 'doc';
				
			} else {
				logit("Upadating index: ({$params['dbm_index']})", $f);
				//continue;
				
			}
			logit('Updating index', $params['dbm_index']);	
			
			$fcount++;
			//$f = str_replace('/', '\\', $f);
			$lcount = 0;
			$data = array();
			$xml = '';
			$lines = array();
			
			logit('processing file', $fn);
		
		/**/	
		// read large files line by line
			$handle = fopen($f, "r");
			if ($handle) {
				
				while (($line = fgets($handle)) !== false) {
			
					$path = rtrim($line);	//file_get_contents doesn't like carriage retruns! :)
					$path = str_replace('\\', '/', $path);
					if(strlen($path) == 0){ continue; };	// e.g. blank lines
					$lcount ++;
					
					if(file_exists($path) ){
						
						$xml = file_get_contents($path);
					
						try {
							//logit($path);
							$tmp = XML2Array::createArray($xml);
							
						} catch (Exception $e) {
						
							logit($e->getMessage(), @$dir . '/' . xmlfield($xml, 'ref_no'));
							@$exception_count ++;
							continue;
						
						}

						$ref = arr_search($tmp, $params['index_files_ref_no'])['path'];
						if(!empty( $ref )){
							
							$ref_no = arr_get($tmp, $ref);
							$body = fn_make_xml2($tmp, $path);

							if(reject_check($body) == true){
							
								$data[$ref_no] = $body[$params['index_files_node_out']];
								
								
							} else {
								
								$rejected_count++;
								
							}
							
							unset($tmp); unset($body);

							if($lcount % $params['index_files_submit_count'] == 0) {
								//run a bulk update
								//logit('sending bulk update', $lcount); 
								//print_r($data); die;
								if($params['updates_method'] == 'update'){
									$err = bulk_update($data);
								} else {
									$err = bulk_index($data);
								}
								$data = array();
								
							}
							
							if($lcount % $params['index_files_report_count'] == 0) {
								logit('..files submitted', $lcount);
							}
							
												
						} else {
							
							$skipped_count++;
							
						}
					} else {
						
						logit('..file not found', $path);
					
					}
					
				} // end lines in file loop
				
				$total_count += $lcount;
				
				
				// don't forget any remaining data!!
				if(!empty($data)) {
					
					logit('sending final update', $lcount); 
					//print_r($data); die;
					if($params['updates_method'] == 'update'){
						$err = bulk_update($data);
					} else {
						$err = bulk_index($data);
					}
					$data = array();
					
				}
			/*	*/
			} // handle check
			fclose($handle);
			
			
			if($params['index_files_archive']) {
				$archive = str_replace($params['index_files_source'], $params['index_files_archive_folder'], $f);
				logit('..archiving file');
				rename($f, $archive);
			}
			
		}	// end source files loop
			
		
		logit("\e[32m");
		logit('Load files processed', $fcount);
		logit('Files submitted: ', $total_count);
		logit('Files skipped (No Ref_No):',	$skipped_count);
		logit('Files rejected (reject_check()):',$rejected_count);
		logit('File Exceptions (XML read):',	$exception_count);
		logit('Index success count:', @$params['success_count']);
		logit("\e[0m");
		
		$response['errormsg'] = 'Index updated: ('.$params['dbm_index'] . ') Type: (' . $params['dbm_type'] .')';
		$response['errorcode'] = @$err ;
		$response['errormsg'] = (empty($err)) ? "Script finished successfully" : "Errors: view log";
		$response['logfile'] = $params['log_file'];
		$response['responsetext'] = "Script finished successfully. Index Updated (" .$params['dbm_index'] . ').  Success count: (' . @$params['success_count'] . '/'. $total_count .')';
	
	}

/*******************************************************************************/
/*
	The idea here is to do an update_by_query request.
	e.g. php elastic-indexer.php dbm=sitename-invoice ax=index-upd-query file=inv.*\.json
	- best is to simply read the required update as JSON from the appropriate upd file
	- Will check for JSON encoded files in 'index_this_data_folder' matching supplied filename or pattern
	- Will accept data=<JSON string>
	
	As I understand it; Update by Query does the search and then runs a bulk update on the resulting ids
	- so its one query at a time
	
	N.B. To send a raw JSON string via cmd> prompt you need to surround "JSON" after converting double to single quotes 
	
*/
	function fnUpdateByQuery(){
		
		global $params, $config, $response, $www_debug;
		$data = array();
			
		
		// Check request came from DEV server
		check_request_authorised('UPDATE_QUERY');
		
		if( !empty($params['file']) ){
			// then lets open the file and try to read it in to $params['data']
			
			logit('Checking for JSON Query files..', 'Check your pattern e.g. file=inv.*\.json');
			logit('using pattern..', $params['file']);
			logit('searching folder ..', $params['index_this_data_folder']);
			
			$files = glob($params['index_this_data_folder'] . '/*' );
			$ptrn = $params['file'];
			$json = '';
			if( preg_match('/^\*/', $ptrn) ){
				$ptrn = '.'.$ptrn;
			}
			//print_r($files);print_r($ptrn );
			
			$files = preg_grep('@'. $ptrn .'@i', $files);
			logit('files found for processing', Count($files));
			//print_r($files);
			
			foreach($files as $f){
				
				$json = file_get_contents($f) . ',';
				$json = str_replace("'", '"', $json);
				$json = trim($json, '[",]');
				$json = "$json\n";
				//$json = '[' . $json . ']';
				//$json = json_decode($json, true);
				//$x = print_r($json, true); echo $x;
				
			// POST my-index-000001/_update_by_query?conflicts=proceed
					
			// submit the query update
				$host = reset($config['hosts']);
				$rurl = "$host/{$params['dbm_index']}/_update_by_query?conflicts=proceed";	// 90s may be too long
				$options = ['request_type' => 'POST'];
				$json = getRest($rurl, $json, $options);
				$resp = json_decode($json, true);
				//$x = print_r($resp, true); echo "<!-- $x -->\r\n"; //die;
				
			// check for REST API error
				if(json_error($explain)){
					logit("Error update_by_query: ", $explain);
					//return;
				}	
				
			// check for CURL error
				if(arr_val($resp, 'error_rest') == true){
					logit('CURL Error' . ' (' .arr_val($resp, 'errorcode') . ')', arr_val($resp, 'errormessage')  );
					//die;
					
				}
					
				//$x = print_r($resp, true); echo $x;
				logit("Records updated:" , $resp['updated']);
				$response['errorcode'] = 0;
				$response['errormsg'] = "Records updated successfully [{$resp['updated']}]";
				//$response['responsetext'] = "Record updated successfully [{$results['updated']}]";
					
				
			}	// end files loop

		} // file import
		
		
		if( !empty($params['data']) ){
			// handle each query one at a time, line by line
			
			logit('Checking for JSON Query data..', '');
			$json = urldecode($params['data']);
			$json = "$json\n";
			//$json = str_replace("'", '"', $json);
			//$json = trim($json, '[",]');
			//$json = '[' . $json . ']';
			
		// submit the query update
			$host = reset($config['hosts']);
			$rurl = "$host/{$params['dbm_index']}/_update_by_query?conflicts=proceed";	// 90s may be too long
			$options = ['request_type' => 'POST'];
			$json = getRest($rurl, $json, $options);
			$resp = json_decode($json, true);
			//$x = print_r($resp, true); echo "<!-- $x -->\r\n"; //die;
			
		// check for REST API error
			if(json_error($explain)){
				logit("Error update_by_query: ", $explain);
				//return;
			}	
			
		// check for CURL error
			if(arr_val($resp, 'error_rest') == true){
				logit('CURL Error' . ' (' .arr_val($resp, 'errorcode') . ')', arr_val($resp, 'errormessage')  );
				//die;
				
			}
			
			logit("Record updated:" , $resp['updated']);
			$response['errorcode'] = 0;
			$response['errormsg'] = "Record updated successfully [{$resp['updated']}]";
			//$response['responsetext'] = "Record updated successfully [{$results['updated']}]";
				

		} // data import
		
		
	
	}
/*******************************************************************************/
/*
	The idea is to send thru via GET or POST data for indexing a single record - dynamically
	- data can either be in name=value pairs, XML or as JSON string attached to data param
	- recommend XML format - really it is the most reliable :)
	- e.g. php elastic-indexer.php dbm=<sitename>-tracker ax=indexthis data="<record><ref_no>f12345</ref_no><data_geo_location>US</data_geo_location><plip>plop</plip></record>"
	- e.g. 127.0.0.1/<sitename>/lib/elastic/elastic-indexer.php?dbm=bds-tracker&ax=indexthis&data=<record><ref_no>f12345</ref_no><data_geo_location>US</data_geo_location><plip>plop</plip></record>
	- should be able to handle multiple records..e.g.<xyz><record><ref_no>9780929975306</ref_no><test_flag>N</test_flag></record><record><ref_no>9780191851933</ref_no><test_flag>N</test_flag></record></xyz>
*/
	function fnIndexThis() {
	
		global $params, $response, $www_debug;
		$data = array();
		
	// Check request came from DEV server
		check_request_authorised('INDEX_THIS');
	
	//ksort($params);	print_r($params['data']); //die;	
		if(!empty($params['data'])){
			$params['data'] = urldecode($params['data']);
		}
	
		
		if(!empty($params['file']) && empty($params['data']) ){
			// then lets open the file and try to read it in to $params['data']
			
			if(file_exists($params['file']) ){
				$params['data'] = file_get_contents($params['file']);
			}
			
			if(file_exists("{$params['index_this_data_folder']}/{$params['file']}") ){
				$params['data'] = file_get_contents("{$params['index_this_data_folder']}/{$params['file']}");
			}

		}
		
	// OK what have we been sent..
		if(preg_match('/<record>/', $params['data'])){
		
			logit('..guessing XML');
			try {
				// $params['data'] = urldecode($params['data']);
				// We are assuming this is nice simple XML that maps straight to elastic JSON without munging like in fn_make_xml2()
				$params['data'] = "<xyz>" . str_replace('<?xml version="1.0" encoding="UTF-8"?>', '', $params['data']) . "</xyz>";
				//print_r($params['data']);
		
				$data = XML2Array::createArray($params['data']);
				//print_r($data);print_r(json_encode($data));
				
				// OK, find the sub-array ['record'] and retreive it, if there is only one record give it the same shape as if there are lots of records :)
				$data = arr_search($data, $params['index_this_record'])['value'];	// i.e. find the <record> nodes
				
				if(!isset($data[0])){
					$tmp = $data;
					unset($data);
					$data[] = $tmp;
				}
				//print_r($data);die;
				
			} catch (Exception $e) {
			
				logit($e->getMessage());
				@$exception_count ++;
				
			}
			
		} elseif (preg_match('/[\{\}]/', $params['data'])){
		
			/*	N.B. JSON requires "double-quotes" but CMD:> gets confused
				
				{'xyz':{
					'record':[
						{'ref_no':'9781783304769','test_flag':'Y'},
						{'ref_no':'9781783304264','test_flag':'Y'}
					]
				}}
			
			*/
			//$params['data'] = urldecode($params['data']);
			//$params['data'] = trim($params['data'], '["]');
			//$params['data'] = str_replace("'", '"', $params['data']);	// You need this if testing via cmd>> 
			logit('..guessing JSON');
			//print_r($params['data']); die;
			$data = json_decode($params['data'], true);
			//print_r($data);die;
			$x = json_error( $explain ); 
			if($explain != 'No JSON errors'){
				echo "<!-- JSON Error:fnIndexThis: [$explain] -->\r\n";die;
				
			}
			
		// OK, find the sub-array ['record'] and retreive it, if there is only one record give it the same shape as if there are lots of records :)
			$data = arr_search_keys($data, $params['index_this_record']);	// i.e. find the <record> nodes
			//print_r($data);die;
			if(!isset($data[0])){
				$tmp = $data;
				unset($data);
				$data[] = $tmp;
			}
			if(isset($data[0][0])){
				// then we've got the wrapper array as well! Remove it
				$tmp = $data[0];
				unset($data);
				$data = $tmp;
			}
			
		} elseif (preg_match('/[\&\=@:]/', $params['data'])){
		
			// JUST the query_string [&name=value pairs] - NOT the full URL - and you may find it easier to eh_encode it first
			logit('..guessing URL');
			$params['data'] = eh_decode($params['data']);
			parse_str($params['data'], $data[]);
			//print_r( $data );die;

		} else {	
			logit('..out of guesses');
			exit;
		}
	
		//print_r($params['data']); die;//print_r($field_vals); //print_r(json_encode($data));
		// we need to make an array: $data['ref_no'] = ['field_name' => 'value']
		foreach ($data as $arr){
			
			if( !empty($arr) ){
				
				// Run thru and explode <xyz>multiple|instance|fields|</xyz> separated by pipes into an array for elastic
				foreach($arr as $key=>$val){
					
					if( in_array($key, $params['default_force_array']) ){
						
						if(!is_array($val) && strpos($val, '|') > 0  ) {
							$arr[$key] = explode('|', $val);				
						}else{
							$arr[$key] = $val;
						}
						
					}
					
					if( in_array($key, $params['default_cdata_elements']) ){
						
						if( !isset($arr[$key]['@cdata']) ){
							
							unset($arr[$key]);
							$arr[$key]['@cdata'] = $val;
							
						}
						
					}
					
					if( in_array($key, $params['default_cdata_convert']) ){
					
						if( isset($arr[$key]['@cdata']) ){
							
							$v = $arr[$key]['@cdata'];
							if( is_array($v) ){
								$v = implode(' - ', $v);
							}
							unset($arr[$key]['@cdata']);
							$arr[$key] = strip_tags($v);	
							
						}
						
					}
					
				}
			}
			
			
			$ref_no = arr_search($arr, $params['index_this_ref_no'])['value'];
			//echo "<!-- $ref_no -->\r\n";
			//if( empty($ref_no) ){ $ref_no = create_unique_key(); }
			$arr['ref_no'] = $ref_no;	//.. this will allow <id> to be rolled into <ref_no>
			if(empty($ref_no)){
			
				// we just do what we're told - up to submitter to provide ref_no for update/new records
				logit('Error', 'No REF_NO supplied');
				exit;
				
			} else {
				
				// Store <filename> 	
				if( !empty($params['data_folder_num']) ){
					$sub_folder = "data/" . padz(substr($ref_no, -$params['data_folder_num']), $params['data_folder_num']+1);
				}else{
					$sub_folder = "data";
				}
				if(@$params['index_this_set_filename']){
					$arr['filename'] = "{$params['data_source_path']}/$sub_folder/$ref_no.xml";
				}
				$upsert[$ref_no] = $arr;
				
			}
		}
			
		//print_r($upsert); die;
		// OK - do we want to filter fields allowed to be submitted $params['index_this_fields']
		
		// anything else ?
		
		//print_r($upsert); //die;
		
		// Default has to be to treat doc_as_upsert = true
		$params['doc_as_upsert'] = $params['index_this_doc_as_upsert'];
		logit('doc_as_upsert', $params['doc_as_upsert'] );
		$err = bulk_update($upsert);
		//print_r($err);
		//$z = print_r($err, true);
		//logit('eek', $z);
		logit('Index success count:', @$params['success_count'] );
		logit();
		//logit('Load files processed', $fcount);
		//logit('Lines submitted: ', $total_count);
		//logit('Lines skipped (No Ref_No):',	$skipped_count);
		//logit('Files rejected (reject_check()):',$rejected_count);
		//logit('File Exceptions (XML read):',	$exception_count);
		//logit('Index success count:', @$params['success_count']);
		if( !empty($err) || empty($params['success_count']) ){
			$errorcode = 1;
		}else{
			$errorcode = 0;
		}
		 
		$response['errorcode'] = $errorcode;
		$response['errormsg'] .= (empty($err)) ? "Updates success count: [" .  @$params['success_count'] . ']' : "Errors: view log";
		$response['logfile'] = $params['log_file'];
		$response['responsetext'] = "Script finished successfully. Index Updated (" .$params['dbm_index'] . ') Type: (' . $params['dbm_type'] .') Host: (' . $params['active_hosts'] . ')';
		
		logit('script finished successfully');			
	
	}
	
/*******************************************************************************/
/*
	This simply writes a new record or UPDATES (i.e. merge-updates fields) an existing one. 
	
		New fields are added..
		Existing fields are preserved / updated where new values are posted 
		New Records are created if where doc_as_upsert set to true
		
		$data['ref_no'] = ['field_name' => 'value']
	
	N.B. See fn_make_xml2() for handling CDATA and multiple instance fields
*/	
	function bulk_update($data){
		
		global $params, $config, $response;
		
		$success_count = 0;
		$failed_count = 0;
		$error = array();
		$err = 0;
		$batch = array();
		$resp = array();
		$nd_json = '';
		//print_r($data);
		
		foreach($data as $key=>$val){
			// N.B. if not happy with specifying '0' (float) or 'null' (string) for empty fields then go thru the array replacing 'null' with 'new \stdClass()'
			
			if($params['es_version'] >= 7){
			
				$batch['body'][] = [
					'update' => [
						'_id'		=> $key,	
						'_index' 	=> $params['dbm_index'],
						'retry_on_conflict'	=> 3,			// N.B. version 7: now without the leading underscore: retry_on_conflict
					],
				];

			
			}else{
				
				$batch['body'][] = [
					'update' => [
						'_id'		=> $key,	
						'_index' 	=> $params['dbm_index'],
						'_type'		=> $params['dbm_type'],		// N.B. version 7: types have been changed/removed!
						'_retry_on_conflict'	=> 3,			// N.B. version 7: now without the leading underscore: retry_on_conflict
					],
				];
				
			}
					
			$batch['body'][] = [
				'doc_as_upsert' => $params['doc_as_upsert'],
				'doc' => $val
			];
				
		}
		
	// prepare ndjson for submission:
		$batch_json = get_ndjson($batch['body']);
		$batch_json = "$batch_json\n";
		//$x = print_r($batch_json, true); echo "<!-- $x -->\r\n"; die;
				
	// submit a bulk load
		$host = reset($config['hosts']);
		$rurl = "$host/_bulk/";
		$options = ['request_type' => 'PUT', 'connect_timeout' => 5000, 'timeout' => 10000];
		$json = getRest($rurl, $batch_json, $options);
		$resp = json_decode($json, true);
		//$x = print_r($resp, true); echo "<!-- $x -->\r\n"; //die;
		
	// check for REST API error
		if(json_error($explain)){
			logit("Error bulk_update: ", $explain);
			//return;
		}	
		
	// check for CURL error
		if(arr_val($resp, 'error_rest') == true){
			logit('CURL Error' . ' (' .arr_val($resp, 'errorcode') . ')', arr_val($resp, 'errormessage')  );
			//die;
			
		}
		
	// check for indexing errors	
		if(isset($resp['errors']) and !empty($resp['errors']) ){
		
			$info = parse_errors($resp);
			//print_r($info);
			$failed_count = Count($info['failed_count']);
			logit("! Errors - [$failed_count] files not indexed", 'Details written to log file');
			
			$eek = print_r($info, true);
			file_put_contents($params['log_path'], $eek, FILE_APPEND);	
				
			if($params['updates_report_submit_count']) {
				logit("..records submitted", number_format(Count($resp['items']))  );	
			}
			 
		 } else {
		 
			$info = parse_errors($resp);	// this will update $params['success_count'] :)
			//print_r($info);
			if($params['updates_report_submit_count']) {
				logit("..records submitted", number_format(Count($resp['items']))  );
			}
			
		 }
		
		
		unset($resp); unset($batch); unset($json);
		return $err;
		
		/*
		
		logit('items in batch', count($resp['items']));
		logit('batch success: ', $success_count);
		logit('errors', $resp['errors']);		
		$x = implode("\r\n", $failed_count);
		logit('error details: ',  $x);
				
		$response['errormsg'] = 'Index updated: ('.$params['dbm_index'] . ') Type: (' . $params['dbm_type'] .')';
		$response['errorcode'] = (empty($resp['errors'])) ? '0' : '1' ;
		$response['errormsg'] = (empty($resp['errors'])) ? "Script finished successfully" : "Errors: " . $resp['errors'];
		$response['logfile'] = $params['log_file'];
		$response['responsetext'] = "Script finished successfully. Index Updated (" .$params['dbm_index'] . ') Type: (' . $params['dbm_type'] .')';
		
		*/
		
			
		
	}
/*******************************************************************************/

	function test_my_batch($batch)
	{
		
		$batch_skipped = false;
		$eek = json_encode($batch);
		$x = json_error( $explain ); 
		
		if($x){
			
			foreach($batch['body'] as $arr){
				
				$eek = json_encode($arr);
				$x = json_error( $explain ); 
				if($x){
					$batch_skipped = true;
					$file = arr_val($arr, 'filename');
					logit($explain, $file);
				}
				
			}			
			
		}
		
		if($batch_skipped){
			logit('!! Entire Batch skipped. Fix XML and reload.');
		}
		
	}
		
	function utf8convert($mixed, $key = null)
	{
		if (is_array($mixed)) {
			foreach ($mixed as $key => $value) {
				$mixed[$key] = utf8convert($value, $key); //recursive
			}
		} elseif (is_string($mixed)) {
			$fixed = mb_convert_encoding($mixed, "UTF-8", "UTF-8");
			return $fixed;
		}
		return $mixed;
	}
	
/*******************************************************************************/
/*
	This simply writes a new record or OVERWRITES an existing one. 
		
		$data['ref_no'] = ['field_name' => 'value']
	
	N.B. See fn_make_xml2() for handling CDATA and multiple instance fields
*/

	function bulk_index($data){
		
		global $params, $config, $response;
		$err = 0;
		//$success_count = 0;
		//$failed_count = array();
		$resp = array();
		$batch = array();
		
		foreach($data as $key=>$val){
			// N.B. if not happy with specifying '0' (float) or 'null' (string) for empty fields then go thru the array replacing 'null' with 'new \stdClass()'
			
			if($params['es_version'] >= 7){
			
				$batch['body'][] = [
					'index' => [
						'_id'		=> $key,	
						'_index' 	=> $params['dbm_index'],
						'retry_on_conflict'	=> 3,			// N.B. version 7: now without the leading underscore: retry_on_conflict
					],
				];

			
			}else{
				
				$batch['body'][] = [
					'index' => [
						'_id'		=> $key,	
						'_index' 	=> $params['dbm_index'],
						'_type'		=> $params['dbm_type'],		// N.B. version 7: types have been changed/removed!
						'_retry_on_conflict'	=> 3,			// N.B. version 7: now without the leading underscore: retry_on_conflict
					],
				];
				
			}
		
			$batch['body'][] = $val;
				
				
		}
			// prepare ndjson for submission:
		$json = get_ndjson($batch['body']);
		$json = "$json\n";
		//$x = print_r($json, true); echo "<!-- $x -->\r\n"; die;
				
	// submit a bulk load
		$host = reset($config['hosts']);
		$rurl = "$host/_bulk/";
		$options = ['request_type' => 'PUT', 'connect_timeout' => 5000, 'timeout' => 10000];
		$json = getRest($rurl, $json, $options);
		$resp = json_decode($json, true);
		//$x = print_r($resp, true); echo "<!-- $x -->\r\n"; //die;
		
	// check for REST API error
		if(json_error($explain)){
			logit("Error bulk_update: ", $explain);
			//return;
		}	
		
	// check for CURL error
		if(arr_val($resp, 'error_rest') == true){
			logit('CURL Error' . ' (' .arr_val($resp, 'errorcode') . ')', arr_val($resp, 'errormessage')  );
			//die;
			
		}
		
	// check for indexing errors	
		if(isset($resp['errors']) and !empty($resp['errors']) ){
		
			$info = parse_errors($resp);
			//print_r($info);
			$failed_count = Count($info['failed_count']);
			logit("! Errors - [$failed_count] files not indexed", 'Details written to log file');
			
			$eek = print_r($info, true);
			file_put_contents($params['log_path'], $eek, FILE_APPEND);	
				
			if($params['updates_report_submit_count']) {
				logit("..records submitted", number_format(Count($resp['items']))  );	
			}
			 
		 } else {
		 
			$info = parse_errors($resp);	// this will update $params['success_count'] :)
			//print_r($info);
			if($params['updates_report_submit_count']) {
				logit("..records submitted", number_format(Count($resp['items']))  );
			}
			
		 }
		
		
		unset($resp); unset($batch); unset($json);
		return $err;
		
		
	}
	
	
/*******************************************************************************/
/*
	N.B. 	The bulk_update() function uses the elastic Update API; records are merge updated
			- this function will overwrite an exisitng document indexed with the same key
*/
	function fnIndexUpdate($index = '', $type = ''){
	
		global $params, $response;
		
		
		//echo $params['data_source_path'];
		
		logit('Updating index', 'Index: ('.$params['dbm_index'] . ') Type: (' . $params['dbm_type'] .')');
		if( !empty($params['data_folder_num']) ){
			$params['data_source_path'] = "{$params['data_source_path']}/data";
		}
		logit('Data source path', $params['data_source_path']);
		
		//logit('Current script owner: ', get_current_user() ) ;
		
		if ( $params['map_network_drive'] ) {
		
			require_once ('../../scripts/network.php');
			
			$drive = new network(false);
			$drive->init();
			$credentials = [
			
				'location' => $params['data_source_path'],
				'username' => 'Administrator',
				'password' => 'Whale9wasp8',
			
			];
			
			$x = $drive->map_network_drive($credentials);
			if($x){
				logit('Network drive successfully mapped:', $drive->drive);
			} else {
				print_r($x);
			}
			
		}
		
		//print_r(get_current_user());						
		$directories = scandir($params['data_source_path'], SCANDIR_SORT_ASCENDING );
		//print_r($directories); die;
		
		$dir_count = 0;
		$tot_fcount = 0;
		$record_count = 0;
		$rejected_count = 0;
		$skipped_count = 0;
		$exception_count = 0;
		$data = array();
		$xml = '';
		
		
		logit('found [' . Count($directories) . '] folders for processing');
		if($params['folder_load_limit'] < 1){ $params['folder_load_limit'] = 10001; }
		logit('Folder Limit set to [' . $params['folder_load_limit'] . ']');
		logit('starting with folder', $params['updates_start_folder']);														 
		
		$params['updates_method'] = @$params['updates_method'] ?: 'update' ;
		$params['doc_as_upsert']  = $params['updates_doc_as_upsert'] ;
		logit('running fnIndexUpdate()', 'DBM Defaults: update/upsert=true');
		logit('..updates_method', $params['updates_method']);
		logit('..doc_as_upsert', ($params['doc_as_upsert'] ? 'true' : 'false') );		
		
		foreach ($directories as $dir) {
			
			if($dir == '.' || $dir == '..'){ continue; }
			// BDSLive data is stored in numeric sub-folders. We want to be able to specify which number to start with and how many folders to process
			if(is_numeric($dir)){
				
				if($dir < $params['updates_start_folder']){ continue; } //N.B. only needed and only works for BDSLive with numbered folders :)
				$dir_count++;
				//logit($params['data_source_path'].'\\'.$dir, $dir_count);
				if($dir_count>$params['folder_load_limit']){
					logit('QUITTING - folder limit reached');
					break;
				}
			} else {
				// otherwise most data is stored in a single /data/ folder
				if ($dir == 'data'){
				
					logit('../data/ folder found for processing.');
					
				} else { continue; }
				
			}
		
			$files = glob($params['data_source_path'].'\\'.$dir . '\\*' );
			$files = preg_grep('/'. $params['data_source_pattern']	.'/', $files);
			//logit('..files found for processing', count($files));
			$fcount = 0;
			
			foreach($files as $f){
				
				$fcount ++;
				$xml = '';
				//logit('reading file', $f);
			
			// read large files line by line. This has been modified so we can parse both 1 record per file and multiple records per file
				$handle = fopen($f, "r");
				if ($handle) {
					
					$lcount = 0;
					while (($line = fgets($handle)) !== false) {
						
					// process the line read.
						$lcount ++;
						$line = trim($line);	// important to remove the line feeds!
						
						// ignore any <collection> tags
						if(!empty($params['updates_collection_node'])){
							
							if( !strpos($line, "{$params['updates_collection_node']}>") > 0 ){
								$xml .= $line;
							}
						
						} else {
							
							$xml .= $line;
							
						}
						
						// when we hit </record> - we have a complete record :)
						if(preg_match("@</{$params['updates_node_in']}>@", $line)){
							
							if(@$params['updates_clean_xml']){
								// Hopefully not needed. Set flag in DBM.inc. See clean_input() below for explanation
								$xml = clean_input($xml);
								//echo("[$xml]\r\n\"); die;
							}
														
							$record_count++;
							if($params['updates_report_count'] > 0 ){
								if($record_count % $params['updates_report_count'] == 0) { logit("..records processed.....[/$dir/]", number_format($record_count) ); }
							}
				
							try {
						
								//logit($f);
								// XML2Array breaks character entities! See escaped version :)
								$body = XML2Array::createArray($xml);
								//print_r($body);
									
							} catch (Exception $e) {
								
								logit($e->getMessage(), $dir . '/' . xmlfield($xml, 'ref_no'));
								@$exception_count ++;
								//print_r($xml);die;
								continue;
								
							}
							
							//print_r($body);die;
							// We need to check if there is a <ref_no>
							$path = arr_search($body, $params['updates_ref_no'])['path'];
							if(!empty( $path )){
								
								$ref_no = arr_get($body, $path);
								$body = fn_make_xml2($body, $f);

								if(reject_check($body) == true){
								
									$data[$ref_no] = $body[$params['updates_node_out']];
									
								} else {
									
									$rejected_count++;
									
								}
								
								unset($body);
								
								//$json = json_encode($fields);
								//print_r($data); die;
								//echo($body['record']['ref_no']."\r\n");
								//logit($ref_no, $record_count);
								if($record_count % $params['updates_submit_count'] == 0) {
									//run a bulk update
									//logit('sending bulk update', $record_count); 
									//print_r($data); 
									if($params['updates_method'] == 'update'){
										$err = bulk_update($data);
									} else {
										$err = bulk_index($data);
									}
									$data = array();
									
								}
													
							} else {
								
								$skipped_count++;
								
							}
							
							$xml = '';
							
						} // now on to the next record
						
					} // end line reading loop

				} else {
					// error opening the file.
					logit("error opening file", $f);
				} 
				fclose($handle);

				
			} //end files loop
			
		} // end directories loop
				
		// don't forget any remaining data!!
		if(!empty($data)) {
			
			logit('sending final update', count($data)); 
			//print_r($data); 
			if($params['updates_method'] == 'update'){
				$err = bulk_update($data);
			} else {
				$err = bulk_index($data);
			}
			$data = array();
			//print_r($err); 
		}
		
		if ( $params['map_network_drive'] ) {
		
			$drive->remove_network_drive($drive->drive);
			
		}
		
		logit('total files processed', number_format(@$fcount));
		logit();
		 
		logit('Total records processed', @$record_count);
		logit('Records skipped (No Ref_No):',	$skipped_count);
		logit('Records rejected (reject_check()):',$rejected_count);
		logit('Records Exceptions (XML read):',	$exception_count);
		logit('Index success count:', @$params['success_count']);
		
		$response['errorcode'] = (empty($err)) ? 0 : 1 ;
		$response['errormsg'] = (empty($err)) ? "Script completed OK" : "Errors: view log";
		$response['logfile'] = $params['log_file'];
		$response['responsetext'] = "Script finished successfully. Index Updated (" .$params['dbm_index'] . ').  Success count: (' . @$params['success_count'] . '/'. @$record_count .')';
		
		logit('script finished successfully');							
	
	}
	
/*******************************************************************************/
/*
	Accepts a list of ref_no's and updates the index for each ref_no supplied
	- Assumes that they are all located in the data folder for the relevant DBM
	
	N.B. 	The bulk_update() function uses the elastic Update API; records are merge updated
			- i.e. New fields are added..
			- Existing fields are preserved / updated where new values are posted 
			- New Records are created if where doc_as_upsert set to true
			
			To ensure this happens set:
			- $params['updates_method'] = 'update'
			- $params['doc_as_upsert']  = true
			
*/
	function fnIndexUpdateThis($index = '', $type = ''){
	
		global $params, $response;
		
		
		//echo $params['data_source_path'];
		logit("Index-Update-This:",  "data=from list of ref_no's supplied");
		logit('Updating index', 'Index: ('.$params['dbm_index'] . ') Type: (' . $params['dbm_type'] .')');
		logit('Data source path', $params['data_source_path']);
		
		$params['updates_method'] = @$params['updates_method'] ?: 'update' ;
		$params['doc_as_upsert']  = $params['updates_doc_as_upsert'] ;
		
		logit('running fnIndexUpdateThis()', 'DBM Defaults: update/upsert=true');
		logit('updates_method', $params['updates_method']);
		logit('doc_as_upsert', ($params['doc_as_upsert'] ? 'true' : 'false') );		
		
		
		
		//logit('Current script owner: ', get_current_user() ) ;
		
		if ( $params['map_network_drive'] ) {
		
			require_once ('../../scripts/network.php');
			
			$drive = new network(false);
			$drive->init();
			$credentials = [
			
				'location' => $params['data_source_path'],
				'username' => 'Administrator',
				'password' => 'Whale9wasp8',
			
			];
			
			$x = $drive->map_network_drive($credentials);
			if($x){
				logit('Network drive successfully mapped:', $drive->drive);
			} else {
				print_r($x);
			}
			
		}
		
		
		$fcount = 0;
		$dir_count = 0;
		$tot_fcount = 0;
		$rejected_count = 0;
		$skipped_count = 0;
		$exception_count = 0;
		$data = array();
		$xml = '';
		
		$refs = explode(',', $params['data']);
		logit('found [' . Count($refs) . '] files for processing');										 
		
		foreach ($refs as $ref) {
			
			if( !empty($params['data_folder_num']) ){
				
				$sub_folder = padz(substr($ref, -$params['data_folder_num']), $params['data_folder_num']+1);
				$file_path = "{$params['data_source_path']}/data/$sub_folder/$ref.xml";
				
			}else{
				
				$sub_folder = "";
				$file_path = "{$params['data_source_path']}/data/$ref.xml";
				
			}
			
			if( file_exists($file_path) ){
				
				$fcount ++;
				$tot_fcount++;
				if($params['updates_report_count'] > 0 ){
					if($tot_fcount % $params['updates_report_count'] == 0) { logit('..files processed..', number_format($tot_fcount) ); }
				}
				
			// read small files all in one go :)
				$xml = file_get_contents($file_path);
				try {
					
					//logit($f);
					// XML2Array breaks character entities! See escaped version :)
					$body = XML2Array::createArray($xml);
					//print_r($body);
						
				} catch (Exception $e) {
					
					logit($e->getMessage(), @$dir . '/' . xmlfield($xml, 'ref_no'));
					@$exception_count ++;
					continue;
					
				}
					
				// We need to check if there is a <ref_no>
				$path = arr_search($body, $params['updates_ref_no'])['path'];
				if(!empty( $path )){
					
					$ref_no = arr_get($body, $path);
					$body = fn_make_xml2($body, $file_path);

					if(reject_check($body) == true){
					
						$data[$ref_no] = $body[$params['updates_node_out']];
						
					} else {
						
						$rejected_count++;
						
					}
					
					unset($body);
					
					//$json = json_encode($fields);
					//print_r($body);
					//echo($body['record']['ref_no']."\r\n");
					
					if($tot_fcount % $params['updates_submit_count'] == 0) {
						//run a bulk update
						//logit('sending bulk update', $tot_fcount); 
						//print_r($data); 
						if($params['updates_method'] == 'update'){
							$err = bulk_update($data);
						} else {
							$err = bulk_index($data);
						}
						$data = array();
						
					}
										
				} else {
					
					$skipped_count++;
					
				}
				
			} else {
				
				logit("Couldn't find file: [$file_path]");
			}
			
		} // end files loop
				
		// don't forget any remaining data!!
		if(!empty($data)) {
			
			logit('sending final update', $tot_fcount); 
			//print_r($data); 
			if($params['updates_method'] == 'update'){
				$err = bulk_update($data);
			} else {
				$err = bulk_index($data);
			}
			$data = array();
			
		}
		
		if ( $params['map_network_drive'] ) {
		
			$drive->remove_network_drive($drive->drive);
			
		}
		
		logit('total files processed', number_format(@$tot_fcount));
		logit();
		 
		logit('Total files processed', @$tot_fcount);
		logit('Files skipped (No Ref_No):',	$skipped_count);
		logit('Files rejected (reject_check()):',$rejected_count);
		logit('File Exceptions (XML read):',	$exception_count);
		logit('Index success count:', @$params['success_count']);
		
		$response['errorcode'] = (empty($err)) ? 0 : 1 ;
		$response['errormsg'] = (empty($err)) ? "Updates success count: [" .  @$params['success_count'] . ']' : "Errors: view log";
		$response['logfile'] = $params['log_file'];
		$response['responsetext'] = "Script finished successfully. Index Updated (" .$params['dbm_index'] . ') Type: (' . $params['dbm_type'] .')';
		
		logit('script finished successfully');							
	
	}
		
/*******************************************************************************/
/*
	Check the records and retrun false if they are not wanted..
*/
	function reject_check($body){
		
		
		$ret = true;
		
		// TRANSUK munge crap!!! BDSLive proper - we don't want to reject anything
		/*	 
		if ( @$body['record']['format_type'] == 'VHS' ) { $ret = false; }
		if ( @$body['record']['format_type'] == 'Vinyl' ) { $ret = false; }
		if ( @$body['record']['format_type'] == 'Digital' ) { $ret = false; }
		if ( @$body['record']['uk_avail_code'] == 'PUL' ) { $ret = false; }
		if ( @$body['record']['uk_avail_code'] == 'DEL' ) { $ret = false; }
		if ( @$body['record']['uk_avail_code'] == 'THR' ) { $ret = false; }
		if ( @$body['record']['uk_avail_code'] == 'Not Available' ) { $ret = false; }
		if ( !empty($body['record']['format']) ) { 
			if( preg_match('/ 4K /', $body['record']['format']) ){
				$ret = false; 
			}
		}
		*/
		return $ret;
		
	}
	
/*******************************************************************************/
/* OK we have an issue with inconsistent handling of CDATA fields in the XML
	- we can store them in the way that XML2Array handles CDATA (which is great) but
	- IF we use Dynamic mapping, Elastic will treat fields as an Object or a string depending on the first one encountered..
	- e.g. <perm_promo><![CDATA[tra la la]]></perm_promo>
	  becomes: [perm_promo] => Array ( [@cdata] => 'tra la la' ) which elastic has no trouble with treating it as an object:
			   "perm_promo": {"@cdata": "tra la la"},
	  so when it encounters a <perm_promo>tra la la</perm_promo> (i.e. no CDATA) it tries to index it as an object
	- SO we will need ensure ALL <perm_promo> fields are CDATA
	- AND set a mapping in the indexcreate params specifying the field type 
	:)
*/
	function fn_make_xml2($body, $fname){
	
		global $params, $response, $bic_map, $thema_map;
		
		//init_quotes_map($chr, $rpl); 
		
		$separator = $params['tree_analyzer_delimiter'] ?: ';';
	
		//print_r($params);	die;
		//print_r( arr_val($params, 'elastic_index_config.body.mappings.doc.properties.thema_subj_code') ); die;
		//print_r($body['record']); //die;
		//print_r( arr_search($body, 'descrip') ); die;
		//print_r( arr_val($params, 'thema_subj_code') ); die;
		//print_r( arr_dot_keys( $body['record'] ) ); die;
		
	
	// clean_data_nodes() will also attempt to clean up price fields..	
		$body['record'] = clean_data_nodes($body['record']);
		
		$sub_nodes = array_unique(array_merge( ['ehaus'], (@$params['default_sub_nodes'] ?: ['']) ));
		foreach($sub_nodes as $node){
			
			if(!empty($body['record'][$node]) ){
				$body['record'][$node] = clean_data_nodes($body['record'][$node]);
			} 
			
		}
		
		//ksort($body['record']); print_r($body);	die;
		
		$bdz = @$body['record']['ref_no'] ?: @$body['record'][ $params['updates_ref_no'] ] ?: @$body['record'][ $params['index_files_ref_no'] ] ;
		//logit('ref', $bdz);
		
		
	// CONTRIBUTOR MAYHEM!! + SUGGEST_TERMS (V7+ ONLY)
	// handle new format onix_contributor // 'default_json_expand' i.e. stored as <xml><![CDATA[some stuff]]></xml> but indexed as expanded JSON

		/*
			<onix_contributor>
				<sequence_number>1</sequence_number>
				<role>Author</role>
				<role_code>A01</role_code>
				<person_name>Louis Kaufman</person_name>
				<person_name_inverted>Kaufman, Louis</person_name_inverted>
				<names_before_key>Louis</names_before_key>
				<key_names>Kaufman</key_names>
				<contributor>Louis Kaufman (Author)</contributor>
			</onix_contributor>

		*/
		
		// COPIES INTO INDEX onix_contributor.contributor => contributor for backwards comaptibility
		if( !empty(arr_val($body['record'], 'onix_contributor')) ){
			
			$contributors = json_decode(arr_val($body['record'], 'onix_contributor'), true);
			if( !isset($contributors[0]) ){
				
				$contributors = [ $contributors ];
				
			}
			
			foreach( $contributors as $idx=>$contributor ){
				
			// For search and backwards compat create old style <contributor>John Smith (Author) field in index 			
				$carr[] = arr_val($contributor, 'contributor'); // appended to the ONIX_CONTRIBUTOR JSON string by CMS and import scripts
				
			// Whilst we are here generate the author suggest_terms array
				$primary = @$contributor['person_name'] ?: @$contributor['corporate_name'] ?: '';
				$primary = convert_chars($primary);
				$primary = preg_replace('/\d{4}/', '', $primary);
				$primary = preg_replace('/\s+/', ' ', $primary);
				$primary = preg_replace('/[^A-Za-z \'\.\-]+/', '', $primary);
				//print_r($primary);die;
				
				$suggest_terms[] = $primary;
				
			}
			
			$carr = arr_filter_blanks($carr);
			$body['record']['contributor'] = implode('|', $carr);
			
		}else{
		
			// Fek it! lets just index the old <contributor> field - make the SUGGEST_TERSM here 
			if(!empty($body['record']['contributor'])){
			
				//$x = print_r($body['record']['contributor'], true); echo "<!-- 1. <contributor>$x -->\r\n";
				$contributors = $body['record']['contributor'];
				if( !is_array($contributors) ){
					$contributors = explode('|', $contributors);
				}
				//print_r($contributors);
				foreach( $contributors as $idx=>$contributor ){
				
				// Whilst we are here generate the author suggest_terms array
					$primary = trunc($contributor) ?: '';
					$primary = str_ireplace( array('author', 'editor', 'contributor', 'contributors', 'narrator'), ' ', $primary );
					$primary = convert_chars($primary);
					$primary = preg_replace('/\d{4}/', '', $primary);
					$primary = preg_replace('/\s+/', ' ', $primary);
					//print_r($primary);die;
					
					$suggest_terms[] = $primary;
					
				}

			} elseif( !empty($body['record']['contributors']) ){
			
				//$x = print_r(@$body['record']['contributor'], true); echo "<!-- 2. <contributor>$x -->\r\n";
				//$x = print_r($body['record']['contributors'], true); echo "<!-- 3. <contributors>$x -->\r\n";
				$first_last = '';
				$contributors = arr_val($body['record'], 'contributors.Contributor'); // array_change_key_case() only works to one level deep!
				if(is_array($contributors)){
					
					if( !isset($contributors[0]) ){ $contributors = [$contributors]; }
					
					foreach($contributors as $contributor){
						
						$names[] = arr_val($contributor, 'person_display_name');
						
					}
					
				}
				
				$body['record']['contributor'] = $names;
				// ? $suggest_terms += $names;
				
				
				// now get rid of new <contributors> node (for ealstic indexing purposes). Keep it for the XML and the ONIX export!
				if(isset($body['record']['contributors'])){
					unset($body['record']['contributors']);
				}
				
				//print_r($body); die;
				
			}else{

				if(isset($body['record']['contributors'])){
					unset($body['record']['contributors']);
				}
				
				if( !empty( arr_val($body['record'], 'contrib')) ){
					
					$contrib = arr_val($body['record'], 'contrib');
					if( strpos($contrib, ",") > 0  ){
					
						$first = explode(',', $contrib)[0];
						$last = explode(',', $contrib)[1];
						$contrib = trim($last, "[, ]") . " " . trim($first, "[, ]");
					}
					
					$body['record']['contributor'] = $contrib;
					
				}
				
			}
			
		}

		// Nightmare BDS <contributors><contrib role="" etc=""> field now ignored
		if(isset($body['record']['contributors'])){
			unset($body['record']['contributors']);
		}
		
		// 	END CONTRIBUTOR MAYHEM!!


	// Store <filename> 	
		$body['record']['filename'] = str_replace('\\', '/', $fname);
		
	// Make sure we have a ref_no
		if (empty($body['record']['ref_no']) ) {
		
			$body['record']['ref_no'] = @$body['record']['barcode'] ?: @$body['record'][ $params['updates_ref_no'] ] ?: @$body['record'][ $params['index_files_ref_no'] ] ?: @$body['record']['ehaus']['ref_no'] ?: 'generateuniquekey'; //ToDo??
			
		}
		
	// Games!! lots of records have no barcode! This breaks load DataHash as dbm=main has ref_no set as dhkey
		if (empty($body['record']['barcode']) ) {
		
			$body['record']['barcode'] = @$body['record']['ref_no'] ?: ''; 
			
		}
		
	/* DATES last_updated 
		[2021-08-16] - removed date handling from clean_data_nodes()
		N.B. strtotime() cannot interpret all possible mangles of a date (neither can we!)
	*/
	// TIDY UK_PUBDATE
		$val = @$body['record']['uk_pubdate'] ?: '';
		//logit('xml date', $val);
		if( !empty($val) ){

			if (strlen($val) == 4){
				// 30th of June, <year> - as per Eric's request.
				$body['record']['uk_pubdate'] = $val;
				//$data[$field . "_assumption"] = "year";
		
			}else{
				
				if ( strtotime($val) === false ) {
			
					// then uk_pubdate is either not really a date or its empty
					$body['record']['uk_pubdate'] = '';
					$body['record']['sort_date'] = '';
				
				} else {
				
					// try to format it pretty
					//logit('val', $val);
					$tmp = date('jS M Y', strtotime($val));
					//logit('tmp', $tmp);
					$body['record']['uk_pubdate'] = $tmp;
					
					$tmp = date('Ymd', strtotime($val));
					$body['record']['sort_date'] = $tmp;
					//logit('sort_date:0', $tmp);
					
				}
			}
			
		} else {
			// BDSLIVE Only (maybe)? if uk_pubdate is empty do we force sort_date to be empty?
			//$body['record']['sort_date'] = '';
			
		}
	
	// TIDY SORT_DATE - we usually get sent the sort_date in the XML
	$val = @$body['record']['sort_date'] ?: '';
	if( !empty($val) ){
		
		$val = preg_replace('/[^0-9]/', '', $val);
		if (strlen($val) == 4){
			
			$val = $val . "0630";
			
		} elseif(strlen($val) == 6){
			
			$val = $val . "01";
			
		}
	/* BDSLive only (maybe)!!
			$cutoff = date('Y', strtotime('+5 years'));
			if ( strtotime($val) > strtotime($cutoff. "0101") ) {
		
			// then sort_date is either not really a date or its empty
			$body['record']['sort_date'] = '';
			
		} else {
		
			// try to format it proper
				$tmp = date('Ymd', strtotime($val));
				//logit('sort_date:2', $tmp);
			$body['record']['sort_date'] = $tmp;
			
		} 
	*/	
	} 
	
	
	
	/* 	CREATE WORK_ID? IDEALLY CREATE THIS ON LOAD AND LEAVE BE
		N.B. Elastic will collapse all null/empty fields - so best populate work_id with work_id ?: ref_no
		USUALLY loaded via the ONIX feed
		OR you can try: MakeExact( $params['record']['ctitle'] ); BUT not too reliable
	
	*/
	
		if( !empty($params['collapse']) ){	// See DBM for the 'collapse' field being used
			
			if(empty($body['record']['work_id'])){
				// ? create one (best done on import) !! Check CMS Main-db validation too
				if(!empty($body['record']['work_id_src'])){
					$body['record']['work_id'] =  md5($body['record']['work_id_src']);
					//$body['record']['work_id'] = make_pagename(trunc($body['record'][$params['collapse']]));
				}
				
			}else{
				// DON'T INTERFERE!
				//$body['record']['work_id'] = md5($body['record']['work_id']);
				
			}
			
		}
		
	
		
	/* FIX MISSING IDENTIFIER FIELD !! 
		if( empty($body['record']['identifier']) ){
			
			$ida = array();
			$ids = ['ref_no', 'barcode', 'isbn_10', 'product_sku', 'product_id'];
			foreach($ids as $id){
				if(!empty($body['record'][$id])){
					$ida[] = $body['record'][$id];
				}
			}
			if(count($ida)>0){
				$body['record']['identifier'] = implode('|', $ida);
			}
			
			//print_r($body['record']['identifier']); die;
			
		}
	*/
	
	/*	Subject Tree
		- Any hierarchical Category code (e.g. BIC / Thema) can be indexed as follows 
		  using the tree_analyzer (see Mapping);
		
			<bic_tree>F;S;H</bic_tree>
			<cat_tree>F;S;H</cat_tree> [e.g. publisher's own category codes]
			<thema_tree>P;H;D</thema_tree>	
			
		- Search as a Filter or Filter_Prefix
			e.g. &SQF=bic_tree:P;H;D
			e.g. &SQF=CATT:P;H;D	// set 'prefix_filters'	=> [ 'CATT' => 'thema_tree', ]
			
		- Aggregations will show next child codes
		- Search.Functions.php will map codes to Friendly headers
				
		- ToDo: load multiple codes as array?
		- ToDO: test genre descriptions [ @genre_tree:adventure;platform;beat 'em up]
		- ToDO: N.B. Load up cat_code and set cat_class headings via filter.mapping.inc
							
		N.B. <psc>:: Keep <psc> as is for backwards compatibility and create new field from BICHash..
		
		- Set separator before you index in DBM [tree_analyzer_delimiter]
		  [N.B. watch out for the replacements you need to make for any given separator/code combination]
		
	*/
		$pubcat = ''; //@$body['record']['cat_code'] ?: @$body['record']['eh_cat_code'] '' ;
		if(!empty($pubcat) ){
		/* 
			This is just an example! You will need to configure this!!
			e.g. publisher's own code or e.g. /like/folder/tree/ or e.g. <genre>Parent|Child</genre>
		*/
			//logit('pubcat', "{$body['record']['ref_no']}/$pubcat");
			$codes = explode('|', $pubcat); 
			$tmp = array();
			foreach($codes as $code){
			
				
				$code = trim($code, " \t\n\r\0\x0B\|");
				$code = strtolower($code);
				$code = ucfirst($code);
				$code = preg_replace('/\&#\d{1,3};/', '@', $code);
				$code = str_replace('&', '@', $code);
				$code = str_replace('@', '¬', $code);			// This is about the last keypoard char left that isn't escaping something!!
				$code = str_replace($separator, ', ', $code); 	// clean the descriptions of $separators (default = ';')
				$code = str_replace('/', '-', $code); 			// clean '/' [could keep? but plays havoc with query_strings]
				$code = str_replace('_', '-', $code); 			// Now remove the underscores so it can be readable (e.g. if coming from psc) - if you want
				$code = preg_replace('/[-]+/', '-', $code); 
				$tmp[] = $code;
			
			}
			
						
			$body['record']['cat_tree'] = implode($separator, $tmp);
			//logit('eek', 	$body['record']['cat_tree']);
			
		} else {
			
			if(preg_match('/(main|alias|load)/i', $params['dbm'])){
				//logit('No Category Code', $body['record']['ref_no']); // ?map from somewhere?
			}
		}
		
		
	/* 	GENRE CODES 
		e.g. <genre>Parent|Child</genre>
	*/	
		if(!empty($body['record']['genre']) ){
		
			// We seem to be getting e.g. 'Add on pack|Adventure: Role Playing' where the pipe denotes multiple instance and ; sub trees
			$genre_tree = array();
			if(is_array($body['record']['genre'])){
				$body['record']['genre'] = implode('|', $body['record']['genre']);
			}
				
			$tree = trim($body['record']['genre'], " \t\n\r\0\x0B\|");
			
			if(preg_match('/^MUS/', $body['record']['ref_no'])){
			
				//logit('music', $tree);
				$tree = str_replace('|', $separator, $tree); 	// games
				$tree = str_replace('/', '-', $tree); 			// clean '/' [could keep? but plays havoc with query_strings]
				$tree = str_ireplace("Childrens", 'Children', $tree); 
				$tree = str_replace("'s", '', $tree); 
				$tree = str_replace("R&B", 'R@B', $tree); 
				$genre_tree[] = $tree;
			
			}elseif(preg_match('/^MM/', $body['record']['ref_no'])){
				
				//logit('film', "$tree");
				if(preg_match('/Documentary/', $tree)){
					
					$tree = str_replace("Special Interest|", '', $tree);
					$tree = str_replace("/", $separator, $tree);
					
				}
				$tree = str_replace('|', $separator, $tree); 	
				$tree = str_replace('/', '-', $tree); 
				$tree = str_replace("'n'", 'n', $tree); 
				$tree = str_ireplace("Childrens", 'Children', $tree); 
				$tree = str_replace("'s", '', $tree); 
				$genre_tree[] = $tree;
				
			}elseif(preg_match('/^SP/', $body['record']['ref_no'])){
				// i.e. GAMES
				//logit('games', $tree);
				$codes = explode('|', $tree);
				foreach($codes as $code){
			
					//logit('code', $code);
					$tree = str_replace(': ', $separator, $code); 	// games
					$tree = str_replace('/', '-', $tree); 			// clean '/' [could keep? but plays havoc with query_strings]
					$tree = str_replace('_', '-', $tree); 			// Now remove the underscores so it can be readable (e.g. if coming from psc) - if you want
					$tree = preg_replace('/\'em /i', 'em ', $tree); 
					$tree = str_replace('&#38;', '@', $tree); 
					$tree = str_replace('&', '@', $tree); 
					$tree = preg_replace('/[-]+/', '-', $tree); 	
					$tree = preg_replace('/[|]+/', '|', $tree); 
					$tree = str_ireplace("Childrens", 'Children', $tree);
					$tree = str_replace("'s", '', $tree); 
			
					if( preg_match('/^none/i', $tree) ){
						// we don't want
					}else{
						$genre_tree[] = $tree;
					}
			
		}
				//$gf = print_r($genre_tree, true); logit('final', $gf);
			}	
			
			$body['record']['genre_tree'] = $genre_tree;
		
		}
		//print_r($body);
		//$g = print_r($body['record']['genre_tree'], true); logit('genre_tree', $g);
	
		
	// THEMA TREE
		if(empty($body['record']['thema_subj_code']) ){
			
			//logit('No THEMA Code', $body['record']['ref_no']); // ?map from BIC? See Tmedia :)
			
		} else {
			
			$code = $body['record']['thema_subj_code'];
			//print_r($body['record']['genre']);
			
			if(is_array($code) ) {
				$code = reset($code); 	//if you are doin bic codes..this will give you the first code in the array					
			} 
				
			$code = trunc($code); // we can only deal with the first one
			$code = str_split( trim($code) );		
			$body['record']['thema_tree'] = implode($separator, $code);
			
		}
		
	// BIC CODE TREE
		if(empty($body['record']['bic_subj_code']) ){
			
			//logit('No BIC Code', $body['record']['ref_no']); // ?map from somewhere?
			
		} else {
			
			$code = $body['record']['bic_subj_code'];
			//print_r($body['record']['genre']);
			
			if(is_array($code) ) {
				$code = reset($code); 	//if you are doin bic codes..this will give you the first code in the array					
			} 
				
			$code = trunc($code); // we can only deal with the first one
			$code = str_split( trim($code) );		
			$body['record']['bic_tree'] = implode($separator, $code);
			
		}
		
	// PST - We get <psc> etc. in the XML2 data files - but not THEMA=><pst>
	/*	However this is only used in really old site now..
	
		$code = @$body['record']['thema_subj_code'];
		if (!empty($code)){
			
			if(is_array($code) ) {
			
				$code = reset($code); 	//..this will give you the first code in the array
				$body['record']['pst'] = @$thema_map[strtoupper($code)];		
			
			} else {
				
				$code = trunc($code);
				$body['record']['pst'] = @$thema_map[strtoupper($code)];	

			}
		
		}
		
	*/
		
	/* SUGGESTION FIELDS [2023-04-01]
		<suggest_terms> indexed as 'type' = 'search_as_you_type' and searched via &MyOwnQuery
		- this is not available Elaastic v5.6
		- use suggest_name and suggest_title
		
		<suggest_name> + <suggest_title>
		N.B. The 'max_input_length' => 100 MAY truncate what is returned making the subsequent search break
			- consider truncating on a whole word < e.g. 100 before adding to index
			
		N.B. The Completion Suggester will ignore any Filters configured in an Alias! yay! So you need to add them here
		
	*/	
		if(@$params['elastic_suggestions_index'] == true){
			
			$arr = array(); $name_arr = array(); $title_arr = array();
			
			$name_field = @$body['record']['contributor'] ?: @$body['record']['product_contributor'] ?: '';
			if(!empty($name_field)){
				
				if( is_array($name_field) ){
					$terms = $name_field;
				} else {
					$terms = explode('|', $name_field);
				}
				//print_r($terms);
				$primary = trunc($terms[0]);
				$primary = str_ireplace( array('author', 'editor', 'contributor', 'contributors', 'narrator'), ' ', $primary );
				$primary = convert_chars($primary);
				$primary = preg_replace('/\d{4}/', '', $primary);
				$primary = preg_replace('/\s+/', ' ', $primary);
				//print_r($primary);die;
				
				$primary = explode(' ', $primary);
				$last = array_pop($primary);
				$first = implode(' ', $primary);
				$names[] = trim("$first $last", '[,.;- ]'); // for BDS this is backwards "$last $first"!
				
				foreach($names as $n){
				
					if(!empty($n)){
						
						$n = preg_replace('/[^A-Za-z \'\.\-]+/', '', $n);
						$name_arr[] = [ 'input' => $n, 'weight' => 10];
						$parts = explode(' ', $n);
						for ($i = 1; $i <= count($parts)-1; $i++) {
														
							$tmp = array_shift($parts);
							array_push($parts, $tmp);
							$inp = implode(' ', $parts);
							$name_arr[] = [ 'input' => $inp, 'weight' => 1];
							
						}
						
					}
				}
				
				//print_r($terms); //die;
				//print_r($body['record']['suggest_name']);
				
			}
			
			$title_field = @$body['record']['ctitle'] ?: @$body['record']['product_title'] ?: '';
			if(!empty($title_field)){
				
				$terms = explode('|', $title_field);
				
				//print_r($parts);die;
				foreach($terms as $t){
					$t = trunc($t);
					if(!empty($t)){
						
						$t = preg_replace('/[^A-Za-z0-9 ]+/', '', $t);
						$suggest_terms[] = $t;
						$title_arr[]  = [ 'input' => $t, 'weight' => 10];
						$parts = explode(' ', $t);
						for ($i = 1; $i <= count($parts)-1; $i++) {
														
							$tmp = array_shift($parts);
							array_push($parts, $tmp);	// adds beginning word onto end
							$inp = implode(' ', $parts);
							$title_arr[] = [ 'input' => $inp, 'weight' => 1];
							$parts = explode(' ', $t);
							for ($i = 1; $i <= count($parts)-1; $i++) {
															
								$tmp = array_shift($parts);
								array_push($parts, $tmp);	// adds beginning word onto end
								$inp = implode(' ', $parts);
								$title_arr[] = [ 'input' => $inp, 'weight' => 1];
								
							}
							
						}
						
					}
				}
														
							
			}
						
			if($params['es_version'] > 7){
				
				$body['record']['suggest_terms'] = @$suggest_terms;
				unset($body['record']['suggest_title']); // in case it was dumped into the XML
				unset($body['record']['suggest_name']); 
			
			}else{
				
				$body['record']['suggest_title'] = $title_arr;
				$body['record']['suggest_name'] = $name_arr;
				unset($body['record']['suggest_terms']); 
				
			}
			
		} else {
			
			/* just don't include the fields at all!
			$arr = new \stdClass();
			$body['record']['suggest_title'] = $arr;
			$body['record']['suggest_name'] = $arr;
			*/
		
		}
		
		// ADD A NEW DATE RANGE
		if( !empty($body['record']['uk_exvat_price']) ){
			
			$price = $body['record']['uk_exvat_price'];
			if($price <= 5) {
				$body['record']['primary_price'] = "A Under _5";
			}elseif($price > 5 && $price <= 10){
				$body['record']['primary_price'] = "B_5 to _10";
			}elseif($price > 10 && $price <= 15){
				$body['record']['primary_price'] = "C_10 to _15";
			}elseif($price > 15 && $price <= 20){
				$body['record']['primary_price'] = "D_15 to _20";
			}elseif($price > 20 && $price <= 30){
				$body['record']['primary_price'] = "E_20 to _30";
			}elseif($price > 30 && $price <= 50){
				$body['record']['primary_price'] = "F_30 to _50";
			}elseif($price > 50 && $price <= 100){
				$body['record']['primary_price'] = 'G_50 to _100';
			}elseif($price > 100 ){
				$body['record']['primary_price'] = "H Over _100";
			}
			
		}
		
		// FIX EDITION FIELD
		if(!empty($body['record']['edition']['@attributes']['relation'])){
			//unset($x['edition']);
			$body['record']['edition'] = $body['record']['edition']['@attributes']['relation'];
		}
		
		// WE DON'T WANT TO INDEX THESE FIELDS [SEE DBM: default_noindex]
		if(!empty($params['default_noindex'])){
			foreach($params['default_noindex'] as $fld){
				
				if( isset($body['record'][ $fld ]) ){
					
					unset( $body['record'][ $fld ] );
					
				}
				
			}
		}
		
		// RESET THESE FIELDS IF 'MISSING' FROM XML
		if(!empty($params['default_reset_index'])){ 
			foreach($params['default_reset_index'] as $fld){ 
				if( empty($body['record'][ $fld ]) ){ 
					$body['record'][ $fld ] = ''; 
				} 
			} 
		}
		
		// SMARTCART: FORMAT_TYPE & FORMAT_TYPE_CODE
		/* 	The SmartCart uses format_type & format_type_code so we can preserve ONIX ProductForm data 
			ONLY BDSLive actually uses these fields for Faceted browsing so free for the SmartCart in most circs */
		if( !empty($body['record']['format']) ){
			
			//logit("..setting format_type fields", "");
			
			switch(strtolower($body['record']['format'])) {

				case (preg_match('/paperback/i', $body['record']['format']) ? true : false) :
					$body['record']['format_type'] = "Paperback";
					$body['record']['format_type_code'] = "BC";
					
				break;
					
				case (preg_match('/hardback/i', $body['record']['format']) ? true : false) :
					$body['record']['format_type'] = "Hardback";
					$body['record']['format_type_code'] = "BB";
					
				break;
				
				default:
				// See ONIX3.1-import.php for handling
					//$body['record']['format_type'] = $body['record']['format'];
					//$body['record']['format_type_code'] = $body['record']['format_code'];
				
			}
			
		}
		
		// ORDER-db upgrade metadata field 
		if( !empty($body['record']['metadata']) ){
			
			if( isset( $body['record']['metadata']['items'] ) ){
				// this should be the new Format
			}else{
				// this should be the old Format
				$meta_old = $body['record']['metadata'];
				if( !is_array($meta_old) ){
					$meta_old = [$meta_old];
				}
		
				$body['record']['metadata'] = [
					'items' => $meta_old
				];
				
			}
			
		}
		
		
		//logit($body['record']['ref_no'], @$body['record']['pst']);
		//echo "\r\n===============\r\n";
		
		
		//ksort($body['record']);
		//print_r($body); die;
		return $body;
		
	}


/*******************************************************************************/
/*
	Attempts to standardise the treatment for fields and field types, 
	BUT you will need to configure the DBM as not always possible to guess intent!
	
	'default_cdata_elements'	=> stored as ['field']['@cdata'] => 'value',
	'default_cdata_convert'		=> converts a supplied <![CDATA[ ]]> field into a normal XML element
	'default_sub_nodes'			=> Passes sub_nodes thru the clean_data_nodes() function (<ehaus> is included by default)
	'default_empty_objects' 	=> For fields that have a nested object mapping that might be updated with an empty <xml></xml> string causing indexing error "object mapping for [xxx] tried to parse field [xxx] as object, but found a concrete value"
	'default_force_array'		=> This stores field as array - vital for e.g. Filter seaching fields configured as type=>keyword on individual codes

	- Multi-instance fields => Default approach is to store as <field>pipe|separated</field> [DBM type => text|keyword]
	- N.B. if field is not mapped in the DBM as type => text|keyword and the XML submits an array - we may not be able to determine whether to convert to pipes
	- Fields with attributes; Default is to strip attributes. Need to configure DBM if you want to store & index
	- Date fields: Attempts to fix formatting issues
	- Price fields: Attempts to fix formatting issues
	
	!! DON'T FORGET IDENTICAL FUNCTION IN ACS-PROXY.PHP !!

*/

	function clean_data_nodes($data){
		
		global $params;
	
		if(empty($data) || !is_array($data)){ return;}
		
		//print_r($data); 
		//print_r(arr_filter_blanks($data));
		$data = array_change_key_case($data);
		
		// LOOP THRU RECORD BY FIELDS..
		foreach($data as $field=>$val){
		
		// MULTI-VALUE FIELDS: by default try to store as pipe-separated text
			$force_array = array_unique(array_merge(['acs_session_id'], (@$params['default_force_array'] ?: ['']) ));
						
			if( is_array($val) ){
				// check the DBM..
				if(!in_array($field, $force_array)){
					// check the Mappings..
					if(!empty(arr_search($params, $field)['value']['type'])){
					$type = arr_search($params, $field)['value']['type'];					
					} else {
						$type = '';
					}
					
					if( $type == 'text' || $type == 'keyword' ){						
						//$x = print_r($val, true); logit("Checking field [$field] array", "$x");
						
						if( arr_depth($val) == 0) {
						
							//$x = print_r($val, true); logit("..[$field] array depth", arr_depth($val));
							if( arr_val($val, '@attributes') ){
							
								//$x = print_r($val, true); logit("1. stripping attributes [$field] array to pipes", "$x");
								$data[$field] = implode('|', arr_search_keys($val, '@value'));
							
							} elseif( arr_val($val, '@cdata') ) {
								
								// do nothing - see later
								//$x = print_r($val, true); logit("skipping cdata [$field] for now", "$x");
								
							} else {
								
								//$x = print_r($val, true); logit("1. converting [$field] array to pipes", "$x");
								$data[$field] = implode('|', $val);
								
							}
						
						} elseif( arr_depth($val) == 1) {
							
							if( arr_val($val, '@attributes') ){
							
								//$x = print_r($val, true); logit("2. stripping attributes [$field] array to pipes", "$x");
								$data[$field] = implode('|', arr_search_keys($val, '@value'));
							
							} 
							
						} elseif( arr_depth($val) == 2) {
							
							if( arr_val($val, '@attributes') ){
							
								//$x = print_r($val, true); logit("3. stripping attributes [$field] array to pipes", "$x");
								$data[$field] = implode('|', arr_search_keys($val, '@value'));
							
							} 
							
						}
						
						$data[$field] = index_ampersands($data[$field]);
						
					}
					
				} else {
					
					// it's already an array AND its in $force_array - we still want to run it thru index_ampersands()
					$data[$field] = index_ampersands($val);
					
				} 

			} else {
				
				// ERG! So, Not an array but we WANT it as an array
				if( in_array($field, $force_array) ){
					
					//$x = print_r($val, true);logit("2. Storing [$field] as array:", $x);
					$data[$field] = explode('|', $val);
					
				}
				
				$data[$field] = index_ampersands($data[$field]);
				
			}
			
		// CDATA FIELDS: These should be defined via DBM Mappings or as $params['default_cdata_elements']
			$properties = arr_get($params, "elastic_index_config.body.mappings.doc.properties.$field.properties.@cdata");
			if( !empty($properties) || in_array($field, $params['default_cdata_elements']) ){
				
				//$x = print_r($val, true);logit("CDATA [$field]:", $x);
				
				if( !isset($data[$field]['@cdata']) ){
										
					//$x = print_r($val, true);logit("CDATA [$field] create @cdata node:", $x);	
					if( is_array($val) && arr_depth($val) == 0 ){
						$val = implode(' - ', $val);
					}
					$val = str_replace('|', ' ', $val);
					//$x = print_r($data, true);logit("6. CDATA [$field] data:", $x);	
					unset($data[$field]);
					$data[$field]['@cdata'] = $val;
					
				} else {
					
					//$x = print_r($val, true);logit("CDATA [$field] clean pipes:", $x);	
					$data[$field]['@cdata']  = str_replace('|', ' ', $data[$field]['@cdata'] );

					
				}
				//print_r($data);
			}
		
		// NOT CDATA - CONVERT <![CDATA[XML]]> BACK TO NORMAL TEXT
			if(!empty($params['default_cdata_convert']) ){
				
				if( in_array($field, $params['default_cdata_convert']) ){
					
					if( !empty(arr_val($val, '@cdata')) ){
						//logit('default_cdata_convert', "$field");
						$v = arr_val($val, '@cdata');
						if( is_array($v) ){
							$v = implode(' - ', $v);
						}
						unset($data[$field]);
						$data[$field] = strip_tags($v);
						
					}
					
				}
				
			}
		
		// JSON EXPAND FIELDS (Stored for convenience as  <xml><![CDATA[some stuff]]></xml> but indexed as expanded JSON
			if( !empty($params['default_json_expand']) ){
				
				if( in_array($field, $params['default_json_expand']) ){
					
					$v = json_decode( arr_val($data, $field), true);
					$data[$field] = $v;
					
				}
				
			}
				
		// DATE FIELDS - too specific - dealt with in fn_make_xml2() above
		
		// XHTML FIELDS - create a copy field [original_field_name + _xhtml] without HTML tags for searching & highlighting
			if(!empty($params['default_strip_html']) ){
				
				if( in_array($field, $params['default_strip_html']) ){
					
					$tmp = '';
					
					if( is_array($val) ){
						
						if( !empty(arr_val($val, '@cdata')) ){
							
							$tmp = arr_val($val, '@cdata');
							if( is_array($tmp) ){
								$tmp = implode(' ', $tmp);
							}
						}
						
					} else {
						
						$tmp = $val;
						
					}
					
					if( !empty($tmp) ){
						
						$data[$field."_xhtml"] = strip_tags($tmp);
						
					}
					
				}
				
			}
			
		// PRICE FIELDS WITH CRAP IN THEM
			if( preg_match('/price/', $field) && !is_array($val) ) {
				
				//$x = print_r($val, true);logit("price field", "$field: [$x]");
				if( preg_match('/^primary_/', $field) === false ){
				
					$val = str_replace('&amp;', '&', $val);
					$val = preg_replace('/&#\d{1,4};/', '', $val); 
					$val = preg_replace('/[^0-9\.]+/', '', $val);
					
					$data[$field] = $val;
					
				}
			
			}
			
	

		} // end body['record']['fields'] loop
		
		// SPECIAL CASE NESTED OBJECTS EEK!
		/* Handle nested field objects 
			- If you declare an elastic mapping for a nested object, then the record will be
			rejected if you send an empty <xml> node UNLESS you spot this and set the value to an empty array()
			N.B. Either configure a full mapping for the nested object or let elastic do this with dynamic mapping
				 - elastic will work with the FIRST record it receives - so make sure this has all fields in it!
			
			BEST BET: tell the DBM Mapping to ignore the whole object for indexing purposes..
			
			'transaction_details' => [
				'type'		=> 'object',
				'enabled'	=> false,
				
			],
												
		*/
		if(!empty($params['default_empty_objects']) ){
			
			foreach( $params['default_empty_objects'] as $obj ){
					
				$arr = array();
				$empties = arr_search_all_keys($data, $obj);
				//$x = print_r($empties, true); logit("keys:[$obj]", "eek:$x");
				foreach($empties as $obj_path=>$obj_val){
					
					if(!is_array($obj_val) && strlen($obj_val) == 0 ) {

						arr_set($data, $obj_path, $arr);
					
					}
					
				}
				
			}
			
		}
			
		//print_r($data); die;
		//print_r("\r\n====================================\r\n");
		return $data;
		

	}
/*******************************************************************************/
	
	function index_ampersands($val){
		
	// WE want to search for things like "Matt's & Lily's" so we need to treat character entities consistently across everything!
	
	/*	
		- Which means we are going to need to store in the index exactly waht the CMS saves to XML
		- store in XML as normal: "Matt's &#38; Lily's" [or Matt&#39;s &#38; Lily&#39;s" 
		- index as "Matt's &#38; Lily's" 
		- search as indexed (CGI just needs to convert '&amp;' to '&' before searching
	
	*/
	
		global $params;
			
			
		if(!is_array($val)){
				
				$val = str_replace('&amp;', '&', $val);
				$val = html_entity_decode($val, ENT_QUOTES, 'UTF-8');
				$val = str_replace('&', '&#38;', $val);
			
		} else {
			
			foreach($val as $idx=>$v){
				
				if(!is_array($v)){
			
					$v = str_replace('&amp;', '&', $v);
					$v = html_entity_decode($v, ENT_QUOTES, 'UTF-8');
					$v = str_replace('&', '&#38;', $v);
					$val[$idx] = $v;
				}
			}
				
		}
			
		return $val;
		
	}
	
/*******************************************************************************/
	
	function fnIndexDelete($idx = '') {
	
		global $config, $params, $response;
		
		check_request_authorised('DELETE');
		
		$index = [
	
			'index' =>  $idx ?: $params['dbm_index']
		
		];
		
		logit('deleting index', $index['index']);
		
		//print_r($batch);die;
		$host = reset($config['hosts']);
		$options = ['request_type' => 'DELETE'];
		$rurl = "$host/{$index['index']}";
		$json = getRest($rurl, "", $options);
		$resp = json_decode($json, true);
		//$x = print_r($resp, true); echo "<!-- $x -->\r\n"; //die;
		
		// check for REST API error
		if(json_error($explain)){
			logit("Error index_delete: ", $explain);
			//return;
		}	
		
		// check for CURL error
		if(arr_val($resp, 'error_rest') == true){
			logit('CURL Error' . ' (' .arr_val($resp, 'errorcode') . ')', arr_val($resp, 'errormessage')  );
			//die;
			
		}
		
		if (@$resp['error']) {
			
			logit('Error deleting index', $params['dbm_index']);
			//$r = print_r($resp, true); logit('resp', $r);
			$response['errorcode'] = 1;
			$response['errormsg'] = 'Error deleting index (' . $params['dbm_index'] .')';
			$response['responsetext'] = arr_val($resp, 'reason');
			
		} else {
		
			logit('index deleted', @$resp['index']);
			
			$response['errorcode'] = 0;		
			$response['errormsg'] = 'Index deleted';
			$response['responsetext'] = "Script finished successfully. Index deleted (" .$params['dbm_index'] . ') Type: (' . $params['dbm_type'] .')';	
			
			
		}
			

		$response['logfile'] = $params['log_file'];
		
	
	}
/*******************************************************************************/
	
	function fnIndexCreate() {
	
		global $params, $config, $response;
				
		$index = $params['elastic_index_config'];	// Directly from the DBM file
		
		/* 	v7.0+ Changes
			At the moment we are going to keep the DBM's as they are and make adjustments in the elastic-indexer
			- index types are now 'deprecated'
		
		*/
		if( $params['es_version'] >= 7){
			
		// REMOVE 'type' node - if present. So, old DBM's will be handled. New DBM should have no 'dbm_type' and no body.mappings.type node
			if(!empty($params['dbm_type'])){
				
				if( isset($index['body']['mappings'][ $params['dbm_type'] ]) ){
					
					$type = $index['body']['mappings'][ $params['dbm_type'] ];
					unset($index['body']['mappings'][ $params['dbm_type'] ]);
					arr_set($index, 'body.mappings', $type);
					//print_r($index);die;
					
				}
			}
			
		// REMOVE mapping '_all' - setting to false no longer any good
			if( isset($index['body']['mappings'][ $params['dbm_type'] ]['_all']) ){
			
				unset($index['body']['mappings'][ $params['dbm_type'] ]['_all']);
				
			}
			if( isset($index['body']['mappings']['_all']) ){
				
				unset($index['body']['mappings']['_all']);
				
			}
			//print_r($index);die;
			
		// REMOVE Filter => ['standard']
			if( isset($index['body']['settings']['analysis']['analyzer']['trigram']['filter']) ){
				// this is the common one - there may be others
				$index['body']['settings']['analysis']['analyzer']['trigram']['filter'] = [
					'shingle_filter'
				];
				//print_r($index);die;
			}
			
			
		}// END v7.0+ adjustments
		
		// toDo provide for create alias at the same time :)
		unset($index['index']);	// its in the DBM but without the $client this goes in the $rurl :)
	
		logit('Creating index', 'Index: ('.$params['dbm_index'] . ') Type: (' . $params['dbm_type'] .')');
		
		$index_json = json_encode($index['body'], JSON_PRETTY_PRINT);
		$index_json = "$index_json\n";
		//$x = print_r($index_json, true); echo "<!-- $x -->\r\n"; die;
		
		$host = reset($config['hosts']);
		$options = ['request_type' => 'PUT', 'timeout' => 5000];
		$rurl = "$host/{$params['dbm_index']}";
		$json = getRest($rurl, $index_json, $options);
		$resp = json_decode($json, true);
		//$x = print_r($resp, true); echo "<!-- $x -->\r\n"; //die;
		
		// check for REST API error
		if(json_error($explain)){
			logit("Error index_create: ", $explain);
			//return;
		}	
		
		// check for CURL error
		if(arr_val($resp, 'error_rest') == true){
			logit('CURL Error' . ' (' .arr_val($resp, 'errorcode') . ')', arr_val($resp, 'errormessage')  );
			//die;
			
		}
		
		if (@$resp['error']) {
			
			logit('Error creating index', $params['dbm_index']);
			//$r = print_r($resp, true); logit('resp', $r);
			$response['errorcode'] = 1;
			$response['errormsg'] = 'Error creating index (' . $params['dbm_index'] .')';
			$response['responsetext'] = arr_val($resp, 'reason');
			
		} else {
		
			logit('index created', @$resp['index']);
			
			$response['errorcode'] = 0;		
			$response['errormsg'] = 'Index created';
			$response['responsetext'] = "Script finished successfully. Index created (" .$params['dbm_index'] . ') Type: (' . $params['dbm_type'] .')';	
			
			
		}
			
		
		$response['logfile'] = $params['log_file'];
		

	}
/*******************************************************************************/
	function fnIndexDeleteCreate(){
	
		global $params, $response;
				

		logit('Deleting & Creating clean index', 'Index: ('.$params['dbm_index'] . ') Type: (' . $params['dbm_type'] .')');
			
		fnIndexDelete();
		fnIndexCreate();
		
	}
	
	
/*******************************************************************************/	
	function fnGetSettings($idx = '') {
	
		global $params, $config, $response;
		
		$request = [
	
			'index' =>  $idx ?: $params['dbm_index']
		
		];
		
		logit('Get settings', 'Index: ('.$params['dbm_index'] . ') Type: (' . $params['dbm_type'] .')');

		$host = reset($config['hosts']);
		$options = ['request_type' => 'GET'];
		$rurl = "$host/{$request['index']}/_settings";
		$json = getRest($rurl, "", $options);
		$resp = json_decode($json, true);
		//$x = print_r($resp, true); echo "<!-- $x -->\r\n"; //die;
		
		// check for REST API error
		if(json_error($explain)){
			logit("Error index_create: ", $explain);
			//return;
		}	
		
		// check for CURL error
		if(arr_val($resp, 'error_rest') == true){
			logit('CURL Error' . ' (' .arr_val($resp, 'errorcode') . ')', arr_val($resp, 'errormessage')  );
			//die;
			
		}
		
		$xml = Array2XML::createXML('settings', $resp[$request['index']]['settings']);
		$xml = $xml->saveXML();
		if ($resp) {
		
			$response['errormsg'] = 'Settings for index: [' . $request['index'] . ']';
			$response['errorcode'] = '0';
			/*$response['log'] = str_replace('<?xml version="1.0" encoding="UTF-8"?>', '', $xml);*/
			$response['log'] = json_encode($resp, JSON_PRETTY_PRINT);
			
		} else {
		
			$response['errormsg'] = 'Error GetSettings (' . $params['dbm_index'] .')';
			$response['errorcode'] = 1;
			
		}
	
		
	
	
	}
/*******************************************************************************/	
	function fnPutSettings($idx = '') {
	
		global $params, $config, $response;
		
		//print_r($params); die;
				
		
		if( (@$params['replicas']) == '' && (@$params['interval']) == '' && (@$params['max_results']) == ''){
			
			logit("Put Settings", "Currently this gives you access to the _settings API.");
			logit("e.g.", "DBM=index-name AX=settings + ");
			logit("Set replicas:", "replicas=0|1|2|3|etc");
			logit("Set refresh_interval:", "interval=-1 (disabled)| =1 (default)| =null");
			logit("Set max_result_window:", "max_results=10000 (default)");
			return;
			
		}
		
		if( @$params['replicas'] >= 0 and strlen(@$params['replicas'] ?: '') > 0){
			
			$msg = "Set replicas: [{$params['replicas']}]";
			$request = [
				'index' => [
					'number_of_replicas' => $params['replicas']
				]
			];

		}
		
		if( !empty($params['interval']) and strlen(@$params['interval'] ?: '') > 0){
			
			$msg = "Set interval: [{$params['interval']}]";
			$request = [
				'index' => [
					'refresh_interval' => $params['interval']
				]
			];
			
		}
			
		if( !empty($params['max_results']) and strlen(@$params['max_results'] ?: '') > 0){
			
			$msg = "Set max_results: [{$params['max_results']}]";
			$request = [
				'index' => [
					'max_result_window' => $params['max_results']
				]
			];
			
		}
				
		logit('Put settings', 'Index: ('.$params['dbm_index'] . ') Type: (' . @$params['dbm_type'] .')');
		
		$request_json = json_encode($request, JSON_PRETTY_PRINT);
		$request_json = "$request_json\n";
		//$x = print_r($request_json, true); echo "<!-- $x -->\r\n"; //die;
		
		$host = reset($config['hosts']);
		$options = ['request_type' => 'PUT'];
		$rurl = "$host/{$params['dbm_index']}/_settings";
		$json = getRest($rurl, $request_json, $options);
		$resp = json_decode($json, true);
		//$x = print_r($resp, true); echo "<!-- $x -->\r\n"; //die;
		
		// check for REST API error
		if(json_error($explain)){
			logit("Error put_settings: ", $explain);
			//return;
		}	
		
		// check for CURL error
		if(arr_val($resp, 'error_rest') == true){
			logit('CURL Error' . ' (' .arr_val($resp, 'errorcode') . ')', arr_val($resp, 'errormessage')  );
			//die;
			
		}

			
		if (@$resp['error']) {
			
			logit('Error Put Settings', $params['dbm_index']);
			//$r = print_r($resp, true); logit('resp', $r);
			$response['errorcode'] = 1;
			$response['errormsg'] = 'Error Put Settings (' . $params['dbm_index'] .')';
			$response['responsetext'] = arr_val($resp, 'reason');
			
		} else {
		
			logit('Put settings: acknowledged', @$resp['acknowledged']);
			
			$response['errorcode'] = 0;		
			$response['errormsg'] = 'Put settings: acknowledged';
			$response['responsetext'] = "Script finished successfully. Index (" .$params['dbm_index'] . ') Action: (' . "$msg" .')';	
			
			
		}
	
	
	}
/*******************************************************************************/
/*	
	curl -X POST "localhost:9200/my-index-000001/_refresh?pretty"
	
	$rurl = AddBaseRef("/lib/elastic/elastic-indexer.php?dbm={$params['sitename']}-order&ax=refresh", 'url');
	$x = get_rest($rurl);
	
*/
	function fnRefresh($idx = ''){
		
		global $params, $config, $response;
		
		
		if( empty($params['dbm']) ){
			
			logit("No DBM - Quitting", "ax=refresh dbm=site-index");
			return;
			
		} else {
			
			logit("DBM loaded - refreshing index", $params['dbm_index']);
			$host = reset($config['hosts']);
			$rurl = "$host/{$params['dbm_index']}/_refresh?pretty";
			logit("refresh", $rurl);
			
			$x = post_xml($rurl, '');
			
			if(!empty($x)){
				
				$r = json_decode($x, true);
				$response['errormsg'] = "failed count: " . arr_val($r, 'failed');
				$response['responsetext'] = "successful count: " . arr_val($r, 'successful');
				$response['errorcode'] = arr_val($r, 'failed');
				
			}
			
		}
		
		
	}
/*******************************************************************************/
/*	Search using the alias by setting -dbm.inc::$params['dbm_index'] = "alias-name",

	ADD: 	 ax=alias-add alias=my_new_alias index=index_it_points_to
	REMOVE:  ax=alias-remove alias=my_old_alias index=index_it_pointed_to
	REPOINT: ax=alias-repoint alias=my_alias index=index_it_points_to new_index=point_here_now
	GET:	 ax=alias-get index=index_name|all
	
	ADD + FILTER: 
	N.B. Term filter fields need to be type=keyword [i.e. field.raw]
	e.g. filter="{'term':{'distributor_code.raw':'GARD'}}"
	e.g. filter="{'term':{'format_code':'DG'}},{'term':{'ehaus.eh_published_flag':'Y'}}"
	e.g. ax=alias-add dbm=sitename-main alias=bds-main-alias index=bds-main filter=^"{^
		 filter=^"{^
			'bool': {^
				'filter':   ^[^
					{ 'terms': { 'uk_avail_code': ['10', '31'] }},^
					{ 'terms': { 'bic_subj_code': ['HBW', 'AB', 'HB'] }}^
				^]^
			}^
		 }^"
	
	N.B. For cmd line you need to remove carriage returns and wrap filter in ^"double-quotes^" 
		 - or escape ^CR/LF and ^[brackets^] using ^
	
*/	
	function fnAlias($idx = '') {
	
		global $params, $config, $response;
		
		//print_r($params); die;
		
		$ax = str_ireplace('alias-', '', $params['ax']);
		$ax = str_ireplace('-live', '', $ax);
		logit('Alias action:', $ax);
		
		if($ax == "get"){
			
			logit("retrieving aliases for:", @$params['index'] ?: $params['dbm']);
			
		}else{
		
			if( empty($params['alias']) || (empty($params['index'])) ){
				
				logit("Alias: error", "You need to enter the alias and the index e.g. [alias=my_new_alias index=index_to_point_to]");
				logit('ax=alias-[add|remove|(repoint)]', 'index=this_index alias=alias_name (new_index=repoint_2_me)');
				return;
				
			}
		}
		
		
		if( strpos($params['index'], ',') ){
			
			$idx_key = 'indices';
			$idx_list = explode(',', $params['index']);
			
		} else {
			
			$idx_key = 'index';
			$idx_list = $params['index'];
			
		}
		
		if($ax == 'add' || $ax == 'remove'){
			
			$action = [
				$ax => [
					$idx_key => $idx_list,
					'alias' => $params['alias'],
				]
			];
				
			
		}
		//print_r($action); die;
		if($ax == 'repoint'){
			
			$action[] = [
				'remove' => [ 'index' => $params['index'], 'alias' => $params['alias'] ]
				
			];
			$action[] = [
				'add' => [ 'index' => $params['new_index'], 'alias' => $params['alias'] ]
			];
			
		}
		
		if($ax == 'get'){
				
			$host = reset($config['hosts']);
			$options = ['request_type' => 'GET'];
			
			if( preg_match("/all/i", $params['index']) ){
				$rurl = "$host/_alias";
			}else{
				$rurl = "$host/{$params['dbm']}/_alias";
			}
			//logit('alias requested', $rurl);die;
			$json = getRest($rurl, "", $options);
			//$resp = json_decode($json, true);
			logit("..aliases", $json);
			//$x = print_r($json, true); echo "<!-- $x -->\r\n"; //die;
			$response['errormsg'] = 'Aliases (' . $params['dbm'] .')';
			$response['errorcode'] = 0;
			$response['responsetext'] = $json;
			return;
			
			
		}
		
		
		//print_r($action); die;
		$request['body'] = [
			'actions' =>   $action 
		];
		
		//print_r($request); die;
		
		if( !empty($params['filter']) && $ax == 'add' ){
			
			$f = str_replace("'", '"', $params['filter']);
			$f = str_replace("^", '', $f);
			$f = str_replace("\t", '', $f);
			//logit('JSON:1',$f);
			$f = json_decode($f, true);
			json_error( $explain ); logit('test JSON', $explain);
			//$xf = print_r($f, true);  logit('JSON:2',$xf);
			if($f){
	
				$request['body']['actions'][$ax] += [
					'filter' => $f
				];
					
			}
			
		} else {
			
			logit("N.B. No Filter supplied.");
			
		}
			
		//print_r($request); die;
		$ax = ucfirst($ax);
		logit("$ax Alias", 'Index: ('.$params['index'] . ') Alias: (' . $params['alias'] .')');
		
		$request_json = json_encode($request['body'], JSON_PRETTY_PRINT);
		$request_json = "$request_json\n";
		//$x = print_r($request_json, true); echo "<!-- $x -->\r\n"; //die;
		
		$host = reset($config['hosts']);
		$options = ['request_type' => 'POST'];
		$rurl = "$host/_aliases";
		$json = getRest($rurl, $request_json, $options);
		$resp = json_decode($json, true);
		//$x = print_r($resp, true); echo "<!-- $x -->\r\n"; //die;
		
		// check for REST API error
		if(json_error($explain)){
			logit("Error put_alias: ", $explain);
			//return;
		}	
		
		// check for CURL error
		if(arr_val($resp, 'error_rest') == true){
			logit('CURL Error' . ' (' .arr_val($resp, 'errorcode') . ')', arr_val($resp, 'errormessage')  );
			//die;
			
		}

		
		if (@$resp['error']) {
			
			logit('Error Alias', $params['dbm_index']);
			//$r = print_r($resp, true); logit('resp', $r);
			$response['errorcode'] = 1;
			$response['errormsg'] = 'Error Alias (' . $params['dbm_index'] .')';
			$response['responsetext'] = arr_val($resp, 'reason');
			
		} else {
		
			logit('Alias: acknowledged', @$resp['acknowledged']);
			
			$response['errorcode'] = 0;		
			$response['errormsg'] = 'Alias: acknowledged';
			$response['responsetext'] = "Script finished successfully. Index (" .$params['dbm_index'] . ') Action: (' . 'Alias' .')';	
			
			
		}
	
	
	}
	
/*******************************************************************************/
	function fnGetMapping($idx = '') {
	
		global $params, $config, $response;
			
		$request = [
	
			'index' =>  $idx ?: $params['dbm_index'] ?: $params['dbm']
		
		];
		logit('Get Mapping for ', $request['index']);
		
		if(empty($params['view'])){
			$params['view'] = 'json';
		}
		
		$host = reset($config['hosts']);
		$options = ['request_type' => 'GET'];
		$rurl = "$host/{$request['index']}/_mapping";
		$json = getRest($rurl, "", $options);
		$resp = json_decode($json, true);
		//$x = print_r($resp, true); echo "<!-- $x -->\r\n"; die;
		
		// check for REST API error
		if(json_error($explain)){
			logit("Error fnGetMapping: ", $explain);
			//return;
		}	
		
		// check for CURL error
		if(arr_val($resp, 'error_rest') == true){
			logit('CURL Error' . ' (' .arr_val($resp, 'errorcode') . ')', arr_val($resp, 'errormessage')  );
			//die;
			
		}
		
		
		if ($resp) {
		
			$response['errormsg'] = 'Settings for index: [' . $request['index'] . ']';
			$response['errorcode'] = '0';
			/*$response['log'] = str_replace('<?xml version="1.0" encoding="UTF-8"?>', '', $xml);*/
			/*if( $params['view'] == '' ){
				$response['log'] = $r->asString();
			} elseif( $params['view'] == 'json'){
				$response['json'] = json_encode($resp, JSON_PRETTY_PRINT);
			}
			*/
			if( $params['view'] == 'print' ){
				$response['log'] = print_r($resp, true);
			} elseif( $params['view'] == 'json'){
				$response['json'] = json_encode($resp, JSON_PRETTY_PRINT);
			}elseif( @$params['view'] == '' ){
				$response['log'] = print_r($resp, true);
			}
			
			
		} else {
		
			$response['errormsg'] = 'Error Get Mapping (' . $params['dbm_index'] .')';
			$response['errorcode'] = 1;
			
		}
			
	
	
	}
/*******************************************************************************/	
	function fnCluster($idx = '') {
	
		global $params, $config, $response;
		
	/*	Stats
		$args['node_id']       = (list) A comma-separated list of node IDs or names to limit the returned information; use `_local` to return information from the node you're connecting to, leave empty to get information from all nodes
		$args['flat_settings'] = (boolean) Return settings in flat format (default: false)
		$args['timeout']       = (time) Explicit operation timeout
		
		Settings
		$params['flat_settings']  = (boolean) Return settings in flat format (default: false)
		$params['master_timeout'] = (time) Explicit operation timeout for connection to master node
		$params['timeout']        = (time) Explicit operation timeout
		$params['body']           = (array) The settings to be updated. Can be either `transient` or `persistent` (survives cluster restart). (Required)
		
		
		
		$args = [
			// ...
		];
		
		*/
		
		if($params['ax'] == 'cluster-stats'){

			$node_id = @$params['node_id'] ?: '_local';
	
			$args = [
		
				'node_id' =>  $node_id
			
			];
			logit('Cluster Stats ', $node_id);
			
			$host = reset($config['hosts']);
			$options = ['request_type' => 'GET'];
			$rurl = "$host/_cluster/stats";
			$json = getRest($rurl, "", $options);
			$resp = json_decode($json, true);
			//$x = print_r($resp, true); echo "<!-- $x -->\r\n"; //die;
			
			// check for REST API error
			if(json_error($explain)){
				logit("Error _cluster/stats: ", $explain);
				//return;
			}	
			
			// check for CURL error
			if(arr_val($resp, 'error_rest') == true){
				logit('CURL Error' . ' (' .arr_val($resp, 'errorcode') . ')', arr_val($resp, 'errormessage')  );
				//die;
				
			}
		
			//$xml = Array2XML::createXML('settings', $resp)
			//$xml = $xml->saveXML();
			if ($resp) {
		
				$response['errormsg'] = "Cluster Stats"  ;
				$response['errorcode'] = '0';
				/* $response['log'] = str_replace('<?xml version="1.0" encoding="UTF-8"?>', '', $xml);*/
				$response['log'] = json_encode($resp, JSON_PRETTY_PRINT);
				
			} else {
			
				$response['errormsg'] = 'Error Settings (' . $params['index'] .')';
				$response['errorcode'] = 1;
				$response['responsetext'] = json_encode($resp, JSON_PRETTY_PRINT);
				
			}
			
			
		}
		
		$shard_allocation = @$params['allocation'] ?: 'all';	// [all|primaries|new_primaries|none] + Null[new \stdClass()]
		
		$routing = @$params['routing'] ?: 'persistent';		// [persistent|transient]
		// 
		
		if($params['ax'] == 'cluster-settings'){
			
			if(empty($params['allocation'])){
				logit('You need to specify [allocation] type', 'all|primaries|new_primaries|none'); die;
			}
			if(empty($params['routing'])){
				//logit('You need to specify [routing] setting', 'persistent|transient');
				logit('- setting default [routing] ', '[persistent]');				//die;
			}
			
			//logit(' - shard allocation', $shard_allocation);
			//logit(' - routing action', $routing);
			
	
			$args = [
		
				'body'  => [
					"$routing" => [
						"cluster.routing.allocation.enable" => $shard_allocation]
				]
			
			];
			logit('applying cluster Settings ', "$routing::$shard_allocation");
			
			$settings_json = json_encode($args['body'], JSON_PRETTY_PRINT);
			$settings_json = "$settings_json\n";
			
			$host = reset($config['hosts']);
			$options = ['request_type' => 'PUT'];
			$rurl = "$host/_cluster/settings";
			$json = getRest($rurl, $settings_json, $options);
			$resp = json_decode($json, true);
			//$x = print_r($resp, true); echo "<!-- $x -->\r\n"; //die;
			
			// check for REST API error
			if(json_error($explain)){
				logit("Error _cluster/set: ", $explain);
				//return;
			}	
			
			// check for CURL error
			if(arr_val($resp, 'error_rest') == true){
				logit('CURL Error' . ' (' .arr_val($resp, 'errorcode') . ')', arr_val($resp, 'errormessage')  );
				//die;
				
			}
			
			if (@$resp['error']) {
			
				logit('Error Cluster Settings', $params['dbm_index']);
				//$r = print_r($resp, true); logit('resp', $r);
				$response['errorcode'] = 1;
				$response['errormsg'] = 'Error Put Settings (' . $params['dbm_index'] .')';
				$response['responsetext'] = arr_val($resp, 'reason');
				
			} else {
			
				logit('Cluster set shard allocation: acknowledged', @$resp['acknowledged']);
				
				$response['errorcode'] = 0;		
				$response['errormsg'] = 'Cluster settings: acknowledged';
				$response['responsetext'] = "Script finished successfully. Index (" .$params['dbm_index'] . ') Action: (' . 'shard allocation' .')';	
				
				
			}
			
			
		}
	
	
	}
/*******************************************************************************/	
	function fnStats($idx = '') {
	
		global $params, $config, $response;
	
		$request = [
	
			'index' =>  $idx ?: $params['dbm_index']
		
		];
		logit('Stats for ', $request['index']);
		//check_request_authorised();
	
		$host = reset($config['hosts']);
		$options = ['request_type' => 'GET'];
		$rurl = "$host/{$request['index']}/_stats/docs";
		$json = getRest($rurl, "", $options);
		$resp = json_decode($json, true);
		//$x = print_r($resp, true); echo "<!-- $x -->\r\n"; //die;
		
		// check for REST API error
		if(json_error($explain)){
			logit("Error index_create: ", $explain);
			//return;
		}	
		
		// check for CURL error
		if(arr_val($resp, 'error_rest') == true){
			logit('CURL Error' . ' (' .arr_val($resp, 'errorcode') . ')', arr_val($resp, 'errormessage')  );
			//die;
			
		}
		
		//$xml = Array2XML::createXML('settings', $resp);
		//$xml = $xml->saveXML();
		if ($resp) {
		
			$response['errormsg'] = 'Settings for index: [' . $request['index'] . ']';
			$response['errorcode'] = '0';
			/*$response['log'] = str_replace('<?xml version="1.0" encoding="UTF-8"?>', '', $xml);*/
			$response['log'] = json_encode($resp, JSON_PRETTY_PRINT);
			
		} else {
		
			$response['errormsg'] = 'Error GetSettings (' . $params['dbm_index'] .')';
			$response['errorcode'] = 1;
			
		}
	
	
	}
/*******************************************************************************/	
	function fnCat($idx = '') {
	
		global $params, $config, $response;
		
		/*
			ax=cat fmt=[|json] view=indices|health|aliases|nodes|etc. params=?ToDo
			for full range of fn and params see E:\tbp\scripts\vendor\elasticsearch\elasticsearch\src\Elasticsearch\Namespaces\CatNamespaces.php
			
			e.g. dbm=bds-main ax=cat fn=aliases
			or ax=cat-aliases dbm=bds-main
			
			N.B. you need a DBM for the hosts - but ALL aliases will still be shown
			
			
		
		*/
		
		if( empty($params['fn']) ){
			// i.e. where ax=cat-aliases
			$params['fn'] = str_ireplace('cat-', '', $params['ax']);
			logit('cat action found:', $params['fn']);
		}
		
		$fn = $params['fn'];
		
		$request = [ 
			'v' =>  true,
			//'expand_wildcards' => 'all'
		];
		
		logit('Cat request ', '');
		
		$host = reset($config['hosts']);
		$options = ['request_type' => 'GET'];
		$format = @$params['fmt'] ?: '';
		
		if( empty($params['view']) ){
			logit("@param: 'view' missing", "[indices|nodes|aliases|etc.]");
			return;
		}
		
		if( preg_match("/index|idx|indices/i", $params['view']) ){
			
			$rurl = "$host/_cat/indices/*,-.ds*,-.internal*/?format=$format&s=index&v=true";
			
		}elseif( preg_match("/nodes/i", $params['view']) ){
			
			$rurl = "$host/_cat/nodes/?v=true&format=$format";
			
		}elseif( preg_match("/aliases/i", $params['view']) ){
			
			$rurl = "$host/_cat/aliases/?v=true&format=$format";
			
		}else{
			
			$rurl = "$host/_cat/{$params['view']}/?v=true&format=$format";
		}
		
		$json = getRest($rurl, "", $options);
		//$x = print_r($json, true); echo "<!-- $x -->\r\n"; die;
		
		if($format == 'json'){
			
			$resp = json_decode($json, true);
			//$x = print_r($resp, true); echo "<!-- $x -->\r\n"; //die;
		
			// check for REST API error
			if(json_error($explain)){
				logit("Error index_create: ", $explain);
				//return;
			}	
		
			// check for CURL error
			if(arr_val($resp, 'error_rest') == true){
				logit('CURL Error' . ' (' .arr_val($resp, 'errorcode') . ')', arr_val($resp, 'errormessage')  );
				//die;
				
			}
		
			//$xml = Array2XML::createXML('settings', $resp);
			//$xml = $xml->saveXML();
			if ($resp) {
			
				$response['errormsg'] = "CAT: {$params['view']}";
				$response['errorcode'] = '0';
				/*$response['log'] = str_replace('<?xml version="1.0" encoding="UTF-8"?>', '', $xml);*/
				$response['log'] = json_encode($resp, JSON_PRETTY_PRINT);
				
			} else {
			
				$response['errormsg'] = 'Error GetSettings (' . $params['dbm_index'] .')';
				$response['errorcode'] = 1;
				
			}
			
		}else{
			
			$response['errormsg'] = "CAT: {$params['view']}";
			$response['errorcode'] = '0';
			/*$response['log'] = str_replace('<?xml version="1.0" encoding="UTF-8"?>', '', $xml);*/
			$response['log'] = "======================================\n$json\n===============================================================================\n";
			
		}
		
	
	
	}
/*******************************************************************************/
/* ToDO
	N.B. It is probably more sensible to use Kibana console for managing Snapshots
		 - so I have not completed this function :)
*/
	function fnSnapshot(){
		
		global $params, $client, $response;
		
		if( $params['ax'] == 'snapshot-repo-list' ){
		
			$request = [
				'repository' => '*',
				//'snapshot' => '*',
				//'index_details' => true
			];
			
			$ax = 'get';
			
		}
			
		if( $params['ax'] == 'snapshot-snap-list' ){
			
			$request = [
				'snapshot' => $params['snap_name'],
			];
			
		}
		
		if( $params['ax'] == 'snapshot-snap-list' ){
			
			$request = [
				'repository' => $params['repo'],
				'snapshot'	=> $params['snap_name'],
				'body' => [
					'type'	=> @$params['snap_type'] ?: 'fs',
					'indices'	=> "{$params['sitename']}-main",
					'settings' => [
						'location'	=> $params['snap_path'],
					]
				]
			];
			
		}
		
		print_r($request);
		
		try {
		
			$r = $client->snapshot()->getRepository( $request );
			print_r($r);
			
			//$xml = Array2XML::createXML('settings', $r[$request['index']]['settings']);
			//$xml = $xml->saveXML();
			if ( !empty($r) ) {
			
				$x = print_r($r, true);
				$response['errormsg'] = 'Suppositories: [' . $request['repository'] . '] acknowledged: ' . "($x)"  ;
				$response['errorcode'] = '0';
				/* $response['log'] = str_replace('<?xml version="1.0" encoding="UTF-8"?>', '', $xml);*/
				//$response['log'] = json_encode($r, JSON_PRETTY_PRINT);
				
			} else {
			
				$response['errormsg'] = 'Error Settings (' . $params['dbm_index'] .')';
				$response['errorcode'] = 1;
				
			}
	
		} catch (Exception $e) {
			
			$r = json_decode($e->getMessage(), true);
			
			//$response['errormsg'] = $r['error']['type'];
			//$response['responsetext'] = $r['error']['reason'] . ' ['. $r['error']['resource.id'] .']';
			//$response['errorcode'] = $r['status'];
			print_r($r);
			
		}
		
		
		
	}
/*******************************************************************************/
/*
	!! N.B. If an old DBM just specifies an IP number (without a protocol) then
		the system is configured to default to HTTPS (Elastricv8+). So for olde sites
		you will need to set the protocol specifically in the DBM
*/
/*******************************************************************************/
/*******************************************************************************/

	function check_hosts_avail(&$config)
	{
          
		global $params, $PXY;
		
	// Pick up list of the hosts so far...[from php.Server.Defaults >> DBM]
		$hosts = $config['hosts'];
		$auth = '';
		$avail_hosts = array();
		
		$PXY['message'][] = "Starting sniffer; [" . timer() . "]";
		
	// Head in the clouds - will always require authentication
		if( !empty( arr_val($params, 'elastic_client_config.basicAuthentication')) ){
			$auth = implode(':', arr_val($params, 'elastic_client_config.basicAuthentication') );
			$PXY['message'][] = "check_hosts_avail: $auth";
		}

	// Check each host in turn - hopefully we hit lucky on the first one..(maybe set local server as first in list?)
		foreach($hosts as $h){
		
			$arr = parse_url($h);
			$prot = @$arr['scheme'] ?: arr_val($params, 'default_protocol') ?: "http";
			$host = @$arr['host'] ?: @$arr['path'] ?: '';
			$port = @$arr['port'] ?: 9200;
			
			if(!empty($auth)){
				$rurl = "$prot://$auth@$host:$port/_nodes/_all/http";	
			} else {
				$rurl = "$prot://$host:$port/_nodes/_all/http";	
			}
			
			$PXY['message'][] = "..sniffing cluster for available hosts: $rurl]";
			
			$t1 = timer();
			$PXY['message'][] = "..setting timeout to: 250ms";
			$options = [ 'connect_timeout' => 250 ]; // in milliseconds
			$json = getRest($rurl, '', $options);	
			$t2= timer(); $t=$t2-$t1; $t = number_format($t, 2);
			$PXY['message'][] = "..lookup took [$t]ms";
			
			if(!empty($json)){
				
			// This node is up and will give us a list of all available nodes :)
				$data = json_decode($json, true);
				$avail_hosts = arr_search_keys($data, 'host');
				foreach($avail_hosts as $idx=>$avail_host){
					
					$avail_hosts[$idx] = "$prot://$avail_host:$port";
					
				}
				//$d = print_r($data, true); $PXY['message'][] = $d;
				$config['hosts'] = $avail_hosts;
				
			// Set es_version while were at it
				$params['es_version'] = arr_val($data, 'version');
				//logit('version found', $params['es_version']);
				
			// Update Active Hosts value
				$params['active_hosts'] = implode(',', $config['hosts']);
				//logit('active hosts found', $params['active_hosts']);
				$PXY['message'][] = "..sniffer found available hosts: [{$params['active_hosts']}] [" . timer() . "]";
				return;
		
			} else {

				$PXY['message'][] = "..sniffer could not reach host: $h";

			}
			
		}

		$PXY['message'][] = "..sniffer could not find any hosts!";
		
	}
/*******************************************************************************/
/*
	Elastic v7+ has become very picky about how the hosts are written
	- safest bet is the full path e.g. 'http://10.100.0.105:9200'
	This function designed to 'fix' lazy host configurations
	
*/
	function clean_hosts_port(&$config)
	{
		global $params, $PXY;
		
		$hosts = $config['hosts'];
		$clean_hosts = Array();
		foreach($hosts as $h){
			
			$arr = parse_url($h);
			// Send in the protocol - set it in the DBM - last resort http (most of our servers)
			$prot = @$arr['scheme'] ?: @$params['dbm_default_protocol'] ?: "http"; // i.e. specify or it will default to this
			$host = @$arr['host'] ?: @$arr['path'] ?: '';
			$port = @$arr['port'] ?: 9200;
				
			if(!empty($host)){
				
				$clean_hosts[] = "$prot://$host:$port";
				
			}else{
				
				$PXY['message'][] = "Unable to dertmine hosts [check_hosts_port]";
				
			}
			
		}
		
		$config['hosts'] = $clean_hosts;
		
		// Update Active Hosts value
		$params['active_hosts'] = implode(',', $config['hosts']);
		$PXY['message'][] = "..available hosts (cleaned): [{$params['active_hosts']}] [" . timer() . "]";
				
		
		
	}	
/*******************************************************************************/
/*	Elastic takes index updates in ndjson i.e. one line at a time
	- see https://www.elastic.co/guide/en/elasticsearch/reference/current/docs-bulk.html
	
	@param array() $batch
	
*/
	function get_ndjson($batch)
	{	
	
		$json = '';
		if(is_array($batch)){
			
			foreach($batch as $idx=>$line){
				
				$json .= json_encode($line) . "\n";
				
			}
			
			return $json;
			
		}
	
	
	
	}
/*******************************************************************************/
/*	We NEED to know! Best set $params['es_version'] in PHP.Server.Defaults.inc
	- CAN also be set in the DBM
	- the rest here is a LAST RESORT!
	
*/
	function get_version($key = 'version.number')
	{
		global $params, $config, $PXY;
		
	// php.Server.Defaults.inc OR DBM says... 
		if( !empty($params['es_version']) ){
			//echo "<!-- I know it already! {$params['es_version']} -->\r\n";
			return $params['es_version'];
			
		}
		
	// Bollocks we need to check the cluster:
		// Pick up list of the hosts so far...[from php.Server.Defaults >> DBM]
		$auth = '';
		$hosts = $config['hosts'];
		$avail_hosts = array();
		
	// Head in the clouds - will always require authentication
		if( !empty( arr_val($params, 'elastic_client_config.basicAuthentication')) ){
			$auth = implode(':', arr_val($params, 'elastic_client_config.basicAuthentication') );
			$PXY['message'][] = "check_hosts_avail: $auth";
		}

	// Check each host in turn - hopefully we hit lucky on the first one..
		foreach($hosts as $h){
		
			$arr = parse_url($h);
			$prot = @$arr['scheme'] ?: arr_val($params, 'default_protocol') ?: "http";
			$host = @$arr['host'] ?: @$arr['path'] ?: '';
			$port = @$arr['port'] ?: 9200;
			
			if(!empty($auth)){
				$rurl = "$prot://$auth@$host:$port";	
			} else {
				$rurl = "$prot://$host:$port";	
			}
			
			$options = [ 'connect_timeout' => 250 ]; // in milliseconds
			$json = getRest($rurl, '', $options);	
			
			if(!empty($json)){
				
			// This node is up and will give us a list of all available nodes :)
				$data = json_decode($json, true);
				$version = arr_val($data, 'number');
				
				return $version;
		
			} else {

				$PXY['message'][] = "..could not reach host: $h";

			}
			
		}

	}
/*******************************************************************************/
/* 	Access Elasticsearch REST API directly...
	2025-02-19
*/		
	function getRest($rurl, $body, $options = Null)
	{

		global $PXY;
		
		$ct = @$options['connect_timeout'] ?: 1000;
		$to = @$options['timeout'] ?: 1000;
		$rq = @$options['request_type'] ?: "GET";	// GET|POST
		$PXY['message'][] = "..get_rest: connect_timeout:$ct timeout:$to type:$rq";
		//print_r($options); die;
		
		if( !empty($options['header']) ){
			
			$header = $options['header'];
			
		}else{
		
			$header = [
				//"Authorization: Basic {$options['auth']}",
				"Content-Type: application/json",
				//"Connection: Close"
			];

			if(!empty($options['auth'])){
				$auth = base64_encode($options['auth']);
				array_unshift($header, "Authorization: Basic $auth");
			}
			
		}
		
		$curl_options = [

			CURLOPT_CUSTOMREQUEST => "$rq",
			CURLOPT_POSTFIELDS => "$body",
			CURLOPT_URL	=> $rurl,
			CURLOPT_HEADER => false,	// 0 removes headers from response leaving just the JSON
			CURLOPT_HTTPHEADER => $header,
			CURLOPT_CONNECTTIMEOUT_MS => $ct,
			CURLOPT_TIMEOUT_MS => $to,
			CURLOPT_RETURNTRANSFER => true, // sets curl_exec() to return request body e.g. as $response
			CURLOPT_FAILONERROR => false,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_ENCODING => '',	
			
			CURLOPT_VERBOSE => false,
			//CURLOPT_CAINFO => "",
			CURLOPT_SSL_VERIFYPEER => false,	// 0 for testing :)
			CURLOPT_SSL_VERIFYHOST => false,
			//CURLOPT_STDERR => ($f = fopen("d:/temp/curl-client.txt", "a")),
			
		];
		
		//$x = print_r($curl_options, true); echo "<!--getRest:curl opts: $x -->\r\n"; //die;
		$curl = curl_init();
		curl_setopt_array($curl, $curl_options);	
		$response = curl_exec($curl);
		//$x = print_r($rurl, true); echo "<!--getRest:rurl: [$x] -->\r\n"; //die;
		//$x = print_r($response, true); echo "<!--getRest:response: [$x] -->\r\n"; //die;
		$info = curl_getinfo($curl);
		//$x = print_r($info, true); echo "<!--getRest:info: $x -->\r\n"; //die;
		
		
		if( $info['http_code'] == 200 ){
			// return $response
			curl_close($curl);
			return $response;
		}
			
		if( $info['http_code'] >= 400 ){
			// return $response // make sure CURLOPT_FAILONERROR = false so we can read the Elastic API response
			curl_close($curl);
			return $response;
			
		}
			
		if (curl_errno($curl)){
			
			// !! Something bad happened
			$cerr = curl_errno($curl);
			$x = print_r($info, true); //echo "<!-- cUrl ERR ($cerr): getinfo: $x --\r\n"; //die;
			
			if(curl_errno($curl) == '28'){
				$emsg = "REST request timed out. ct[$ct] to[$to]";
			}elseif(curl_errno($curl) == '22'){	// IF _FAILONERROR = true
				$emsg = "BAD REST request (http:400)";
			}else{
				$emsg = "CURL Err [$cerr] $x";
			}

			$error_array = [
				'errordetails' => [
					'error_rest'    => true,
					'errorcode'     => curl_errno($curl),
					'errormessage'  => "CURL Error: [$emsg]",
				]
			];
		   
			$response = json_encode($error_array);
			curl_close($curl);
			return $response;
			
		} else {

			if( $info['http_code'] == 0 or $info['size_download'] < 1){

				$emsg = "No data received: [{$info['url']}]";
				$error_array = [
					'errordetails' => [
						'error_rest'    => true,
						'errorcode'     => 1,
						'errormessage'  => "URL Error: [$emsg]",
					]
				];
			   
				$response = json_encode($error_array); 
				curl_close($curl);
				return $response;
			
			}
			//$skip = intval(curl_getinfo($curl, CURLINFO_HEADER_SIZE)); 
			//$responseHeader = substr($response, 0, $skip);
			//$response = substr($response,$skip);

		}  
		
		

	}
		
/*******************************************************************************/
	function parse_bulk_delete_resp($arr){
	
		global $params;
		
		$ret = array();
		
		foreach($arr['items'] as $a){
		
			$k = arr_search($a, 'result')['value'];
			@$ret[$k]++;
			
		}
		
		/* returns Array
			(
				[not_found] => 2
				[deleted] => 2
			)
		*/
		return $ret;
		
	}	

/*******************************************************************************/

	function parse_errors($arr){
	
		global $params;
		$ret = array();
		
		if(isset($arr['items'])){
		
			foreach($arr['items'] as $item=>$info){
									 
				if(!empty($info['index']['error'])){
				
					$id 			= $info['index']['_id'];
					$idx 			= $info['index']['_index'];
					$type			= $info['index']['_type'];
					$error			= $info['index']['error']['type'];
					$error_reason	= $info['index']['error']['reason'];
					$error_type		= @$info['index']['error']['caused_by']['type'];
					$error_cause	= @$info['index']['error']['caused_by']['reason'];
					
					//logit($id, $error . ' ' . $error_reason . ' ' . $error_type . ' ' . $error_cause);
					logit($id, $error_reason . ' ' . $error_cause);
					
				}
				 
				if(@$info['update']['_shards']['successful'] >= 1) {
				
					@$ret['success_count']++;
					@$params['success_count']++;
					
				}
				if(!empty($info['update']['error']['reason']) )  {
					
					$ret['failed_count'][] = '[' . $info['update']['_id'] .'] ' . $info['update']['error']['reason'];
					
				}
				if(@$info['index']['_shards']['successful'] >= 1) {
				
					@$ret['success_count']++;
					@$params['success_count']++;
					
				}
				if(!empty($info['index']['error']['reason']) )  {
					
					$ret['failed_count'][] = '[' . $info['index']['_id'] .'] ' . $info['index']['error']['reason'];
					
				}
				 
			 }
			 
		}

		return $ret;
		
	}
	
// ******************************************************************************/
	function init_quotes_map(&$chr, &$rpl){
		
		$chr_map = array(
		   // Windows codepage 1252
		   "\xC2\x82" => "'", // U+0082-U+201A single low-9 quotation mark
		   "\xC2\x84" => '"', // U+0084-U+201E double low-9 quotation mark
		   "\xC2\x8B" => "'", // U+008B-U+2039 single left-pointing angle quotation mark
		   "\xC2\x91" => "'", // U+0091-U+2018 left single quotation mark
		   "\xC2\x92" => "'", // U+0092-U+2019 right single quotation mark
		   "\xC2\x93" => '"', // U+0093-U+201C left double quotation mark
		   "\xC2\x94" => '"', // U+0094-U+201D right double quotation mark
		   "\xC2\x9B" => "'", // U+009B-U+203A single right-pointing angle quotation mark

		   // Regular Unicode     // U+0022 quotation mark (")
								  // U+0027 apostrophe     (')
		   "\xC2\xAB"     => '"', // U+00AB left-pointing double angle quotation mark
		   "\xC2\xBB"     => '"', // U+00BB right-pointing double angle quotation mark
		   "\xE2\x80\x98" => "'", // U+2018 left single quotation mark
		   "\xE2\x80\x99" => "'", // U+2019 right single quotation mark
		   "\xE2\x80\x9A" => "'", // U+201A single low-9 quotation mark
		   "\xE2\x80\x9B" => "'", // U+201B single high-reversed-9 quotation mark
		   "\xE2\x80\x9C" => '"', // U+201C left double quotation mark
		   "\xE2\x80\x9D" => '"', // U+201D right double quotation mark
		   "\xE2\x80\x9E" => '"', // U+201E double low-9 quotation mark
		   "\xE2\x80\x9F" => '"', // U+201F double high-reversed-9 quotation mark
		   "\xE2\x80\xB9" => "'", // U+2039 single left-pointing angle quotation mark
		   "\xE2\x80\xBA" => "'", // U+203A single right-pointing angle quotation mark
		);
		$chr = array_keys  ($chr_map); // but: for efficiency you should
		$rpl = array_values($chr_map); // pre-calculate these two arrays
		
	}
	
/*******************************************************************************/	


	function logit($key='', $notes=''){

		global $params, $response, $www_debug;
		
		$date = getdate(); 
		$timeStr = DisplayDateTime($fmt = 'Y-m-d H:i:s', $d1 = 0);
		//$timeStr .= $date['hours'] . ':' . padz($date['minutes'], 2) . ':' . padz($date['seconds'], 2);
		$msec = explode('.', microtime(true));
		//$timeStr .= '.' .substr($msec[1], 2);
		
		if($key == ''){
			$format = " ";
		}else{
			$format = " [%s]  %'.-55.135s [%s]";
		}
		
		if(!empty($_REQUEST)){ 
			// save into $www_debug for display after script has completed
			$format = ($format != ' ') ? $format .= "\r\n" : ' '; 
			$www_debug .= sprintf($format, $timeStr, $key, $notes);
			
		} else {
			// echo now into the command line
			$format .= "\r\n"; 
			echo sprintf($format, $timeStr, $key, $notes);	
		}		
		// always save to the log file
		if(@$params['index_this_logging'] == 'off') {
			// do nothing
		} else {
			file_put_contents($params['log_path'], sprintf($format, $timeStr, $key, $notes), FILE_APPEND);	
		}
		

	}	
	
	/*******************************************************************************/		

		function sprint_hash($params){
			
			$txt = '';
			$format = "     %35s [%s]";
			ksort($params);
			
			if(!empty($_GET)){ 
				$format .= "\r\n"; 
			} else {
				$format .= "\r\n"; 
			}
			
			foreach ($params as $key=>$value){
				if(!is_array($value)){
					$txt .= sprintf($format, str_pad($key, 35, '.'), @$value);	
				}
			}
		
			file_put_contents($params['log_path'], $txt, FILE_APPEND);	
			return $txt;
	
	}

	/*******************************************************************************/		
	
	function response_init() {
	
	//	Response is the array we shall use, let's sort of fill some stuff up so it doesn't give us undefined index errors all over the shop
		$response					=	array();
		$response['errorcode']		=	"";
		$response['log']			=	"";
		$response['errormsg']		=   "";
		$response['errormsg']		=   "";
		$response['errorcode']		=   "";
		$response['responsetext']	=   "";
		$response['logfile']		=   "";
		return $response;
		
	}	
	
/*******************************************************************************/	

	function response_write($response){

		global $params, $www_debug;
		
		if(!empty($_REQUEST)){ 
		
			
			//ksort($response);
			$return_string	=	'';
				
			header("Content-type: text/xml; charset=utf-8");
			
			foreach($response as $key => $value) {
				/* msg($value, $key);  //N.B. This debug will break the AJAX by repeating the responsetxt */
				if(strlen($value)>0 ) {					
							
					$return_string .= xml_tag($key, $value, 0) . "\r\n";
						
				}
			}
			
			if(@$params['debug'] == 'on') {
				$debug = "\r\n<!-- $www_debug -->";
			}
			return "<response>\r\n$return_string</response>";
			
		} else {
			
			echo print_hash($response);
			
		}
		
	}

/*******************************************************************************/	
	function xml_tag($tag, $val, $action){
	// 0=<tag>val</tag> | 1=<tag> | 2=</tag> | 3=<tag><![CDATA[val]]></tag> | 4=<tag />

		$retstr = '';
		strtolower($tag); trim($tag);
		if ($action == 0 || $action == ''){
			// Test $val for blankness beofre you get here as response_write actually needs to send the tags even if empty
			//if($val != ''){
				$retstr = '<' . $tag . '>' . $val . '</' . $tag . '>';
			//}
		}elseif($action == 1){
			$retstr = '<' . $tag . '>';
		}elseif($action == 2){
			$retstr = '</' . $tag . '>';
		}elseif($action == 3){
			$retstr = '<' . $tag . '><![CDATA[' . $val . ']]></' . $tag . '>';
		}elseif($action == 4){
			$retstr = '<' . $tag . ' />';
		}
		
		return $retstr;
	}
/*******************************************************************************/	
	function print_hash($hash) {
		
		$ret = "\r\n";
		ksort($hash);
		$format = "     %1s:%s\r\n";
		
		foreach ($hash as $key=>$value){
			if(is_array($value)){
				$ret .= sprintf($format, str_pad($key, 35, ' '), "array");		
			} else {
				$ret .= sprintf($format, str_pad($key, 35, ' '), @$value);	
			}
		}

		return "<print>\r\n$ret\r\n</print>";
			
	}
/*******************************************************************************/
	function json_error(&$explain){
		
		$explain = '';
	
		 switch (json_last_error()) {
			 
			case JSON_ERROR_NONE:
				$explain = 'No JSON errors';
				return false;
			break;
			
			case JSON_ERROR_DEPTH:
				$explain = ' - Maximum stack depth exceeded';
				return true;
			break;
			
			case JSON_ERROR_STATE_MISMATCH:
				$explain =  ' - Underflow or the modes mismatch';
				return true;
			break;
			
			case JSON_ERROR_CTRL_CHAR:
				$explain =  ' - Unexpected control character found';
				return true;
			break;
			
			case JSON_ERROR_SYNTAX:
				$explain =  ' - Syntax error, malformed JSON';
				return true;
			break;
			
			case JSON_ERROR_UTF8:
				$explain =  ' - Malformed UTF-8 characters, possibly incorrectly encoded';
				return true;
			break;
			
			default:
				$explain =  ' - Unknown error';
				return true;
			break;
			
		}

	}
/*******************************************************************************/
	function is_json($string) {
		
    return ((is_string($string) &&
            (is_object(json_decode($string)) ||
            is_array(json_decode($string))))) ? true : false;
			
}
/*******************************************************************************/	
	function find_key(array $array, $search) {
		foreach (new RecursiveIteratorIterator(new RecursiveArrayIterator($array)) as $key => $value) {
			print_r($key);
			if ($search === $key)
			return $value;
		}
		return false;
	}
	
/*******************************************************************************/	
	function validDates($date, $format = 'Ymd'){
		
		$ret = true;
		$parts = explode(':', $date);
		foreach($parts as $p){
			
			if (($timestamp = strtotime($p)) === false) {
				$ret = false;
			} 
			
		}
		
		return $ret;
				
	}
	
/*******************************************************************************/		
	function show_help(){
	
		logit();
		logit('Commands');
		logit('ax=index-create', 'dbm=<site-db>');
		logit('ax=index-delete', 'dbm=<site-db>');
		logit('ax=index-rebuild', 'dbm=<site-db>');
		logit('.', 'deletes & then creates new index.');
		logit('ax=index-update', 'dbm=<site-db>');
		logit('...set params in DBM', '');
		logit('...[data_source_path]', '');
		logit('...[folder_load_limit]', ' [0|n] 0 means all');
		logit('...[updates_method]', ' [update|index] update + set doc_as_upsert = true');
		logit('...[updates_clean_xml]', ' tidy data on import ');
		logit('...[updates_report_count]', ' indicate progress ');
		logit('...[updates_ref_no]', ' set _id field defaults to <ref_no> ');
		logit();
		logit('Add -live to switch to LIVE hosts configured in DBM', 'ax=index-create-live|index-delete-live|index-rebuild-live|index-update-live etc.');
		logit();
		
		logit('ax=alias-[add|remove|(repoint)]', 'dbm=this_index alias=alias_name (index=current_index new_index=repoint_2_me)');
		logit('ax=analyze', ' [dbm|analyzer|attributes|char_filter|field|normalizer|text|tokenizer] ');
		logit('ax=cat', 'dbm=<site-dbm> ax=cat view=[indices|health|aliases|nodes] fmt=[|json]');
		logit('ax=cluster-[stats|settings]', ' [_local|node_id] + [allocation|routing] ');
		logit('ax=delete-this', 'dbm=<site-db> data=ref_no,ref_no');
		logit('ax=delete-file', 'dbm=<site-db>');
		logit('...set params in DBM', 'Expects file listing <ref_no>s');
		logit('ax=getmapping', 'dbm=<site-db> view=[json|]');
		logit('ax=getsettings', 'dbm=<site-db>');
		logit();		
		logit('ax=index-dump', 'dbm=<site-db> path=[ data-source_path|d:/path/to/data ] ' );
		logit('...dump-format=[xml|json]', 'bulk index dump as either xml (default) or json.');
		logit('...dump-path=[d:\temp|return]', 'output path|data_source_path/data/|<cmd>');
		logit('...dump-data=[9781234567890 OR 9781234567891]', 'output path|data_source_path/data/');
		logit('...dump-date-range=[90:30]', 'days ago:days ahead');
		logit('...dump-query=', 'query_string query syntax search query');
		logit('...dump-fields=[field1,field2]', 'csv only. comma separated list of fields to export');
		logit('...dump-csv-format=[csv|tsv|ansi]', 'defaults to tsv');
		logit('...dump-aize=[1000]', 'defaults to 1000');
		logit();		
		logit('ax=index-files', 'dbm=<site-db>');
		logit('...', 'indexes specific files listed with full path in load file.');
		logit('...[index_files_source]', 'set in DBM - where the list of files to index can be found.');
		
		logit('ax=index-csv[-partial]', 'dbm=<site-db>');
		logit('...set params in DBM', 'Expects CSV file with header line');
		logit('...[partial_updates_folder]', 'location of CSV files to import.');
		
		logit('ax=index-partialxml2', 'dbm=<site-db>');
		logit('...set params in DBM', 'Selected <xml2> fields - e.g. for updating price and avail fields');
		
		logit('ax=index-this', 'dbm=<site-db> data=[JSON|XML] file=[full_path|<data_folder>/filename] ');
		logit('ax=index-update-this', 'dbm=<site-db> data=<ref_no>,<ref_no>');
		logit('...', 'Accepts a list of ref_no\'s and updates the index using /data/ref_no.xml files');
		logit('ax=index-upd-query', 'dbm=<site-db> file=<pattern> data=<JSON>');
		logit('...', 'reads files in DBM:index_this_data_folder or accepts data=JSON');
		logit();		
		logit('ax=settings[-live]', 'accesses PUT settings');
		logit('...[replicas=0|1|2|etc]', '');
		logit('...[interval]', 'refresh_interval [-1(disabled)|1=default|null');
		logit('...[max_results]', 'Set max_result_window:", "max_results=10000 (default)');
		
		logit();
		logit('ax=stats', 'dbm=<site-db>');
		
		
		logit();		
		logit();
		logit();
	
	}
	
/******************************************************************************/
/*
	find an array value without trying too hard 
		e.g. using dot notation: 	arr_val('client.post.error')
		e.g. searching by key:		arr_val('error')

*/
	function arr_val($arr, $key){
	
		$ret = '';
				
		if(empty($key)){ return $ret; }
				
		if(!empty($arr)){
			
			if(strpos($key, '.') > 0){
				
				$ret = arr_get($arr, $key);
				
			} else {
				
				$ret = arr_search($arr, $key)['value'];
				
			}
			
		} else {
			
			// should really check for zero
			$ret = '';
			
		}
		
		if( is_array($ret) ){
			
			if( !empty($ret['@cdata']) ){
				
				$ret = $ret['@cdata'];
				
			} 
			
			if( is_array($ret) && isset($ret[0]) ) {
				
				if(is_array($ret[0]) === false){
					$ret = implode('|', $ret);
				}
				
			}
		}

		return $ret;
		
	}
		
/******************************************************************************/
	function arr_search($array, $searchKey=''){
/*	
	create a recursive iterator to loop over the array and find the key you are looking for
	returns both the path (in dot notation) and the key's value. e.g.
		
		$path = arr_search($arr, $var)['path'];
		$val  = arr_search($arr, $var)['value'];
*/
		if(!is_array($array)){ return array('path'=>'', 'value'=>''); }
		
		$iter = new RecursiveIteratorIterator(
			new RecursiveArrayIterator($array),
			RecursiveIteratorIterator::SELF_FIRST);

		//loop over the iterator
		foreach ($iter as $key => $value) {
			//if the key matches our search
			if ($key === $searchKey) {
				//add the current key
				$keys = array($key);
				//loop up the recursive chain
				for($i=$iter->getDepth()-1;$i>=0;$i--){
					//add each parent key
					array_unshift($keys, $iter->getSubIterator($i)->key());
				}
				//return our output array
				return array('path'=>implode('.', $keys), 'value'=>$value);
			}
		}
		//return false if not found. PHP 7.4 this shorthand not allowed. Return empty arrays!
		return array('path'=>'', 'value'=>'');
	}
		
/******************************************************************************/
/*
	retrieves value/child-array using dot notation (that which is returned by arr_search()['path']
*/
	function arr_get(array &$a, $path, $default = null){

		$current = $a;
		$p = strtok($path, '.');

		while ($p !== false) {
			if (!isset($current[$p])) {
			  return $default;
			}
			$current = $current[$p];
			$p = strtok('.');
		}

		return $current;
		
	}	

/******************************************************************************/
/*
	sets/creates an array key=>val using dot.notation 
	e.g. arr_set($arr, "record.ehaus.eh_uk_vat_price", $fval);
*/
	function arr_set(array &$arr, $path, $val){
		
	   $loc = &$arr;
	   foreach(explode('.', $path) as $step)
	   {
		 $loc = &$loc[$step];
	   }
	   return $loc = $val;
	   
	}
		
/******************************************************************************/
/*
	returns an array of values for all matching keys in array
*/
	function arr_search_keys($array, $searchKey){

		$iter = new RecursiveIteratorIterator(
			new RecursiveArrayIterator($array),
			RecursiveIteratorIterator::SELF_FIRST);
		$outputArray = array();
		
		foreach ($iter as $key => $value) {

			if ($key === $searchKey) {
				
				$outputArray[] = $value;
				
			}
			
		}

		return $outputArray;

	}

/******************************************************************************/
	function arr_search_all_keys($array, $searchKey=''){
/*	
	create a recursive iterator to loop over the array and find the all instances 
	of the key you are looking for
	returns an array of key (in dot notation) and the key's value.
	
*/
		if(!is_array($array)){ return false; }
		
		$outputArray = array();
		
		$iter = new RecursiveIteratorIterator(
			new RecursiveArrayIterator($array),
			RecursiveIteratorIterator::SELF_FIRST);

		//loop over the iterator
		foreach ($iter as $key => $value) {
			//if the key matches our search
			if ($key === $searchKey) {
				//add the current key
				$keys = array($key);
				//loop up the recursive chain
				for($i=$iter->getDepth()-1;$i>=0;$i--){
					//add each parent key
					array_unshift($keys, $iter->getSubIterator($i)->key());
				}
				//return our output array
				$path = implode('.', $keys);
				$outputArray[$path] = $value;
				
			}
		}
		//return false if not found
		return $outputArray;
	}
		
/******************************************************************************/
/*
	removes keys with empty values - 
*/
	function arr_filter_blanks($array){

		if(is_array($array) ){
			
			foreach ($array as $key => &$value) {
				if (empty($value)) {
					// N.B. Zero & false are treated as empty()
					unset($array[$key]);
					
				} else {
					
					if (is_array($value)) {
						$value = arr_filter_blanks($value);
						if (empty($value)) {
							
						   unset($array[$key]);
						   
						}
					}
					
				}
			}

			return $array;
			
		}

	}
	
/******************************************************************************/
/*
	
*/	
	function arr_depth($array) {
		
		$depth = 0;
		$iteIte = new RecursiveIteratorIterator(new RecursiveArrayIterator($array));

		foreach ($iteIte as $ite) {
			$d = $iteIte->getDepth();
			$depth = $d > $depth ? $d : $depth;
		}

		return $depth;
	}

/******************************************************************************/

	function arr_dot_keys($myArray){
		
		$result = array();
		if( is_array($myArray) ){
			
			$ritit = new RecursiveIteratorIterator(new RecursiveArrayIterator($myArray));
			
			foreach ($ritit as $leafValue) {
				$keys = array();
				foreach (range(0, $ritit->getDepth()) as $depth) {
					$keys[] = $ritit->getSubIterator($depth)->key();
				}
				$result[ join('.', $keys) ] = $leafValue;
			}
			
			return $result;
		
		}
		
	}
	
/*******************************************************************************/
/*
	We receive crappy data from many different places:
	
	i)	Best case: ensure that the data input is clean and serene
		- most of our databases read XML/CSV straight in to the index with little or no mapping
		
	ii)	BDSLive / Nielsen data is specifically cleaned on import via fn_make_xml2()
		- this means we can implement the same elasticsearch mapping for both
		
	iii) Other: Some simple cleaning may be sufficient..
		- xml2array(): This class uses the DOMDocument object, so the xml does have to comply with the basic rules
		- xml2array(): has been modified [2018-09-19] so that text which contains crappy HTML will not be rejected
						- unprotected HTML will be stripped from text nodes
						- e.g. <descrip>Here is <i>my</i> amazing stuff</descrip> => [descrip] => Here is my amazing stuff
						- so, if you want to preserve the HTML send <descrip> in with <![CDATA[ ]]> tags
		- xml2array(): WON'T load text that is not <![CDATA[ ]]> which contains unclosed HTML tags: e.g. <br> or lone ampersands
*/	
	function clean_input($txt){
	
		//$txt = str_replace('@#', '&#', $txt);
		//$txt = str_replace('@amp;', '&amp;', $txt);
		//$txt = str_replace('&amp;amp;', '&amp;', $txt);
		//$txt = str_replace('&amp;apos;', '&#39;', $txt);
		//$txt = preg_replace('/&amp;([a-z]{3,6};)/', "&$1", $txt);
		//$txt = str_replace('“', '"', $txt);
		//$txt = str_replace('”', '"', $txt);
		
	// try to trap lone ampersands
		//$txt = preg_replace('/&(?!(?:apos|quot|[gl]t|amp);|#)/', '&amp;', $txt);
		
	// If you need to remove the <collection> tags from files with lots of records in the same file
		//$txt = str_replace('<records>', '', $txt);
		//$txt = str_replace('</records>', '', $txt);
		

	/* This is a quick hack for Transmedia ebook db
		$txt = str_replace('<I>', '<i>', $txt);		
		$txt = str_replace('</I>', '</i>', $txt);
		$txt = str_ireplace('<br>', '<br/>', $txt);
	*/	
		
	/* set these as <![CDATA[ ]]> nodes - saves the DOM from parsing the crap 
		N.B. This will preserve all the crap in the nodeText
		N.B. fn_make_xml2() will convert nodes to @cdata from param in DBM.inc 
	
		$dodgies = Array('contents', 'description');
		foreach($dodgies as $d){
			
			$old = xmlfield($txt, $d, true);
			$fld = xmlfield($txt, $d);
			//$fld = strip_tags($fld);
			$new = "<$d><![CDATA[$fld]]></$d>";
			$txt = str_replace($old, $new, $txt);
			
		}
	*/

		// Can be quite useful this one to get rid of horrid characters
		// N.B. The Array2XML() class will generate dom inspired UTF-8/16
		/*
			$convmap = array(0x80, 0xffff, 0, 0xffff);
			$txt = mb_encode_numericentity($txt, $convmap, 'UTF-8');
			
		*/
		//echo $txt;
		
		return $txt;
		
	}

/******************************************************************************/
	
	function convert_chars($str)
	{
		
		$unwanted_array = array(    'Š'=>'S', 'š'=>'s', 'Ž'=>'Z', 'ž'=>'z', 'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E',
						'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U',
						'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'Ss', 'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 'æ'=>'a', 'ç'=>'c',
						'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o',
						'ö'=>'o', 'ø'=>'o', 'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ü'=>'u', 'ý'=>'y', 'þ'=>'b', 'ÿ'=>'y' );
		return strtr( $str, $unwanted_array );

	}

/******************************************************************************/
/*
	Generate a unique id - this is stored as <ref_no> and also used to populate 
	the _id field in elastic
	
	
*//******************************************************************************/
		
		function create_unique_key($cstr = ''){
			
			$unix = time();		// 10 digits
			$mt = explode('.', microtime(true));
			if(count($mt) == 2){
				$msec = padz($mt[1],4);
			} else {
				$msec = '0000';
			}
			
			if ($cstr != ''){ 
			// current target = VARCHAR(14): Unix timestamp = 10 digits (for some while :)
				$cstr = padz($cstr, 4); 
				$key = "$unix$cstr";
				
			} else {
			// Recommended: "e$unix$msec$cstr";
				$key = "e$unix$msec$cstr";
		
			}
			return $key;
			
		}

			
/*******************************************************************************/
	function email_out($log_path)
	{

		
		//require_once __DIR__ . '/phpMailer/PHPMailerAutoload.php';
		require_once '../phpMailer/src/PHPMailer.php';
		require_once '../phpMailer/src/SMTP.php';
		require_once '../phpMailer/src/Exception.php';
		
		global $params;
		
		//$parties	=	['management@bibdsl.co.uk'];
		//$parties	=	['matthew.pollock@bdslive.com', 'chris.miller@bdslive.com', 'kyle.mcintosh@bdslive.com', 'dorothy.reid@bibdsl.co.uk', 'routines@bdslive.com', 'andrea.sherry@bibdsl.co.uk', 'eric.green@bibdsl.co.uk', 'lesley.whyte@bibdsl.co.uk', 'sarah.armitage@bibdsl.co.uk', 'graham.barke@bibdsl.co.uk', 'keith.walters@bdslive.com'];
		$parties	=	['mattyp@pollocks.london'];
		
		$mail = new PHPMailer\PHPMailer\PHPMailer;
		
		$mail->isSMTP();                                     			// Set mailer to use SMTP
		$mail->Host = 'mail.authsmtp.com';  							// Specify main and backup server [?10.0.0.2/ mail.authsmtp.com]
		$mail->SMTPAuth = true;                                 		// Enable SMTP authentication
		$mail->Username = 'ac31009';                            		// SMTP username
		$mail->Password = 'rhew2bnwn';                           		// SMTP password

		$mail->From 		= 'noreply@bdslive.com';
		$mail->FromName 	= 'Elastic Indexer Script';
		
		foreach($parties as $party)	{
			
			$mail->addAddress($party, '');  							// Add a recipient
		}
			
		$mail->WordWrap = 50;                                 			// Set word wrap to 50 characters
		$mail->isHTML(false);                                  			// Set email format to HTML
		$mail->Subject = 'Elastic Index Log';
		$mail->Body    = 'Indexing has been performed' . "\r\n" . 'Please read the log for details on this';
		$mail->AddAttachment($log_path . '');
		
		if (!$mail->send())	{
			
			return $mail->ErrorInfo;
			
		} else {
			
			return true;
			
		}
		
	}
	
/*******************************************************************************/
	function check_request_authorised($ax){
		
		global $params, $response;
		
		
		$http_referer 	= @$_SERVER['HTTP_REFERER'] ?: '';
		$remote_host 	= @$_SERVER['REMOTE_HOST'] ?: '';
		$local_addrs 	= @$_SERVER['LOCAL_ADDR'] ?: '';
		
		$admin = false;
		if( preg_match("/{$params['sitename']}\/admin/i", $http_referer) ){
			
			$admin = true;
			
		} 
		
		$arr = [
			'ax' => $ax,
			'http_referer' => $http_referer,
			'remote_host' => $remote_host,
			'local_addrs' => $local_addrs,
			'admin' => $admin,
			'params:ax' => $params['ax'],
			'dbm'	=> $params['dbm'],
		];
		
		$json = json_encode($arr, JSON_PRETTY_PRINT );
		
		//file_put_contents("D:/temp/elastic-indexer.txt", "$json\r\n", FILE_APPEND);
		
		if( $remote_host <> $local_addrs ){
			// then the elastic-indexer was requested directly via a URL (NOT by a proxy server:AJAX)
			if($admin == false){
				// then NOT permitted!
				$response['errorcode'] = 1;
				$response['errormsg'] = "Action Not permitted [$ax] [{$params['ax']}] [{$params['dbm']}].";
				//$response['logfile'] = $params['log_file'];
				$response['responsetext'] = "Script failed.";
				echo response_write($response);
				exit;
				
			}
		}
		
		
		
	}
/*******************************************************************************/
/*******************************************************************************/

?>