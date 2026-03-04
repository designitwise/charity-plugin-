(function($){
  "use strict";

  // -------- helpers ----------
  function parseMoney(val) {
    if (val == null) return 0;
    var s = String(val).replace(/[^\d,.\-]/g, '').replace(',', '.');
    var n = parseFloat(s);
    return isNaN(n) ? 0 : n;
  }

  function readCustomAmount($wrap) {
    // primary class used in your markup
    var $inp = $wrap.find('.donation-custom-input');
    if ($inp.length) {
      var v = parseMoney($inp.val());
      if (v > 0) return v;
    }
    // fallbacks (in case themes/builders rename the field)
    var $alts = $wrap.find('input[name="donation_custom"], input[name="custom_amount"], input[data-role="custom-amount"]');
    if ($alts.length) {
      var v2 = parseMoney($alts.val());
      if (v2 > 0) return v2;
    }
    return 0;
  }

  function readCardAmount($wrap) {
    var $active = $wrap.find('.donation-card.active').first();
    if ($active.length) {
      var a = parseMoney($active.data('amount'));
      if (a > 0) return a;
    }
    // fallback: any card focused/first card
    var $first = $wrap.find('.donation-card').first();
    if ($first.length) {
      var a2 = parseMoney($first.data('amount'));
      if (a2 > 0) return a2;
    }
    return 0;
  }

  function getSelectedAmount($wrap) {
    var c = readCustomAmount($wrap);
    if (c > 0) return c;
    return readCardAmount($wrap);
  }

  function getSelectedDesc($wrap) {
    if (readCustomAmount($wrap) > 0) {
      // re-use placeholder as label so admins can customize it
      var ph = $wrap.find('.donation-custom-input').attr('placeholder') || 'Custom amount';
      return ph;
    }
    var $active = $wrap.find('.donation-card.active').first();
    if ($active.length && String($active.data('desc')||'').trim()) {
      return String($active.data('desc'));
    }
    return 'Donation';
  }

  function getPostId($wrap) {
    var pid = parseInt($wrap.data('post-id'), 10);
    if (!pid && window.DonationsCPT && DonationsCPT.post_id) {
      pid = parseInt(DonationsCPT.post_id, 10) || 0;
    }
    return pid || 0;
  }

  function ajaxUrl() {
    return (window.DonationsCPT && DonationsCPT.ajax) ? DonationsCPT.ajax : '/wp-admin/admin-ajax.php';
  }

  // -------- UI behaviors ----------
  // Activate a preset card
  $(document).on('click', '.donation-card', function(){
    var $wrap = $(this).closest('.donation-wrapper');
    $wrap.find('.donation-card').removeClass('active').attr('aria-selected','false');
    $(this).addClass('active').attr('aria-selected','true');
    // Optional: clear custom field when choosing a preset to avoid ambiguity
    $wrap.find('.donation-custom-input').val('');
  });

  // Typing a custom amount deselects cards so intent is clear
  $(document).on('input', '.donation-custom-input', function(){
    var $wrap = $(this).closest('.donation-wrapper');
    if (parseMoney($(this).val()) > 0) {
      $wrap.find('.donation-card').removeClass('active').attr('aria-selected','false');
    }
  });

  // Submit on Enter in custom field
  $(document).on('keydown', '.donation-custom-input', function(e){
    if (e.key === 'Enter') {
      e.preventDefault();
      $(this).closest('.donation-wrapper').find('.donation-submit').trigger('click');
    }
  });

  // MAIN: Donate button
  $(document).on('click', '.donation-submit', function(e){
    e.preventDefault();
    var $btn  = $(this);
    var $wrap = $btn.closest('.donation-wrapper');

    var pid   = getPostId($wrap);
    var amt   = getSelectedAmount($wrap);
    var desc  = getSelectedDesc($wrap);

    if (!pid)   { alert('Could not determine the donation campaign. Please refresh the page and try again.'); return; }
    if (amt<=0) { alert('Please choose an amount or enter a valid custom amount.'); return; }

    // remember last pid (safety for ajaxSend)
    window._dw_last_post_id = pid;
    if (window.DonationsCPT) DonationsCPT.post_id = pid;

    $btn.prop('disabled', true);

    $.post(ajaxUrl(), {
      action: 'donation_add_to_cart',
      nonce:  (window.DonationsCPT ? DonationsCPT.nonce : ''),
      post_id: pid,
      amount:  amt,
      description: desc
    }).done(function(res){
      // UPDATED: open fly-out mini cart instead of redirecting
      if (res && res.success) {
        // Refresh Woo fragments (mini-cart HTML + count)
        $(document.body).trigger('wc_fragment_refresh');
        // Tell the flycart to open
        $(document.body).trigger('added_to_cart');

        // Fallback to checkout if the flycart isn't present
        if (!document.getElementById('dw-flycart') && res.data && res.data.redirect) {
          window.location = res.data.redirect;
          return;
        }

        $btn.prop('disabled', false);
        $wrap.find('.donation-msg').text('Added to basket.');
      } else {
        $btn.prop('disabled', false);
        var msg = (res && res.data && res.data.message) ? res.data.message : 'Could not add to cart. Please try again.';
        alert(msg);
      }
    }).fail(function(){
      $btn.prop('disabled', false);
      alert('Network error. Please try again.');
    });
  });

  // Safety net: ensure post_id is present
  $(document).ajaxSend(function(e, xhr, settings){
    try {
      if (!settings || !settings.data || typeof settings.data !== 'string') return;
      if (settings.data.indexOf('action=donation_add_to_cart') === -1) return;
      var pid = window._dw_last_post_id || 0;
      if (!pid) return;
      var hasPost = /(?:^|&)post_id=/.test(settings.data);
      var hasZero = /(?:^|&)post_id=0(?:&|$)/.test(settings.data);
      if (!hasPost || hasZero) {
        settings.data += (settings.data.indexOf('&')>-1 ? '&' : '') + 'post_id=' + encodeURIComponent(pid);
      }
    } catch(err) {}
  });

})(jQuery);
// Put this in your dw-support.js or donations.js
jQuery(document.body).on('removed_from_cart', function () {
  try {
    sessionStorage.removeItem('wc_fragments');
    localStorage.removeItem('wc_fragments');
    localStorage.removeItem('wc_cart_hash');
  } catch(e) {}
  jQuery(document.body).trigger('wc_fragment_refresh');
});

// Force hard navigation when clicking the mini-cart remove link
jQuery(document).on('click touchend', 'a.remove_from_cart_button', function (e) {
  e.preventDefault();
  e.stopImmediatePropagation();
  window.location.assign(this.href); // full refresh
});