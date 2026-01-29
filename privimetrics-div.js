;(() => {
  /* =========================================================
    FIND CURRENT SCRIPT
  ========================================================= */
  var s = document.currentScript
  if (!s) return

  var code = s.getAttribute("data-privimetrics-code")
  if (!code) return

  /* =========================================================
    CREATE HIDDEN DIV
  ========================================================= */
  var noTrackIp = s.getAttribute("data-not-track-ip") === "true"

  var divId = "privimetrics-analytics-system-data"
  var d = document.getElementById(divId)

  if (!d) {
    d = document.createElement("div")
    d.id = divId
    d.style.display = "none"

    var endpoint = s.src.replace(/\/[^/]+$/, "/privimetrics.php")

    d.setAttribute("data-k", code)
    d.setAttribute("data-e", endpoint)

    document.documentElement.appendChild(d)
  }

  /* =========================================================
    CREATE TRACKING IMG (READS FROM DIV)
  ========================================================= */
  function sendTracking() {
    try {
      var k = d.getAttribute("data-k")
      var e = d.getAttribute("data-e")

      if (!k || !e) return

      var tz = ""
      try {
        tz = Intl.DateTimeFormat().resolvedOptions().timeZone || ""
      } catch (_) {}

      var lang = navigator.language || navigator.userLanguage || ""

      var img = document.createElement("img")
      img.width = 1
      img.height = 1
      img.style.display = "none"

      img.src =
        e +
        "?t=" +
        encodeURIComponent(k) +
        "&p=" +
        encodeURIComponent(window.location.href) +
        "&title=" +
        encodeURIComponent(document.title || "") +
        "&r=" +
        encodeURIComponent(document.referrer || "d") +
        "&js=1" +
        "&track-ip=" +
        encodeURIComponent(noTrackIp ? "false" : "true") +
        "&tz=" +
        encodeURIComponent(tz) +
        "&lang=" +
        encodeURIComponent(lang) +
        "&z=" +
        Math.random()

      document.documentElement.appendChild(img)
    } catch (_) {}
  }

  if (document.readyState === "complete" || document.readyState === "interactive") {
    sendTracking()
  } else {
    document.addEventListener("DOMContentLoaded", sendTracking)
  }
})()
