console.log("Course Comparator Pro v2.1.0 – Dynamic taxonomy meta");

(function () {
  "use strict";

  // Config from PHP
  var settings = window.CCProEngineSettings || {};
  var maxLimit = parseInt(settings.maxLimit, 10) || 8;
  var taxonomySlug = settings.taxonomySlug || 'course-levels';
  var metaKeyBg = settings.metaKeyBg || 'badge_bg_color';
  var metaKeyText = settings.metaKeyText || 'badge_text_color';

  // 1. Modal helpers
  function ensureModalExists() {
    var overlay = document.getElementById("cc-custom-overlay");
    if (!overlay) {
      overlay = document.createElement("div");
      overlay.id = "cc-custom-overlay";
      overlay.innerHTML = '<div class="cc-modal-wrapper" id="cc-modal-content"></div>';
      document.body.appendChild(overlay);
      overlay.addEventListener("click", function (e) {
        if (e.target === overlay) window.closeCCModal();
      });
    }
    return overlay;
  }

  window.openCCModal = function () {
    ensureModalExists().classList.add("cc-show");
    updatePopupNow();
  };

  window.closeCCModal = function () {
    var overlay = document.getElementById("cc-custom-overlay");
    if (overlay) overlay.classList.remove("cc-show");
  };

  // 2. Local store
  window.CourseCompareStore = {
    items: new Set(),
    add: function (id) {
      this.items.add(String(id));
      this.save();
      this.sync();
    },
    remove: function (id) {
      this.items.delete(String(id));
      this.save();
      this.sync();
    },
    clear: function () {
      this.items.clear();
      this.save();
      this.sync();
    },
    has: function (id) {
      return this.items.has(String(id));
    },
    count: function () {
      return this.items.size;
    },
    getArray: function () {
      return Array.from(this.items);
    },
    save: function () {
      try { localStorage.setItem("cc_items", JSON.stringify(this.getArray())); } catch(e) {}
    },
    load: function () {
      try {
        var saved = localStorage.getItem("cc_items");
        if (saved) { var arr = JSON.parse(saved); if (Array.isArray(arr)) this.items = new Set(arr.map(String)); }
      } catch(e) {}
    },
    sync: function () { updateUI(); }
  };

  var dataCache = {};

  function updateUI() {
    var items = window.CourseCompareStore.items;
    document.querySelectorAll('.jet-add-to-store[data-store="course-compare"]').forEach(function (btn) {
      var id = btn.getAttribute("data-post");
      if (!id) return;
      var label = btn.querySelector(".jet-listing-dynamic-link__label");
      var icon = btn.querySelector(".jet-listing-dynamic-link__icon");
      var parentCard = btn.closest(".course-listing-card");

      if (items.has(id)) {
        btn.classList.add("in-store");
        if (parentCard) parentCard.classList.add("cc-selected-card");
        if (label) label.textContent = "Added";
        if (!icon) {
          icon = document.createElement("i");
          icon.className = "jet-listing-dynamic-link__icon icon icon-checked1";
          btn.insertBefore(icon, btn.firstChild);
        }
      } else {
        btn.classList.remove("in-store");
        if (parentCard) parentCard.classList.remove("cc-selected-card");
        if (label) label.textContent = "Compare";
        if (icon) icon.remove();
      }
    });

    document.querySelectorAll(".course-count").forEach(function (el) { el.textContent = items.size; });
    var bar = document.querySelector(".course-compare-bar");
    if (bar) bar.style.display = items.size > 0 ? "flex" : "none";
  }

  // 3. API helpers – now using dynamic taxonomy and meta keys
  async function fetchTermMeta(termId) {
    try {
      var r = await fetch("/wp-json/wp/v2/" + taxonomySlug + "/" + termId);
      if (!r.ok) throw new Error();
      var term = await r.json();
      return {
        name: term.name || "",
        bg: (term.meta && term.meta[metaKeyBg]) ? term.meta[metaKeyBg] : null,
        text: (term.meta && term.meta[metaKeyText]) ? term.meta[metaKeyText] : null
      };
    } catch(e) {
      return { name: "N/A", bg: null, text: null };
    }
  }

  async function fetchUni(uid) {
    try {
      var r = await fetch("/wp-json/wp/v2/universities/" + uid);
      if (!r.ok) throw new Error();
      var d = await r.json();
      var n = d.title && d.title.rendered ? d.title.rendered : "N/A";
      var logoUrl = "";
      if (d.featured_media) {
        try {
          var mediaRes = await fetch("/wp-json/wp/v2/media/" + d.featured_media);
          if (mediaRes.ok) {
            var mediaData = await mediaRes.json();
            logoUrl = (mediaData.media_details && mediaData.media_details.sizes && mediaData.media_details.sizes.medium) ? mediaData.media_details.sizes.medium.source_url : mediaData.source_url;
          }
        } catch(e) {}
      }
      return {
        name: n,
        location: (d.meta && d.meta.location) || "N/A",
        initials: n.split(" ").map(function(x){return x[0];}).join("").substring(0,2).toUpperCase(),
        logo: logoUrl
      };
    } catch(e) { return { name: "N/A", location: "N/A", initials: "U", logo: "" }; }
  }

  async function fetchCourse(pid) {
    if (dataCache[pid]) return dataCache[pid];
    try {
      var r = await fetch("/wp-json/wp/v2/academic-courses/" + pid);
      if (!r.ok) throw new Error();
      var d = await r.json();
      var m = d.meta || {};

      var levelTermId = m.course_level;
      var levelName = "N/A";
      var badgeBg = "#e4f0fb";
      var badgeText = "#2c7be5";
      if (levelTermId) {
        var termData = await fetchTermMeta(levelTermId);
        levelName = termData.name || "N/A";
        if (termData.bg) badgeBg = termData.bg;
        if (termData.text) badgeText = termData.text;
      }

      var durationTermId = m.course_duration;
      var durationName = "N/A";
      if (durationTermId) {
        try {
          var dur = await fetch("/wp-json/wp/v2/course-durations/" + durationTermId);
          if (dur.ok) {
            var durData = await dur.json();
            durationName = durData.name || "N/A";
          }
        } catch(e) {}
      }

      var intakeIds = m.course_intakes || [];
      var intakeNames = [];
      if (intakeIds.length > 0) {
        try {
          var intakesRes = await fetch("/wp-json/wp/v2/course-intakes?include=" + intakeIds.join(",") + "&per_page=100");
          if (intakesRes.ok) {
            var intakesData = await intakesRes.json();
            intakesData.sort(function(a,b){
              var oA = parseInt(a.meta && a.meta.intake_order ? a.meta.intake_order : 999, 10);
              var oB = parseInt(b.meta && b.meta.intake_order ? b.meta.intake_order : 999, 10);
              return oA - oB;
            });
            intakeNames = intakesData.map(function(t){ return t.name; });
          }
        } catch(e) {}
      }

      var u = m.university_ref ? await fetchUni(m.university_ref) : { name: "N/A", location: "N/A", initials: "C", logo: "" };

      var result = {
        id: pid,
        title: m.course_name || (d.title && d.title.rendered) || "Course #" + pid,
        level: levelName,
        levelColors: [badgeBg, badgeText],
        duration: durationName,
        fee: m.course_fee ? "RM " + Number(m.course_fee).toLocaleString() : "N/A",
        intakes: intakeNames,
        university: u.name,
        location: u.location,
        initials: u.initials,
        uniLogo: u.logo
      };
      dataCache[pid] = result;
      return result;
    } catch(e) {
      return { id: pid, title: "Course #"+pid, level: "N/A", levelColors: ["#e4f0fb","#2c7be5"], duration: "N/A", fee: "N/A", intakes: [], university: "N/A", location: "N/A", initials: "?", uniLogo: "" };
    }
  }

  // 4. Render comparison chunk (unchanged)
  function renderChunk(courses) {
    var h = '<div class="cc-grid-chunk"><div class="cc-row"><div class="cc-cell cc-label-col"></div>';
    courses.forEach(function(c, idx) {
      var lc = idx%2===0 ? "#1e398a" : "#045037";
      var bc = idx>0 ? "cc-border-left" : "";
      h += '<div class="cc-cell cc-value-col cc-course-header '+bc+'"><button class="cc-col-dismiss" data-id="'+c.id+'" title="Remove">\u2715</button><div class="cc-logo" style="background:'+(c.uniLogo?'transparent':lc)+';">'+(c.uniLogo?'<img src="'+c.uniLogo+'" alt="'+c.university+'" />':c.initials)+'</div><h3 class="cc-course-title">'+c.title+'</h3><p class="cc-course-uni">'+c.university+'</p></div>';
    });
    h += '</div>';

    function row(lbl, fn) {
      var r = '<div class="cc-row"><div class="cc-cell cc-label-col">'+lbl+'</div>';
      courses.forEach(function(c,idx){
        var bc = idx>0 ? "cc-border-left" : "";
        r += '<div class="cc-cell cc-value-col cc-value '+bc+'">'+fn(c)+'</div>';
      });
      r += '</div>';
      return r;
    }

    h += row("Qualification", function(c){ return '<span class="cc-badge-qual" style="background:'+c.levelColors[0]+';color:'+c.levelColors[1]+';">'+c.level+'</span>'; });
    h += row("University", function(c){ return c.university; });
    h += row("Location", function(c){ return c.location; });
    h += row("Duration", function(c){ var d=c.duration; if(d.toLowerCase().indexOf("year")===-1 && d.toLowerCase().indexOf("month")===-1) d+=" Years"; return d; });
    h += row("Intakes", function(c){ return c.intakes.length ? c.intakes.map(function(m){return '<span class="cc-badge-intake">'+m+'</span>';}).join("") : '<span style="color:#9ca3af;">N/A</span>'; });
    h += row("Tuition Fee", function(c){ return '<span class="cc-fee">'+c.fee+'</span>'; });
    h += '</div>';
    return h;
  }

  function renderView(courses) {
    var h = '<div class="cc-header"><h2 class="cc-title">Course Comparison</h2><div class="cc-actions"><button class="cc-btn cc-btn-pdf" id="cc-pdf-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg> Download PDF</button><button class="cc-btn cc-btn-close-popup" onclick="window.closeCCModal()">\u2715 Close</button></div></div><div class="cc-export-wrapper">';
    for (var i=0; i<courses.length; i+=4) { h += renderChunk(courses.slice(i,i+4)); }
    h += '</div>';
    return h;
  }

  // 5. PDF trigger (native PHP)
  window.downloadCCPDF = function() {
    var items = window.CourseCompareStore.getArray();
    if (items.length === 0) { alert("Please select courses first."); return; }
    var url = window.location.origin + "/?generate_comparison_pdf=native&ids=" + items.join(",");
    window.open(url, "_blank");
  };

  // 6. Popup refresh
  async function updatePopupNow() {
    var container = document.getElementById("cc-modal-content");
    if (!container) return;
    var items = window.CourseCompareStore.getArray();
    if (items.length === 0) {
      container.innerHTML = '<div class="cc-header"><h2 class="cc-title">Course Comparison</h2><button class="cc-btn cc-btn-close-popup" onclick="window.closeCCModal()">\u2715 Close</button></div><div style="text-align:center;padding:40px;color:#6b7280;">No courses selected.</div>';
      return;
    }
    container.innerHTML = '<div class="cc-loader-wrapper"><div class="cc-loader"></div></div>';
    var data = await Promise.all(items.map(function(id){ return fetchCourse(id); }));
    container.innerHTML = renderView(data);
    container.querySelectorAll(".cc-col-dismiss").forEach(function(btn){
      btn.onclick = function(e){ e.preventDefault(); window.CourseCompareStore.remove(this.getAttribute("data-id")); updatePopupNow(); };
    });
    var pdfBtn = container.querySelector("#cc-pdf-btn");
    if (pdfBtn) pdfBtn.onclick = function(e){ e.preventDefault(); window.downloadCCPDF(); };
  }

  // 7. Button hijack
  function hijackButtons() {
    document.querySelectorAll('.jet-add-to-store[data-store="course-compare"]:not([data-cc-hijacked])').forEach(function(btn){
      btn.setAttribute("data-cc-hijacked", "true");
      var newBtn = btn.cloneNode(true);
      btn.parentNode.replaceChild(newBtn, btn);
      newBtn.onclick = function(e){
        e.preventDefault(); e.stopPropagation();
        var id = this.getAttribute("data-post");
        if (!id) return;
        if (window.CourseCompareStore.has(id)) {
          window.CourseCompareStore.remove(id);
        } else {
          if (window.CourseCompareStore.count() >= maxLimit) {
            alert("Maximum " + maxLimit + " courses allowed.");
            return false;
          }
          window.CourseCompareStore.add(id);
        }
        return false;
      };
    });
  }

  document.addEventListener("click", function(e){
    if (e.target.closest(".clear-course-compare")) { e.preventDefault(); window.CourseCompareStore.clear(); }
    var openBtn = e.target.closest(".open-course-comparison-popup");
    if (openBtn) {
      e.preventDefault(); e.stopImmediatePropagation();
      if (window.CourseCompareStore.count() === 0) { alert("Please select courses first."); return; }
      window.openCCModal();
    }
  }, true);

  function init() {
    window.CourseCompareStore.load();
    ensureModalExists();
    updateUI();
    hijackButtons();
    new MutationObserver(function(){
      clearTimeout(window._ccH);
      window._ccH = setTimeout(hijackButtons, 200);
    }).observe(document.body, { childList: true, subtree: true });
  }

  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", init);
  else init();
})();