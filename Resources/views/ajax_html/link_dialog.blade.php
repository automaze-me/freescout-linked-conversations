<div class="lc-dialog" data-lc-ajax-url="{{ route('linkedconversations.ajax') }}" data-lc-conversation-id="{{ $conversation->id }}">
    <form class="lc-search-form">
        <div class="input-group">
            <input type="text" class="form-control lc-number" placeholder="{{ __('Conversation number') }}" aria-label="{{ __('Conversation number') }}" autocomplete="off">
            <span class="input-group-btn">
                <button type="submit" class="btn btn-default lc-search-btn" data-loading-text="{{ __('Search') }}…">{{ __('Search') }}</button>
            </span>
        </div>
    </form>
    <div class="lc-search-result margin-top hidden"></div>
    @if (count($candidates))
        <p class="text-help margin-top">{{ __('Other conversations of this customer') }}</p>
        <div class="lc-candidates">
            @foreach ($candidates as $candidate)
                @include('linkedconversations::partials/search_result', ['conversation' => $candidate])
            @endforeach
        </div>
    @endif
</div>
