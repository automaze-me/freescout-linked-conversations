<?php

/*
| Loads the module routes when the module starts.
*/

if (!app()->routesAreCached()) {
    require __DIR__.'/Http/routes.php';
}
