<?php

// URLs reach the module JS through data attributes rendered server-side, so
// these routes are not exported to laroute.
Route::group([
    'middleware' => ['web', 'auth', 'roles'],
    'roles'      => ['user', 'admin'],
    'prefix'     => \Helper::getSubdirectory(),
    'namespace'  => 'Modules\LinkedConversations\Http\Controllers',
], function () {
    Route::post('/linked-conversations/ajax', 'LinkedConversationsController@ajax')->name('linkedconversations.ajax');
});
