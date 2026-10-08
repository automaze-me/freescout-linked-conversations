<div class="lc-new-dialog">
    <div class="form-group">
        <label for="lc-mailbox">{{ __('Mailbox') }}</label>
        <select id="lc-mailbox" class="form-control lc-mailbox">
            @foreach ($mailboxes as $mailbox)
                <option value="{{ \Modules\LinkedConversations\Services\Actions::createUrl($conversation, $mailbox->id) }}" @if ($mailbox->id == $conversation->mailbox_id) selected="selected" @endif>{{ $mailbox->name }}</option>
            @endforeach
        </select>
    </div>
    <button type="button" class="btn btn-primary lc-new-continue">{{ __('Continue') }}</button>
</div>
