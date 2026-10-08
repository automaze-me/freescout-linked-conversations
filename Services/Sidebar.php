<?php

namespace Modules\LinkedConversations\Services;

/**
 * The "Linked Conversations" box above "Previous Conversations".
 */
class Sidebar
{
    public static function render($conversation)
    {
        $user = auth()->user();
        if (!$user || !$conversation || !$conversation->id || $conversation->isDraft()) {
            return;
        }

        $list = LinkedList::forConversation($conversation, $user);
        if (!$list['visible'] && !$list['hidden_count']) {
            return;
        }

        echo view('linkedconversations::sidebar', [
            'conversation' => $conversation,
            'visible'      => $list['visible'],
            'hidden_count' => $list['hidden_count'],
            'new_target'   => Actions::newConversationTarget($conversation, $user),
        ])->render();
    }
}
