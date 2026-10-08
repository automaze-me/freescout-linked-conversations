<?php

namespace Modules\LinkedConversations\Services;

use App\Conversation;

/**
 * Labels the views show for a linked conversation.
 */
class Presenter
{
    public static function statusLabel(Conversation $conversation)
    {
        return $conversation->state == Conversation::STATE_DELETED
            ? __('Deleted')
            : $conversation->getStatusName();
    }

    /**
     * CSS modifier for the status dot: lc-status-{key}.
     */
    public static function statusKey(Conversation $conversation)
    {
        return $conversation->state == Conversation::STATE_DELETED
            ? 'deleted'
            : (string) (int) $conversation->status;
    }

    public static function assigneeName(Conversation $conversation)
    {
        return $conversation->user ? $conversation->user->getFullName() : __('Unassigned');
    }

    public static function customerName(Conversation $conversation)
    {
        return $conversation->customer
            ? $conversation->customer->getFullName(true)
            : (string) $conversation->customer_email;
    }
}
