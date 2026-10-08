<?php

namespace Modules\LinkedConversations\Http\Controllers;

use App\Conversation;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\LinkedConversations\Entities\ConversationLink;
use Modules\LinkedConversations\Services\LinkSearch;
use Modules\LinkedConversations\Services\Timeline;

class LinkedConversationsController extends Controller
{
    public function ajax(Request $request)
    {
        $user = auth()->user();
        $conversation = Conversation::find((int) $request->input('conversation_id'));
        if (!$conversation || !$user->can('view', $conversation)) {
            return $this->error(__('Conversation not found'));
        }

        switch ($request->input('action')) {
            case 'search':
                $other = LinkSearch::findByNumber($request->input('number'));
                $error = LinkSearch::validate($conversation, $other, $user);
                if ($error) {
                    return $this->error($error);
                }

                return response()->json([
                    'status' => 'success',
                    'html'   => view('linkedconversations::partials/search_result', ['conversation' => $other])->render(),
                ]);

            case 'link':
                $other = Conversation::find((int) $request->input('linked_id'));
                $error = LinkSearch::validate($conversation, $other, $user);
                if ($error) {
                    return $this->error($error);
                }
                $link = ConversationLink::link($conversation->id, $other->id, ConversationLink::SOURCE_MANUAL, $user->id);
                if ($link && $link->wasRecentlyCreated) {
                    Timeline::recordPair(Timeline::EVENT_LINKED, $conversation, $other, $user->id);
                }

                return response()->json(['status' => 'success']);

            case 'unlink':
                $other = Conversation::find((int) $request->input('linked_id'));
                if (!$other || !$user->can('view', $other)) {
                    return $this->error(__('Conversation not found'));
                }
                if (ConversationLink::unlink($conversation->id, $other->id)) {
                    Timeline::recordPair(Timeline::EVENT_UNLINKED, $conversation, $other, $user->id);
                }

                return response()->json(['status' => 'success']);

            case 'clear_pending':
                if ($conversation->isDraft()) {
                    \Modules\LinkedConversations\Services\NewLinked::clearPending($conversation);
                }

                return response()->json(['status' => 'success']);
        }

        return $this->error(__('Unknown action'));
    }

    public function ajaxHtml(Request $request, $action)
    {
        $user = auth()->user();
        $conversation = Conversation::find((int) $request->input('conversation_id'));
        if (!$conversation || !$user->can('view', $conversation)) {
            abort(404);
        }

        switch ($action) {
            case 'link_dialog':
                return view('linkedconversations::ajax_html/link_dialog', [
                    'conversation' => $conversation,
                    'candidates'   => LinkSearch::candidates($conversation, $user),
                ]);

            case 'new_dialog':
                return view('linkedconversations::ajax_html/new_dialog', [
                    'conversation' => $conversation,
                    'mailboxes'    => $user->mailboxesCanView(true),
                ]);
        }

        abort(404);
    }

    protected function error($msg)
    {
        return response()->json(['status' => 'error', 'msg' => $msg]);
    }
}
