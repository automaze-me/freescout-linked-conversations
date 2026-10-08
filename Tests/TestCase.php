<?php

namespace Modules\LinkedConversations\Tests;

use App\Conversation;
use App\Customer;
use App\Mailbox;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase as CoreTestCase;

abstract class TestCase extends CoreTestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // A cached config (bootstrap/cache/config.php) silently overrides the
        // DB_CONNECTION env from phpunit.xml and would point the suite at the
        // dev database.
        if (config('database.default') !== 'testing') {
            $this->fail(
                'Tests must run on the "testing" DB connection but "'.config('database.default').'" is active. '
                .'Run "make artisan c=config:clear" and try again.'
            );
        }

        if (!\Module::isActive('linkedconversations')) {
            $this->markTestSkipped('The linkedconversations module must be active in the testing database.');
        }

        Conversation::$custom_number_cache = null;
    }

    protected function tearDown(): void
    {
        // Static, survives the rollback.
        Conversation::$custom_number_cache = null;

        parent::tearDown();
    }

    protected function user($role = User::ROLE_USER)
    {
        return factory(User::class)->create(['role' => $role]);
    }

    protected function mailbox(array $users = [], array $attributes = [])
    {
        $mailbox = factory(Mailbox::class)->create($attributes);
        foreach ($users as $user) {
            $mailbox->users()->attach($user->id);
        }

        return $mailbox;
    }

    protected function conversation(Mailbox $mailbox, array $attributes = [])
    {
        if (empty($attributes['created_by_user_id'])) {
            $attributes['created_by_user_id'] = $this->user()->id;
        }

        return factory(Conversation::class)->create(array_merge([
            'mailbox_id' => $mailbox->id,
            'status'     => Conversation::STATUS_ACTIVE,
            'state'      => Conversation::STATE_PUBLISHED,
        ], $attributes));
    }

    protected function customer()
    {
        return factory(Customer::class)->create();
    }

    /**
     * Everything an Eventy action echoes. Rendering is asserted at hook level:
     * a GET of conversations.view 500s in fresh test sessions (core bug).
     */
    protected function renderAction($hook, ...$args)
    {
        ob_start();
        \Eventy::action($hook, ...$args);

        return ob_get_clean();
    }
}
