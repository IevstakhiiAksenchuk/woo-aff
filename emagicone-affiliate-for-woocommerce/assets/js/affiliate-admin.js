jQuery(document).ready(function ($) {
  $("#use_recaptcha")
    .change(function () {
      if (this.checked) {
        $(".recaptcha-fields").show();
      } else {
        $(".recaptcha-fields").hide();
      }
    })
    .change();
});

jQuery(document).ready(function ($) {
  $(".approve-payout, .decline-payout").click(function () {
    var payoutId = $(this).data("id");
    var actionType = $(this).hasClass("approve-payout") ? "approve" : "decline";
    var adminNotes = $("#admin_notes_" + payoutId).val();

    var data = {
      action: "handle_payout_action",
      payout_id: payoutId,
      action_type: actionType,
      admin_notes: adminNotes,
      nonce: affiliateAdmin.nonce,
    };

    $.post(ajaxurl, data, function (response) {
      if (response.success) {
        alert("Payout " + actionType + "d successfully.");
        window.location.reload();
      } else {
        alert("Error: " + response.data);
      }
    });
  });
});
