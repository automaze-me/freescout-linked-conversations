<?php

namespace Modules\LinkedConversations\Tests;

use App\Thread;
use Modules\LinkedConversations\Entities\ConversationLink;
use Modules\LinkedConversations\Services\ForwardImporter;

class ForwardImporterTest extends TestCase
{
    public function test_imports_forwards_stored_under_both_meta_key_generations()
    {
        $user = $this->user();
        $mailbox = $this->mailbox([$user]);
        $a = $this->conversation($mailbox);
        $b = $this->conversation($mailbox);
        $c = $this->conversation($mailbox);
        $d = $this->conversation($mailbox);
        $current = Thread::create($a, Thread::TYPE_NOTE, 'fw', [
            'created_by_user_id' => $user->id,
            'meta'               => [Thread::META_FORWARD_CHILD_CONVERSATION_ID => $b->id],
        ]);
        Thread::create($c, Thread::TYPE_NOTE, 'fw', [
            'created_by_user_id' => $user->id,
            'meta'               => ['forward_child_conversation_id' => (string) $d->id],
        ]);

        ForwardImporter::run();

        $link = ConversationLink::pair($a->id, $b->id);
        $this->assertNotNull($link);
        $this->assertSame('import', $link->source);
        $this->assertSame($user->id, (int) $link->created_by_user_id);
        $this->assertSame($current->created_at->format('Y-m-d H:i:s'), $link->created_at->format('Y-m-d H:i:s'));
        $this->assertNotNull(ConversationLink::pair($c->id, $d->id));
    }

    public function test_a_second_run_adds_nothing()
    {
        $mailbox = $this->mailbox();
        $a = $this->conversation($mailbox);
        $b = $this->conversation($mailbox);
        Thread::create($a, Thread::TYPE_NOTE, 'fw', [
            'meta' => [Thread::META_FORWARD_CHILD_CONVERSATION_ID => $b->id],
        ]);

        ForwardImporter::run();
        $second = ForwardImporter::run();

        $this->assertSame(0, $second['linked']);
        $this->assertSame([$b->id], ConversationLink::linkedIds($a->id));
    }

    public function test_skips_missing_conversations_and_unreadable_meta()
    {
        $mailbox = $this->mailbox();
        $a = $this->conversation($mailbox);
        Thread::create($a, Thread::TYPE_NOTE, 'fw', [
            'meta' => [Thread::META_FORWARD_CHILD_CONVERSATION_ID => 999999999],
        ]);
        $broken = Thread::create($a, Thread::TYPE_NOTE, 'fw', []);
        \DB::table('threads')->where('id', $broken->id)->update(['meta' => '{"fw_cci": broken']);

        $stats = ForwardImporter::run();

        $this->assertSame([], ConversationLink::linkedIds($a->id));
        $this->assertGreaterThanOrEqual(1, $stats['skipped']);
        $this->assertGreaterThanOrEqual(1, $stats['malformed']);
    }
}
