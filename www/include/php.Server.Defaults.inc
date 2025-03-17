<?php
/* 	
	
	
*/
	define("SITENAME", strtolower('xmla'));
	$host = gethostname(); 
	
	switch ($host) {
		
		// MSP Laptop
		case 'WIN-FFFSSBI0OMH':
		case 'WIN-BSRQOLNKKM3':
			
			return array (
			
				'sitename'			=> SITENAME,
				'app_root'			=> 'd:/tbp',		// e.g. to access app_root/scripts/vendor/ etc.
				'www_root'			=> 'd:/tbp/www',	// i.e. all website files [www_root/sitename/]
				'temp_folder'		=> 'd:/temp',		// mainly for debugging and temp generated files
				'dtsearch_root'		=> 'd:/dtsearch',	// i.e. where the XML /data/ folders are
				
				'this_server_ip'	=> 'http://127.0.0.1',
				'this_server_url'	=> 'https://mattyp',
				
				'dev_server_ip'		=> 'http://127.0.0.1',
				'dev_server_url'	=> 'https://mattyp',
			
				'live_server_ip'	=> 'http://127.0.0.1',
				'live_server_url'	=> '',
				
				'default_protocol'	=> 'http',	// i.e. the elastic hostname. Best specified in 'dbm_elastic_hosts'
				//'sniff_hosts'		=> '',	// [on|off|] - defaults to 'on'

			
			// Default Elastic Client Settings: Moved from /lib/elastic/config/Elastic-Config.inc [2022-02-01]. N.B. Override via DBM
				'es_version'		=> '8.2',	// [5.5|8.2]
				'elastic_client_config' 	=> [
					'hosts' => [				
						'http://127.0.0.1',  
										
					],
					//'basicAuthentication' => [ 'mattyp', 'Chalc0t' ],	// => [ $params['username'], $params['password'] ],
					

				],	

			);
			
			break;
			
			
	}
	