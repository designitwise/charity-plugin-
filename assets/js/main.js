(function(){
  function refreshCartAndCheckout(){
    if (window.jQuery){
      jQuery(document.body).trigger('wc_fragment_refresh');
      jQuery(document.body).trigger('update_checkout');
      jQuery(document.body).trigger('updated_wc_div');
    }
  }

  function postSel(data){
    if (!window.jQuery || !window.DonExtras) return;
    jQuery.post(DonExtras.ajax, Object.assign({
      action: 'don_cpt_update_extras_products',
      _wpnonce: DonExtras.nonce
    }, data), function(){
      refreshCartAndCheckout();
    });
  }

  function syncHidden(){
    var selected = [];
    document.querySelectorAll('.extra-cause-prod:checked').forEach(function(c){
      selected.push({
        key:    c.getAttribute('data-key'),
        label:  c.getAttribute('data-label'),
        amount: c.getAttribute('data-amount')
      });
    });
    var cover = (document.querySelector('.extra-cover') && document.querySelector('.extra-cover').checked) ? '1' : '0';
    var f1 = document.getElementById('don_cpt_selected_extras');
    var f2 = document.getElementById('don_cpt_cover');
    if (f1) f1.value = JSON.stringify(selected);
    if (f2) f2.value = cover;
    if (window.jQuery) { jQuery(document.body).trigger('update_checkout'); }
  }

  document.addEventListener('change', function(e){
    if (e.target.classList.contains('extra-cause-prod')){
      var selected = [];
      document.querySelectorAll('.extra-cause-prod:checked').forEach(function(c){
        selected.push({
          key:    c.getAttribute('data-key'),
          label:  c.getAttribute('data-label'),
          amount: c.getAttribute('data-amount')
        });
      });
      postSel({ selected: JSON.stringify(selected) });
      syncHidden();
      refreshCartAndCheckout();
    }
    if (e.target.classList.contains('extra-cover')){
      postSel({ cover: e.target.checked ? '1' : '0' });
      syncHidden();
      refreshCartAndCheckout();
    }
  });

  // seed once
  syncHidden();
})();