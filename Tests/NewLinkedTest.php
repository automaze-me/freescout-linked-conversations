<?php

namespace Modules\LinkedConversations\Tests;

use App\Conversation;
use App\Events\UserCreatedConversationDraft;
use App\Thread;
use Modules\LinkedConversations\Entities\ConversationLink;
use Modules\LinkedConversations\Services\Actions;
use Modules\LinkedConversations\Services\LinkedList;
use Modules\LinkedConversations\Services\NewLinked;
use Modules\LinkedConversations\Services\Timeline;

class NewLinkedTest extends TestCase
{
    private function scenario($subject = 'Wrong size delivered')
    {
        $user = $this->user();
        $mailbox = $this->mailbox([$user]);
        $original = $this->conversation($mailbox, ['subject' => $subject]);
        $this->actingAs($user);

        return [$user, $mailbox, $original];
    }

    private function hint(Conversation $conversation, $mailbox)
    {
        return $this->renderAction('conversation.create_form.before_subject', $conversation, $mailbox, null);
    }

    private function createdBy($user, Conversation $conversation)
    {
        return Thread::create($conversation, Thread::TYPE_NOTE, 'Hello fulfiller', ['created_by_user_id' => $user->id]);
    }

    public function test_the_form_shows_the_hint_and_the_hidden_field()
    {
        list(, $mailbox, $original) = $this->scenario();
        request()->query->set(Actions::LINK_TO_PARAM, $original->id);

        $html = $this->hint(new Conversation(), $mailbox);

        $this->assertStringContainsString('#'.$original->number, $html);
        $this->assertStringContainsString('Wrong size delivered', $html);
        $this->assertStringContainsString('name="linkedconversations_link_to" value="'.$original->id.'"', $html);
    }

    public function test_no_hint_for_a_conversation_without_access_or_a_draft()
    {
        list(, $mailbox) = $this->scenario();
        $secret = $this->conversation($this->mailbox());
        $draft = $this->conversation($mailbox, ['state' => Conversation::STATE_DRAFT]);

        request()->query->set(Actions::LINK_TO_PARAM, $secret->id);
        $this->assertStringNotContainsString('lc-pending', $this->hint(new Conversation(), $mailbox));

        request()->query->set(Actions::LINK_TO_PARAM, $draft->id);
        $this->assertStringNotContainsString('lc-pending', $this->hint(new Conversation(), $mailbox));
    }

    public function test_the_hint_comes_back_when_the_draft_is_reopened()
    {
        list(, $mailbox, $original) = $this->scenario();
        $draft = $this->conversation($mailbox, ['state' => Conversation::STATE_DRAFT]);
        $draft->setMeta(NewLinked::META, $original->id, true);

        $this->assertStringContainsString('value="'.$original->id.'"', $this->hint($draft, $mailbox));
    }

    public function test_subject_is_escaped_in_the_hint()
    {
        list(, $mailbox, $original) = $this->scenario('<b>Bold</b> & "quotes"');
        request()->query->set(Actions::LINK_TO_PARAM, $original->id);

        $html = $this->hint(new Conversation(), $mailbox);

        $this->assertStringNotContainsString('<b>Bold</b>', $html);
        $this->assertStringContainsString('&lt;b&gt;Bold&lt;/b&gt;', $html);
    }

    public function test_saving_a_draft_remembers_the_link()
    {
        list($user, $mailbox, $original) = $this->scenario();
        $draft = $this->conversation($mailbox, ['state' => Conversation::STATE_DRAFT]);
        request()->merge([Actions::LINK_TO_PARAM => $original->id]);

        event(new UserCreatedConversationDraft($draft, $this->createdBy($user, $draft)));

        $this->assertSame($original->id, (int) $draft->fresh()->getMeta(NewLinked::META));
    }

    public function test_creating_the_conversation_links_it()
    {
        list($user, $mailbox, $original) = $this->scenario();
        $new = $this->conversation($mailbox);
        request()->merge([Actions::LINK_TO_PARAM => $original->id]);

        \Eventy::action('conversation.created_by_user_can_undo', $new, $this->createdBy($user, $new));

        $link = ConversationLink::pair($original->id, $new->id);
        $this->assertSame('created', $link->source);
        $this->assertSame($user->id, (int) $link->created_by_user_id);
    }

    public function test_an_emptied_field_wins_over_the_draft_and_clears_it()
    {
        list($user, $mailbox, $original) = $this->scenario();
        $new = $this->conversation($mailbox);
        $new->setMeta(NewLinked::META, $original->id, true);
        request()->merge([Actions::LINK_TO_PARAM => '']);

        \Eventy::action('conversation.created_by_user_can_undo', $new, $this->createdBy($user, $new));

        $this->assertNull(ConversationLink::pair($original->id, $new->id));
        $this->assertNull($new->fresh()->getMeta(NewLinked::META));
    }

    public function test_without_the_field_the_draft_memory_is_used_and_cleared()
    {
        list($user, $mailbox, $original) = $this->scenario();
        $new = $this->conversation($mailbox);
        $new->setMeta(NewLinked::META, $original->id, true);

        \Eventy::action('conversation.created_by_user_can_undo', $new, $this->createdBy($user, $new));

        $this->assertNotNull(ConversationLink::pair($original->id, $new->id));
        $this->assertNull($new->fresh()->getMeta(NewLinked::META));
    }

    public function test_no_link_to_a_conversation_without_access()
    {
        list($user, $mailbox) = $this->scenario();
        $secret = $this->conversation($this->mailbox());
        $new = $this->conversation($mailbox);
        request()->merge([Actions::LINK_TO_PARAM => $secret->id]);

        \Eventy::action('conversation.created_by_user_can_undo', $new, $this->createdBy($user, $new));

        $this->assertNull(ConversationLink::pair($secret->id, $new->id));
    }

    public function test_timeline_entries_appear_once_after_the_undo_timeout()
    {
        list($user, $mailbox, $original) = $this->scenario();
        $new = $this->conversation($mailbox);
        $thread = $this->createdBy($user, $new);
        request()->merge([Actions::LINK_TO_PARAM => $original->id]);
        \Eventy::action('conversation.created_by_user_can_undo', $new, $thread);

        \Eventy::action('conversation.created_by_user', $new, $thread);
        \Eventy::action('conversation.created_by_user', $new, $thread);

        $this->assertSame(1, $this->timelineEntries($new, Timeline::EVENT_CREATED_FROM));
        $this->assertSame(1, $this->timelineEntries($original, Timeline::EVENT_CREATED_LINKED));
    }

    public function test_an_undone_conversation_is_hidden_until_it_is_sent_again()
    {
        list($user, $mailbox, $original) = $this->scenario();
        $new = $this->conversation($mailbox);
        $thread = $this->createdBy($user, $new);
        request()->merge([Actions::LINK_TO_PARAM => $original->id]);
        \Eventy::action('conversation.created_by_user_can_undo', $new, $thread);

        // Core's undo turns the new conversation back into a draft.
        $new->state = Conversation::STATE_DRAFT;
        $new->save();
        \Eventy::action('conversation.created_by_user', $new, $thread);

        $this->assertSame([], LinkedList::forConversation($original, $user)['visible']);
        $this->assertSame(0, $this->timelineEntries($original, Timeline::EVENT_CREATED_LINKED));

        $new->state = Conversation::STATE_PUBLISHED;
        $new->save();
        \Eventy::action('conversation.created_by_user', $new, $thread);

        $this->assertCount(1, LinkedList::forConversation($original, $user)['visible']);
        $this->assertSame(1, $this->timelineEntries($original, Timeline::EVENT_CREATED_LINKED));
    }

    public function test_clear_pending_drops_the_remembered_link()
    {
        list($user, $mailbox, $original) = $this->scenario();
        $draft = $this->conversation($mailbox, ['state' => Conversation::STATE_DRAFT]);
        $draft->setMeta(NewLinked::META, $original->id, true);

        $response = $this->post(route('linkedconversations.ajax'), ['action' => 'clear_pending', 'conversation_id' => $draft->id])->json();

        $this->assertSame('success', $response['status']);
        $this->assertNull($draft->fresh()->getMeta(NewLinked::META));
    }
}
