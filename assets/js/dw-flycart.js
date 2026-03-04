

(function($){
  "use strict";

  var $body    = $('body');
  var $panel   = $('#dw-flycart');
  var $overlay = $('.dw-flycart-overlay');

  function openCart(){
    // Refresh fragments right before opening to ensure latest items
    $(document.body).trigger('wc_fragment_refresh');
    $body.addClass('dw-flycart-open');
    $panel.attr('aria-hidden','false');
    $overlay.removeAttr('hidden');
  }
  function closeCart(){
    $body.removeClass('dw-flycart-open');
    $panel.attr('aria-hidden','true');
    $overlay.attr('hidden','hidden');
  }

  // Toggle actions
  $(document).on('click','.dw-flycart-toggle',function(e){ e.preventDefault(); openCart(); });
  $(document).on('click','.dw-flycart-close, .dw-flycart-overlay',function(e){ e.preventDefault(); closeCart(); });
  $(document).on('keydown',function(e){ if(e.key === 'Escape') closeCart(); });

  // 1) If add-to-cart happened via AJAX (archives), force a full reload with a flag.
  $(document.body).on('added_to_cart', function(){
    try {
      var url = new URL(window.location.href);
      if (!url.searchParams.get('dw_flycart')) {
        url.searchParams.set('dw_flycart', '1');
        window.location.replace(url.toString()); // reload current page
      }
    } catch(e){
      // Fallback if URL API not supported
      var sep = window.location.search ? '&' : '?';
      window.location.replace(window.location.href + sep + 'dw_flycart=1');
    }
  });

  // 2) On page load, open the flyout if the flag is present, then clean the URL.
  $(function(){
    try {
      var url = new URL(window.location.href);
      var flag = url.searchParams.get('dw_flycart');
      if (flag === '1' || flag === 'open') {
        openCart();
        url.searchParams.delete('dw_flycart');
        var cleaned = url.pathname + (url.searchParams.toString() ? '?' + url.searchParams.toString() : '') + url.hash;
        window.history.replaceState({}, '', cleaned);
      }
    } catch(e){
      // No-op; opening is just a nicety
    }
  });

})(jQuery);

jQuery(function($){
  $(document).on('click', '.remove_from_cart_button', function(e){
    // Optional: prevent default AJAX behavior
    // e.preventDefault();
    // Small delay to let WooCommerce do its remove if needed
    setTimeout(function(){
      location.reload();
    }, 100); // 100ms delay
  });
});