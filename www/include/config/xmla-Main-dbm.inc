<?php
/* 	Main-db elasticSearch Config file
	
	[2025-04-02] - Main DBM
	
*/
/*******************************************************************************/
// GENERAL PARAMS

		
	/* 

		
	*/
	
	global $params;
					
				
		$params['dbm_index'] 	= "xmla-main";	// This is tyhe elastic index/alias name
		$params['dbm_type']  	= 'doc';		// This should always be set to 'doc' - deprecated v6+ :)
		$params['xmla']  		= 'include/xmla-elastic.php';	
		
	return array (

		'dbm_index' 				=> $params['dbm_index'], 
		'dbm_type' 					=> $params['dbm_type'],
		'dbm_elastic_hosts'			=> ['127.0.0.1'], //N.B. If possible set local host first in array: ['http://localhost:9200', 'https://mattyp'], //['10.100.0.31', '10.100.0.32', '10.100.0.33'],	// Overrides elastic-config.inc::$params['elastic_client_config']['hosts']
		//'dbm_elastic_hosts'		=> ['https://bds-engine-1.es.us-central1.gcp.cloud.es.io:9243'],	
		
		'dbm_sniff_hosts'			=> 'on',	// [on|off|] - defaults to 'on'
		'dbm_connect_timeout'		=> 250,	// For the Sniffer. Defaults to 250ms
		'dbm_timeout'				=> 250,	// For the Sniffer. Defaults to 250ms
		//'shard_preference'			=> '_local',	// v7+ only [|_local] See [https://www.elastic.co/guide/en/elasticsearch/reference/current/search-search.html#search-preference]
		'dbm_elastic_cloud'			=> [	// You need PHP client v7.0+. Default local cluster basicAuthentication set via php.server.defaults
										//'elasticCloudId'	=> 'bds-engine-1:dXMtY2VudHJhbDE...etc.',
										//'username'		=> 'elastic',	
										//'password'		=> 'cJtDTQtWn...etc.',	
									],
		'data_source_path' 			=> "../data/main-db",	// N.B. Default assumption all Main-db xml files are in /data/ folder. BDSLive specifies a data_source_path with "/data" and assumes sub_folders /00012/ etc. CHECK THE ELASTIC_INDEXER if you ned sub_folders.
		'data_source_pattern' 		=> '.*\.(xml)$',
		'data_folder_num'			=> 0,	// e.g. for v. large MAIN-db's. 
		
		'get_data_file_xmla'		=> "include/xmla-elastic.php",	// used in elasticsearchCGI::get_data_file()
		'get_data_file_source'		=> 'index',	// [|data|index] - leave blank or specify 'data' to view /data/ folder xml doc direct. 
		'require_map_hashes'		=> [],
		'map_network_drive'			=> false,	// not a good idea to load the files across a network connection - very slow :)
		
	// DEFAULT SEARCH PARAMS:
		'default_phrase_proximity'	=> '',		// e.g. '~10' gets added to phrase searches (I think this is the same as the 'phrase_slop' operator
		'default_phrase_slop'		=> '3',		// 3 should be fine
		'default_fuzziness'			=> '1',		// 0, 1, 2, AUTO
		'default_field_boost'		=> ['contributor' => 3],	// for multi_match queries :)
		'default_field_prefix'		=> 'fv_',
		'default_keyword_fields'	=> ['cindex','contributor'],	//
		'default_search_type'		=> 'best_fields',	// primary 'must' search only: see xmla-elastic.php [phrase|best_fields|phrase_prefix]
		'default_search_operator'	=> 'AND',			// i.e. for fielded searches - Default = AND
		'default_force_quotes'		=> [],				// for field searches only e.g. (contributor:(eric green)) forces quotes around search phrase >>  (contributor:("eric green"))
		'default_min_should_match'	=> '2<75%', 		// Applied to initial 'must' query (see xmla-elastic.php) - can be overriden in qs - e.g. '3<90%' 1-3 must match 4 or more requires only 90% to match.
		'default_doc_as_upsert'		=> false,			// Doesn't appear to be used any more
		'html_output_tpl'			=> 'html_output_bdslive.tpl',	// see Elastic-config to make sure the templates folder is in the include path

		
	/* 	DEFAULT FIELD HANDLING: Handles XML CDATA Elements - configures as array e.g. [descrip] => Array( [@cdata] => 'blah blah.' ). The system needs to know which fieds are CDATA's
		- indexing as a nested field helps preserve CDATA elements when converting to/from the XML but may add a search overhead.
		- in which case index as a flar field and specify whcih fields should be CDATA'd on output (dump & XML2) by using default_cdata_output.
	*/
		'default_cdata_elements'	=> ['author_bio', 'biblio_notes', 'bic_basic_valid_json', 'biographical_note', 'contents', 'descrip', 'descrip_trade', 'description', 'eh_author_prevbooks', 'eh_author_bio', 'eh_contents', 'eh_descrip', 'eh_description', 'eh_review', 'eh_video_link', 'eh_url_link', 'jacket_l', 'jacket_m', 'jacket_orig_a', 'jacket_orig_b', 'jacket_s', 'jacket_xl', 'kirkukrev', 'kirkusrev', 'kword_index', 'minimum_spec', 'nbd_review', 'not_for_sale', 'onix_contributor', 'perm_promo', 'prizes', 'pub_descrip', 'quotes', 'related_editions', 'review', 'review_quotes', 'sales_restrictions', 'sales_rights', 'tracks'],	// Adds the <![CDATA[ ]]> section to the XML2 in index
		'default_cdata_convert'		=> ['series', 'sub_title'], //['ctitle', 'series', 'sub_title', 'author_bio', 'biblio_notes', 'bic_basic_valid_json', 'biographical_note', 'contents', 'descrip', 'description', 'eh_author_prevbooks', 'eh_author_bio', 'eh_contents', 'eh_descrip', 'eh_description', 'eh_review', 'eh_video_link', 'eh_url_link', 'jacket_l', 'jacket_m', 'jacket_orig_a', 'jacket_orig_b', 'jacket_s', 'jacket_xl', 'kirkukrev', 'kirkusrev', 'kword_index', 'minimum_spec', 'nbd_review', 'not_for_sale', 'perm_promo', 'prizes', 'quotes', 'review', 'review_quotes', 'sales_restrictions', 'sales_rights', 'tracks'  ], // See elastic-index.php fn_make_xml2(): converts a supplied <![CDATA[ ]]> field into a normal XML element (if you want to strip HTML in addition, see the elastic-indexer): Stops CDATA sections being stored and indexed as nested objects.
		'default_cdata_output'		=> [], //['author_bio', 'biblio_notes', 'bic_basic_valid_json', 'biographical_note', 'contents', 'descrip', 'description', 'eh_author_prevbooks', 'eh_author_bio', 'eh_contents', 'eh_descrip', 'eh_description', 'eh_review', 'eh_video_link', 'eh_url_link',  'jacket_l', 'jacket_m', 'jacket_orig_a', 'jacket_orig_b', 'jacket_s', 'jacket_xl', 'kirkukrev', 'kirkusrev', 'kword_index', 'minimum_spec', 'nbd_review', 'not_for_sale', 'perm_promo', 'prizes', 'quotes', 'review', 'review_quotes', 'sales_restrictions', 'sales_rights', 'tracks'  ], // Adds the <![CDATA[ ]]> section to the XML2 output 
		'default_sub_nodes'			=> ['ehaus'],	// Passes sub_nodes thru the clean_data_nodes() function
		'default_empty_objects' 	=> [],	// For fields that have a nested object mapping that might be updated with an empty <xml></xml> string causing indexing error "object mapping for [xxx] tried to parse field [xxx] as object, but found a concrete value"
		'default_force_array'		=> ['bic_subj_code', 'bic_qual_code', 'contributor', 'cat_tree', 'cat_code',  'distributor_code', 'eh_cat_code', 'facet', 'lib_qual_code', 'lib_reader_code', 'name_exact', 'readership_code', 'suggest_terms', 'thema_subj_code', 'thema_qual_code'],	// Default store multi-instance fields as pipe|separated; 'type' = 'text'. This stores field as array - vital for e.g. Filter seaching on individual codes
		'default_strip_html'		=> [],	// e.g. 'cms_text' Creates a copy field called field_name_xhtml with HTML tags stripped out - for searching & highlighting. Create mapping & copy_to => cindex
		'default_noindex'			=> [],	// i.e. unset from data array before submitting to index
		'default_reset_index'		=> [],	// Sets fields listed to empty string '' if 'missing' from the XML source file

	// DEFAULT FIELD LIST: ALL <ehaus> node fields called via parent node; no need to specify each field individually. To call without 'ehaus' set in URL's e.g. 'ehaus.eh_review'. To call in templates e.g. <fv_ehaus/eh_review>
		//'default_fieldlist' 		=> 'ehaus,accessories,alt_title,au_extax_price,au_tax_amount,au_tax_code,au_tax_notes,au_tax_price,au_tax_rate,auth_title,author_bio,barcode,based_on,biblio_notes,bic_qual_code,bic_qualifier,bic_subj_code,bic_subject,biographical_note,bisac,bisac_code,bisac_version,cats,cbmc,cbmc_code,cert_irish,cert_pegi,cert_uk,contents,contributor,ctitle,date_created,date_created_by,date_created_file,date_created_user,date_created_user_company,date_created_user_company_code,date_updated,date_updated_by,date_updated_file,date_updated_user,date_updated_user_company,date_updated_user_company_code,descrip,description,dewey,dewey_edition,dewey_num,dimensions,duration,ean,edition,edition_statement,edition_type,edition_type_code,eu_avail,eu_avail_code,eu_exvat_price,eu_price_currency,eu_vat_amount,eu_vat_price,export_ebook,facet,filename,first_published,format,format_code,format_description,format_ebook,format_ebook_code,format_note,format_type,genre,identifier,illus,imprint,imprint_exact,interest_age,isbn_10,isbn13h,jacket_l,jacket_m,jacket_s,kword_index,language,language_code,language_text,language_text_code,last_updated,lc_head,lib_qual_code,lib_reader_code,name_as_subject,name_exact,nbd_review,nfs_code,nof_items,nof_pages,nof_pieces,nof_players,not_for_sale,onix_pubstatus,onix_pubstatus_code,onix_readership,onix_readership_code,primary_avail,primary_edition,primary_name,primary_price,prizes,psc,psg,psq,pst,pub_month,pub_year,publ_country,publ_place,publisher,readership,readership_code,ref_no,related_editions,related_ebook,related_ids,replaced_by_ean,replaces_ean,responsibility,review,sales_rank,sales_restrictions,sales_rights,screen_ratio,series,series_exact,series_number,sort_alpha,sort_date,sort_month,sort_name,sort_series,sort_title,stock_flag,sub_title,thema_qual_code,thema_qualifier,thema_subj_code,thema_subject,tindex,title_2,title_exact,title_index,tracks,trans_from,trans_from_code,trans_from_text,uk_avail,uk_avail_area,uk_avail_code,uk_avail_date,uk_dcode,uk_distributor,uk_distributor_code,uk_exvat_price,uk_exvat_price,uk_pack_qty,uk_price_area,uk_price_currency,uk_price_type,uk_price_type_code,uk_pubdate,uk_pubdate_assumption,uk_tax_notes,uk_vat_amount,uk_vat_code,uk_vat_price,uk_vat_rate,us_avail,us_avail_area,us_avail_code,us_avail_date,us_extax_price,us_tax_amount,us_tax_code,us_tax_price,us_tax_rate,vx_first_name,wco,weight',
		'default_fieldlist' 		=> '*',

	/*	LIVE HOSTS: COPY LIVE - See elastic-indexer.php
		The elastic-indexer.php will switch to use these hosts for ax=
		- index-delete-live / index-create-live / index-rebuild-live / index-dump-live / 
		- index-update-live / index-update-this-live /
	
	*/
		'elastic_indexer_live_hosts' => ['10.100.0.31','10.100.0.32','10.100.0.33'],	// Overrides elastic-config.inc::$params['elastic_client_config']['hosts']
		
/************************** ELASTIC_INDEXER ************************************/

	// DELETES: See fnIndexDelete() elastic-indexer.php	
		'delete_files_folder'		=> "data/upload",
		'delete_files_pattern'		=> '(delete).*\.(dat)$',
		'delete_files_archive'		=> true,
		'delete_files_start_line'	=> 2,
		'delete_files_chunk_count'	=> 250,	// suggest maybe 250?
		'distinct_fieldlist'		=> '',
		
	// BULK UPDATES: See fnIndexUpdate() AND fnIndexUpdateThis() elastic-indexer.php
		'folder_load_limit'			=> 0, // set to 0 for full loading of all folders
		'updates_report_count'		=> 1000, // set to 0 to switch off logging
		'updates_submit_count'		=> 1000,
		'updates_report_submit_count'	=> false,	// true/false											   
		'updates_start_folder'		=> 0,	// Assumes folders were sorted so \\10.100.0.5\g$\dtsearch\Film\00000 comes before \\10.100.0.5\g$\dtsearch\Film\00001 
		'updates_ref_no'			=> 'ref_no',
		'updates_node_in'			=> 'record',
		'updates_node_out'			=> 'record',
		//'updates_collection_node'	=> 'collection',
		'updates_clean_xml'			=> false,
		'updates_undo_period'		=> 'Y-M-d-H.i.s', // ['Y-M-d-H.i.s'] or leave blank not to bother with ../edited/undo.files
	
	/* UPDATES METHOD: 
		UPDATE: enables bulk_update() elastic-indexer.php
		- will update an existing doc IF PRESENT
		- set _doc_as_upsert = true to ALSO CREATE NEW doc (if not present)
		- e.g. to load/update prices/avail codes to existing records: set update_method = 'update' and doc_as_upsert = false (to avoid creating new partial records)
		- e.g. to update BDSLive: set method = 'index' (new docs will be created and existing ones ovwerwritten) [doc_as_upsert not relevant]
		- e.g. to update TransUK: set method = 'update' and doc_as_upsert = true (so that new XML2 loads don't overwrite any prices/flags set via CMS :)
		
		INDEX: to create new docs and OVERWRITE existing docs in the index. {enables bulk_index() elastic-indexer.php)
		
	*/
		'updates_method'			=> 'update',		// [update|index] i.e. use bulk_update() or bulk_index() [see elastic-indexer.php]
		'updates_doc_as_upsert'		=>	true,		// ONLY applicable where _method = 'update'. DEFAULT = false : will create & update. So, set to false if you don't want to create new partial records.
		
	// CSV FILE UPDATES: See fnIndexPartialUpdate() elastic-indexer.php [e.g. to load prices, sales_rank etc from CSV file]
		'partial_updates_folder'	=> "data/upload",
		'partial_updates_pattern'	=> '(partial).*\.(dat)$',
		'partial_updates_delimiter'	=> '\t',
		'partial_start_on_line'		=> 2,
		'partial_ref_no'			=> 'ref_no',
		'partial_chunk_count'		=> 1000,
		
	// XML FILE UPDATES: See fnIndexPartialUpdateXML2() elastic-indexer. Partial index updates from XML2 source data ..[e.g. update selected fields from an XML2 source]
		'partial_xml2_data'			=> 'data',
		'partial_xml2_pattern'		=> '.*\.(xml)$',
		'partial_xml2_ref_no'		=> 'ref_no',
		'partial_xml2_node_in'		=> 'record',
		'partial_xml2_node_out'		=> 'record',
		'partial_xml2_chunk_count'	=> 1000,
		'partial_xml2_flatten_fields'	=> false,	// attempts to promote _fields to first:children of root node_in
		'partial_xml2_fields'		=> [			// <ehaus> node is added as a group by specifying 'ehaus'
										'ehaus', 
										'promotion_code'
									],	
		
	// INDEX FROM XML FILES LIST: See fnIndexFilesByRefXML2() elastic-indexer.php. Indexes using a list of full paths to XML2 files
		'index_files_source'		=> "data/filestoindex",
		'index_files_source_pattern'=>  'idx.*\.(dat)$',
		'index_files_archive'		=> true,
		'index_files_archive_folder'=> "data/archive",
		'index_files_ref_no'		=> 'ref_no',
		'index_files_node_in'		=> 'record',
		'index_files_node_out'		=> 'record',
		'index_files_submit_count'	=> 1000,		// elastic seems happy at around 1000 records per bulk submit
		'index_files_report_count'	=> 1000,		// gives an idea of progress :0
		'index_files_method'		=> 'update',	// [update|index] i.e. use bulk_update() or bulk_index()
		'index_files_doc_as_upsert'	=>	true,		// ONLY applicable where _method = 'update'. DEFAULT = false : will create & update. So, set to false if you don't want to create new partial records.
		
		
	// INEDEX THIS POST [JSON|XML]: See fnIndexThis() elastic-indexer.php. Indexes using REST
		'index_this_allowed_fields'	=> 'contributor,ctitle,descrip,description,identifier,barcode,tracks,sort_date',	// I thnk we safe as only localserver requests allowed.
		'index_this_ref_no'			=> 'ref_no',	// Blah!
		'index_this_record'			=> 'record',	// blah!
		'index_this_doc_as_upsert'  => true,		// why would be anything else?
		'index_this_data_folder'	=> '',			//"{$params['www_root']}/{$params['sitename']}/Admin/FTP/upload",
		'index_this_set_filename'	=> true, 		// sets <filename> using data_source_path
		'pagelength' 				=> 16, 
	
/******************************************************************************/	
	
	/* Q SEARCH FILTER
			elasticCGI will append this to each search. Can be overwritten by specifying q='';
			e.g. '((format_code:(-DG))+AND+(ehaus.eh_published_flag:(Y))+AND+(uk_avail_code:(20 23)))',
	*/
		'permit_q_search'			=> true,
		//'site_q_search_filter'		=> '((format_code:(-DG))+AND+(ehaus.eh_published_flag:(Y))+AND+(uk_avail_code:(20 23)))',
		//'site_q_search_filter'		=> '(sort_date:([20201001+TO+20201031]))', // [inclusive]|{exclusive}
		'site_q_search_filter'		=> '',
		
		
	/* COLLAPSE	RESULTSET
			N.B. in-house fields with type=keyword are called field.raw. 
				 Elasticsearch auto-mapper calls them field.keyword 
			N.B. There is no easy way to tell the XMLa whether you are in ADMIN or not
				 - so, either use separate DBM's or set value of collapse via query_string (i.e. in each search).
			N.B. You cannot Rescore AND Collapse
	*/
		'collapse'					=> '',	// e.g. auth_title field name (of type=keyword) on which to collapse search results. Can be overridden in query_string &collapse=[field_name.raw|off] !WARNING: if <fv_work_id> is mostly empty all empty records are collapsed!
		'collapse_inner_fields'		=> ['ref_no', 'barcode', 'bic_subj_code', 'ctitle', 'contributor', 'discount_flag', 'distributor', 'format', 'format_code', 'format_type', 'format_type_code', 'imprint', 'epub_type', 'eu_avail', 'eu_avail_code', 'eu_exvat_price', 'eu_vat_price', 'uk_avail', 'uk_avail_code', 'uk_vat_price', 'uk_exvat_price', 'sort_date', 'uk_pubdate', 'us_avail', 'us_avail_code', 'us_tax_price', 'us_extax_price', 'work_id'],	// cgi DEFAULTS TO 'REF_NO'
		'collapse_fields_size'		=> 10,						
		'collapse_fields_sort'		=> ['format_code' => 'asc'],

	
	/*	SQF Filters need to be configured here so that the SQF search parameter can be parsed numerically
		- The RULE is: Filters should be type=keyword ('raw') and multi-instance codes stored as an array
		- e.g.: 'SQF=/1:arts/2:UK3' 		(numbers mapped to fields in order)
		- e.g. 'SQF=psc:arts/facet:UK3'		(OR you can just use the field names)
		
	*/
		'sqf_filters'				=> ['null', 'bic_tree', 'cat_tree', 'thema_tree', 'format_type', 'format_code', 'primary_name.raw', 'primary_price', 'pub_month', 'cat_code', 'uk_avail_code', 'facet', 'imprint', 'primary_avail', 'sort_month', 'lib_qual_code', 'lib_reader_code', 'dewey_num', 'readership_code', 'lc_head', 'series', 'thema_subj_code'], 
		
	/*	PREFIX FILTERS:
		- i.e. which SQF filters should be prefix filters [Mainly the _tree ones]
		- prefix filters only work if the field type = keyword (or tree analyzer with fielddata switched on)
		- so you may need to map the filter name to the field.raw (i.e.keyword) type
		- e.g. &SQF=/facet:BOOK will be added to search query as a prefix_filter.facet.raw
	*/
		'prefix_filters'			=> [	
											'bic_tree' => 'bic_tree',		
											'cat_tree' => 'cat_tree',	
											'genre_tree' => 'genre_tree',
											'thema_tree' => 'thema_tree',
											'thema_qual_code' => 'thema_qual_code',
											'primary_name' => 'primary_name.raw',
											'series' => 'series.raw',
											'suggest_terms' => 'suggest_terms.raw',
											
										],	// check aggs (below), both dt AND elstic ini's

		'short_code_bic'			=> 'bic_subj_code',		// i.e. the field to search when you use the BIC= short_code in the querytext
		'short_code_isb'			=> 'identifier',
		'short_code_ehcat'			=> 'eh_cat_class',		// USED to be &CAT=<eh_cat_class> NOW &EHCAT=<eh_cat_class>. If publisher wants own hierarchical tree then you will need to replace out what's been done with categories/<bic_subj_code> etc. [See elastic-index.php] !!!
		'short_code_date'			=> 'sort_date',			// Default Date Range field e.g. used by DTSPAN/ADV/SINCE
		'short_code_aub'			=> 'contributor',
		'short_code_tit'			=> 'ctitle',
		'short_code_key'			=> 'ref_no',	// key field [i.e. what does 'k=9781234567890' map to: [ref_no|identifier|barcode]

		'track_total_hits'			=> true,	// v7.0+ [true|false|n] e.g. n=10000: total.hits.value elastic returns the ?first?random?best? 10000 matches - like au7tostoplimit in dtsearch. Quicker but you don't get everything back!
	
		
	/*	AGGREGATIONS:
		- this tells elastic to DO these aggregations and return them in the search as <distinct_fields>	
		- elastic requires aggregated fields to be type => keyword NOT 'text'
		- we are using field_name.raw for type => keyword
		- see 'elastic_aggregations' below REMEMBER defined below BUT switched ON here [or set in URL &AGGS=field1,field2]
		- these fields displayed when &FIELD=distinct PROVIDED they are defined below ['elastic_aggregations']
		- CAN ALSO be switched on as &AGGS=this_one in e.g. <list_products> query
	
	*/																																													   
		//'elastic_aggregations_inc'	=> 'bic_tree,facet,format_type,genre_tree,imprint,primary_name,primary_price,series,sort_month,thema_tree,uk_avail_code',	// configure these down below see 'elastic_aggregations' BUT list the one you want switched on here (or set in URL &AGGS=)
		'elastic_aggregations_inc'	=> '',	// [Configure in See MT_Search_Results.tpl] these fields displayed when &FIELD=distinct PROVIDED they are defined below ['elastic_aggregations']
		'elastic_aggs_tree_filter'	=> [
										'thema_tree' => '[A-Z0-9]{1}', // [A-Z0-9]{1}, allows s to step through the code one character at a time
										'bic_tree'   => '[A-Z0-9]{1}',										
										'cat_tree'   => '[^;]*',	// '[^;]*' up to the next separator (but not including it).										
										'genre_tree' => '[^;]*',	// '.*','[^;]*' effectively hides parent and shows all children
										//'thema_qual_code' => '4Z-GB.*',

										],	// Adds a filter to the Aggregation 'include' => 'Xn[A-Z]{1}', where Xn is the value /&SQF=/field:Xn/ from the the querystring
		
	/*	SWITCHES	*/
		'elastic_highlight_show'	=> false,	// configured below see: 'elastic_highlight'
		'elastic_highlight_separator' => '<span class="highlight"> ... </span>',
		'elastic_suggestions_index'	=> true,	// configured below see: 'elastic_suggest' / MOQ (v7+)
		'elastic_suggestions_show'	=> false,	// configured below see: 'elastic_suggest'. This will include a SUGGEST lookup with every search using search_terms. Otherwise set SUGGEST lookup dynamically using &SUG=xyz [older versions only (using suggest_title and suggest_name:: type='completion' ]
		'elastic_rescore_show'		=> true,	// configured below see: 'elastic_rescore'	
		'rescore_window_size'		=> 5000,	// used to boost by date (maybe also sales rank)
		
	// path_hierarchy delimiter is set below in the tree_tokenizer, but must be the same used by elastic-index.php when loading the cats field
	// - delimiter used in the cats.tree field to split tree on. // try to avoid reserved chars [?\/&=.] etc. + we use [/:] which leaves [;,@~^# _|]
		'tree_analyzer_delimiter'	=> ';', // defaults to ';' in elastic-indexer.php
		//'tree_analyzer_format'		=> [],	// [text|code] - not currently used
		
		
/****	ELASTICSEARCH MAPPINGS	*/
		'elastic_index_config' 		=> [
	
			'index' =>  $params['dbm_index'],
			'body' => [
			
				'settings' => [ 
					// N.B. for testing (e.g. counts, hits, aggs etc. set _shards => 1, _replicas => 0)
					'number_of_shards' => 1,
					'number_of_replicas' => 0,
					'analysis' => [
					
						// normalizer: For type=keyword only - permits e.g. prefix filtering exact matches AND case insensitive :)
						// .nor index will contain lcase - when you search, search terms are lower-cased
						'normalizer'=> [	
						
							'lcase_normalizer'	=> [
								'type'	=> 'custom',
								'char_filter'	=> new \stdClass(),
								'filter'=> ['lowercase', 'asciifolding']
							]
								
						],
						
						'char_filter'=> [
						
							'hyphen_filter' => [
							  'type'=> 'mapping',
							  'mappings'=> [
								'- => _',
							  ]
							]
							
						],
						
						'analyzer' => [
							
						// this should be equivalent to english + asciifolding (for diacritics)
							'lcase_name_analyzer' => [
								'type' => 'custom',
								'tokenizer' => 'standard',
								'filter' => [
									'lowercase', 
									'asciifolding',
									'english_possessive_stemmer',
									'english_stemmer'
								],
								//'char_filter'	=> 'hyphen_filter'
							],
							
							
							'hyphen_analyzer' => [
								'tokenizer' => 'standard',
								'char_filter'	=> 'hyphen_filter'
							],

						],
						
						'tokenizer' => [
						
							
							
						],

						'filter' => [
						
							'shingle_filter' => [
								'type' => 'shingle',
								'min_shingle_size' => 2, 
								'max_shingle_size' => 3, 
								
							],
							'english_stemmer' =>  [
								'type' =>  'stemmer',
								'language' =>  'english'
							],
							'light_english_stemmer' =>  [
								'type' =>  'stemmer',
								'language' =>  'light_english'
							],
							'english_possessive_stemmer' =>  [
								 'type' => 'stemmer',
								 'language' => 'possessive_english'
							]
							
						],
					]
				],
				
				'mappings' => [
				
					$params['dbm_type'] => [
					
						'_all' => [
							// N.B. v6+ _all has been removed! Set default_keyword_fields to cindex
							'enabled' => false,						
						],
						'_source' => [
							'enabled' => true,
							'excludes' => [
								//'suggest_terms',	// v7+
								//'suggest_name',	// v5.6
								//'suggest_title'	// v5.6
							],
						],
						'date_detection' => false,
						/* N.B. Unless we need to do something special - Dynamic indexing will index all submitted fields just fine */ 
						'properties' => [
						
							/* N.B. 
								- distinct aggs require type = keyword (i.e. a .raw field type)
								- sorting also requires type = keyword (i.e. a .raw field type)
								- - XMLa assumes sort_fields are type=keyword 
								- - AND maps &SORT=other_field to other_field.raw for sorting
								-  KEYWORD MAPPING IMPLIES CASE SENSITIVE (e.g. for cms+pagename) otherwise use a TEXT mapping
								- all date fields should be of basic_date format (i.e. yyyymmdd)
									
							*/ 

							'accessories' => [			
								'type' => 'text',
								'index' => false,
							],
							'alt_text' => [			
								'type' => 'text',
								'index' => false,		
							],
							'alt_title' => [			
								'type' => 'text',
								'copy_to' => ['cindex', 'tindex']
											
							],
							'audience' => [
								'type' => "text",
								'index' => false,
							],
							'audience_code' => [			
								"type" => "keyword",
							],
							'aus_extax_price' => [			
								'type' => 'keyword',
								'index' => false,
							],
							'aus_tax_amount' => [			
								'type' => 'keyword',
								'index' => false,
							],
							'aus_tax_code' => [			
								'type' => 'keyword',
								'index' => false,
								'doc_values' => false
							],
							'aus_tax_notes' => [			
								'type' => 'keyword',
								'index' => false,
								'doc_values' => false
							],
							'aus_tax_price' => [			
								'type' => 'keyword',
								'index' => false,
								'doc_values' => false
							],
							'aus_tax_rate' => [			
								'type' => 'keyword',
								'index' => false,
								'doc_values' => false
							],
							'auth_title' => [			
								'type' => 'keyword',
							],
							'author_bio.@cdata' => [
								'type' => 'text',
								'index' => false,
							],
							'ba_flag' => [			
								'type' => 'keyword',
								'doc_values' => false
							],
							'batch_order_count' => [
								'type' => 'integer',
							],
							'batch_order_flag' => [
								'type' => 'keyword',
								'doc_values' => false
							],
							'batch_order_date_created' => [
								'enabled' => false
								
							],
							'batch_order_date_updated' => [
								'enabled' => false
							],
							'batch_order_month' => [
								'type' => 'keyword',
							],
							'batch_order_rank' => [
								'type' => 'integer',
							],
							'batch_order_week' => [
								'type' => 'keyword',
							],
							
							'batch_sales_count' => [
								'type' => 'integer',
							],
							'batch_sales_flag' => [
								'type' => 'keyword',
								'doc_values' => false
							],
							'batch_sales_date_created' => [
								'enabled' => false
							],
							'batch_sales_date_updated' => [
								'enabled' => false
							],
							'batch_sales_month' => [
								'type' => 'keyword',
							],
							'batch_sales_rank' => [
								'type' => 'integer',
							],
							'batch_sales_week' => [
								'type' => 'keyword',
							],
							'barcode' => [
								'type' => 'keyword',
								'doc_values' => false 
							],
							'based_on' => [			
								'type' => 'text',
								'copy_to' => 'cindex',
							],
							'biblio_notes.@cdata' => [			
								'type' => 'text',
								'copy_to' => 'xindex',
							],
							'bic_basic_flag' => [
								'type' => 'keyword',
							],
							'bic_basic_valid_flag' => [
								'type' => 'keyword',
							],
							'bic_basic_valid_json.@cdata' => [
								'type' => 'text',
								'index' => false,
							],
							'bic_qual' => [			// please try to resolve to bic_qualifier
								'type' => 'text',
								'copy_to' => 'bic_qualifier',
							],
							'bic_qualifier' => [	
								'type' => 'text',
							],
							'bic_qual_code' => [
								'type' => 'keyword',
							],
							'bic_subj_code' => [
								'type' => 'keyword',
							],
							'bic_subject' => [			
								'type' => 'text',
								'copy_to' => 'cindex',
							],
							'bic_tree' => [
								'type' => 'keyword',
							
							],	
							'bisac' => [			
								'type' => 'text',
							],
							'bisac_code' => [
								'type' => 'keyword',
							],
							'bisac_version' => [			
								'type' => 'keyword',
								'index' => false,
								'doc_values' => false
							],
							'bl_subject' => [			
								'enabled' => false
							],
							'biographical_note.@cdata' => [
								'type' => 'text',
								'index'	=> false		
							],
							
							/* CATEGAORY BROWSING/FACETING
								- now configured to use the tree_analyzer with path_hierarchy
								- see aggs => category
								- please try to standardise: category|cat_tree|cat_code NOT category_code|pubcat or anything else
								- multi-instance fields better off stored as an array of type keyword
							*/
							'category' => [			
								'type' => 'text',
								'copy_to' => 'cindex',
							],
							'cat_code' => [			
								'type' => 'keyword',
								//'copy_to' => 'cindex',
							],
							'cat_tree' => [
								'type' => 'keyword',
						
							],	
							'cbmc' => [
								'type' => 'text',
							],
							'cbmc_code' => [
								'type' => 'keyword',
							],
							'cert_irish' => [
								'type' => 'keyword',
								 'index' => false,
							],
							'cert_pegi' => [
								'type' => 'keyword',
								 'index' => false,
							],
							'cert_uk' => [
								'type' => 'keyword',
								 'index' => false,
							],
							// This the big keyword index substitute
							'cindex' => [
								'type' => 'text',
								'store' => true,	// for highlighting
								'analyzer' => 'lcase_name_analyzer',
								//'index_options' => 'offsets',
								//'position_increment_gap' => 100, // but only for match_phrase queries?
								
							],
							'colour' => [			
								'type' => 'text',
								'index' => false,
							],
							'contents.@cdata' => [
								'type' => 'text',
								'analyzer' => 'lcase_name_analyzer',
								'copy_to' => 'xindex',		
							],
							'contributor' => [			
								'type' => 'text',
								'analyzer' => 'lcase_name_analyzer',
								//'position_increment_gap' => 100, // maybe only 'phrase searchces'
								//'index_options' => 'offsets',
								'fields' => [
									'raw' => [
										'type' => 'keyword',
										'ignore_above' => 256
									],
									'hlt' => ['type' => 'text'], // for highlighting
									
								],
								'copy_to' => 'cindex',
							],
							'cr_year' => [			
								'type' => 'keyword',
								'index' => false,
							],
							'contributors' => [			
								'type' => 'text',
								'index' => false,
							],
							'contributor_list' => [			
								'enabled' => false
							],
							'ctitle' => [			
								'type' => 'text',
								'analyzer' => 'lcase_name_analyzer',		  
								'fields' => [
									'raw' => [	// e.g. if we wanted to collapse on this field
										'type' => 'keyword',
										'ignore_above' => 256
									],
									'hlt' => [	// e.g. if we wanted to collapse on this field
										'type' => 'text',
									],
									
								],
								'copy_to' => ['cindex', 'tindex'],
							],
							'date_created' => [			
								'type' => 'keyword',
							],
							'date_created_by' => [			
								'type' => 'keyword',
								'index' => false,
							],
							'date_created_file' => [			
								'type' => 'keyword',
								//'index' => false,
							],
							'date_created_user' => [			
								'type' => 'keyword',
								//'index' => false,
							],
							'date_created_user_company' => [			
								'type' => 'text',
								//'index' => false,
							],
							'date_created_user_company_code' => [			
								'type' => 'keyword',
								//'index' => false,
							],
							'date_updated' => [			
								'type' => 'keyword',
							],
							'date_updated_by' => [			
								'type' => 'keyword',
							],
							'date_updated_file' => [			
								'type' => 'keyword',
							],
							'date_updated_user' => [			
								'type' => 'keyword',
							],
							'date_updated_user_company' => [			
								'type' => 'keyword',
							],
							'date_updated_user_company_code' => [			
								'type' => 'keyword',
							],
							'descrip.@cdata' => [
								'type' => 'text',
								'copy_to' => 'xindex',					
							],
							'descrip_trade.@cdata' => [
								'type' => 'text',
							],
							'description.@cdata' => [
								'type' => 'text',
								'copy_to' => 'xindex',					
							],
							'dewey' => [
								'type' => 'text',
								'fields' => [
									'raw' => [
										'type' => 'keyword',
									]
								]
							],
							'dewey_edition' => [			
								'type' => 'keyword',
								'index' => false,
							],
							'dewey_num' => [
								//'type' => 'long',
								'type' => 'double',	// ?NBD dewey numbers are longer?
							],
							'dimensions' => [			
								'type' => 'keyword',
								'index' => false,
							],
							'doi' => [			
								'type' => 'keyword',
							],
							'discount_code' => [			
								'type' => 'keyword',
							],
							'discount_flag' => [			
								'type' => 'keyword',
							],
							'disc_format' => [			
								'type' => 'text',
								'index' => false,
							],
							'distributor' => [			
								'type' => 'text',
								'fields' => [
									'raw' => [
										'type' => 'keyword',
									]
								]
							],
							'distributor_code' => [			
								'type' => 'keyword',
							],
							'duration' => [			
								'type' => 'text',
								'index' => false,
							],
							'ean' => [			
								'type' => 'keyword',
							],
							'edi_number' => [			
								'type' => 'keyword',
							],
							'edition' => [			
								'type' => 'text',
								'index' => false,
							],
							'edition_statement' => [			
								'type' => 'text',
								'index' => false,
							],
							'ehaus' => [	
								/* N.B. if the first record indexed has no ehaus nodes then by default elastic treats it as a 
										text field i.e not as a node with child nodes
										- the next record WITH ehaus child nodes will then be rejected as unable to parse 
										  an array as straight text
										- UNLESS we prepare the ground with at least one field as follows
								
								*/
								'properties' => [

									'eh_amazon_flag' => [			
										'type' => 'keyword',
										//'index' => false,
									],
									'eh_approved_flag' => [			
										'type' => 'keyword',
										//'index' => false,
									],
									'eh_archive_flag' => [			
										'type' => 'keyword',
										//'index' => false,
									],
									'eh_author_bio.@cdata' => [			
										'type' => 'text',
										'index' => false,
									],
									'eh_author_prevbooks.@cdata' => [			
										'type' => 'text',
										'index' => false,
									],
									
									'eh_category' => [			
										'type' => 'text',
										'index' => false,
									],
									'eh_cat_code' => [			
										'type' => 'keyword',
										//'index' => false,
									],
									'eh_contents.@cdata' => [			
										'type' => 'text',
										'index' => false,
									],
									'eh_descrip.@cdata' => [			
										'type' => 'text',
										'index' => false,
									],
									'eh_description.@cdata' => [			
										'type' => 'text',
										'index' => false,
									],
									'eh_ebook_flag' => [			
										'type' => 'keyword',
										//'index' => false,
									],
									'eh_feature_flag' => [			
										'type' => 'keyword',
										//'index' => false,
									],
									'eh_published_flag' => [			
										'type' => 'keyword',
										//'index' => false,
									],
									'eh_reading_list' => [			
										'type' => 'text',
										'fields' => [
											'raw' => [
												'type' => 'keyword',
											]
										]
									],
									'eh_reading_list_code' => [			
										'type' => 'keyword',
									],
									'eh_review.@cdata' => [			
										'type' => 'text',
										'index' => false,
									],
									'eh_related_titles' => [			
										'type' => 'text',
										//'index' => false,
									],
									'eh_url_link' => [			
										'type' => 'text',
										'index' => false,
									],
									'eh_video_link.@cdata' => [			
										'type' => 'text',
										'copy_to' => 'video_link',
									],
									
								]	// end <ehaus> node
							],
							'epub_type' => [			
								'type' => 'text',
								'index' => false,
							],
							'epub_type_code' => [			
								'type' => 'keyword',
								//'index' => false,
							],
							'errors' => [			
								'type' => 'text',
								'index' => false,
							],
							'eu_avail' => [			
								'type' => 'text',
							],
							'eu_avail_code' => [			
								'type' => 'keyword',
							],
							'explicit_lyrics' => [			
								'type' => 'text',
								'index' => false,
							],
							'eu_exvat_price' => [			
								'type' => 'keyword',
								'index' => false,
							],
							'eu_price_currency' => [			
								'type' => 'keyword',
								'index' => false,
							],
							'eu_vat_amount' => [			
								'type' => 'keyword',
								'index' => false,
							],
							'eu_vat_code' => [			
								'type' => 'keyword',
								'index' => false,
							],
							'eu_vat_price' => [			
								'type' => 'keyword',
								'index' => false,
							],
							'eu_vat_rate' => [			
								'type' => 'keyword',
								'index' => false,
							],
							'ex_rights' => [			
								'type' => 'keyword',
								'index' => false,
							],
							'ex_rights_code' => [			
								'type' => 'keyword',
								'index' => false,
							],
							'extras_1' => [			
								'type' => 'text',
								'index' => false,
							],
							'extras_2' => [			
								'type' => 'text',
								'index' => false,
							],
							'extras_tbc' => [			
								'type' => 'text',
								'index' => false,
							],
							'facet' => [
								'type' => 'keyword',
							],
							'filename' => [
								'type' => 'keyword',
								'index' => false,
								'doc_values' => false
							],
							'first_published' => [			
								'type' => 'text',
								'index' => false,
							],
							'format' => [
								'type' => 'text',
								 'fields' => [
									'raw' => [
										'type' => 'keyword',
									]
								],
							],
							'format_code' => [
								'type' => 'keyword',
							],
							'format_detail' => [			
								'type' => 'text',
								'index' => false,
							],
							'format_detail_code' => [			
								'type' => 'keyword',
								//'index' => false,
							],
							'format_ebook' => [			
								'type' => 'text',
								'index' => false,
							],
							'format_ebook_code' => [			
								'type' => 'keyword',
								//'index' => false,
							],

							'format_form_description' => [			
								'type' => 'text',
								'index' => false,
							],
							'format_note' => [
								'type' => 'text',
								'index' => false,
							],
							'format_type' => [
								'type' => 'keyword',
							],
							'format_type_code' => [
								'type' => 'keyword',
							],
							'genre' => [
								'type' => 'text',
								'copy_to' => 'cindex',
								 'fields' => [
									'raw' => [
										'type' => 'keyword',
									],
								]
							],
							'genre_code' => [
								'type' => 'keyword',
							],
							'genre_tree' => [
								'type' => 'keyword',
								'normalizer'=> 'lcase_normalizer'
							],	
							'identifier' => [			
								'type' => 'text',
								'copy_to' => 'cindex',
							],
							'illus' => [			
								'type' => 'text',
								'index' => false,
							],
							'imprint' => [
								'type' => 'text',
								 'fields' => [
									'raw' => [
										'type' => 'keyword',
									],									
								]
							],
							'imprint_code' => [
								'type' => 'keyword',
							],
							'imprint_exact' => [
								'type' => 'keyword',
							],
							'interest_age' => [
								'type' => 'keyword',
							],
							'interest_age_exact' => [
								'type' => 'keyword',
							],
							'isbn13h' => [
								'type' => 'keyword',
							],
							'isbn_10' => [
								'type' => 'keyword',
								'doc_values' => false
							],
							'jacket' => [			
								'enabled' => false,
							],
							'jacket_flag' => [
								'type' => 'keyword',
								'doc_values' => false
							],
							'jacket_l' => [			
								'enabled' => false,
							],
							'jacket_m' => [			
								'enabled' => false,
							],
							'jacket_orig_a' => [			
								'enabled' => false,
							],
							'jacket_orig_b' => [			
								'enabled' => false,
							],
							'jacket_orig_add_1' => [			
								'enabled' => false,
							],
							'jacket_s' => [			
								'enabled' => false,
							],
							'jacket_ud' => [			
								'enabled' => false,
							],
							'jacket_xl' => [			
								'enabled' => false,
							],
							'keystage' => [			
								'type' => 'text',
								'index' => false,
							],
							'keystage_code' => [			
								'type' => 'keyword',
							],
							'kword_index.@cdata' => [			
								'type' => 'text',
								'index' => false,
							],
							'language' => [
								'type' => 'text',
							],
							'language_code' => [
								'type' => 'keyword',
							],
							'language_orig' => [
								'enabled' => false,
							],
							'language_text' => [
								'type' => 'text',
								'index' => false,
							],
							'language_text_code' => [
								'type' => 'keyword',
							],
							'last_updated' => [			
								'enabled' => false,
							],
							'lc_class' => [
								'type' => 'keyword',
							],
							'lc_head' => [			
								'type' => 'text',
								'copy_to' => 'cindex',
								'fields' => [
									'raw' => [
										'type' => 'keyword',
									]
								]
							],
							'lcode' => [
								'type' => 'keyword',
							],

							'lib_qual_code' => [
								'type' => 'keyword',
							],
							'lib_reader_code' => [
								'type' => 'keyword',
							],
							'library_holdings' => [
								'type' => 'keyword',
							],
							'map_scale' => [			
								'type' => 'text',
								'index' => false,
							],
						    'minimum_spec.@cdata' => [			
								'type' => 'text',
								'index' => false,
							],
							'name_as_subject' => [
								'type' => 'text',
								'fields' => [
									'raw' => [
										'type' => 'keyword',
									]
								]
							],
							'name_exact' => [			
								'type' => 'text',
								
							],
							'nbd_review.@cdata' => [			
								'type' => 'text',
								'index' => false,
							],
							'nc_subject' => [			
								'type' => 'text',
								'index' => false,
							],
							'nex_rights_code' => [			
								'type' => 'keyword',
								'index' => false,
							],
							'nfs_code' => [			
								'type' => 'keyword',
								'index' => false,
							],
							'nof_items' => [			
								'type' => 'keyword',
								'index' => false,
								'doc_values' => false
							],
							'nof_pages' => [			
								'type' => 'keyword',
								'index' => false,
								'doc_values' => false
							],
							'nof_pieces' => [			
								'type' => 'keyword',
								'index' => false,
								'doc_values' => false
							],
							'nof_players' => [			
								'type' => 'keyword',
								'index' => false,
								'doc_values' => false
							],
							'not_for_sale.@cdata' => [
								'type' => 'text',					
							],
							'num_pages' => [			
								'type' => 'text',
								'index' => false,
							],
							'onix_contributor.@cdata' => [			
								'type' => 'text',
								'index' => false,
							],
							'onix_pubstatus' => [			
								'type' => 'text',
								'index' => false,
							],
							'onix_pubstatus_code' => [			
								'type' => 'keyword',
							],
							'onix_readership' => [			
								'type' => 'text',
								'index' => false,
							],
							'onix_readership_code' => [			
								'type' => 'keyword',
							],
							'op_date' => [			
								'type' => 'keyword',
								'index' => false,
							],
							'pcode' => [			
								'type' => 'keyword',
							],
							'perm_promo.@cdata' => [			
								'type' => 'text',
								'index' => false,
							],
							'pov' => [			
								'enabled' => false,
							],
							'primary_avail' => [
								'type' => 'keyword',
							],
							'primary_edition' => [
									'type' => 'keyword',
							],
							'primary_name' => [
								'type' => 'text',
								 'fields' => [
									'raw' => [
										'type' => 'keyword',
									],
								]
							],
							'primary_price' => [
								'type' => 'keyword',
							],
							'prize_flag' => [
								'type' => 'keyword',
								'doc_values' => false
							],
							'prizes.@cdata' => [
								'type' => 'text',
								'copy_to' => 'xindex',				
							],
							'psc' => [				// Not used any more
								'type' => 'keyword',
								'index' => false,
							],
							/**/
							'psc' => [					// Not used any exclude from indexing
								'enabled' => false,
							],
							'pse' => [					// ??? Not used any exclude from indexing
								'enabled' => false,
							],
							'psg' => [					// ? use normal prefix + filter like Thema
								'enabled' => false,
							],
							'publ_country' => [
								'type' => 'text',
								 'fields' => [
									'raw' => [
										'type' => 'keyword',
									]
								]
							],
							'pub_cat' => [
								'type' => 'text',
							],
							'pub_cat_code' => [
								'type' => 'keyword',
							],
							'pub_descrip.@cdata' => [			
								'type' => 'text',
								'index' => false,
							],
							'pub_month' => [
								'type' => 'keyword',
							],
							'pub_name' => [			
								'enabled' => false,
							],
							'pub_week' => [			
								'type' => 'keyword',
							],
							'pub_year' => [
								'type' => 'keyword',
							],
							'publ_place' => [
								'type' => 'text',
								 'fields' => [
									'raw' => [
										'type' => 'keyword',
									]
								]
							],
							'publisher' => [
								'type' => 'text',
								 'fields' => [
									'raw' => [
										'type' => 'keyword',
									]
								]
							],
							'publisher_exact' => [			
								'type' => 'keyword',
							],
							'quotes' => [			
								'enabled' => false,
							],							
							'rda_flag' => [			
								'type' => 'keyword',
							],
							'read_by' => [
								'type' => 'text',
								 'fields' => [
									'raw' => [
										'type' => 'keyword',
									],
								],
								'copy_to' => 'cindex',
							],
							'readership' => [
								'type' => 'text',
								 'fields' => [
									'raw' => [
										'type' => 'keyword',
									]
								]
							],
							'readership_code' => [			
								'type' => 'keyword',
							],
							'reading_age' => [			
								'type' => 'keyword',
							],
							'ref_no' => [
								'type' => 'keyword',
							],
							
							'related_ebook' => [			
								'type' => 'keyword',
							],
							'related_author' => [			
								'type' => 'text',
							],
							'related_editions.@cdata' => [	// New ONIX format		
								'type' => 'text',
							],
							'related_previous' => [			
								'type' => 'text',
							],
							'related_similar' => [			
								'type' => 'text',
							],
							'related_ids' => [			
								'type' => 'text',
							],
							'replaced_by_ean' => [			
								'enabled' => false,
							],
							'replaced_by_isbn' => [			
								'enabled' => false,
							],
							'replaces_ean' => [			
								'enabled' => false,
							],
							'replaces_isbn' => [			
								'type' => 'keyword',
								'index' => false,
							],
							'responsibility' => [			
								'enabled' => false,
							],
							'review' => [			
								'enabled' => false,
							],
							'review_quotes' => [			
								'enabled' => false,
							],
							'rights_afs' => [			
								'enabled' => false,
							],
							'rights_inc' => [			// stored in _source by default but don't index :)
								'enabled' => false,
							],
							'sales_rank' => [
								'type' => 'float',
								'null_value' => 9999	// ?or 'NULL' 
							],
							'sales_restrictions' => [
								'enabled' => false,		
							],
							'sales_rights' => [
								'enabled' => false,		
							],
							'screen_ratio' => [			
								'enabled' => false,
							],
							'screenshots' => [			
								'enabled' => false,
							],
							'series' => [
								'type' => 'text',
								'copy_to' => ['cindex', 'tindex'],
								'fields' => [
									'raw' => [
										'type' => 'keyword',
									]
								]
							],
							'series_code' => [			
								'type' => 'keyword',
								//'index' => false,
							],
							'series_exact' => [			
								'type' => 'keyword',
							],
							'series_issn' => [			
								'type' => 'keyword',
							],
							'series_number' => [			
								'enabled' => false,
							],
							'series_vol' => [			
								'enabled' => false,
							],
							'series_part' => [
								'enabled' => false,
							],
							'sim_pub' => [
								'type' => 'keyword',
								'index' => false,
								'doc_values' => false
							],
							'sort_alpha' => [
								'type' => 'keyword',
							],
							'sort_date' => [
								'type' => 'date',
								'format' => 'basic_date||yyyyMMdd',
								'ignore_malformed' => true,
								 'fields' => [
									'raw' => [
										'type' => 'keyword',
									],
								]
							],
							'sort_month' => [
								'type' => 'keyword',
							],
							'sort_name' => [
								'type' => 'keyword',
							],
							'sort_series' => [
								'type' => 'keyword',
							],
							'sort_title' => [
								'type' => 'keyword',
							],
							'sort_year' => [
								'type' => 'keyword',
							],
							'sound' => [			
								'type' => 'text',
								'index' => false,
							],
							'stock_flag' => [
								'type' => 'keyword',
								'doc_values' => false
							],
							'sub_title' => [
								'type' => 'text',
								'copy_to' => ['cindex', 'tindex']
							],
							'suggest_terms' => [			
								'type' => 'text',
								'analyzer' => 'lcase_name_analyzer',
								'fields' => [
									'sug' => [
										'type' => 'search_as_you_type',
									],
									'raw' => [
										'type' => 'keyword',
										'normalizer'=> 'lcase_normalizer'
									],
									
								],
							],
							'supplier' => [
								'enabled' => false,
							],
							'thema_qualifier' => [
								'enabled' => false,
							],
							'thema_qual_code' => [
								'type' => 'keyword',
							],
							'thema_subj_code' => [
								'type' => 'keyword',
							],
							'thema_subject' => [			
								'type' => 'text',
								'copy_to' => 'cindex',
							],
							'thema_tree' => [
								'type' => 'keyword',
						
							],	
							'tindex' => [
								'type' => 'text',
								'analyzer' => 'lcase_name_analyzer',
							],
							'title_2' => [			
								'type' => 'text',
								'copy_to' => ['cindex', 'tindex']
											
							],
							'title_exact' => [
								'type' => 'keyword',
							],
							'title_index' => [			
								'enabled' => false,
							],
							'tracklist' => [			
								'enabled' => false,
							],
							'tracks.@cdata' => [			
								'type' => 'text',
								'copy_to' => ['xindex', 'tindex']					
							],
							'trans_from' => [			
								'type' => 'text',
								'index' => false,
							],
							'trans_from_code' => [			
								'type' => 'keyword',
								'index' => false,
							],
							'trans_from_text' => [			
								'type' => 'text',
								'index' => false,
							],
							'trailer' => [			
								'type' => 'text',
								'index' => false,
							],
							'uk_avail' => [
								'type' => 'text',
							],
							'uk_avail_code' => [
								'type' => 'keyword',
							],
							'uk_avail_date' => [
								'type' => 'keyword',
								'index' => false,
							],
							
							'uk_dcode' => [			
								'type' => 'keyword',
							],
							'uk_distributor' => [			
								'type' => 'text',
								'index' => false,
							],
							'uk_distributor_code' => [			
								'type' => 'keyword',
								'index' => false, // use distributor_code!
							],
							'uk_del_date' => [
								'type' => 'keyword',
								'index' => false,
								'doc_values' => false
							],
							'uk_dlr_ex_vat' => [			
								'type' => 'keyword',
								'index' => false,
								'doc_values' => false
							],
							'uk_exvat_price' => [			
								'type' => 'keyword',
								'index' => false,
							],
							'uk_pack_qty' => [			
								'type' => 'keyword',
								'index' => false,
							],
							'uk_price_area' => [			
								'type' => 'keyword',
								'index' => false,
							],
							'uk_price_currency' => [			
								'type' => 'keyword',
								'index' => false,
							],
							'uk_price_type' => [			
								'type' => 'keyword',
								'index' => false,
							],
							'uk_price_type_code' => [			
								'type' => 'keyword',
								'index' => false,
							],
							'uk_vat_amount' => [			
								'type' => 'keyword',
								'index' => false,
								'doc_values' => false
							],
							'uk_vat_code' => [			
								'type' => 'keyword',
								'index' => false,
								'doc_values' => false
							],
							'uk_vat_price' => [			
								'type' => 'keyword',
								'index' => false,
								'doc_values' => false
							],
							'uk_vat_rate' => [			
								'type' => 'keyword',
								'index' => false,
								'doc_values' => false
							],
							'uk_pubdate' => [
								'type' => 'keyword',
							],
							'uk_pubdate_assumption' => [
								'type' => 'keyword',
							],
							'uk_rrp_code' => [			
								'type' => 'keyword',
								'index' => false,
							],
							'uk_tax_notes' => [			
								'type' => 'keyword',
								'index' => false,
							],
							'ukslc' => [
								'type' => 'text',
							],
							'ukslc_code' => [
								'type' => 'keyword',
							],
							'us_avail' => [			
								'type' => 'keyword',
								'index' => false,
							],
							'us_avail_area' => [			
								'type' => 'keyword',
								'index' => false,
							],
							'us_avail_code' => [			
								'type' => 'keyword',
								'index' => false,
							],
							'us_avail_date' => [			
								'type' => 'keyword',
								'index' => false,
							],
							'us_extax_price' => [			
								'type' => 'keyword',
								'index' => false,
							],
							'us_tax_amount' => [			
								'type' => 'keyword',
								'index' => false,
							],
							'us_tax_code' => [			
								'type' => 'keyword',
								'index' => false,
							],
							'us_tax_price' => [			
								'type' => 'keyword',
								'index' => false,
							],
							'us_tax_rate' => [			
								'type' => 'keyword',
								'index' => false,
							],
							'vx_first_name' => [
								'type' => 'keyword',								
							],
							'video_link' => [
								'type' => 'keyword',								
							],
							'w10_title_code' => [
								'type' => 'keyword',								
							],
							'wco' => [
								'type' => 'keyword',								
							],
							'weight' => [			
								'type' => 'keyword',
								'index' => false,
								'doc_values' => false
							],
							'work_doi' => [
								'type' => 'keyword',								
							],
							'work_exact' => [
								'type' => 'keyword',								
							],
							'work_id' => [
								'type' => 'keyword',								
							],
							'xindex' => [	// a catchall 'text' index
								'type' => 'text',
								'analyzer' => 'lcase_name_analyzer',
							],
							'year_of_production' => [
								'type' => 'keyword',								
							],
							
						]
					]
				]
			]
		],
		
/**** 	AGGREGATIONS 	
		// DEFINE here and set which ones you want to see either as Defaults 'elastic_aggregations_inc' or in query_string [&aggs=a,b,c]
		// Default = 10	and you cannot set size = 0	 
*/
		'elastic_aggregations' => [
		
			'livesearch' => [
				'terms' => [
					'size' => 10,
					'script' => [
						'lang' => 'painless',
						'inline' => "doc['ctitle.raw'].value + '|' + doc['contributor.raw'].value",
					],
					'order' => [ 'my_score' => 'desc' ],				
				],	
				'aggs' => [
					'my_score' => [
						'avg' => ['script' => '_score']
					]
				]
			],

			'bic_subj_code' => [
				'terms' => [
					'field' => 'bic_subj_code',
					'size' => 500,
					'order' => [ '_key' => 'asc' ],
				]
			],			
			'bic_tree' => [
				'terms' => [
					'field' => 'bic_tree',
					'size' => 500,
					'order' => [ '_key' => 'asc' ]
				]
			],
			'category' => [
				'terms' => [
					'field' => 'category',
					'size' => 100,
					'order' => [ '_key' => 'asc' ]
				]
			],
			'cat_code' => [
				'terms' => [
					'field' => 'cat_code',
					'size' => 100,
					'order' => [ '_key' => 'asc' ]
				]
			],
			'cat_tree' => [
				'terms' => [
					'field' => 'cat_tree',
					'size' => 500,
					'order' => [ '_key' => 'asc' ]
				]
			],
			'cert_irish' => [
				'terms' => [
					'field' => 'cert_irish.raw',
					//'size' => 0,
					'order' => [ '_key' => 'asc' ]
				]
			],
			'cert_pegi' => [
				'terms' => [
					'field' => 'cert_pegi.raw',
					//'size' => 0,
					'order' => [ '_key' => 'asc' ]
				]
			],
			'cert_uk' => [
				'terms' => [
					'field' => 'cert_uk.raw',
					//'size' => 0,
					'order' => [ '_key' => 'asc' ]
				]
			],
			'contributor' => [	
				'composite' => [
					'sources' => [
						
						
						['date' => [
								'terms' => [
									'field' => 'sort_date',
									'order' => 'desc'
								]
							]
						],
						['name' => [
								'terms' => [
									'field' => 'contributor.raw',
									'order' => 'asc'
								]
							]
						],
						
						
					]
				],
			],
			'name' => [	// finds the top 100 unique titles (from the search), ordered by date, newest first :))))
				'terms' => [
					'field' => 'contributor.raw',
					'size' => 5,
					'order' => [ 'avg_score' => 'desc' ]
				],
				 'aggs' => [
					'avg_score' => [
						'avg' =>  ['script' => '_score']
					  ]
				  ]
				
			],
			'ctitle' => [	// finds the top 100 unique titles (from the search), ordered by date, newest first :))))
				'terms' => [
					'field' => 'ctitle.raw',
					'size' => 5,
					'order' => [ 'avg_score' => 'desc' ]
				],
				'aggs' => [
					'avg_score' => [
						'avg' =>  ['script' => '_score']
				]
				  ]
				
			],
			'distributor' => [
				'terms' => [
					'field' => 'distributor_code',
					'size' => 1000,
					'order' => [ '_count' => 'desc' ]
				]
			],
			'facet' => [
				'terms' => [
					'field' => 'facet',
					'size' => 30,
					'order' => [ '_key' => 'asc' ]
				]
			],
			'format' => [
				'terms' => [
					'field' => 'format',
					'size' => 100,
					'order' => [ '_key' => 'asc' ]
				]
			],
			'format_code' => [
				'terms' => [
					'field' => 'format_code',
					'size' => 1000,
					'order' => [ '_key' => 'asc' ]
				]
			],
			'format_type' => [
				'terms' => [
					'field' => 'format_type',
					'size' => 1000,
					'order' => [ '_key' => 'asc' ]
				]
			],
			'format_type_code' => [
				'terms' => [
					'field' => 'format_type_code',
					'size' => 1000,
					'order' => [ '_key' => 'asc' ]
				]
			],
			'genre_tree' => [
				'terms' => [
					'field' => 'genre_tree',
					'size' => 100,
					'order' => [ '_key' => 'asc' ],
					
					
				]
			],
			'imprint' => [
				'terms' => [
					'field' => 'imprint.raw',
					'size' => 50,
					'order' => [ '_count' => 'desc' ]
				]
			],
			'language_text' => [
				'terms' => [
					'field' => 'language_text.raw',
					'size' => 10,
					'order' => [ '_key' => 'asc' ]
				]
			],
			'language_text_code' => [
				'terms' => [
					'field' => 'language_text_code',
					'size' => 10,
					'order' => [ '_key' => 'asc' ]
				]
			],
			'lib_qual_code' => [
				'terms' => [
					'field' => 'lib_qual_code',
					'size' => 20,
					'order' => [ '_key' => 'asc' ]
				]
			],
			'lib_reader_code' => [
				'terms' => [
					'field' => 'lib_reader_code',
					'size' => 25,
					'order' => [ '_key' => 'asc' ]
				]
			],
			'onix_publisher' => [
				'terms' => [
					'field' => 'onix_publisher.raw',
					'size' => 1000,
					'order' => [ '_key' => 'asc' ]
				]
			],
			'onix_pubstatus_code' => [
				'terms' => [
					'field' => 'onix_pubstatus_code',
					'size' => 20,
					'order' => [ '_key' => 'asc' ]
				]
			],
			'publisher' => [
				'terms' => [
					'field' => 'publisher.raw',
					'size' => 1000,
					'order' => [ '_key' => 'asc' ]
				]
			],
			'primary_name' => [
				'terms' => [
					'field' => 'primary_name.raw',
					'size' => 20,
					'order' => [ '_count' => 'desc' ]
				]
			],
			'primary_price' => [
				'terms' => [
					'field' => 'primary_price',
					'size' => 20,
					'order' => [ '_term' => 'asc' ]
				]
			],
			'reading_list' => [
				'terms' => [
					'field' => 'ehaus.eh_reading_list.raw',
					'size' => 100,
					'order' => [ '_count' => 'desc' ]
				]
			],
			'series' => [
				'terms' => [
					'field' => 'series.raw',
					'size' => 100,
					'order' => [ '_count' => 'desc' ]
				]
			],
			'sort_date' => [
				'terms' => [
					'field' => 'sort_date',
					'size' => 12,
					'order' => [ '_key' => 'desc' ]
				]
			],
			'thema_qual_code' => [
				'terms' => [
					'field' => 'thema_qual_code',
					'size' => 500,
					'order' => [ '_key' => 'asc' ],
				]
			],
			'thema_subj_code' => [
				'terms' => [
					'field' => 'thema_subj_code',
					'size' => 500,
					'order' => [ '_key' => 'asc' ],
				]
			],
			'thema_tree' => [
				'terms' => [
					'field' => 'thema_tree',
					'size' => 500,
					'order' => [ '_key' => 'asc' ],
				]
			],
			'uk_avail_code' => [
				'terms' => [
					'field' => 'uk_avail_code',
					'size' => 20,
					'order' => [ '_key' => 'asc' ]
				]
			],
			'work_id' => [
				'terms' => [
					'field' => 'work_id',
					'size' => 20,
					'order' => [ '_count' => 'desc' ]
				]
			],
			
			
		],
		
/****	HIGHLIGHTING	
		- The terms searched for must be in the specified as highlight fields
		- i.e. you need to search the fields in order for the highlights to show
		- e.g. 'query' => 'ctitle:(term1 term2)', plus set 'highlight.fields' = 'ctitle'
		- copy_to indexes like cindex: set: store => true
		- specified fields must be listed in search e.g. see xmla-elastic:: should clause
		- 'require_field_match' => false, will match the search terms in all the specified fields
		- switch on/off in DBM see 'elastic_highlight_show'
		- 2023-09-15 - added field ctitle.hlt to avoid confusion with ctitle
		
	*/
		'elastic_highlight'	=> [
		
			//'pre_tags' => ['<mark class="highlight">'],
					//'post_tags' => ['</mark>'],
			'tags_schema' => 'styled',	// overrides the pre_tags with <em class="hlt1">..</em>
					'fragment_size' => 250,
					'number_of_fragments' => 5,
					'no_match_size' => 0,
					'require_field_match' => false,	
					'fields' => [
						
						'ctitle.hlt' => ['number_of_fragments' => 3], 
						'contributor.hlt' => ['number_of_fragments' => 3], 
						'cindex' => [ 'type' => 'unified', 'number_of_fragments' => 5, 'no_match_size' => 150], 
						'description.@cdata' => new \stdClass(), 
												
			],
		
		],
		
/****	SHOULD Clause
		- effective for boosting the scores of records found by the MUST clause
		- substitutions are made for the following values: [search_terms|search_query|yyyymmdd[n]]
		- N.B. 'n' = +/- days from today (used in range queries)
	*/
		
		'elastic_should' => [
		/*				
			[ // this one configured to boost proximity of terms in specified fields: Also include any fields to highlight
			
				
					'match_phrase' => [
						'ctitle'	=> [
							'query' => 'search_terms',
							'slop' => 2,
						]
					]
			
			],
			
		
						
			[ // this one configured to boost proximity of terms in specified fields: Also include any fields to highlight
			
				'multi_match' => [
					'query' => 'search_terms',	// ['search_terms|search_query|xyz']
					'type'	=> 'cross_fields',
					'fields' => ['contributor', 'ctitle'], 
					'slop' => 3,
					'boost' => 2,
					'operator' => 'AND',
				]
			],
			
			[ // this one configured to boost proximity of terms in specified fields (and triggers highlighted fields)
			
				'multi_match' => [
					'query' => 'search_terms',	// ['search_terms|search_query|xyz']
					'type'	=> 'best_fields',
					'fields' => ['contributor', 'ctitle'], 
					'slop' => 3,
					'boost' => 2,
					'operator' => 'AND',
				]
			],
		*/	
		/*
			[
				'query_string' => [
				
					'query' => 'search_query',
					'default_operator' => 'AND',
					'fuzziness' => 0,
					'phrase_slop' => 0,
					'boost' => 0,									

				]							
			],
		*/
		
		/*	
			[
				'range' => [
					'sort_date' => [
						'boost' => 4,
						'lte' => 'yyyymmdd[40]',	// e.g. yymmdd[-60]
						'gte' => 'yyyymmdd[-10]',
					]
				]
			],
		
			[
				'range' => [
					'sort_date' => [
						'boost' => 2,
						'lte' => 'yyyymmdd[-10]',
						'gte' => 'yyyymmdd[-90]',
					]
				]
			],
			
			[
				'range' => [
					'sort_date' => [
						'boost' => 2,
						'lte' => 'yyyymmdd[0]',
						'gte' => 'yyyymmdd[-180]',
					]
				]
			],
			
			[
				'range' => [
					'sort_date' => [
						'boost' => 2,
						'lte' => 'yyyymmdd[0]',
						'gte' => 'yyyymmdd[-360]',
					]
				]
			],
		*/
			/**/
		],	// end should
		
/****	LIKE
		- url param '&LIKE=<ref_no>'
		- will then compare current record with other records by reference to selected fields
		- substitutions are made for the following values: [elastic_like_index|elastic_like_id|elastic_like_terms]
		- - _id and _terms both substitute the value of the &LIKE="enter text string or ref_no here" param
		- 'elastic_like_index': defaults to current index unless alternative DBM is specified (e.g. <sitename>-main)
		
	*/
		'elastic_like'	=> [
		
			'more_like_this' => [
			
				'fields' => [	
					'ctitle', 'primary_name', 'thema_subject', 'series', 'prizes'
				],
				'like' => [
					[
						'_index'  	=> 'elastic_like_index',
						'_id'  		=> 'elastic_like_id'
					],					
					'elastic_like_terms',
				],
				'min_term_freq' => 1,
				'min_doc_freq' => 1,
				'max_query_terms' => 25	
			]
		
		],	// end LIKE
		
/****	SUGGEST
		 - using completion fields (see elastic-indexer)
		 - substitutions are made for the following values: [search_terms|search_query|yyyymmdd[n]]
		 - N.B. 'n' = +/- days from today (used in range queries)
		 - SWITCH on above: 'elastic_suggestions_show' => [true/false] If False then set dynamically via &SUG=xyz
		 - FUZZY = 1 usually works best
		
	*/
		'elastic_suggest' => [
		
			'suggest-name' => [
				'prefix' => 'search_terms',
				'completion' => [ 
					'field' => 'suggest_name',
					'fuzzy' => [
						'fuzziness' => 1,
						'prefix_length' => 3
					],
					'size' => 5,
					'skip_duplicates' => true // Not available until v6.0+
				],
				
			],
			'suggest-title' => [
				'prefix' => 'search_terms',
				'completion' => [ 
					'field' => 'suggest_title',
					'fuzzy' => [
						'fuzziness' => 1,
						'prefix_length' => 3
						],
					'size' => 5,
					'skip_duplicates' => true	// Not available until v6.0+
				], 
						
			],
					
		],	// end suggest
		
/****	MY OWN QUERY
		 - the idea is to construct your own query - replaces body.query
		 - substitutions are made for the following values: [search_terms|search_query|yyyymmdd[n]]
		 - N.B. 'n' = +/- days from today (used in range queries)
		 - Set dynamically via &MOQ=search_terms
		 - FUZZY = 1 usually works best
		
	*/
		'elastic_query' => [
		/*
		// 'query' is assumed	
			'match_bool_prefix' => [
				'suggest_terms' => [
					'query' => 'search_terms',
					//'fuzziness' => 1,
					'minimum_should_match' => '100%',
				],
				
			]

		*/	
		// 'query' is assumed	
			'multi_match' => [
				'query' => 'search_terms',
				'type' => 'bool_prefix',
				'fields' => [
					'suggest_terms.sug',
					'suggest_terms.sug._2gram',
					'suggest_terms.sug._3gram',
				],
				'minimum_should_match' => '100%',
				'fuzziness' => 1,
				'prefix_length' => 2,
			]
			
					
		],	// end my own query
		

/****	RESCORE: 
		- see SWITCHES above: 'elastic_rescore_show' => [true/false]
		- substitutions will be made for the following strings:
		- - rescore_window_size	(set as a default above)
		- - yyyymmdd[n]] ('n' = +/- days from today (used in range queries))
		- score_mode:
			total		Add the original score and the rescore query score. The default.
			multiply	Multiply the original score by the rescore query score.
			avg			Average the original score and the rescore query score.
			max			Take the max of original score and the rescore query score.
			min			Take the min of the original score and the rescore query score.
			
		- NOTES: 	Filter searches don't score, so if you use 'multiply' you are multiplying the rescore x 0 = 0!!
					Multiple rescores - the first one rescores the search, the next rescores the rescore window and so on, top-down.
		
	*/		
		'elastic_rescore' => [
		
			[
				'window_size' => 'rescore_window_size',
				'query' => [
					'rescore_query' => [
						'multi_match' => [
							'query' => 'search_terms',	
							'type'	=> 'phrase',
							'fields' => ['contributor', 'ctitle'], 
							'slop' => 3,
							'boost' => 2,
							//'operator' => 'AND',
						]
					],	
					'query_weight' => 1.0,
					'rescore_query_weight' => 2,
					'score_mode' => 'total',
				]
			],	// end rescore_1
		/*	
			[
				'window_size' => 'rescore_window_size',
				'query' => [
					'rescore_query' => [
						'query_string' => [
							'query' => '(lib_qual_code:(LIB))',
						]
					],	
					'query_weight' => 1.0,
					'rescore_query_weight' => 2,
					'score_mode' => 'total',
					
				]
			],	// end rescore_2
			
		
			[
				'window_size' => 'rescore_window_size',
				'query' => [
					'rescore_query' => [
						'query_string' => [
							'query' => '(uk_avail_code:(20))',
						]
					],	
					'query_weight' => 1.0,
					'rescore_query_weight' => 2,
					'score_mode' => 'total',
				]
			],	// end rescore_3
			
		
			[
				'window_size' => 'rescore_window_size',
				'query' => [
					'rescore_query' => [
						'query_string' => [
							'query' => '(language_text_code:(ENG))',
						]
		],
					'query_weight' => 1.0,
					'rescore_query_weight' => 0.1,
					'score_mode' => 'total',
				]	
			],	// end rescore_4
				
			*/
			
			[
				'window_size' => 'rescore_window_size',
				'query' => [
					'rescore_query' => [
						'range' => [
							'sort_date' => [
								'lte' => 'yyyymmdd[30]',
								'gte' => 'yyyymmdd[-360]',
							]
						]
					],	
					'query_weight' => 0.5,
					'rescore_query_weight' => 5,
					'score_mode' => 'total',
				]
			],	// end rescore_5
			
			[
				'window_size' => 'rescore_window_size',
				'query' => [
					'rescore_query' => [
						'range' => [
							'sort_date' => [
								'lte' => 'yyyymmdd[30]',
								'gte' => 'yyyymmdd[-100]',
							]
						]
					],	
					'query_weight' => 0.5,
					'rescore_query_weight' => 5,
					'score_mode' => 'total',
				]
			],	// end rescore_6
			
			[
				'window_size' => 30,
				'query' => [
					'rescore_query' => [
						'range' => [
							'sort_date' => [
								'lte' => 'yyyymmdd[30]',
								'gte' => 'yyyymmdd[-60]',
							]
						]
					],	
					'query_weight' => 0.5,
					'rescore_query_weight' => 5,
					'score_mode' => 'total',
				]
			],	// end rescore_7
		
			/* */
		],	// end rescore array 
		
		
		
	
	);
					

		  
/*******************************************************************************/			 
/*******************************************************************************/
			 
			 
?>