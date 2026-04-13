<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Match book (PDF / HTML programme builder)
    |--------------------------------------------------------------------------
    |
    | When false, match book routes and related placement UI are disabled.
    | Set MATCHBOOK_ENABLED=true in .env when the feature is ready again.
    |
    */
    'matchbook_enabled' => (bool) env('MATCHBOOK_ENABLED', false),

];
