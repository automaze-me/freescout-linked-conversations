<?php

namespace Modules\LinkedConversations\Services;

use App\Conversation;
use App\User;
use Modules\LinkedConversations\Entities\ConversationLink;

class LinkSearch
{
    /**
     * Find a conversation the way core's merge search does: by "number" when
     * custom conversation numbers are enabled, by id otherwise.
     */
    public static function findByNumber($number)
    {
        $number = (int) ltrim(trim((string) $number), '#');
        if ($number <= 0) {
            return null;
        }

        return Conversation::where(Conversation::numberFieldName(), $number)->first();
    }

    /**
     * Why $other can not be linked to $conversation by $user, or null. A
     * conversation the user can not view is "not found", like in core.
     */
    public static function validate(Conversation $conversation, $other, User $user)
    {
        if (!$other || !$user->can('view', $other)) {
            return __('Conversation not found');
        }
        if ($other->id == $conversation->id) {
            return __('This is the current conversation');
        }
        if ($other->isDraft() || $conversation->isDraft()) {
            return __('Drafts can not be linked');
        }
        if (ConversationLink::pair($conversation->id, $other->id)) {
            return __('Already linked');
        }

        return null;
    }

    /**
     * Up to $limit other published conversations of the same customer that the
     * user can view and that are not linked yet, newest first.
     */
    public static function candidates(Conversation $conversation, User $user, $limit = 10)
    {
        if (!$conversation->customer_id) {
            return collect();
        }
        $exclude = array_merge([$conversation->id], ConversationLink::linkedIds($conversation->id));

        return Conversation::where('customer_id', $conversation->customer_id)
            ->where('state', Conversation::STATE_PUBLISHED)
            ->whereNotIn('id', $exclude)
            ->orderBy('id', 'desc')
            ->limit($limit * 3)
            ->get()
            ->filter(function ($candidate) use ($user) {
                return $user->can('view', $candidate);
            })
            ->take($limit)
            ->values();
    }
}
