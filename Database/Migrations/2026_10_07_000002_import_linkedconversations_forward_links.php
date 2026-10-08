<?php

use Illuminate\Database\Migrations\Migration;
use Modules\LinkedConversations\Services\ForwardImporter;

class ImportLinkedconversationsForwardLinks extends Migration
{
    public function up()
    {
        ForwardImporter::run();
    }

    public function down()
    {
        // The table migration's down() drops the imported rows with the table.
    }
}
