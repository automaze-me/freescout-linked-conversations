<?php

namespace Modules\LinkedConversations\Services;

use App\Conversation;
use Modules\LinkedConversations\Entities\ConversationLink;

/**
 * Keeps links in step with what core does to conversations.
 */
class Lifecycle
{
    /**
     * Core forwarded $parent into the new $child conversation. Both are
     * re-read: the delayed repeat of a manual forward can arrive after the
     * forward was undone and the child deleted.
     */
    public static function forwarded($parent, $parent_thread, $child)
    {
        if (!$parent || !$child) {
            return null;
        }
        $parent = Conversation::find($parent->id);
        $child = Conversation::find($child->id);
        if (!$parent || !$child || $parent->isDraft() || $child->isDraft()) {
            return null;
        }
        $user_id = $parent_thread ? $parent_thread->created_by_user_id : null;

        return ConversationLink::link($parent->id, $child->id, ConversationLink::SOURCE_FORWARD, $user_id);
    }

    public static function merged($remaining, $removed)
    {
        ConversationLink::repoint($removed->id, $remaining->id);
    }

    public static function deleted(array $conversation_ids)
    {
        ConversationLink::deleteForConversations($conversation_ids);
    }
}
