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

        // Timeline text for the module's line items (action type 170).
        \Eventy::addFilter('thread.action_text', function ($did_this, $thread) {
            return \Modules\LinkedConversations\Services\Timeline::actionText($did_this, $thread);
        }, 20, 2);

        // "More Actions" menu: Link Conversation… / New Linked Conversation…
        \Eventy::addFilter('conversation.get_action_buttons', function ($actions, $conversation, $user, $mailbox) {
            return \Modules\LinkedConversations\Services\Actions::addButtons($actions, $conversation, $user, $mailbox);
        }, 20, 4);

        \Eventy::addFilter('javascripts', function ($javascripts) {
            $javascripts[] = \Module::getPublicPath(LINKED_CONVERSATIONS_MODULE).'/js/module.js';

            return $javascripts;
        }, 20, 1);
        \Eventy::addFilter('stylesheets', function ($stylesheets) {
            $stylesheets[] = \Module::getPublicPath(LINKED_CONVERSATIONS_MODULE).'/css/module.css';

            return $stylesheets;
        }, 20, 1);

        // Sidebar box above "Previous Conversations" (only rendered when the
        // conversation has a customer, like the rest of core's customer sidebar).
        \Eventy::addAction('conversation.before_prev_convs', function ($customer, $conversation, $mailbox) {
            \Modules\LinkedConversations\Services\Sidebar::render($conversation);
        }, 20, 3);

        // New linked conversation: hint in core's create form, memory on the
        // draft, link on creation, timeline entries after the undo timeout.
        \Eventy::addAction('conversation.create_form.before_subject', function ($conversation, $mailbox, $thread) {
            \Modules\LinkedConversations\Services\NewLinked::renderHint($conversation);
        }, 20, 3);
        \Event::listen(\App\Events\UserCreatedConversationDraft::class, function ($event) {
            \Modules\LinkedConversations\Services\NewLinked::rememberOnDraft($event->conversation);
        });
        \Eventy::addAction('conversation.created_by_user_can_undo', function ($conversation, $thread) {
            \Modules\LinkedConversations\Services\NewLinked::linkOnCreate($conversation, $thread);
        }, 20, 2);
        \Eventy::addAction('conversation.created_by_user', function ($conversation, $thread) {
            \Modules\LinkedConversations\Services\NewLinked::announce($conversation);
        }, 20, 2);
    }

    public function provides()
    {
        return [];
    }
}
