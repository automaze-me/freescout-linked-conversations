<?php

namespace Modules\LinkedConversations\Providers;

use Illuminate\Support\ServiceProvider;

define('LINKED_CONVERSATIONS_MODULE', 'linkedconversations');

class LinkedConversationsServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    public function boot()
    {
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'linkedconversations');
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->hooks();
    }

    public function register()
    {
        $this->loadJsonTranslationsFrom(__DIR__.'/../Resources/lang');
    }

    /**
     * Core hooks. Later tasks append one block per feature, each naming the
     * core hook it relies on.
     */
    public function hooks()
    {
    }

    public function provides()
    {
        return [];
    }
}
