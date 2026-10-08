<?php

namespace Modules\LinkedConversations\Services;

use App\Conversation;
use App\Misc\ConversationActionButtons;

/**
 * The two entries in core's "More Actions" menu and the URLs/attributes the
 * sidebar reuses.
 */
class Actions
{
    // Query parameter / form field carrying the conversation to link a new one to.
    const LINK_TO_PARAM = 'linkedconversations_link_to';

    public static function linkDialogUrl(Conversation $conversation)
    {
        return route('linkedconversations.ajax_html', ['action' => 'link_dialog', 'conversation_id' => $conversation->id]);
    }

    public static function linkDialogAttrs()
    {
        return [
            'data-trigger'         => 'modal',
            'data-modal-title'     => __('Link Conversation'),
            'data-modal-no-footer' => 'true',
            'data-modal-on-show'   => 'initLinkedConversationsLinkDialog',
        ];
    }

    public static function createUrl(Conversation $conversation, $mailbox_id)
    {
        return route('conversations.create', ['mailbox_id' => $mailbox_id, self::LINK_TO_PARAM => $conversation->id]);
    }

    /**
     * Straight to core's new-conversation form when the user has one mailbox,
     * otherwise a small dialog to pick the mailbox first.
     *
     * @return array ['url' => string, 'attrs' => array]
     */
    public static function newConversationTarget(Conversation $conversation, $user)
    {
        if (count($user->mailboxesCanView(true)) <= 1) {
            return ['url' => self::createUrl($conversation, $conversation->mailbox_id), 'attrs' => []];
        }

        return [
            'url'   => route('linkedconversations.ajax_html', ['action' => 'new_dialog', 'conversation_id' => $conversation->id]),
            'attrs' => [
                'data-trigger'         => 'modal',
                'data-modal-title'     => __('New Linked Conversation'),
                'data-modal-no-footer' => 'true',
                'data-modal-on-show'   => 'initLinkedConversationsNewDialog',
            ],
        ];
    }

    /**
     * conversation.get_action_buttons filter.
     */
    public static function addButtons($actions, $conversation, $user, $mailbox)
    {
        if (!$conversation || !$conversation->id || $conversation->isDraft() || !$user) {
            return $actions;
        }

        $actions['linkedconversations_link'] = [
            'icon'           => 'glyphicon-link',
            'location'       => ConversationActionButtons::LOCATION_DROPDOWN,
            'label'          => __('Link Conversation…'),
            'permission'     => function () {
                return true;
            },
            'class'          => 'lc-action-link',
            'url'            => function ($conversation) {
                return Actions::linkDialogUrl($conversation);
            },
            'attrs'          => self::linkDialogAttrs(),
            'fixed_location' => true,
        ];

        $new = self::newConversationTarget($conversation, $user);
        $actions['linkedconversations_new'] = [
            'icon'           => 'glyphicon-plus',
            'location'       => ConversationActionButtons::LOCATION_DROPDOWN,
            'label'          => __('New Linked Conversation…'),
            'permission'     => function () {
                return true;
            },
            'class'          => 'lc-action-new',
            'url'            => function () use ($new) {
                return $new['url'];
            },
            'attrs'          => $new['attrs'],
            'fixed_location' => true,
        ];

        return $actions;
    }
}
