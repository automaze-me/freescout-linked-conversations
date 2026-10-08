<div class="lc-result">
    <button type="button" class="btn btn-primary btn-xs pull-right lc-link-btn" data-lc-linked-id="{{ $conversation->id }}" data-loading-text="{{ __('Link Conversation') }}…">{{ __('Link Conversation') }}</button>
    <strong>#{{ $conversation->number }}</strong> {{ $conversation->getSubject() }}
    <div class="text-help">
        {{ \Modules\LinkedConversations\Services\Presenter::customerName($conversation) }}
        · {{ \Modules\LinkedConversations\Services\Presenter::statusLabel($conversation) }}
        · {{ $conversation->mailbox->name }}
    </div>
</div>
