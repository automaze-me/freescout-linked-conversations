<?php

namespace Modules\LinkedConversations\Tests;

use App\Conversation;
use Modules\LinkedConversations\Entities\ConversationLink;

class SidebarTest extends TestCase
{
    private function render(Conversation $conversation, $user)
    {
        $this->actingAs($user);

        return $this->renderAction('conversation.before_prev_convs', null, $conversation, $conversation->mailbox);
    }

    public function test_renders_nothing_without_links()
    {
        $user = $this->user();
        $conversation = $this->conversation($this->mailbox([$user]));

        $this->assertStringNotContainsString('lc-sidebar', $this->render($conversation, $user));
    }

    public function test_lists_linked_conversations_open_first_with_status_and_assignee()
    {
        $user = $this->user();
        $assignee = $this->user();
        $mailbox = $this->mailbox([$user, $assignee]);
        $current = $this->conversation($mailbox);
        $closed = $this->conversation($mailbox, ['subject' => 'Closed question', 'status' => Conversation::STATUS_CLOSED]);
        $pending = $this->conversation($mailbox, [
            'subject' => 'Return order 4510',
            'status'  => Conversation::STATUS_PENDING,
            'user_id' => $assignee->id,
        ]);
        ConversationLink::link($current->id, $closed->id, ConversationLink::SOURCE_MANUAL);
        ConversationLink::link($current->id, $pending->id, ConversationLink::SOURCE_MANUAL);

        $html = $this->render($current, $user);

        $this->assertStringContainsString('#'.$pending->number, $html);
        $this->assertStringContainsString('Return order 4510', $html);
        $this->assertStringContainsString($assignee->getFullName(), $html);
        $this->assertStringContainsString('Unassigned', $html);
        $this->assertStringContainsString('lc-status-'.Conversation::STATUS_PENDING, $html);
        $this->assertLessThan(strpos($html, 'Closed question'), strpos($html, 'Return order 4510'));
        $this->assertStringContainsString('data-lc-linked-id="'.$closed->id.'"', $html);
    }

    public function test_names_the_mailbox_only_when_it_differs()
    {
        $user = $this->user();
        $support = $this->mailbox([$user], ['name' => 'Support Inbox']);
        $fulfilment = $this->mailbox([$user], ['name' => 'Fulfilment Inbox']);
        $current = $this->conversation($support);
        $same = $this->conversation($support);
        $other = $this->conversation($fulfilment);
        ConversationLink::link($current->id, $same->id, ConversationLink::SOURCE_MANUAL);
        ConversationLink::link($current->id, $other->id, ConversationLink::SOURCE_MANUAL);

        $html = $this->render($current, $user);

        $this->assertStringContainsString('Fulfilment Inbox', $html);
        $this->assertStringNotContainsString('Support Inbox', $html);
    }

    public function test_counts_links_into_mailboxes_without_access_without_showing_them()
    {
        $user = $this->user();
        $current = $this->conversation($this->mailbox([$user]));
        $secret = $this->conversation($this->mailbox(), ['subject' => 'Secret supplier thread']);
        ConversationLink::link($current->id, $secret->id, ConversationLink::SOURCE_MANUAL);

        $html = $this->render($current, $user);

        $this->assertStringContainsString('lc-sidebar', $html);
        $this->assertStringContainsString('+1 linked conversation in another mailbox', $html);
        $this->assertStringNotContainsString('Secret supplier thread', $html);
    }

    public function test_hides_drafts_and_marks_trashed_conversations_as_deleted()
    {
        $user = $this->user();
        $mailbox = $this->mailbox([$user]);
        $current = $this->conversation($mailbox);
        $draft = $this->conversation($mailbox, ['subject' => 'Unsent draft', 'state' => Conversation::STATE_DRAFT]);
        $trashed = $this->conversation($mailbox, ['subject' => 'Trashed one', 'state' => Conversation::STATE_DELETED]);
        ConversationLink::link($current->id, $draft->id, ConversationLink::SOURCE_CREATED);
        ConversationLink::link($current->id, $trashed->id, ConversationLink::SOURCE_MANUAL);

        $html = $this->render($current, $user);

        $this->assertStringNotContainsString('Unsent draft', $html);
        $this->assertStringContainsString('Trashed one', $html);
        $this->assertStringContainsString('lc-status-deleted', $html);
        $this->assertStringContainsString('Deleted', $html);
    }

    public function test_subjects_are_escaped()
    {
        $user = $this->user();
        $mailbox = $this->mailbox([$user]);
        $current = $this->conversation($mailbox);
        $tricky = $this->conversation($mailbox, ['subject' => '<b>Bold</b> & "quotes"']);
        ConversationLink::link($current->id, $tricky->id, ConversationLink::SOURCE_MANUAL);

        $html = $this->render($current, $user);

        $this->assertStringContainsString('&lt;b&gt;Bold&lt;/b&gt; &amp; &quot;quotes&quot;', $html);
        $this->assertStringNotContainsString('<b>Bold</b>', $html);
    }
}
