<?php
/*
	The XMLAdapter attempts to work out where everything is from the webserver 
	and the URL supplied to it.
	
	The key file it needs is the DBM for the index you are searching. 
	The default locations are as follows:
	
		site_root/test-page.php
		site_root/include/config/site_DBM.inc
		
	For convenience various locations and parameters are loaded into $params[]
	which can also be passed into the XMLAdapter onn initialisation.
	


*/
   
	$params = array();
    /* E.G. manually pass in key config: */
	
	$params = [
		'sitename' => 'juniper',
		//'script_filename' => 'D:/tbp/www/juniper',
		'www_root' => 'D:/tbp/www',
        'dtsearch_root' => 'D:/dtsearch',
		'elastic_client_config' => [
			'hosts' => [			// Default site-wide hosts - overwritten by DBM config
				'127.0.0.1:9200',
			],
			'basicAuthentication' => [ 'mattyp', 'Abc12345' ],	// => [ $params['username'], $params['password'] ],
		],
	];
	
	
   

    $tt1 = microtime(true);

   
    // Loads the Elasticsearch PHP Client
    require_once("d:/tbp/scripts/vendor/autoload.php");
	
	// Load the XMLAdapter
	require_once("d:/tbp/scripts/vendor/mattylabs/XMLAdapter/vendor/autoload.php");
	
    $tt2 = microtime(true);
    $autoload_time = "AutoLoad: [" . number_format($tt2 - $tt1, 4) . "] seconds";


    use MattyLabs\XMLAdapter\Config;
    use Mattylabs\XMLAdapter\Helpers\Arr;
    use MattyLabs\XMLAdapter\Logger\SimpleLogger;
    use MattyLabs\XMLAdapter\XMLAdapter;

    // Test query_string queries
    //$request = "DEBUG=on,query&FMM=true&DBM=mattylabs-main&DS=&SF1=keyword&ST1=pollock&SF2=contributor&ST2=&VIEW=xml&FIELDS=default,distinct,uk_vat_price&PL=&debug=&mf=10000&dtspan=&SORT=sort_date/d,imprint_code&SQF=/1:F/2:UK12,NBD&LIKE=&AGGS=bic_tree&COLLAPSE=work_id&DTSPAN=10:10";
    $request = "DEBUG=on,query&FIELDS=distinct,ctitle,contributor&PL=2&DBM=juniper-main&SF1=keyword&ST1=matthew&SQF=&DTSPAN=&AGGS=&SUGGEST=on&COLLAPSE=&M=&PL=2";
    
	/*
     * SimpleLogging
     */
    $log = new SimpleLogger;
    $log::info($autoload_time, 'Autoload');
    $log::time('PAGE');
    $log::info('Beginning script', 'PAGE');


    $xmla = new XMLAdapter($request, $params);
    //print_r( $config->getConfigArray() ); //die;
    echo $xmla->search('xml');

    /*
     * Debug if you want to
     */
    $log::info("Script finished.", 'PAGE');
    $log::timeEnd('PAGE');
    echo $log::dump_to_string($request);


