<?php

/*
|--------------------------------------------------------------------------
| Settings for the static page cache
|--------------------------------------------------------------------------
*/

return [
    /*
    |--------------------------------------------------------------------------
    | The default expire time for page type
    |--------------------------------------------------------------------------
    |
    | Set the default expire time in minutes per page type
    | Page types are: page | plp | pdp
    */
    'ignore_query_strings' => false,
    'max_filename_length' => 255,
    'expire_time' => [
        'page' => env('STATIC_PAGE_CACHE_EXPIRE_TIME_PAGE', 15),
        'plp' => env('STATIC_PAGE_CACHE_EXPIRE_TIME_PLP', 15),
        'pdp' => env('STATIC_PAGE_CACHE_EXPIRE_TIME_PDP', 2),
    ],
    /*
     * The directory in the public folder where cache files are stored
     * Make sure you use this in the nginx try_files config
     */
    'cache-path' => 'static-pagecache',

    /*
     * Does this site support statamic mutlisite.
     * Site config is pulled from the statamic site config
     * If true make sure to reference the {uri} in the try files
     */
    'multisite' => true,

    /*
     * Set the whitelist of parameters you would like
     *
        Add this the nginx config

      #calulate the value for the page parameter: page=1
       map $query_string $cache_param_page {
           "~*(page=\d+)" $1;
           default "";
       }

       #calulate the value for the sub_type parameter: sub_type=Headwear
       map $query_string $cache_param_subtype {
           "~*(sub_type=[a-z0-9]+(?:(?:-|_)+[a-z0-9]+)*)" $1;
           default "";
       }

       #combine all cache params
       map $query_string $temp_cache_query_string {
           default "$cache_param_page&$cache_param_subtype";
       }

       #strip out traliing & to get complete querystring
       map $temp_cache_query_string $cache_query_string {
           "~*([^&].*.[^&])" $1;
           default "";
       }


       location / {
           try_files /static-pagecache{uri}_${cache_query_string}.html $uri /index.php?$query_string;
       }

    */
    'query_strings_params_whitelist' => [
        'page',
        'sub_type',
    ],

];
