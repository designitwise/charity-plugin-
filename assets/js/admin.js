(function($){
  "use strict";

  // Ensure DOM is ready
  $(function(){

    var $table   = $("#donation-rows-table");
    if (!$table.length) return;

    var $tbody   = $table.find("tbody");
    var $addBtn  = $("#donation-row-add");

    // Template for a new row (matches admin-metabox.php names and classes)
    function buildRow(){
      return [
        '<tr>',
          '<td><input type="number" step="0.01" min="0" name="donation_rows_amount[]" value="" class="widefat" placeholder="10.00"></td>',
          '<td><input type="text" name="donation_rows_desc[]" value="" class="widefat" placeholder="What this supports"></td>',
          '<td><button type="button" class="button link-delete donation-row-remove">Remove</button></td>',
        '</tr>'
      ].join("");
    }

    // Add new row
    $addBtn.on("click", function(e){
      e.preventDefault();
      // Append after last row (create <tbody> if missing)
      if (!$tbody.length){
        $tbody = $("<tbody/>").appendTo($table);
      }
      $tbody.append(buildRow());
    });

    // Remove row — use event delegation so it works for future rows too
    $(document).on("click", ".donation-row-remove", function(e){
      e.preventDefault();
      var $tr = $(this).closest("tr");
      // If there's only one row left, clear it instead of removing (optional)
      var total = $tbody.find("tr").length;
      if (total <= 1){
        $tr.find('input[type="number"]').val("");
        $tr.find('input[type="text"]').val("");
      } else {
        $tr.remove();
      }
    });

  });

})(jQuery);