<?php

namespace Modules\LinkedConversations\Tests;

use App\Conversation;
use Modules\LinkedConversations\Entities\ConversationLink;
use Modules\LinkedConversations\Services\ListIcons;

class ListIconsTest extends TestCase
{
    private function countQueries(callable $callback)
    {
        $count = 0;
        \DB::listen(function () use (&$count) {
            $count++;
        });
        $callback();

        return $count;
    }

    public function test_linked_map_returns_both_sides_without_drafts_in_one_query()
    {
        $mailbox = $this->mailbox();
        $a = $this->conversation($mailbox);
        $b = $this->conversation($mailbox);
        $c = $this->conversation($mailbox);
        $draft = $this->conversation($mailbox, ['state' => Conversation::STATE_DRAFT]);
        ConversationLink::link($a->id, $b->id, ConversationLink::SOURCE_MANUAL);
        ConversationLink::link($c->id, $a->id, ConversationLink::SOURCE_MANUAL);
        ConversationLink::link($a->id, $draft->id, ConversationLink::SOURCE_CREATED);

        $map = null;
        $queries = $this->countQueries(function () use (&$map, $a, $b) {
            $map = ConversationLink::linkedMap([$a->id, $b->id]);
        });

        $this->assertSame(1, $queries);
        $a_ids = array_map(function ($row) {
            return (int) $row->id;
        }, $map[$a->id]);
        sort($a_ids);
        $expected = [$b->id, $c->id];
        sort($expected);
        $this->assertSame($expected, $a_ids);
        $this->assertSame($a->id, (int) $map[$b->id][0]->id);
        $this->assertSame($a->number, (int) $map[$b->id][0]->number);
    }

    public function test_the_icon_lists_numbers_the_user_may_see()
    {
        $user = $this->user();
        $mine = $this->mailbox([$user]);
        $a = $this->conversation($mine);
        $b = $this->conversation($mine);
        $secret = $this->conversation($this->mailbox());
        ConversationLink::link($a->id, $b->id, ConversationLink::SOURCE_MANUAL);
        ConversationLink::link($a->id, $secret->id, ConversationLink::SOURCE_MANUAL);
        $this->actingAs($user);

        \Eventy::filter('conversations_table.preload_table_data', collect([$a]));
        $html = $this->renderAction('conversations_table.before_subject', $a);

        $this->assertStringContainsString('lc-list-icon', $html);
        $this->assertStringContainsString('#'.$b->number, $html);
        $this->assertStringNotContainsString('#'.$secret->number.',', $html);
        $this->assertStringContainsString('+1 in other mailboxes', $html);
    }

    public function test_no_icon_without_links()
    {
        $user = $this->user();
        $a = $this->conversation($this->mailbox([$user]));
        $this->actingAs($user);

        \Eventy::filter('conversations_table.preload_table_data', collect([$a]));

        $this->assertStringNotContainsString('lc-list-icon', $this->renderAction('conversations_table.before_subject', $a));
    }

    public function test_a_page_costs_one_query_for_links_and_at_most_one_for_mailboxes()
    {
        $user = $this->user();
        $mailbox = $this->mailbox([$user]);
        $page = [];
        for ($i = 0; $i < 3; $i++) {
            $conversation = $this->conversation($mailbox);
            ConversationLink::link($conversation->id, $this->conversation($mailbox)->id, ConversationLink::SOURCE_MANUAL);
            $page[] = $conversation;
        }
        $this->actingAs($user);

        $preload = $this->countQueries(function () use ($page) {
            \Eventy::filter('conversations_table.preload_table_data', collect($page));
        });
        $render = $this->countQueries(function () use ($page) {
            foreach ($page as $conversation) {
                $this->renderAction('conversations_table.before_subject', $conversation);
            }
        });

        $this->assertSame(1, $preload);
        $this->assertLessThanOrEqual(1, $render);
    }
}
