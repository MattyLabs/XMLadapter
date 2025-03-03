# MattyLabs: XMLAdapter
A very simple Elasticsearch client written in PHP with a simplified search syntax of HTML name=value pairs (i.e. web form values) which are transcribed into the **Elasticsearch** query_string search syntax. The class will also run the search and return the search results as either XML, JSON or a raw array.

Configuration comes in the form of a DBM configuration file for each index (sample provided) see www.

The class focuses primarily on the query_string query syntax but will also generate simple multi_match queries. The following search requests are supported:

- query bool [must(query_string|multi_match)|should(multi_match)|filter|range]
- aggs
- collapse
- highlight
- rescore
- sort
- suggest (completion)
- like

Default templates and configuration settings supporting the query generator are stored in the DBM file for the index being searched. See sample supplied. Additional settings are retrieved from a server-default.inc file and a site-ini.inc file but only really for backwards compatibility - the few values set could easily be moved.

Most, if not all, query_string syntax is preserved and the class should be reasonably easy to extend to accomodate additional types of query and outputs.

# HTML Search Syntax
The HTML syntax assumes all queries are formatted and &name=value pairs i.e. from a form post or querystring.

### Search Paramater
```
	&SP1=[AND|OR|NOT]
```
The Search Parameter is appended to the end of a search phrase, so "SP1=AND&SF1=title&ST1=foo&SP2=OR&SF2=author&ST2=potter" is interpreted as (title:(foo)) OR (author:(potter)). The first SP1 is meaningless and so is ignored.

### Search Field
``````
&SF1=<field_name1>
``````
The SFn parameter can take a comma separated list of field names, so "&SF1=title,author is interpreted as (title:(xyz)) OR (author:(xyz)). Nested fields or alternative field types are dot separated e.g. title.raw

### Search Text
``````
&ST1=<search terms>
``````
The STn parameter can take most query_string operators:
- wildcards (*)
- NOT operator (-)
- Boolean operators (AND|OR|NOT)
- Fuzzy (term~[1|2])
- Boost (term^2)
- Range (:) obviously only if the field takes a range query
- Grouping () i.e. via extensive use of brackets
- Comparison (<>=) single values only i.e. it wont handle a range using <>, although it would handle &ST1=(>%3D10+AND+<20)
- Phrases ('my search phrase'). N.B. you can use either single or double quotes.
- Proximity ('fox quick'~5)

### Sort
``````
&SORT=field_name/[a|d]
``````
The SORT parameter can take a comma separated list of field names. To sort the field must be of type=keyword. Sort ascending is the default so no need to specify. /d for descending. Fields with "sort_" or "_code" are assumed to be of type=keyword but all other fields are assumed to be of type=text but to have a 'field.raw' alternative with type=keyword. You may want to modify this.

### Field List
``````
&FIELDS=field1,field2,field3 etc.
``````
A comma separated list of the fields you want returned by the search (i.e. maps to _source['includes']). "default" specifies the list of fields configured in the DBM::['default_fieldlist']. "*" will return all available fields. "distinct" will display the default aggregations configured in DBM::['elastic_aggregations_inc'] as <distinct_fields></distinct_fields>

### DEBUG
``````
&DEBUG=on,query,must,aggs,suggest,etc
``````
"on" is required to switch on the debug. Other params will display the final JSON array submitted to the search client or the specified part.

### Collapse
``````
&COLLAPSE=field1
``````
Switches on the collapse search results using the specified field (which must be of type=keyword). Additional settings e.g. particularly with regard to the display of inner_hits fields.

### Date Ranges
``````
&DTSPAN=60:30
``````
Defaults to a date range search equivalent to: (sort_date:(gte:60 days ago from today AND lte:30 days from today)). The field sort_date is usually mapped as type=basic_date or keyword with the format 'yyyymmdd'. to configure the field to use see the DBM:['short_code_date'] = 'sort_date'

- Otherwise ranges are specified as follows; e.g. &SF2=dewey_num&ST2=700:791 is which is interpreted as ((dewey_num:[700 TO 791])) 

### Like
``````
&LIKE=BDZ0049220007
``````
i.e. simply pass in the document _id (or ref_no). The more_like_this template is configured in the DBM.


### Aggregations
``````
&AGGS=format_code,bic_tree
``````
The aggregations are defined in the DBM. Specify which aggregations you wish to see as a comma separated list of the aggs' names. Default aggregations can be configured in the DBM:'elastic_aggregations_inc' and the triggered to display by adding 'distinct' to the FIELDS parameter: e.g. &FIELDS=default,distinct. With the XML output aggs are displayed in <resultsetinformation> <distinct_fields> <aggs_name>etc. Aggs Filters are also defined in the DBM.



### Search Query Filters
``````
&SQF=/format_code:B/field_2:prefi/3:xyz
``````
The SQF parameter allows you to define a series of search filters. Filters are separated from each other by means of a '/'. A colon ':' is used in place of the '=' assignment operator. The DBM allows you to configure a variety of options:

- 'sqf_filters' = ['null', 'field1', 'field2', etc.] permits you to map field names to a number, so &SQF=/field1:foo is the same as &SQF=/1:foo

- 'prefix_filters' = ['field1', 'field2', etc.] the default filter is configured a 'term' filter. If configured as 'prefix_filters' the filter will set as a 'prefix' filter. 

- ranges are also handled; e.g. &SQF=/sort_date:20220301~20220308 is interpreted as

		"filter": [
                    {
                        "range": {
                            "sort_date": {
                                "gte": "20220301",
                                "lte": "20220308"
                            }
                        }
                    }
                ]


- N.B. '~' maps to 'gte/lte' and '-' maps to 'gt/lt'


### Q Parameter
``````
&Q=terms1 term2
``````
The Q parameter simply passes an unaltered query_string through to the final query. This is most useful if you want to add a 'hidden' filter to every search - simply append a Q param (although it probably easier and better to create an alias with the required filter).

- e.g. &SF1=ctitle&ST1=james&AGGS=&SQF=&Q=AND+contributor:(james+OR+john) will be interpreted as "((ctitle:(james))) AND contributor:(james OR john)"

 
### Search
The general approach is to construct a MUST search using the query_string search syntax. You can always broaden the initial MUST search by setting OR as the default operator, applying FUZZY and adjusting minimum_should_match and slop. 

If you don't specify any fields then a simple multi_match search is run against the default fields (one of which you might consider making into a combined index by copying key fields into it).

Optionally, a SHOULD search is then applied - primarily for the purpose of boosting results according to i) the search terms as they appear in certain key fields ii) dates iii) other vital criteria.

You can then add FILTERs and RESCOREs to further fine-tune the results.


### Rescore
The template for configuring Rescoring is in the DBM. Works well for boosting e.g. by date without having to sort and affect terms relevance.

- 'elastic_rescore_show' => [true|false]


### Highlight
The template for configuring Highlighting is in the DBM. Configure tags, fragment_size etc. in the DBM. The XML output displays highlighted fields as <fv_highlights><field1><![CDATA[...]]> etc.

- 'elastic_highlight_show'		=> true,	
- 'elastic_highlight_separator' => '<span class="highlight-separator"> ... </span>', // overrides the default which is as shown.

N.B. The query needs to contain the fields you want highlighted unless you set require_fields_match = true, so what you can do is add the fields to the SHOULD clause. Just an idea.

### Suggestions
The XMLAdapter makes use of the Elasticsearch Completion Suggester. You will need to create fields of type = completion. Frankly, we've been disappointed with all the various suggesters, this is the best one provided you limit the fields e.g. names & titles and make sure that you index each name/title variation (see the elastic-indexer). The other Suggesters require a different temnplate for the query and return JSON with a different structure so you'd need to extend the class to handle these. Not worth the effort in our view.

- Configure via the DBM
- 'elastic_suggestions_show' => [true|false]
- - override this with the querystring parameter SUGGEST=on|off
- configure 


### FMM - Force Multi-Match
``````
&FMM=on
``````

- N.B. the field 'keyword' is ignored so "&SF1=keyword&ST1=sleeping dogs lie" is interpreted as "(sleeping dogs lie)" and is configured as a multi_match query, whereas "&SF1=cindex&ST1=sleeping dogs lie" is interpreted as "cindex:(sleeping dogs lie)" and is configured as a query_string query.

- FMM=on will force what would otherwise have been a query_string query into a multi_match query. 

### MSM - Minimum Should Match
``````
&MSM=2<60%
``````

- impacts only the primary MUST search
- you will need to escape this as 2%3C60%25

### NQF - No Q Filter
``````
&NQF=Y
``````
- i.e. if you are in the CMS and you want to see every record in the index but you have a Q Filter limiting access to some records, this allows you to reset the Q Filter in the querystring - overriding the DBM default. 

### View
``````
&VIEW=[xml|json]
``````

