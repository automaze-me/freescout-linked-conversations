<?php

namespace Modules\LinkedConversations\Tests;

use App\Conversation;
use Modules\LinkedConversations\Entities\ConversationLink;
use Modules\LinkedConversations\Services\Timeline;

class AjaxTest extends TestCase
{
    private function ajax($user, array $data)
    {
        return $this->actingAs($user)->post(route('linkedconversations.ajax'), $data)->json();
    }

    /** A user, two conversations they can see and one in a mailbox they can not. */
    private function scenario()
    {
        $user = $this->user();
        $mine = $this->mailbox([$user]);
        $foreign = $this->mailbox();

        return [
            $user,
            $this->conversation($mine, ['subject' => 'Wrong size delivered']),
            $this->conversation($mine, ['subject' => 'Return order 4510']),
            $this->conversation($foreign, ['subject' => 'Secret supplier thread']),
        ];
    }

    public function test_search_finds_by_number_when_custom_numbers_are_enabled()
    {
        config(['app.custom_number' => true]);
        list($user, $a, $b) = $this->scenario();

        $response = $this->ajax($user, ['action' => 'search', 'conversation_id' => $a->id, 'number' => '#'.$b->number]);

        $this->assertSame('success', $response['status']);
        $this->assertStringContainsString('Return order 4510', $response['html']);
        $this->assertStringContainsString('data-lc-linked-id="'.$b->id.'"', $response['html']);
    }

    public function test_search_uses_the_id_when_custom_numbers_are_disabled()
    {
        config(['app.custom_number' => false]);
        list($user, $a, $b) = $this->scenario();

        $response = $this->ajax($user, ['action' => 'search', 'conversation_id' => $a->id, 'number' => (string) $b->id]);

        $this->assertSame('success', $response['status']);
        $this->assertStringContainsString('Return order 4510', $response['html']);
    }

    public function test_search_reports_conversations_without_access_as_not_found()
    {
        config(['app.custom_number' => false]);
        list($user, $a, , $secret) = $this->scenario();

        $response = $this->ajax($user, ['action' => 'search', 'conversation_id' => $a->id, 'number' => (string) $secret->id]);

        $this->assertSame(['status' => 'error', 'msg' => 'Conversation not found'], $response);
    }

    public function test_search_explains_why_a_conversation_can_not_be_linked()
    {
        config(['app.custom_number' => false]);
        list($user, $a, $b) = $this->scenario();
        $draft = $this->conversation($a->mailbox, ['state' => Conversation::STATE_DRAFT]);
        ConversationLink::link($a->id, $b->id, ConversationLink::SOURCE_MANUAL);

        $self = $this->ajax($user, ['action' => 'search', 'conversation_id' => $a->id, 'number' => (string) $a->id]);
        $linked = $this->ajax($user, ['action' => 'search', 'conversation_id' => $a->id, 'number' => (string) $b->id]);
        $drafted = $this->ajax($user, ['action' => 'search', 'conversation_id' => $a->id, 'number' => (string) $draft->id]);

        $this->assertSame('This is the current conversation', $self['msg']);
        $this->assertSame('Already linked', $linked['msg']);
        $this->assertSame('Drafts can not be linked', $drafted['msg']);
    }

    public function test_link_creates_the_link_and_timeline_entries_in_both_conversations()
    {
        list($user, $a, $b) = $this->scenario();

        $response = $this->ajax($user, ['action' => 'link', 'conversation_id' => $a->id, 'linked_id' => $b->id]);

        $this->assertSame('success', $response['status']);
        $this->assertSame('manual', ConversationLink::pair($a->id, $b->id)->source);
        $this->assertSame(1, $this->timelineEntries($a, Timeline::EVENT_LINKED));
        $this->assertSame(1, $this->timelineEntries($b, Timeline::EVENT_LINKED));
    }

    public function test_link_is_refused_without_access_to_the_other_conversation()
    {
        list($user, $a, , $secret) = $this->scenario();

        $response = $this->ajax($user, ['action' => 'link', 'conversation_id' => $a->id, 'linked_id' => $secret->id]);

        $this->assertSame('error', $response['status']);
        $this->assertNull(ConversationLink::pair($a->id, $secret->id));
    }

    public function test_unlink_from_the_other_side_removes_the_link()
    {
        list($user, $a, $b) = $this->scenario();
        ConversationLink::link($a->id, $b->id, ConversationLink::SOURCE_MANUAL);

        $response = $this->ajax($user, ['action' => 'unlink', 'conversation_id' => $b->id, 'linked_id' => $a->id]);

        $this->assertSame('success', $response['status']);
        $this->assertNull(ConversationLink::pair($a->id, $b->id));
        $this->assertSame(1, $this->timelineEntries($a, Timeline::EVENT_UNLINKED));
        $this->assertSame(1, $this->timelineEntries($b, Timeline::EVENT_UNLINKED));
    }

    public function test_unlinking_a_pair_that_is_not_linked_writes_nothing()
    {
        list($user, $a, $b) = $this->scenario();

        $response = $this->ajax($user, ['action' => 'unlink', 'conversation_id' => $a->id, 'linked_id' => $b->id]);

        $this->assertSame('success', $response['status']);
        $this->assertSame(0, $this->timelineEntries($a, Timeline::EVENT_UNLINKED));
    }

    public function test_link_and_unlink_answer_not_found_for_a_deleted_conversation()
    {
        list($user, $a, $b) = $this->scenario();
        $gone_id = $b->id;
        $b->delete();

        $link = $this->ajax($user, ['action' => 'link', 'conversation_id' => $a->id, 'linked_id' => $gone_id]);
        $unlink = $this->ajax($user, ['action' => 'unlink', 'conversation_id' => $a->id, 'linked_id' => $gone_id]);

        $this->assertSame(['status' => 'error', 'msg' => 'Conversation not found'], $link);
        $this->assertSame(['status' => 'error', 'msg' => 'Conversation not found'], $unlink);
    }

    public function test_requests_for_a_conversation_without_access_are_refused()
    {
        list($user, $a, , $secret) = $this->scenario();

        $response = $this->ajax($user, ['action' => 'link', 'conversation_id' => $secret->id, 'linked_id' => $a->id]);

        $this->assertSame(['status' => 'error', 'msg' => 'Conversation not found'], $response);
    }

    public function test_guests_are_redirected()
    {
        list(, $a, $b) = $this->scenario();

        $this->post(route('linkedconversations.ajax'), ['action' => 'link', 'conversation_id' => $a->id, 'linked_id' => $b->id])
            ->assertStatus(302);
        $this->assertNull(ConversationLink::pair($a->id, $b->id));
    }
}
