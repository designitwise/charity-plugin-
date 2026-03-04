jQuery(function($){
  // Repeater for Extras rows
  $('#extras-add').on('click', function(){
    var row = '<tr>' +
      '<td><input type="text" name="extras_label[]" value="" class="widefat" placeholder="Cause label"></td>' +
      '<td><input type="number" step="0.01" min="0" name="extras_amount[]" value="" class="widefat" placeholder="0.00"></td>' +
      '<td><button type="button" class="button link-delete extras-remove">Remove</button></td>' +
      '</tr>';
    $('#extras-rows tbody').append(row);
  });
  $(document).on('click', '.extras-remove', function(){
    $(this).closest('tr').remove();
  });
});

/* Injected: ensure per-form post_id is sent */
(function($){
  // Remember the wrapper ID on press/click
  $(document).on('mousedown touchstart pointerdown', '.donation-submit', function(){
    var $w = $(this).closest('.donation-wrapper');
    var pid = parseInt($w.data('post-id'),10) || 0;
    if (pid) { window._dw_last_post_id = pid; try { if (window.DonationsCPT) { DonationsCPT.post_id = pid; } } catch(e){} }
  });

  // If a request goes out without post_id, inject it
  $(document).ajaxSend(function(e, xhr, settings){
    try {
      if (!settings || !settings.data || typeof settings.data !== 'string') return;
      if (settings.data.indexOf('action=donation_add_to_cart') === -1) return;
      var pid = window._dw_last_post_id || 0;
      if (!pid) return;
      // If post_id missing or zero, append ours
      var hasPost = /(?:^|&)post_id=/.test(settings.data);
      var hasZero = /(?:^|&)post_id=0(?:&|$)/.test(settings.data);
      if (!hasPost || hasZero) {
        settings.data += (settings.data.indexOf('&')>-1 ? '&' : '') + 'post_id=' + encodeURIComponent(pid);
      }
    } catch(err) {}
  });
})(jQuery);