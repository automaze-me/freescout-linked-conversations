<?php

namespace Modules\LinkedConversations\Services;

use Modules\LinkedConversations\Entities\ConversationLink;

/**
 * Chain icon in front of the subject in conversation lists. Link data for the
 * whole page is loaded once in conversations_table.preload_table_data.
 */
class ListIcons
{
    protected static $map = [];

    // user id => mailbox ids the user can view
    protected static $mailbox_ids = [];

    public static function reset()
    {
        self::$map = [];
        self::$mailbox_ids = [];
    }

    public static function preload($conversations)
    {
        $ids = [];
        foreach ($conversations as $conversation) {
            $ids[] = $conversation->id;
        }
        self::$map = ConversationLink::linkedMap($ids);

        return $conversations;
    }

    /**
     * The tooltip names only conversations in mailboxes the user can view and
     * exposes nothing but their numbers; the sidebar applies the full policy.
     */
    public static function render($conversation, $user)
    {
        $rows = isset(self::$map[$conversation->id]) ? self::$map[$conversation->id] : [];
        if (!$rows || !$user) {
            return;
        }

        $allowed = self::mailboxIds($user);
        $numbers = [];
        $hidden = 0;
        foreach ($rows as $row) {
            if (in_array((int) $row->mailbox_id, $allowed, true)) {
                $numbers[] = '#'.$row->number;
            } else {
                $hidden++;
            }
        }

        $parts = [];
        if ($numbers) {
            $parts[] = __('Linked: :numbers', ['numbers' => implode(', ', $numbers)]);
        }
        if ($hidden) {
            $parts[] = __('+:count in other mailboxes', ['count' => $hidden]);
        }

        echo '<i class="glyphicon glyphicon-link lc-list-icon" data-toggle="tooltip" title="'.e(implode(' ', $parts)).'"></i>';
    }

    protected static function mailboxIds($user)
    {
        if (!isset(self::$mailbox_ids[$user->id])) {
            self::$mailbox_ids[$user->id] = array_map('intval', $user->mailboxesIdsCanView());
        }

        return self::$mailbox_ids[$user->id];
    }
}
