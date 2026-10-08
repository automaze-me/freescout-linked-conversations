<?php

namespace Modules\LinkedConversations\Tests;

use Modules\LinkedConversations\Entities\ConversationLink;

class ConversationLinkTest extends TestCase
{
    private function conversations($count)
    {
        $mailbox = $this->mailbox();
        $conversations = [];
        for ($i = 0; $i < $count; $i++) {
            $conversations[] = $this->conversation($mailbox);
        }

        return $conversations;
    }

    private function sorted(array $ids)
    {
        sort($ids);

        return $ids;
    }

    public function test_link_stores_the_smaller_id_first_whatever_the_argument_order()
    {
        list($a, $b) = $this->conversations(2);
        $user = $this->user();

        $link = ConversationLink::link($b->id, $a->id, ConversationLink::SOURCE_MANUAL, $user->id);

        $this->assertTrue($link->wasRecentlyCreated);
        $this->assertSame(min($a->id, $b->id), (int) $link->conversation_id);
        $this->assertSame(max($a->id, $b->id), (int) $link->linked_conversation_id);
        $this->assertSame('manual', $link->source);
        $this->assertSame($user->id, (int) $link->created_by_user_id);
        $this->assertNotNull($link->created_at);
    }

    public function test_a_conversation_can_not_be_linked_to_itself()
    {
        list($a) = $this->conversations(1);

        $this->assertNull(ConversationLink::link($a->id, $a->id, ConversationLink::SOURCE_MANUAL));
        $this->assertSame([], ConversationLink::linkedIds($a->id));
    }

    public function test_linking_an_existing_pair_returns_the_existing_row()
    {
        list($a, $b) = $this->conversations(2);

        $first = ConversationLink::link($a->id, $b->id, ConversationLink::SOURCE_FORWARD);
        $second = ConversationLink::link($b->id, $a->id, ConversationLink::SOURCE_MANUAL);

        $this->assertSame($first->id, $second->id);
        $this->assertFalse($second->wasRecentlyCreated);
        $this->assertSame('forward', $second->source);
        $this->assertSame([$b->id], ConversationLink::linkedIds($a->id));
    }

    public function test_linked_ids_come_from_both_sides()
    {
        list($a, $b, $c) = $this->conversations(3);
        ConversationLink::link($a->id, $b->id, ConversationLink::SOURCE_MANUAL);
        ConversationLink::link($c->id, $b->id, ConversationLink::SOURCE_MANUAL);

        $this->assertSame($this->sorted([$a->id, $c->id]), $this->sorted(ConversationLink::linkedIds($b->id)));
        $this->assertSame([$b->id], ConversationLink::linkedIds($a->id));
    }

    public function test_unlink_removes_the_pair_in_either_order()
    {
        list($a, $b) = $this->conversations(2);
        ConversationLink::link($a->id, $b->id, ConversationLink::SOURCE_MANUAL);

        $this->assertTrue(ConversationLink::unlink($b->id, $a->id));
        $this->assertFalse(ConversationLink::unlink($a->id, $b->id));
        $this->assertNull(ConversationLink::pair($a->id, $b->id));
    }

    public function test_repoint_moves_links_and_drops_self_links_and_duplicates()
    {
        list($kept, $removed, $x, $y) = $this->conversations(4);
        $user = $this->user();
        ConversationLink::link($removed->id, $x->id, ConversationLink::SOURCE_FORWARD, $user->id, '2020-01-02 03:04:05');
        ConversationLink::link($removed->id, $kept->id, ConversationLink::SOURCE_MANUAL);
        ConversationLink::link($removed->id, $y->id, ConversationLink::SOURCE_MANUAL);
        ConversationLink::link($kept->id, $y->id, ConversationLink::SOURCE_CREATED);

        ConversationLink::repoint($removed->id, $kept->id);

        $this->assertSame([], ConversationLink::linkedIds($removed->id));
        $this->assertSame($this->sorted([$x->id, $y->id]), $this->sorted(ConversationLink::linkedIds($kept->id)));
        $moved = ConversationLink::pair($kept->id, $x->id);
        $this->assertSame('forward', $moved->source);
        $this->assertSame($user->id, (int) $moved->created_by_user_id);
        $this->assertSame('2020-01-02 03:04:05', $moved->created_at->format('Y-m-d H:i:s'));
        $this->assertSame('created', ConversationLink::pair($kept->id, $y->id)->source);
    }

    public function test_delete_for_conversations_removes_links_on_either_side()
    {
        list($a, $b, $c) = $this->conversations(3);
        ConversationLink::link($a->id, $b->id, ConversationLink::SOURCE_MANUAL);
        ConversationLink::link($c->id, $a->id, ConversationLink::SOURCE_MANUAL);
        ConversationLink::link($b->id, $c->id, ConversationLink::SOURCE_MANUAL);

        ConversationLink::deleteForConversations([$a->id]);

        $this->assertSame([], ConversationLink::linkedIds($a->id));
        $this->assertNotNull(ConversationLink::pair($b->id, $c->id));
    }
}
