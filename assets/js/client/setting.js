$("#tab_tag").click(function () {
  reset();
  $("#tabbody_tag").removeClass("d-none");
  $("#tab_tag").addClass("btn-secondary");
});
$("#tab_link").click(function () {
  reset();
  $("#tabbody_link").removeClass("d-none");
  $("#tab_link").addClass("btn-secondary");
});
$("#tab_notice").click(function () {
  reset();
  $("#tabbody_notice").removeClass("d-none");
  $("#tab_notice").addClass("btn-secondary");
});
$("#tab_form").click(function () {
  reset();
  $("#tabbody_form").removeClass("d-none");
  $("#tab_form").addClass("btn-secondary");
});
$("#tab_resend").click(function () {
  reset();
  $("#tabbody_resend").removeClass("d-none");
  $("#tab_resend").addClass("btn-secondary");
});
$("#tab_other").click(function () {
  reset();
  $("#tabbody_other").removeClass("d-none");
  $("#tab_other").addClass("btn-secondary");
});

function reset() {
  $(".tabbody").addClass("d-none");
  $(".tab_btn").addClass("btn-primary");
  $(".tab_btn").removeClass("btn-secondary");
}