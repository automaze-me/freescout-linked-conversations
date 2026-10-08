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
        // Forwards. A manual forward fires *_can_undo at once and repeats as
        // conversation.user_forwarded after the undo timeout; Conversation::forward()
        // (used by the Workflows module) fires only conversation.user_forwarded.
        // link() is idempotent, so the repeat adds nothing.
        \Eventy::addAction('conversation.user_forwarded_can_undo', function ($conversation, $thread, $forwarded_conversation, $forwarded_thread) {
            \Modules\LinkedConversations\Services\Lifecycle::forwarded($conversation, $thread, $forwarded_conversation);
        }, 20, 4);
        \Eventy::addAction('conversation.user_forwarded', function ($conversation, $thread, $forwarded_conversation, $forwarded_thread) {
            \Modules\LinkedConversations\Services\Lifecycle::forwarded($conversation, $thread, $forwarded_conversation);
        }, 20, 4);

        // Merge: the merged-away conversation goes to trash; its links move over.
        \Eventy::addAction('conversation.merged', function ($conversation, $second_conversation, $user) {
            \Modules\LinkedConversations\Services\Lifecycle::merged($conversation, $second_conversation);
        }, 20, 3);

        // Hard deletes: single model deletes (incl. core's undo of a forward) and
        // the bulk "delete forever" path, which bypasses Eloquent events.
        \Eventy::addAction('conversation.deleting', function ($conversation) {
            \Modules\LinkedConversations\Services\Lifecycle::deleted([$conversation->id]);
        }, 20, 1);
        \Eventy::addAction('conversations.before_delete_forever', function ($conversation_ids) {
            \Modules\LinkedConversations\Services\Lifecycle::deleted((array) $conversation_ids);
        }, 20, 1);
    }

    public function provides()
    {
        return [];
    }
}
