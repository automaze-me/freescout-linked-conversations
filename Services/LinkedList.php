<?php

namespace Modules\LinkedConversations\Services;

use App\Conversation;
use Modules\LinkedConversations\Entities\ConversationLink;

class LinkedList
{
    /**
     * Linked conversations of $conversation as $user may see them. Drafts are
     * never listed; conversations the user can not view are only counted.
     *
     * @return array ['visible' => Conversation[], 'hidden_count' => int]
     */
    public static function forConversation(Conversation $conversation, $user)
    {
        $ids = ConversationLink::linkedIds($conversation->id);
        if (!$ids) {
            return ['visible' => [], 'hidden_count' => 0];
        }

        $linked = Conversation::whereIn('id', $ids)
            ->where('state', '!=', Conversation::STATE_DRAFT)
            ->with(['user', 'mailbox'])
            ->get();

        $visible = [];
        $hidden_count = 0;
        foreach ($linked as $other) {
            if ($user->can('view', $other)) {
                $visible[] = $other;
            } else {
                $hidden_count++;
            }
        }

        usort($visible, function ($a, $b) {
            $group = self::group($a) - self::group($b);

            return $group !== 0 ? $group : (int) $b->number - (int) $a->number;
        });

        return ['visible' => $visible, 'hidden_count' => $hidden_count];
    }

    /**
     * 0 for active/pending conversations, 1 for everything else.
     */
    public static function group(Conversation $conversation)
    {
        $open = $conversation->state != Conversation::STATE_DELETED
            && ($conversation->isActive() || $conversation->isPending());

        return $open ? 0 : 1;
    }
}
