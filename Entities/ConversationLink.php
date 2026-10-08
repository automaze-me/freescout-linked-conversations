<?php

namespace Modules\LinkedConversations\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

/**
 * One row per linked pair of conversations. The smaller conversation id is
 * always stored in conversation_id, so every pair exists at most once.
 */
class ConversationLink extends Model
{
    const SOURCE_MANUAL = 'manual';
    const SOURCE_CREATED = 'created';
    const SOURCE_FORWARD = 'forward';
    const SOURCE_IMPORT = 'import';

    protected $table = 'conversation_links';

    public $timestamps = false;

    protected $dates = ['created_at'];

    /**
     * Link two conversations in any order. Returns the new row
     * (wasRecentlyCreated = true), the row that already links this pair, or
     * null for a self-link.
     */
    public static function link($a, $b, $source, $user_id = null, $created_at = null)
    {
        list($low, $high) = self::order($a, $b);
        if (!$low || $low === $high) {
            return null;
        }

        $existing = self::pair($low, $high);
        if ($existing) {
            return $existing;
        }

        $link = new self();
        $link->conversation_id = $low;
        $link->linked_conversation_id = $high;
        $link->source = $source;
        $link->created_by_user_id = $user_id ?: null;
        $link->created_at = $created_at ?: now();

        try {
            $link->save();
        } catch (QueryException $e) {
            // Another request linked the same pair in between (unique index).
            $existing = self::pair($low, $high);
            if ($existing) {
                return $existing;
            }
            throw $e;
        }

        return $link;
    }

    public static function pair($a, $b)
    {
        list($low, $high) = self::order($a, $b);

        return self::where('conversation_id', $low)->where('linked_conversation_id', $high)->first();
    }

    public static function unlink($a, $b)
    {
        list($low, $high) = self::order($a, $b);

        return self::where('conversation_id', $low)->where('linked_conversation_id', $high)->delete() > 0;
    }

    /**
     * Ids of every conversation linked to $conversation_id, from both sides.
     *
     * @return int[]
     */
    public static function linkedIds($conversation_id)
    {
        $id = (int) $conversation_id;

        return self::where('conversation_id', $id)
            ->orWhere('linked_conversation_id', $id)
            ->get()
            ->map(function ($link) use ($id) {
                return $link->otherId($id);
            })
            ->values()
            ->all();
    }

    /**
     * Move every link of $from_id to $to_id (merge). A link between the two
     * disappears; a pair $to_id already has keeps its existing row.
     */
    public static function repoint($from_id, $to_id)
    {
        $from_id = (int) $from_id;
        $to_id = (int) $to_id;

        $links = self::where('conversation_id', $from_id)->orWhere('linked_conversation_id', $from_id)->get();
        foreach ($links as $link) {
            $other_id = $link->otherId($from_id);
            $link->delete();
            if ($other_id !== $to_id) {
                self::link($to_id, $other_id, $link->source, $link->created_by_user_id, $link->created_at);
            }
        }
    }

    public static function deleteForConversations(array $conversation_ids)
    {
        $ids = array_values(array_filter(array_map('intval', $conversation_ids)));
        if (!$ids) {
            return;
        }

        self::whereIn('conversation_id', $ids)->orWhereIn('linked_conversation_id', $ids)->delete();
    }

    public function otherId($conversation_id)
    {
        return (int) $this->conversation_id === (int) $conversation_id
            ? (int) $this->linked_conversation_id
            : (int) $this->conversation_id;
    }

    protected static function order($a, $b)
    {
        $a = (int) $a;
        $b = (int) $b;

        return $a <= $b ? [$a, $b] : [$b, $a];
    }
}
