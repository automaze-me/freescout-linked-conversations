/**
 * Linked Conversations module.
 * URLs and the conversation id come from data attributes rendered server-side.
 */

function lcAjax(root, data, callback)
{
	data.conversation_id = root.attr('data-lc-conversation-id');
	fsAjax(data, root.attr('data-lc-ajax-url'), callback, true);
}

function lcErrorHtml(response)
{
	var msg = (response && response.msg) ? response.msg : Lang.get("messages.error_occurred");
	return '<p class="text-danger">'+htmlEscape(msg)+'</p>';
}

// "Link Conversation" dialog (data-modal-on-show).
function initLinkedConversationsLinkDialog(modal)
{
	var dialog = modal.find('.lc-dialog:first');
	var result = dialog.find('.lc-search-result:first');

	dialog.find('.lc-search-form:first').on('submit', function(e) {
		e.preventDefault();
		var button = dialog.find('.lc-search-btn:first');
		button.button('loading');
		lcAjax(dialog, {action: 'search', number: dialog.find('.lc-number:first').val()}, function(response) {
			button.button('reset');
			result.html(isAjaxSuccess(response) ? response.html : lcErrorHtml(response)).removeClass('hidden');
		});
	});

	dialog.on('click', '.lc-link-btn', function(e) {
		e.preventDefault();
		var button = $(this);
		button.button('loading');
		lcAjax(dialog, {action: 'link', linked_id: button.attr('data-lc-linked-id')}, function(response) {
			if (isAjaxSuccess(response)) {
				window.location.href = '';
			} else {
				button.button('reset');
				result.html(lcErrorHtml(response)).removeClass('hidden');
			}
		});
	});

	dialog.find('.lc-number:first').focus();
}

// "New Linked Conversation" mailbox picker (data-modal-on-show).
function initLinkedConversationsNewDialog(modal)
{
	var dialog = modal.find('.lc-new-dialog:first');
	dialog.find('.lc-new-continue:first').on('click', function(e) {
		e.preventDefault();
		var url = dialog.find('.lc-mailbox:first').val();
		if (url) {
			window.location.href = url;
		}
	});
}

// Sidebar: unlink after confirmation.
$(document).on('click', '.lc-sidebar .lc-unlink', function(e) {
	e.preventDefault();
	var link = $(this);
	var root = link.closest('.lc-sidebar');
	showModalConfirm(htmlEscape(link.attr('data-lc-confirm')), 'lc-confirm-unlink', {
		on_show: function(modal) {
			modal.children().find('.lc-confirm-unlink:first').click(function() {
				modal.modal('hide');
				lcAjax(root, {action: 'unlink', linked_id: link.attr('data-lc-linked-id')}, function(response) {
					if (isAjaxSuccess(response)) {
						window.location.href = '';
					} else {
						showAjaxResult(response);
					}
				});
			});
		}
	}, htmlEscape(link.attr('data-lc-ok')));
});
