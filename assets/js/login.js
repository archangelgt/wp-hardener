/**
 * Seraph secure login — light motion / focus polish.
 */
(function () {
  "use strict";

  var root = document.querySelector("[data-seraph-login]");
  if (!root) return;

  var reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  var panel = document.getElementById("login");
  var layout = document.querySelector(".seraph-login-layout");
  var brand = document.querySelector(".seraph-login-brand");

  // Move WP #login into the branded panel slot if WP printed it as a sibling.
  if (layout && panel && brand && !brand.nextElementSibling?.contains?.(panel)) {
    var holder = document.querySelector(".seraph-login-panel");
    if (holder && panel.parentElement !== holder) {
      holder.appendChild(panel);
    }
  }

  if (reduce) return;

  var stage = document.querySelector(".seraph-login-stage");
  if (!stage) return;

  var mx = 0;
  var my = 0;
  var tx = 0;
  var ty = 0;

  window.addEventListener(
    "pointermove",
    function (event) {
      var x = event.clientX / window.innerWidth - 0.5;
      var y = event.clientY / window.innerHeight - 0.5;
      mx = x * 12;
      my = y * 10;
    },
    { passive: true }
  );

  function tick() {
    tx += (mx - tx) * 0.06;
    ty += (my - ty) * 0.06;
    stage.style.transform = "translate3d(" + tx.toFixed(2) + "px," + ty.toFixed(2) + "px,0)";
    window.requestAnimationFrame(tick);
  }

  window.requestAnimationFrame(tick);

  var user = document.getElementById("user_login");
  if (user && !user.value) {
    window.setTimeout(function () {
      user.focus();
    }, 350);
  }
})();
