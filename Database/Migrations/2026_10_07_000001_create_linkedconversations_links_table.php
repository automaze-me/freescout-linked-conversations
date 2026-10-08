<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLinkedconversationsLinksTable extends Migration
{
    public function up()
    {
        Schema::create('conversation_links', function (Blueprint $table) {
            $table->increments('id');
            // Always the smaller conversation id of the pair.
            $table->unsignedInteger('conversation_id');
            $table->unsignedInteger('linked_conversation_id');
            // manual | created | forward | import
            $table->string('source', 20);
            $table->unsignedInteger('created_by_user_id')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['conversation_id', 'linked_conversation_id']);
            $table->index('linked_conversation_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('conversation_links');
    }
}
