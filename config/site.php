<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Geographic dimension
    |--------------------------------------------------------------------------
    |
    | The template ships with programmatic local SEO: a tree of locations and
    | one landing per category × location, served from the `/{slug}` route.
    | That is the majority case, so it defaults to enabled.
    |
    | Some sites have no geographic dimension at all — a catalog of offerings
    | where each offering is itself the page. Turning this off hides Locations
    | and Landings from the admin, drops the `/{slug}` landing route and stops
    | registering their MCP tools, WITHOUT removing any code: the tables, models
    | and views stay identical to the template, so `git cherry-pick` from the
    | template keeps working and the dimension can be switched back on later.
    |
    | `/services-map-source` derives the right value from the source site's URL
    | pattern (A and B need locations, C does not) and `/services-scaffold-structure`
    | writes it, so this is not a question anyone has to answer by hand.
    |
    */

    'locations' => env('SITE_LOCATIONS', true),

];
