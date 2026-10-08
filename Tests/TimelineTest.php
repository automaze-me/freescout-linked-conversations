<?php

namespace Modules\LinkedConversations\Tests;

use App\Conversation;
use App\Thread;
use Modules\LinkedConversations\Entities\ConversationLink;
use Modules\LinkedConversations\Services\Timeline;

class TimelineTest extends TestCase
{
    public function test_record_pair_writes_a_line_item_into_both_conversations()
    {
        $user = $this->user();
        $mailbox = $this->mailbox([$user]);
        $a = $this->conversation($mailbox);
        $b = $this->conversation($mailbox);

        Timeline::recordPair(Timeline::EVENT_LINKED, $a, $b, $user->id);

        $item = Thread::where('conversation_id', $a->id)->where('type', Thread::TYPE_LINEITEM)->first();
        $this->assertSame(Timeline::ACTION_TYPE, (int) $item->action_type);
        $this->assertSame($user->id, (int) $item->created_by_user_id);
        $this->assertEquals(
            ['event' => 'linked', 'conversation_id' => $b->id, 'number' => $b->number],
            $item->getMeta(Timeline::META_KEY)
        );
        $this->assertSame(1, $this->timelineEntries($b, Timeline::EVENT_LINKED));
    }

    /**
     * @dataProvider eventTexts
     */
    public function test_action_text_per_event_in_english_and_german($event, $english, $german)
    {
        $user = $this->user();
        $mailbox = $this->mailbox([$user]);
        $a = $this->conversation($mailbox);
        $b = $this->conversation($mailbox);
        $item = Timeline::record($event, $a, $b, $user->id);
        $number = '<a href="'.e($b->url()).'">#'.$b->number.'</a>';

        $this->assertSame(str_replace(':number', $number, $english), $item->getActionText('', true, false, null, 'Jörn'));

        app()->setLocale('de');
        $this->assertSame(str_replace(':number', $number, $german), $item->getActionText('', true, false, null, 'Jörn'));
    }

    public function eventTexts()
    {
        return [
            ['linked', 'Jörn linked this conversation with :number', 'Jörn hat dieses Gespräch mit :number verknüpft'],
            ['unlinked', 'Jörn unlinked conversation :number', 'Jörn hat die Verknüpfung mit :number gelöst'],
            ['created_linked', 'Jörn created linked conversation :number', 'Jörn hat das verknüpfte Gespräch :number angelegt'],
            ['created_from', 'Jörn created this conversation from :number', 'Jörn hat dieses Gespräch aus :number angelegt'],
        ];
    }

    public function test_the_number_is_plain_text_once_the_other_conversation_is_gone()
    {
        $user = $this->user();
        $mailbox = $this->mailbox([$user]);
        $a = $this->conversation($mailbox);
        $b = $this->conversation($mailbox);
        $item = Timeline::record(Timeline::EVENT_LINKED, $a, $b, $user->id);
        $number = $b->number;

        $b->delete();

        $this->assertSame(
            'Jörn linked this conversation with #'.$number,
            $item->fresh()->getActionText('', true, false, null, 'Jörn')
        );
    }

    public function test_foreign_line_items_with_the_same_action_type_are_left_alone()
    {
        $user = $this->user();
        $a = $this->conversation($this->mailbox([$user]));
        $item = Thread::create($a, Thread::TYPE_LINEITEM, '', [
            'action_type'        => Timeline::ACTION_TYPE,
            'created_by_user_id' => $user->id,
        ]);

        $this->assertSame('unchanged', Timeline::actionText('unchanged', $item));
    }

    public function test_announce_created_writes_both_entries_once()
    {
        $user = $this->user();
        $mailbox = $this->mailbox([$user]);
        $original = $this->conversation($mailbox);
        $new = $this->conversation($mailbox);
        ConversationLink::link($original->id, $new->id, ConversationLink::SOURCE_CREATED, $user->id);

        Timeline::announceCreated($new);
        Timeline::announceCreated($new);

        $this->assertSame(1, $this->timelineEntries($new, Timeline::EVENT_CREATED_FROM));
        $this->assertSame(1, $this->timelineEntries($original, Timeline::EVENT_CREATED_LINKED));
    }

    public function test_announce_created_skips_drafts()
    {
        $user = $this->user();
        $mailbox = $this->mailbox([$user]);
        $original = $this->conversation($mailbox);
        $new = $this->conversation($mailbox, ['state' => Conversation::STATE_DRAFT]);
        ConversationLink::link($original->id, $new->id, ConversationLink::SOURCE_CREATED, $user->id);

        Timeline::announceCreated($new);

        $this->assertSame(0, $this->timelineEntries($new, Timeline::EVENT_CREATED_FROM));
        $this->assertSame(0, $this->timelineEntries($original, Timeline::EVENT_CREATED_LINKED));
    }
}
