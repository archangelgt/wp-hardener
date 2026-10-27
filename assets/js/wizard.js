(function () {
  "use strict";

  var form = document.getElementById("seraph-wph-wizard-form");
  if (!form) return;

  function selectedProfile() {
    var checked = form.querySelector('input[name="profile"]:checked');
    return checked ? checked.value : "standard";
  }

  function syncRules() {
    var profile = selectedProfile();
    form.querySelectorAll(".seraph-wph-rule").forEach(function (row) {
      var profiles = (row.getAttribute("data-profiles") || "").split(",");
      var show =
        profile === "custom" ||
        profiles.indexOf(profile) !== -1;
      if (profile === "blog" && row.querySelector('input[value="disable_comments"]')) {
        show = false;
        var input = row.querySelector("input");
        if (input) input.checked = false;
      }
      row.classList.toggle("is-hidden", !show);
      var box = row.querySelector('input[type="checkbox"]');
      if (box && show && profile !== "custom") {
        box.checked = true;
      }
    });
  }

  form.querySelectorAll('input[name="profile"]').forEach(function (radio) {
    radio.addEventListener("change", syncRules);
  });

  syncRules();
})();
