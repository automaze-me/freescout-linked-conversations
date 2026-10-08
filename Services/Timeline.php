<?php

namespace Modules\LinkedConversations\Services;

use App\Conversation;
use App\Thread;
use Modules\LinkedConversations\Entities\ConversationLink;

/**
 * Timeline line items for linking, unlinking and creating linked
 * conversations. Their text is built when rendered (thread.action_text), so it
 * follows the viewer's language.
 */
class Timeline
{
    // threads.action_type is an unsigned tinyint; the Workflows module uses 201/202.
    const ACTION_TYPE = 170;
    const META_KEY = 'linkedconversations';

    const EVENT_LINKED = 'linked';
    const EVENT_UNLINKED = 'unlinked';
    const EVENT_CREATED_LINKED = 'created_linked';
    const EVENT_CREATED_FROM = 'created_from';

    public static function record($event, Conversation $conversation, Conversation $other, $user_id)
    {
        return Thread::create($conversation, Thread::TYPE_LINEITEM, '', [
            'user_id'            => $conversation->user_id,
            'created_by_user_id' => $user_id,
            'action_type'        => self::ACTION_TYPE,
            'source_via'         => Thread::PERSON_USER,
            'source_type'        => Thread::SOURCE_TYPE_WEB,
            'meta'               => [
                self::META_KEY => [
                    'event'           => $event,
                    'conversation_id' => (int) $other->id,
                    // Kept so the text survives deletion of the other conversation.
                    'number'          => (int) $other->number,
                ],
            ],
        ]);
    }

    public static function recordPair($event, Conversation $a, Conversation $b, $user_id)
    {
        self::record($event, $a, $b, $user_id);
        self::record($event, $b, $a, $user_id);
    }

    /**
     * "Created linked conversation" entries for a new conversation. Called from
     * the delayed conversation.created_by_user action: an undone conversation
     * is a draft by then and gets nothing; a re-sent one gets them once.
     */
    public static function announceCreated(Conversation $new)
    {
        $new = Conversation::find($new->id);
        if (!$new || !$new->isPublished()) {
            return;
        }

        // The new conversation always has the larger id of a "created" pair.
        $links = ConversationLink::where('linked_conversation_id', $new->id)
            ->where('source', ConversationLink::SOURCE_CREATED)
            ->get();
        foreach ($links as $link) {
            $original = Conversation::find($link->conversation_id);
            if (!$original || self::hasEntry($new, self::EVENT_CREATED_FROM, $original->id)) {
                continue;
            }
            self::record(self::EVENT_CREATED_FROM, $new, $original, $link->created_by_user_id);
            self::record(self::EVENT_CREATED_LINKED, $original, $new, $link->created_by_user_id);
        }
    }

    public static function hasEntry(Conversation $conversation, $event, $other_id)
    {
        return Thread::where('conversation_id', $conversation->id)
            ->where('type', Thread::TYPE_LINEITEM)
            ->where('action_type', self::ACTION_TYPE)
            ->get()
            ->contains(function ($thread) use ($event, $other_id) {
                $meta = $thread->getMeta(self::META_KEY);

                return is_array($meta)
                    && ($meta['event'] ?? '') === $event
                    && (int) ($meta['conversation_id'] ?? 0) === (int) $other_id;
            });
    }

    /**
     * thread.action_text filter. Core replaces :person afterwards.
     */
    public static function actionText($did_this, $thread)
    {
        if ($thread->type != Thread::TYPE_LINEITEM || (int) $thread->action_type !== self::ACTION_TYPE) {
            return $did_this;
        }
        $meta = $thread->getMeta(self::META_KEY);
        if (!is_array($meta) || empty($meta['event'])) {
            return $did_this;
        }

        $number = self::numberHtml($meta);
        switch ($meta['event']) {
            case self::EVENT_LINKED:
                return __(':person linked this conversation with :number', ['number' => $number]);
            case self::EVENT_UNLINKED:
                return __(':person unlinked conversation :number', ['number' => $number]);
            case self::EVENT_CREATED_LINKED:
                return __(':person created linked conversation :number', ['number' => $number]);
            case self::EVENT_CREATED_FROM:
                return __(':person created this conversation from :number', ['number' => $number]);
        }

        return $did_this;
    }

    protected static function numberHtml(array $meta)
    {
        $text = '#'.(int) ($meta['number'] ?? 0);
        $conversation = Conversation::find((int) ($meta['conversation_id'] ?? 0));

        return $conversation ? '<a href="'.e($conversation->url()).'">'.$text.'</a>' : $text;
    }
}
