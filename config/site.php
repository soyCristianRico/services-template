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

    /*
    |--------------------------------------------------------------------------
    | Menu dynamic blocks
    |--------------------------------------------------------------------------
    |
    | A menu item of type «dynamic block» does not carry a URL: it names a block
    | that the layout knows how to paint (a mega-dropdown listing the catalog,
    | for instance). Which blocks exist is a property of THIS site's views, so
    | the list lives here and the admin offers exactly these — otherwise the
    | editor would have to type an identifier that only the templates know
    | about, and a typo would render an empty dropdown with nothing to explain
    | it.
    |
    | Key = the `source` stored on the item. Value = what the admin shows.
    |
    | Empty by default: the template's public layout renders plain links only,
    | so the admin hides the «dynamic block» type until a site declares one.
    |
    */

    'menu_blocks' => [],

];
