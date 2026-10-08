<?php

namespace Modules\LinkedConversations\Services;

use App\Conversation;
use App\User;
use Modules\LinkedConversations\Entities\ConversationLink;

/**
 * "New Linked Conversation": core's unchanged new-conversation form plus a hint
 * and a hidden field. The link is created when the conversation is created.
 */
class NewLinked
{
    // Conversation meta key remembering the pending link on a draft.
    const META = 'lc_link_to';

    /**
     * The conversation the form will link to, or null. $conversation is a new
     * (unsaved) conversation or a reopened draft.
     */
    public static function pendingTarget(Conversation $conversation, $user)
    {
        $id = $conversation->id ? (int) $conversation->getMeta(self::META) : 0;
        if (!$id) {
            $id = (int) request()->query(Actions::LINK_TO_PARAM);
        }

        return $id ? self::validTarget($id, $conversation, $user) : null;
    }

    /**
     * conversation.create_form.before_subject
     */
    public static function renderHint(Conversation $conversation)
    {
        $target = self::pendingTarget($conversation, auth()->user());
        if (!$target) {
            return;
        }

        echo view('linkedconversations::create_form_hint', ['target' => $target])->render();
    }

    /**
     * UserCreatedConversationDraft: remember the pending link on the draft.
     */
    public static function rememberOnDraft(Conversation $draft)
    {
        $id = (int) request()->input(Actions::LINK_TO_PARAM);
        if ($id) {
            $draft->setMeta(self::META, $id, true);
        }
    }

    /**
     * conversation.created_by_user_can_undo. The form field wins when present,
     * even empty ("do not link"); otherwise the draft's memory is used.
     */
    public static function linkOnCreate(Conversation $conversation, $thread)
    {
        $request = request();
        $id = $request->has(Actions::LINK_TO_PARAM)
            ? (int) $request->input(Actions::LINK_TO_PARAM)
            : (int) $conversation->getMeta(self::META);

        if ($conversation->getMeta(self::META) !== null) {
            self::clearPending($conversation);
        }
        if (!$id) {
            return null;
        }

        $user = auth()->user() ?: User::find($thread ? $thread->created_by_user_id : null);
        $target = self::validTarget($id, $conversation, $user);
        if (!$target) {
            \Log::warning('[LinkedConversations] Not linking new conversation #'.$conversation->number
                .' to conversation id '.$id.': missing, draft or not viewable.');

            return null;
        }

        return ConversationLink::link($target->id, $conversation->id, ConversationLink::SOURCE_CREATED, $user->id);
    }

    /**
     * conversation.created_by_user, fired by core after the undo timeout.
     */
    public static function announce(Conversation $conversation)
    {
        Timeline::announceCreated($conversation);
    }

    public static function clearPending(Conversation $conversation)
    {
        $meta = $conversation->meta ?: [];
        unset($meta[self::META]);
        $conversation->meta = $meta;
        $conversation->save();
    }

    protected static function validTarget($id, Conversation $conversation, $user)
    {
        $target = Conversation::find((int) $id);
        if (!$target || !$user || $target->isDraft() || $target->id == $conversation->id || !$user->can('view', $target)) {
            return null;
        }

        return $target;
    }
}
