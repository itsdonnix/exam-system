/**
 * ExamSafe Security Module
 * Anti-cheat system untuk ujian online SMA
 */

const ExamSecurity = {
  violationCount: 0,
  maxViolations: 3,
  isExamActive: false,
  monitorInterval: null,
  violations: [],
  debugMode: false,

  init() {
    // Check for debug flag BEFORE setting up any security
    this.debugMode = window.DEBUG_DISABLE_VIOLATIONS === true;

    if (this.debugMode) {
      console.log(
        "%c🔧 ExamSecurity: DEBUG MODE - All violation detectors DISABLED",
        "background: #f59e0b; color: #000; font-size: 14px; padding: 4px 8px; border-radius: 4px; font-weight: bold"
      );
      this.isExamActive = true; // Keep active but no monitoring
      return; // EXIT EARLY - don't set up any security listeners
    }

    console.log("[ExamSafe] Security module initialized (Production Mode)");
    this.isExamActive = true;
    this.blockKeyboardShortcuts();
    this.blockContextMenu();
    this.blockCopyPaste();
    this.monitorTabSwitch();
    this.monitorFullscreen();
    this.blockDevTools();
    this.preventNavigation();
  },

  // ===== FULLSCREEN =====
  requestFullscreen() {
    if (this.debugMode) return;
    const el = document.documentElement;
    if (el.requestFullscreen) el.requestFullscreen();
    else if (el.webkitRequestFullscreen) el.webkitRequestFullscreen();
    else if (el.mozRequestFullScreen) el.mozRequestFullScreen();
  },

  monitorFullscreen() {
    if (this.debugMode) return;
    const checkFullscreen = () => {
      if (!this.isExamActive) return;
      const isFS =
        document.fullscreenElement ||
        document.webkitFullscreenElement ||
        document.mozFullScreenElement;
      if (!isFS) {
        this.triggerViolation("Keluar dari mode fullscreen terdeteksi!");
        setTimeout(() => this.requestFullscreen(), 500);
      }
    };
    document.addEventListener("fullscreenchange", checkFullscreen);
    document.addEventListener("webkitfullscreenchange", checkFullscreen);
    document.addEventListener("mozfullscreenchange", checkFullscreen);
  },

  // ===== KEYBOARD BLOCKING =====
  blockKeyboardShortcuts() {
    if (this.debugMode) return;
    const blockedKeys = [
      { ctrl: true, key: "t" },
      { ctrl: true, key: "n" },
      { ctrl: true, key: "w" },
      { ctrl: true, key: "r" },
      { ctrl: true, key: "l" },
      { ctrl: true, key: "u" },
      { ctrl: true, key: "a" },
      { ctrl: true, key: "c" },
      { ctrl: true, key: "v" },
      { ctrl: true, key: "x" },
      { ctrl: true, key: "p" },
      { ctrl: true, key: "s" },
      { ctrl: true, key: "f" },
      { ctrl: true, key: "h" },
      { ctrl: true, key: "j" },
      { ctrl: true, shift: true, key: "i" },
      { ctrl: true, shift: true, key: "j" },
      { ctrl: true, shift: true, key: "c" },
      { alt: true, key: "F4" },
      { alt: true, key: "Tab" },
      { key: "F12" },
      { key: "F5" },
      { key: "F11" },
      { key: "Escape" },
      { meta: true, key: "Tab" },
    ];

    document.addEventListener(
      "keydown",
      (e) => {
        if (!this.isExamActive) return;
        for (const blocked of blockedKeys) {
          if (
            blocked.ctrl &&
            e.ctrlKey &&
            blocked.key &&
            e.key.toLowerCase() === blocked.key.toLowerCase()
          ) {
            if (blocked.shift && !e.shiftKey) continue;
            e.preventDefault();
            e.stopPropagation();
            this.showBlockedAction(
              `Shortcut Ctrl+${e.key.toUpperCase()} diblokir`
            );
            return;
          }
          if (blocked.alt && e.altKey && blocked.key && e.key === blocked.key) {
            e.preventDefault();
            e.stopPropagation();
            this.showBlockedAction(`Shortcut Alt+${e.key} diblokir`);
            return;
          }
          if (
            !blocked.ctrl &&
            !blocked.alt &&
            blocked.key &&
            e.key === blocked.key
          ) {
            e.preventDefault();
            e.stopPropagation();
            this.showBlockedAction(`Tombol ${e.key} diblokir selama ujian`);
            return;
          }
        }
      },
      true
    );
  },

  // ===== COPY PASTE BLOCKING =====
  blockCopyPaste() {
    if (this.debugMode) return;
    ["copy", "cut", "paste", "selectstart"].forEach((evt) => {
      document.addEventListener(evt, (e) => {
        if (!this.isExamActive) return;
        e.preventDefault();
        this.showBlockedAction("Copy/Paste diblokir selama ujian!");
      });
    });
  },

  // ===== RIGHT CLICK BLOCKING =====
  blockContextMenu() {
    if (this.debugMode) return;
    document.addEventListener("contextmenu", (e) => {
      if (!this.isExamActive) return;
      e.preventDefault();
      this.showBlockedAction("Klik kanan diblokir selama ujian!");
    });
  },

  // ===== TAB/WINDOW SWITCH MONITORING =====
  monitorTabSwitch() {
    if (this.debugMode) return;
    document.addEventListener("visibilitychange", () => {
      if (!this.isExamActive) return;
      if (document.hidden) {
        this.triggerViolation("Perpindahan tab/jendela terdeteksi!");
      }
    });

    window.addEventListener("blur", () => {
      if (!this.isExamActive) return;
      if (this._suppressBlurUntil && Date.now() < this._suppressBlurUntil)
        return;
      this.triggerViolation("Jendela browser kehilangan fokus!");
    });
  },

  // ===== DEVTOOLS BLOCKING =====
  blockDevTools() {
    if (this.debugMode) return;
    const threshold = 160;
    setInterval(() => {
      if (!this.isExamActive) return;
      if (
        window.outerWidth - window.innerWidth > threshold ||
        window.outerHeight - window.innerHeight > threshold
      ) {
        this.triggerViolation("Developer Tools terdeteksi dibuka!");
      }
    }, 1000);

    document.addEventListener("keydown", (e) => {
      if (
        e.key === "F12" ||
        (e.ctrlKey &&
          e.shiftKey &&
          ["I", "J", "C"].includes(e.key.toUpperCase()))
      ) {
        e.preventDefault();
      }
    });
  },

  // ===== PREVENT NAVIGATION =====
  preventNavigation() {
    if (this.debugMode) return;
    window.addEventListener("beforeunload", (e) => {
      if (!this.isExamActive) return;
      e.preventDefault();
      e.returnValue =
        "Ujian sedang berlangsung! Apakah Anda yakin ingin keluar?";
      return e.returnValue;
    });

    history.pushState(null, null, location.href);
    window.addEventListener("popstate", () => {
      if (this.isExamActive) {
        history.pushState(null, null, location.href);
        this.showBlockedAction("Tombol back diblokir selama ujian!");
      }
    });
  },

  // ===== VIOLATION HANDLER =====
  triggerViolation(reason) {
    if (this.debugMode) {
      console.log(
        `%c🚫 VIOLATION BLOCKED (Debug): ${reason}`,
        "color: #f59e0b"
      );
      return;
    }

    this.violationCount++;
    const timestamp = new Date().toLocaleTimeString("id-ID");
    this.violations.push({ reason, timestamp, count: this.violationCount });

    console.warn(`[ExamSafe] VIOLATION #${this.violationCount}: ${reason}`);
    this.showViolationWarning(reason);
    this.notifySupervisor(reason);

    if (this.violationCount >= this.maxViolations) {
      this.forceSubmit("Batas pelanggaran tercapai");
    }
  },

  showViolationWarning(reason) {
    if (this.debugMode) return;
    const overlay = document.getElementById("violation-overlay");
    const msg = document.getElementById("violation-msg");
    const count = document.getElementById("violation-count");
    if (overlay && msg) {
      msg.textContent = reason;
      count.textContent = `Pelanggaran ${this.violationCount}/${this.maxViolations}`;
      overlay.classList.add("show");
      setTimeout(() => overlay.classList.remove("show"), 4000);
    }
  },

  showBlockedAction(msg) {
    if (this.debugMode) {
      console.log(`%c🔇 Blocked: ${msg}`, "color: #6b7280");
      return;
    }
    const toast = document.getElementById("blocked-toast");
    if (toast) {
      toast.textContent = "🚫 " + msg;
      toast.style.opacity = "1";
      toast.style.transform = "translateY(0)";
      setTimeout(() => {
        toast.style.opacity = "0";
        toast.style.transform = "translateY(20px)";
      }, 2000);
    }
  },

  notifySupervisor(reason) {
    if (this.debugMode) return;
    const data = {
      student: sessionStorage.getItem("user"),
      reason,
      timestamp: new Date().toISOString(),
      violationCount: this.violationCount,
    };
    console.log("[ExamSafe] Notifying supervisor:", data);
  },

  forceSubmit(reason) {
    if (this.debugMode) {
      console.log(
        `%c⏸️ Force submit prevented in debug mode: ${reason}`,
        "color: #f59e0b"
      );
      return;
    }
    this.isExamActive = false;
    alert(
      `⚠️ Ujian dihentikan paksa!\nAlasan: ${reason}\nJawaban Anda telah disimpan otomatis.`
    );
    if (typeof ExamEngine !== "undefined") {
      ExamEngine.submitExam(true);
    }
  },

  stop() {
    this.isExamActive = false;
    if (this.monitorInterval) clearInterval(this.monitorInterval);
    if (document.exitFullscreen && !this.debugMode) document.exitFullscreen();
  },
};

// Auto-initialize but check debug flag first
// Use setTimeout to ensure window.DEBUG_DISABLE_VIOLATIONS is set
setTimeout(() => {
  ExamSecurity.init();
}, 0);
