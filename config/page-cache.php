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

];
