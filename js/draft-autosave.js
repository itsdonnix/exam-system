/**
 * DraftAutoSave - Auto-save exam drafts to localStorage
 * Standalone module with zero app dependencies
 *
 * Usage:
 *   DraftAutoSave.init({
 *     key: 'exam_draft_autosave',
 *     interval: 30000,
 *     collectData: () => ({ ... }),
 *     onSave: () => { ... },
 *   });
 */
const DraftAutoSave = {
  _key: "exam_draft_autosave",
  _interval: 30000,
  _collectData: null,
  _onSave: null,
  _timer: null,
  _dirty: false,
  _destroyed: false,
  _boundBeforeUnload: null,
  _boundKeyDown: null,

  init(options = {}) {
    this._key = options.key || "exam_draft_autosave";
    this._interval = options.interval || 30000;
    this._collectData = options.collectData || (() => ({}));
    this._onSave = options.onSave || (() => {});
    this._dirty = false;
    this._destroyed = false;

    this._boundBeforeUnload = this._handleBeforeUnload.bind(this);
    this._boundKeyDown = this._handleKeyDown.bind(this);

    window.addEventListener("beforeunload", this._boundBeforeUnload);
    document.addEventListener("keydown", this._boundKeyDown);

    this._startTimer();
  },

  destroy() {
    this._destroyed = true;
    if (this._timer) {
      clearInterval(this._timer);
      this._timer = null;
    }
    if (this._boundBeforeUnload) {
      window.removeEventListener("beforeunload", this._boundBeforeUnload);
    }
    if (this._boundKeyDown) {
      document.removeEventListener("keydown", this._boundKeyDown);
    }
    this._boundBeforeUnload = null;
    this._boundKeyDown = null;
  },

  markDirty() {
    if (this._destroyed) return;
    this._dirty = true;
    this._resetTimer();
  },

  isDirty() {
    return this._dirty;
  },

  save() {
    if (!this._collectData || this._destroyed) return false;

    try {
      const data = this._collectData();
      const payload = {
        data: data,
        savedAt: new Date().toISOString(),
        examId: data.exam_id || null,
      };
      localStorage.setItem(this._key, JSON.stringify(payload));
      this._dirty = false;
      if (this._onSave) this._onSave();
      return true;
    } catch (e) {
      if (e.name === "QuotaExceededError" || e.code === 22) {
        console.warn("[DraftAutoSave] localStorage quota exceeded");
      } else {
        console.error("[DraftAutoSave] Save error:", e);
      }
      return false;
    }
  },

  load() {
    try {
      const raw = localStorage.getItem(this._key);
      if (!raw) return null;
      return JSON.parse(raw);
    } catch (e) {
      console.error("[DraftAutoSave] Load error:", e);
      return null;
    }
  },

  clear() {
    try {
      localStorage.removeItem(this._key);
    } catch (e) {
      console.error("[DraftAutoSave] Clear error:", e);
    }
  },

  hasData() {
    return this.load() !== null;
  },

  getLastSavedTime() {
    const payload = this.load();
    if (!payload || !payload.savedAt) return null;
    return new Date(payload.savedAt);
  },

  _startTimer() {
    if (this._timer) clearInterval(this._timer);
    this._timer = setInterval(() => {
      if (this._dirty && !this._destroyed) {
        this.save();
      }
    }, this._interval);
  },

  _resetTimer() {
    this._startTimer();
  },

  _handleBeforeUnload(event) {
    if (this._dirty) {
      this.save();
      event.preventDefault();
      event.returnValue = "";
    }
  },

  _handleKeyDown(event) {
    if ((event.ctrlKey || event.metaKey) && event.key === "s") {
      event.preventDefault();
      this.save();
    }
  },
};

window.DraftAutoSave = DraftAutoSave;