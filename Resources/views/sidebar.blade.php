<div class="conv-sidebar-block lc-sidebar" data-lc-ajax-url="{{ route('linkedconversations.ajax') }}" data-lc-conversation-id="{{ $conversation->id }}">
    <div class="panel-group accordion accordion-empty">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h4 class="panel-title">
                    <a data-toggle="collapse" href=".lc-collapse">{{ __('Linked Conversations') }} ({{ count($visible) + $hidden_count }})
                        <b class="caret"></b>
                    </a>
                </h4>
            </div>
            <div class="lc-collapse panel-collapse collapse in">
                <div class="panel-body">
                    <ul class="sidebar-block-list lc-list">
                        @foreach ($visible as $linked)
                            <li class="lc-item">
                                <a href="#" class="lc-unlink" role="button" data-lc-linked-id="{{ $linked->id }}" data-lc-confirm="{{ __('Unlink conversation #:number?', ['number' => $linked->number]) }}" data-lc-ok="{{ __('Unlink') }}" title="{{ __('Unlink') }}" aria-label="{{ __('Unlink') }}">&times;</a>
                                <a href="{{ $linked->url() }}" class="help-link lc-link">#{{ $linked->number }} {{ $linked->getSubject() }}</a>
                                <div class="lc-meta">
                                    <span class="lc-status lc-status-{{ \Modules\LinkedConversations\Services\Presenter::statusKey($linked) }}" aria-hidden="true">&#9679;</span>
                                    {{ \Modules\LinkedConversations\Services\Presenter::statusLabel($linked) }}
                                    · {{ \Modules\LinkedConversations\Services\Presenter::assigneeName($linked) }}
                                    @if ($linked->mailbox_id != $conversation->mailbox_id)
                                        · {{ $linked->mailbox->name }}
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                    @if ($hidden_count == 1)
                        <div class="lc-hidden">{{ __('+1 linked conversation in another mailbox') }}</div>
                    @elseif ($hidden_count > 1)
                        <div class="lc-hidden">{{ __('+:count linked conversations in other mailboxes', ['count' => $hidden_count]) }}</div>
                    @endif
                    <div class="lc-actions">
                        <a href="{{ \Modules\LinkedConversations\Services\Actions::linkDialogUrl($conversation) }}" @foreach (\Modules\LinkedConversations\Services\Actions::linkDialogAttrs() as $attr => $value) {{ $attr }}="{{ $value }}" @endforeach>+ {{ __('Link') }}</a>
                        <a href="{{ $new_target['url'] }}" @foreach ($new_target['attrs'] as $attr => $value) {{ $attr }}="{{ $value }}" @endforeach>+ {{ __('New Conversation') }}</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
