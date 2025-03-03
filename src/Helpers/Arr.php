<?php
    namespace MattyLabs\XMLAdapter\Helpers;

    use ArrayAccess;
    use RecursiveArrayIterator;
    use RecursiveIteratorIterator;

    /**
     *  Simple set of Array handling functions
     */
    class Arr{

        /**
         * Determine whether the given value is array accessible.
         *
         * @param  mixed  $value
         * @return bool
         */
        public static function accessible($value)
        {
            return is_array($value) || $value instanceof ArrayAccess;
        }

        /**
         * Add an element to an array using "dot" notation if it doesn't exist.
         *
         * @param  array   $array
         * @param  string  $key
         * @param  mixed   $value
         * @return array
         */
        public static function add($array, $key, $value)
        {
            if (is_null(static::get($array, $key))) {
                static::set($array, $key, $value);
            }

            return $array;
        }

        /**
         * Set an array item to a given value using "dot" notation.
         *
         * If no key is given to the method, the entire array will be replaced.
         *
         * @param  array   $array
         * @param  string  $key
         * @param  mixed   $value
         * @return array
         */
        public static function set(&$array, $key, $value)
        {
            if (is_null($key)) {
                return $array = $value;
            }

            $keys = explode('.', $key);

            while (count($keys) > 1) {
                $key = array_shift($keys);

                // If the key doesn't exist at this depth, we will just create an empty array
                // to hold the next value, allowing us to create the arrays to hold final
                // values at the correct depth. Then we'll keep digging into the array.
                if (! isset($array[$key]) || ! is_array($array[$key])) {
                    $array[$key] = [];
                }

                $array = &$array[$key];
            }

            $array[array_shift($keys)] = $value;

            return $array;
        }

        /**
         * Get an item from an array using "dot" notation.
         *
         * @param ArrayAccess|array  $array
         * @param  string  $key
         * @param  mixed   $default
         * @return mixed
         */
        private static function get($array, $key, $default = null)
        {
            if (! static::accessible($array)) {
                return $default;
            }

            if (is_null($key)) {
                return $default;
            }

            if (static::exists($array, $key)) {
                return $array[$key];
            }

            foreach (explode('.', $key) as $segment) {
                if (static::accessible($array) && static::exists($array, $segment)) {
                    $array = $array[$segment];
                } else {
                    return $default;
                }
            }

            return $array;
        }

        /**
         * Determine if the given key exists in the provided array.
         *
         * @param ArrayAccess|array  $array
         * @param  string|int  $key
         * @return bool
         */
        public static function exists($array, $key):bool
        {
            if ($array instanceof ArrayAccess) {

                return $array->offsetExists($key);
            }

            return array_key_exists($key, $array);
        }

        /**
         * Flatten a multi-dimensional associative array with dots.
         *
         * @param  array   $array
         * @param  string  $prepend
         * @return array
         */
        public static function dot($array, $prepend = '')
        {
            $results = [];

            foreach ($array as $key => $value) {
                if (is_array($value) && ! empty($value)) {
                    $results = array_merge($results, static::dot($value, $prepend.$key.'.'));
                } else {
                    $results[$prepend.$key] = $value;
                }
            }

            return $results;
        }

        /**
         *  find an array value without trying too hard
         *   e.g. using dot notation: 	Arr::val('client.post.error')
         *   e.g. searching by key:		Arr::val('error')
         *
         *
         * @param $array
         * @param $key
         * @return array|mixed|string
         */
        public static function val($array, $key){

            $ret = '';

            if(!empty($array)){
                if(strpos($key, '.') > 0){
                    $ret = static::get($array, $key);
                } else {
                    $ret = static::search($array, $key)['value'];
                }
            }

            if( is_array($ret) ){
                if( isset($ret['@cdata']) ){
                    $ret = $ret['@cdata'];
                }

            }

            return $ret;

        }

        /**
         * create a recursive iterator to loop over the array and find the key you are looking for
         * returns both the path (in dot notation) and the key's value. e.g.
         * $path = arr_search($arr, $var)['path'];
         * $val  = arr_search($arr, $var)['value'];
         *
         * @param $array
         * @param $searchKey
         * @return array|string[]
         */
        public static function search($array, $searchKey){

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
            //return empty if not found. PHP 7.4 this shorthand not allowed. Return empty arrays!
            return array('path'=>'', 'value'=>'');

        }


        /**
         * returns an array of values for all matching keys in array
         *
         * @param $array
         * @param $searchKey
         * @return array
         */
        public static function searchKeys($array, $searchKey){

            $iter = new RecursiveIteratorIterator(new RecursiveArrayIterator($array),RecursiveIteratorIterator::SELF_FIRST);
            $outputArray = array();

            foreach ($iter as $key => $value) {

                if ($key === $searchKey) {

                    $outputArray[] = $value;

                }

            }

            return $outputArray;

        }

        /**
         *  removes any keys with empty values
         *
         * @param $array
         * @return array|void
         */
        public static function filterBlanks($array){

            if(is_array($array) ){

                foreach ($array as $key => &$value) {
                    if (empty($value) && !is_numeric($value)) {
                        // N.B. Zero & false are treated as empty()
                        unset($array[$key]);

                    } else {

                        if (is_array($value)) {
                            $value = static::filterBlanks($value);
                            if (empty($value) && !is_numeric($value)) {
                                unset($array[$key]);
                            }
                        }
                    }
                }

                return $array;

            }

        }

        /**
         *  replaceKeys()
         * @param $oldKey
         * @param $newKey
         * @param array $input
         * @return array
         */
        public static function replaceKeys($oldKey, $newKey, array $input){

            $return = array();
            foreach ($input as $key => $value) {

                if ($key===$oldKey){
                    $key = $newKey;
                }

                if (is_array($value)){
                    $value = static::replaceKeys( $oldKey, $newKey, $value);
                }

                $return[$key] = $value;
            }
            return $return;


        }

        /**
         * @param array $array
         * @param $key
         */
        public static function del(array &$array, $key){

            $parts = explode('.', $key);
            while( count($parts) > 1 ){
                $p = array_shift($parts);
                if(isset($array[$p]) and is_array($array[$p])){
                    $array = &$array[$p];
                }
            }

            unset($array[array_shift($parts)]);

        }

        /**
         * Flatten a multi-dimensional associative array with dots.
         *
         * @param  iterable  $array
         * @param  string  $prepend
         *
         * @return array
         */
        public static function array_dot(iterable $array, string $prepend = ''): array
        {
            $results = [];

            foreach ($array as $key => $value) {
                if (is_array($value) && !empty($value)) {
                    $results = array_merge($results, self::array_dot($value, $prepend.$key.'.'));
                } else {
                    $results[$prepend.$key] = $value;
                }
            }

            return $results;
        }

        /**
         * Return simple array with values re-ordered per reference array 
         *
         * @param  array  $arr
         * @param  array  $order_by
         *
         * @return array
         */
        public static function reOrder($arr, $order_by)
        {
            $arr_ordered = array();
            foreach ($order_by as $key) {
			
                if(in_array($key, $arr)){
                    $arr_ordered[] = $key;
                }
                
            }
            
            if(empty($arr_orderedarr)){
                // ?Better unsorted than ruined?
                return $arr;
            }else{
                return $arr_ordered;
            }
            
        }

    }