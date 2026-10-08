<?php

namespace Modules\LinkedConversations\Services;

use App\Conversation;
use App\Thread;
use Modules\LinkedConversations\Entities\ConversationLink;

/**
 * Links every conversation to the conversations it was forwarded into before
 * the module was installed. Runs once from a migration.
 */
class ForwardImporter
{
    /**
     * @return array ['linked' => int, 'skipped' => int, 'malformed' => int]
     */
    public static function run()
    {
        $stats = ['linked' => 0, 'skipped' => 0, 'malformed' => 0];

        // Core writes "fw_cci"; older installs still hold rows written under the
        // previous name. Same lookup order as Thread::getMetaFw().
        $key = Thread::META_FORWARD_CHILD_CONVERSATION_ID;
        $legacy_key = Thread::$meta_fw_backward_compat[$key];

        \DB::table('threads')
            ->select('id', 'conversation_id', 'created_by_user_id', 'created_at', 'meta')
            ->where(function ($query) use ($key, $legacy_key) {
                $query->where('meta', 'like', '%"'.$key.'"%')
                    ->orWhere('meta', 'like', '%'.$legacy_key.'%');
            })
            ->chunkById(500, function ($threads) use (&$stats, $key, $legacy_key) {
                foreach ($threads as $thread) {
                    $meta = json_decode($thread->meta, true);
                    $child_id = is_array($meta) ? (int) ($meta[$key] ?? 0) : 0;
                    if (!$child_id && is_array($meta)) {
                        $child_id = (int) ($meta[$legacy_key] ?? 0);
                    }
                    if (!$child_id) {
                        $stats['malformed']++;
                        continue;
                    }
                    if (!Conversation::where('id', $thread->conversation_id)->exists()
                        || !Conversation::where('id', $child_id)->exists()
                    ) {
                        $stats['skipped']++;
                        continue;
                    }
                    $link = ConversationLink::link(
                        $thread->conversation_id,
                        $child_id,
                        ConversationLink::SOURCE_IMPORT,
                        $thread->created_by_user_id,
                        $thread->created_at
                    );
                    if ($link && $link->wasRecentlyCreated) {
                        $stats['linked']++;
                    } else {
                        $stats['skipped']++;
                    }
                }
            }, 'id');

        if ($stats['malformed']) {
            \Log::warning('[LinkedConversations] Forward import skipped '.$stats['malformed'].' thread(s) with unreadable forward meta.');
        }

        return $stats;
    }
}
