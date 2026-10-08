<?php

namespace Modules\LinkedConversations\Tests;

use App\Thread;
use App\User;
use Modules\LinkedConversations\Entities\ConversationLink;

class LifecycleTest extends TestCase
{
    private function forwardScenario()
    {
        $user = $this->user();
        $mailbox = $this->mailbox([$user]);
        $parent = $this->conversation($mailbox);
        $child = $this->conversation($mailbox);
        $thread = Thread::create($parent, Thread::TYPE_NOTE, 'Forwarding', ['created_by_user_id' => $user->id]);

        return [$user, $mailbox, $parent, $child, $thread];
    }

    public function test_a_manual_forward_links_both_conversations()
    {
        list($user, , $parent, $child, $thread) = $this->forwardScenario();

        \Eventy::action('conversation.user_forwarded_can_undo', $parent, $thread, $child, null);

        $link = ConversationLink::pair($parent->id, $child->id);
        $this->assertNotNull($link);
        $this->assertSame('forward', $link->source);
        $this->assertSame($user->id, (int) $link->created_by_user_id);
    }

    public function test_the_delayed_repeat_of_a_manual_forward_adds_nothing()
    {
        list(, , $parent, $child, $thread) = $this->forwardScenario();

        \Eventy::action('conversation.user_forwarded_can_undo', $parent, $thread, $child, null);
        \Eventy::action('conversation.user_forwarded', $parent, $thread, $child, null);

        $this->assertSame([$child->id], ConversationLink::linkedIds($parent->id));
    }

    public function test_forwarding_to_several_recipients_links_every_child()
    {
        list(, $mailbox, $parent, $child, $thread) = $this->forwardScenario();
        $second_child = $this->conversation($mailbox);

        // Core fires the hook once per forwarded conversation.
        \Eventy::action('conversation.user_forwarded_can_undo', $parent, $thread, $child, null);
        \Eventy::action('conversation.user_forwarded_can_undo', $parent, $thread, $second_child, null);

        $ids = ConversationLink::linkedIds($parent->id);
        sort($ids);
        $expected = [$child->id, $second_child->id];
        sort($expected);
        $this->assertSame($expected, $ids);
    }

    public function test_conversation_forward_links_the_new_conversation()
    {
        $user = $this->user(User::ROLE_ADMIN);
        $mailbox = $this->mailbox([$user]);
        $parent = $this->conversation($mailbox, ['customer_id' => $this->customer()->id]);
        \Queue::fake();

        // The path the Workflows module's "Forward" action uses.
        $parent->forward($user, 'Please check the return', 'fulfiller@example.com');

        $ids = ConversationLink::linkedIds($parent->id);
        $this->assertCount(1, $ids);
        $this->assertSame('forward', ConversationLink::pair($parent->id, $ids[0])->source);
    }

    public function test_undoing_a_forward_removes_the_link()
    {
        list(, , $parent, $child, $thread) = $this->forwardScenario();
        \Eventy::action('conversation.user_forwarded_can_undo', $parent, $thread, $child, null);

        // Core's undo hard-deletes the forwarded conversation.
        $child->delete();
        // The delayed hook still arrives afterwards with the stale model.
        \Eventy::action('conversation.user_forwarded', $parent, $thread, $child, null);

        $this->assertSame([], ConversationLink::linkedIds($parent->id));
    }

    public function test_merging_moves_the_links_of_the_merged_away_conversation()
    {
        $user = $this->user();
        $mailbox = $this->mailbox([$user]);
        $kept = $this->conversation($mailbox);
        $removed = $this->conversation($mailbox);
        $other = $this->conversation($mailbox);
        ConversationLink::link($removed->id, $other->id, ConversationLink::SOURCE_MANUAL);

        \Eventy::action('conversation.merged', $kept, $removed, $user);

        $this->assertNotNull(ConversationLink::pair($kept->id, $other->id));
        $this->assertNull(ConversationLink::pair($removed->id, $other->id));
    }

    public function test_deleting_forever_removes_the_links()
    {
        $mailbox = $this->mailbox();
        $a = $this->conversation($mailbox);
        $b = $this->conversation($mailbox);
        ConversationLink::link($a->id, $b->id, ConversationLink::SOURCE_MANUAL);

        \Eventy::action('conversations.before_delete_forever', [$a->id]);

        $this->assertNull(ConversationLink::pair($a->id, $b->id));
    }

    public function test_moving_to_trash_keeps_the_link()
    {
        $user = $this->user(User::ROLE_ADMIN);
        $mailbox = $this->mailbox([$user]);
        $a = $this->conversation($mailbox);
        $b = $this->conversation($mailbox);
        ConversationLink::link($a->id, $b->id, ConversationLink::SOURCE_MANUAL);

        $a->deleteToFolder($user);

        $this->assertNotNull(ConversationLink::pair($a->id, $b->id));
    }
}
