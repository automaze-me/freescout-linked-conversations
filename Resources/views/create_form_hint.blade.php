<div class="form-group lc-pending" data-lc-ajax-url="{{ route('linkedconversations.ajax') }}">
    <div class="col-sm-9 col-sm-offset-2">
        <div class="alert alert-info lc-pending-text">
            <a href="#" class="lc-pending-remove pull-right" role="button" title="{{ __('Do not link') }}" aria-label="{{ __('Do not link') }}">&times;</a>
            <i class="glyphicon glyphicon-link"></i>
            {{ __('Will be linked to #:number ":subject" after creating', ['number' => $target->number, 'subject' => $target->getSubject()]) }}
        </div>
        <input type="hidden" name="{{ \Modules\LinkedConversations\Services\Actions::LINK_TO_PARAM }}" value="{{ $target->id }}">
    </div>
</div>
