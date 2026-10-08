<?php

namespace Modules\LinkedConversations\Tests;

use App\Conversation;
use App\Misc\ConversationActionButtons;
use Modules\LinkedConversations\Entities\ConversationLink;
use Modules\LinkedConversations\Services\Actions;

class ActionsTest extends TestCase
{
    public function test_adds_the_link_action_to_the_more_actions_menu()
    {
        $user = $this->user();
        $mailbox = $this->mailbox([$user]);
        $conversation = $this->conversation($mailbox);

        $actions = ConversationActionButtons::getActions($conversation, $user, $mailbox);

        $this->assertArrayHasKey('linkedconversations_link', $actions);
        $link = $actions['linkedconversations_link'];
        $this->assertSame(ConversationActionButtons::LOCATION_DROPDOWN, $link['location']);
        $this->assertSame(Actions::linkDialogUrl($conversation), $link['url']($conversation));
        $this->assertSame('initLinkedConversationsLinkDialog', $link['attrs']['data-modal-on-show']);
    }

    public function test_new_linked_conversation_opens_the_form_directly_with_one_mailbox()
    {
        $user = $this->user();
        $mailbox = $this->mailbox([$user]);
        $conversation = $this->conversation($mailbox);

        $new = ConversationActionButtons::getActions($conversation, $user, $mailbox)['linkedconversations_new'];

        $this->assertSame(
            route('conversations.create', ['mailbox_id' => $mailbox->id, 'linkedconversations_link_to' => $conversation->id]),
            $new['url']($conversation)
        );
        $this->assertSame([], $new['attrs']);
    }

    public function test_new_linked_conversation_asks_for_the_mailbox_with_several()
    {
        $user = $this->user();
        $mailbox = $this->mailbox([$user]);
        $this->mailbox([$user]);
        $conversation = $this->conversation($mailbox);

        $new = ConversationActionButtons::getActions($conversation, $user, $mailbox)['linkedconversations_new'];

        $this->assertStringContainsString('/linked-conversations/ajax-html/new_dialog', $new['url']($conversation));
        $this->assertSame('initLinkedConversationsNewDialog', $new['attrs']['data-modal-on-show']);
    }

    public function test_drafts_get_no_actions()
    {
        $user = $this->user();
        $mailbox = $this->mailbox([$user]);
        $draft = $this->conversation($mailbox, ['state' => Conversation::STATE_DRAFT]);

        $actions = Actions::addButtons([], $draft, $user, $mailbox);

        $this->assertSame([], $actions);
    }

    public function test_link_dialog_lists_other_conversations_of_the_customer()
    {
        $user = $this->user();
        $mailbox = $this->mailbox([$user]);
        $customer = $this->customer();
        $conversation = $this->conversation($mailbox, ['customer_id' => $customer->id, 'subject' => 'Current one']);
        $this->conversation($mailbox, ['customer_id' => $customer->id, 'subject' => 'Earlier complaint']);
        $linked = $this->conversation($mailbox, ['customer_id' => $customer->id, 'subject' => 'Already linked one']);
        $this->conversation($mailbox, ['customer_id' => $customer->id, 'subject' => 'Unsent draft', 'state' => Conversation::STATE_DRAFT]);
        $this->conversation($mailbox, ['customer_id' => $this->customer()->id, 'subject' => 'Somebody else']);
        ConversationLink::link($conversation->id, $linked->id, ConversationLink::SOURCE_MANUAL);

        $response = $this->actingAs($user)->get(Actions::linkDialogUrl($conversation));

        $response->assertStatus(200);
        $html = $response->getContent();
        $this->assertStringContainsString('Earlier complaint', $html);
        $this->assertStringNotContainsString('Already linked one', $html);
        $this->assertStringNotContainsString('Unsent draft', $html);
        $this->assertStringNotContainsString('Somebody else', $html);
        $this->assertStringContainsString('data-lc-conversation-id="'.$conversation->id.'"', $html);
    }

    public function test_dialogs_are_refused_without_access()
    {
        $user = $this->user();
        $conversation = $this->conversation($this->mailbox());

        $this->actingAs($user)->get(Actions::linkDialogUrl($conversation))->assertStatus(404);
    }

    public function test_new_dialog_offers_each_mailbox_of_the_user()
    {
        $user = $this->user();
        $support = $this->mailbox([$user], ['name' => 'Support']);
        $fulfilment = $this->mailbox([$user], ['name' => 'Fulfilment']);
        $conversation = $this->conversation($support);

        $html = $this->actingAs($user)
            ->get(route('linkedconversations.ajax_html', ['action' => 'new_dialog', 'conversation_id' => $conversation->id]))
            ->getContent();

        $this->assertStringContainsString('Fulfilment', $html);
        $this->assertStringContainsString(e(Actions::createUrl($conversation, $fulfilment->id)), $html);
    }
}
