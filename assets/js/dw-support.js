// assets/js/dw-support.js
(function ($) {
  // -------- helpers --------
  function getCurrencySymbol($scope) {
    var txt = $scope.find('.woocommerce-Price-currencySymbol').first().text().trim();
    return txt || '£';
  }

  function parseAmount(text) {
    var s = (text || '').replace(/[^0-9.,]/g, '');
    if (s.indexOf(',') > -1 && s.indexOf('.') > -1) {
      var lastComma = s.lastIndexOf(',');
      var lastDot = s.lastIndexOf('.');
      if (lastComma > lastDot) {
        s = s.replace(/\./g, '').replace(',', '.');
      } else {
        s = s.replace(/,/g, '');
      }
    } else if (s.indexOf(',') > -1) {
      s = s.replace(',', '.');
    }
    var n = parseFloat(s);
    return isNaN(n) ? 0 : n;
  }

  function getSubtotal($scope) {
    var $amt = $scope.find('.woocommerce-mini-cart__total .woocommerce-Price-amount').last();
    if (!$amt.length) $amt = $scope.find('.woocommerce-mini-cart__total .amount').last();
    return parseAmount($amt.text());
  }

  function sumSelectedSupport($scope) {
    var sum = 0;
    $scope.find('.dw-support-toggle:checked').each(function () {
      sum += parseFloat($(this).data('amount')) || 0;
    });
    return sum;
  }

  function ensureTotalNode($scope) {
    // place Total directly after the Subtotal line
    var $subLine = $scope.find('.woocommerce-mini-cart__total').last();
    var $total = $scope.find('p.dw-mini-total.extra-total, p.dw-mini-total');
    if (!$total.length) {
      $total = $(
        '<p class="dw-mini-total extra-total">' +
          '<strong>Total:</strong> ' +
          '<span class="woocommerce-Price-amount amount"><bdi></bdi></span>' +
          ' <span class="dw-total-status" aria-live="polite" style="display:none;">Loading…</span>' +
        '</p>'
      );
      if ($subLine.length) $total.insertAfter($subLine);
      else $scope.append($total);
    } else if (!$total.find('.dw-total-status').length) {
      $total.append(' <span class="dw-total-status" aria-live="polite" style="display:none;">Loading…</span>');
    }
    return $total;
  }

  function showStatus($total, on) {
    var $s = $total.find('.dw-total-status');
    if (!$s.length) return;
    if (on) {
      $total.addClass('dw-total-loading');
      $s.stop(true, true).fadeIn(120);
    } else {
      $total.removeClass('dw-total-loading');
      $s.stop(true, true).fadeOut(120);
    }
  }

  function formatMoney(symbol, value) {
    return symbol + value.toFixed(2);
  }

  function scopeContainer() {
    // works for widget + custom flyout
    var $scope = $('.dw-mini-cart, .woocommerce-mini-cart, #dw-flycart, .widget_shopping_cart_content').first();
    if (!$scope.length) $scope = $(document.body);
    return $scope;
  }

  function instantTotalUpdate() {
    var $scope = scopeContainer();
    var symbol = getCurrencySymbol($scope);
    var subtotal = getSubtotal($scope);
    var support = sumSelectedSupport($scope);
    var total = subtotal + support;

    var $totalNode = ensureTotalNode($scope);
    $totalNode.find('bdi').text(formatMoney(symbol, total));
    return $totalNode;
  }

  function collectKeys($scope) {
    var keys = [];
    $scope.find('.dw-support-toggle:checked').each(function () {
      var k = $(this).data('key');
      if (k && keys.indexOf(k) === -1) keys.push(k);
    });
    return keys;
  }

  function sendServerUpdate(keys) {
    // Expect DW_SUPPORT to be localized from PHP; fallback to admin-ajax.php if missing
    var ajaxUrl = (window.DW_SUPPORT && DW_SUPPORT.ajax) || (window.ajaxurl || '/wp-admin/admin-ajax.php');
    var nonce = (window.DW_SUPPORT && DW_SUPPORT.nonce) || '';

    return $.post(ajaxUrl, {
      action: 'dw_support_update',
      keys: JSON.stringify(keys || []),
      _wpnonce: nonce
    });
  }

  // -------- events --------
  $(document).on('change', '.dw-support-toggle', function () {
    var $wrap = $(this).closest('.dw-support-block');

    // 1) Instant visual update (no wait)
    var $totalNode = instantTotalUpdate();
    showStatus($totalNode, true); // show "Loading…"

    // 2) Sync to server; then refresh Woo fragments to confirm official total
    var keys = collectKeys($wrap.length ? $wrap : scopeContainer());
    sendServerUpdate(keys).always(function () {
      $(document.body).trigger('wc_fragment_refresh');
    });
  });

  // When Woo replaces fragments, re-create/update our Total node & hide "Loading…"
  $(document.body).on('wc_fragments_loaded wc_fragments_refreshed', function () {
    var $totalNode = instantTotalUpdate();
    showStatus($totalNode, false);
  });

  // First paint
  $(function () {
    instantTotalUpdate();
  });
})(jQuery);