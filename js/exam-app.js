// ═══════════════════════════════════════════════════════════════
// EXAMSAFE EXAM APP — Vue 3 (CDN)
// Replaces: js/exam.js + student/exam.php inline JS
// Depends: vue@3 (global), api-client.js, toast.js, student-api.js,
//          confirm-dialog.js, security.js
// ═══════════════════════════════════════════════════════════════

const { createApp, ref, reactive, computed, watch, nextTick, onMounted } = Vue;

const { confirm, ConfirmDialog } = useConfirm();

// ── UTILITIES ────────────────────────────────────────────────

const LETTERS = ["A", "B", "C", "D", "E", "F"];

function decodeHtml(str) {
  if (!str) return "";
  const txt = document.createElement("textarea");
  txt.innerHTML = str;
  return txt.value;
}

function escapeUrl(url) {
  return encodeURI(url);
}

/**
 * Parse media_url field from question data.
 * Handles: JSON array, JSON object, plain string, HTML-encoded strings.
 * Returns: array of clean URL strings.
 */
function parseMediaUrls(mediaUrl) {
  if (!mediaUrl || !mediaUrl.trim() || mediaUrl.trim() === "[]") return [];
  try {
    const decoded = decodeHtml(mediaUrl).trim();
    if (decoded.startsWith("[") || decoded.startsWith("{")) {
      try {
        const parsed = JSON.parse(decoded);
        const arr = Array.isArray(parsed) ? parsed : [parsed];
        return arr
          .filter((u) => typeof u === "string" && u.trim().length > 5)
          .map((u) => u.trim())
          .filter(
            (u) =>
              (u.includes("uploads/") || u.startsWith("http")) &&
              !u.startsWith("[") &&
              !u.endsWith("]")
          );
      } catch (e) {
        const matches = decoded.match(/uploads\/[^"'\s\]]+/g);
        return matches || [];
      }
    }
    return [decoded];
  } catch (e) {
    return [];
  }
}

/**
 * Process raw question from API into render-ready format.
 * Decodes HTML entities, parses options, extracts media URLs.
 */
function processQuestion(q, idx) {
  const qType = q.question_type || q.type || "multiple";
  const qText = decodeHtml(q.question_text || q.text || "");

  let options = [];
  if (Array.isArray(q.options)) {
    options = q.options;
  } else if (typeof q.options === "string") {
    try {
      options = JSON.parse(q.options);
    } catch (e) {
      options = [];
    }
  }

  options = options.map((opt) => {
    if (typeof opt === "string") {
      return { text: decodeHtml(opt), image: "" };
    } else if (typeof opt === "object" && opt !== null) {
      return { text: decodeHtml(opt.text || ""), image: opt.image || "" };
    }
    return { text: "", image: "" };
  });

  if (qType === "truefalse" && options.length === 0) {
    options = [
      { text: "Benar", image: "" },
      { text: "Salah", image: "" },
    ];
  }

  const media = parseMediaUrls(q.media_url);

  let typeLabel = "Pilihan Ganda";
  if (qType === "checkbox") typeLabel = "Pilihan Ganda Kompleks";
  if (qType === "truefalse") typeLabel = "Benar / Salah";
  if (qType === "essay") typeLabel = "Essay";

  const inputType = qType === "checkbox" ? "checkbox" : "radio";

  return {
    ...q,
    _idx: idx,
    question_type: qType,
    question_text: qText,
    processed_options: options,
    media_urls: media,
    type_label: typeLabel,
    input_type: inputType,
  };
}

// ── RULE DATA (Agreement Modal) ──────────────────────────────

const RULE_SECTIONS = [
  {
    icon: "🔒",
    title: "Aturan Keamanan Ujian",
    rules: [
      "Saya tidak akan membuka tab atau jendela browser lain selama ujian berlangsung",
      "Saya tidak akan melakukan copy-paste (Ctrl+C, Ctrl+V, Ctrl+X)",
      "Saya tidak akan menggunakan klik kanan (right-click)",
      "Saya tidak akan keluar dari mode layar penuh",
      "Saya tidak akan membuka Developer Tools (F12 / Ctrl+Shift+I)",
      "Saya tidak akan menggunakan tombol pintas browser (Ctrl+T, Ctrl+W, Ctrl+R, F5)",
      "Saya tidak akan berpindah ke aplikasi lain (Alt+Tab)",
      "Saya memahami bahwa pelanggaran maksimal 3 kali akan mengakhiri ujian secara paksa",
      "Jawaban hanya dapat dikirim satu kali dan tidak dapat diubah",
    ],
  },
  {
    icon: "📱",
    title: "Persyaratan Perangkat",
    rules: [
      'Saya telah mengatur screen timeout perangkat minimal 30 menit (atau mengaktifkan fitur "Never Sleep")',
      "Saya telah mengaktifkan mode Jangan Ganggu / Do Not Disturb (DND) pada perangkat saya",
      "Saya memastikan koneksi internet stabil selama ujian berlangsung",
    ],
  },
  {
    icon: "✅",
    title: "Persetujuan Akhir",
    rules: [
      "Saya telah membaca dan memahami seluruh peraturan ujian",
      "Saya bersedia menerima sanksi jika terbukti melanggar peraturan",
    ],
  },
];

// Flatten to get total rule count & global index helper
const TOTAL_RULES = RULE_SECTIONS.reduce((sum, s) => sum + s.rules.length, 0);

function getRuleGlobalIndex(sectionIdx, ruleIdx) {
  let idx = 0;
  for (let i = 0; i < sectionIdx; i++) {
    idx += RULE_SECTIONS[i].rules.length;
  }
  return idx + ruleIdx;
}

// ── COMPOSABLES ──────────────────────────────────────────────

/**
 * useExamState — Core exam state management.
 * Questions, answers, navigation, phase tracking.
 */
function useExamState() {
  const examId = ref(window.EXAM_ID || 0);
  const studentName = ref(window.STUDENT_NAME || "Siswa");

  const questions = ref([]);
  const answers = reactive({});
  const currentQuestion = ref(0);
  const markedQuestions = ref(new Set());
  const examData = ref(null);
  const studentData = ref(null);
  const isSubmitted = ref(false);
  const phase = ref("agreement"); // 'agreement' | 'fullscreen' | 'exam' | 'result'
  const securitySettings = ref(null);
  const maxViolations = ref(3);
  const duration = ref(90);
  const loading = ref(false);

  // ── Computed ──

  const navButtons = computed(() =>
    questions.value.map((q, i) => ({
      idx: i,
      isAnswered: answers[q.id] !== undefined,
      isCurrent: currentQuestion.value === i,
      isMarked: markedQuestions.value.has(i),
    }))
  );

  const isCurrentMarked = computed(() =>
    markedQuestions.value.has(currentQuestion.value)
  );

  const isLastQuestion = computed(
    () => currentQuestion.value === questions.value - 1
  );

  const totalQuestions = computed(() => questions.value.length);

  const answeredCount = computed(() => Object.keys(answers).length);

  // ── Methods ──

  function selectOption(qId, optIdx, qType) {
    if (qType === "checkbox") {
      const current = Array.isArray(answers[qId]) ? [...answers[qId]] : [];
      const val = optIdx.toString();
      const pos = current.indexOf(val);
      if (pos >= 0) current.splice(pos, 1);
      else current.push(val);
      if (current.length > 0) answers[qId] = current;
      else delete answers[qId];
    } else if (qType === "truefalse") {
      answers[qId] = optIdx === 0 ? "true" : "false";
    } else {
      answers[qId] = optIdx.toString();
    }
  }

  function updateEssay(qId, value) {
    if (value.trim()) answers[qId] = value;
    else delete answers[qId];
  }

  function toggleMark() {
    const newSet = new Set(markedQuestions.value);
    const idx = currentQuestion.value;
    if (newSet.has(idx)) newSet.delete(idx);
    else newSet.add(idx);
    markedQuestions.value = newSet;
  }

  function goToQuestion(idx) {
    if (idx < 0 || idx >= questions.value.length) return;
    currentQuestion.value = idx;
  }

  function nextQuestion() {
    goToQuestion(currentQuestion.value + 1);
  }

  function prevQuestion() {
    goToQuestion(currentQuestion.value - 1);
  }

  // ── Scroll current nav button into view ──

  watch(currentQuestion, () => {
    nextTick(() => {
      const btn = document.getElementById(`nav-q${currentQuestion.value}`);
      if (btn) {
        btn.scrollIntoView({
          behavior: "smooth",
          block: "nearest",
          inline: "center",
        });
      }
      // Scroll question card into view
      const card = document.getElementById(`qcard-${currentQuestion.value}`);
      if (card) {
        card.scrollIntoView({ behavior: "smooth", block: "center" });
      }
    });
  });

  return {
    examId,
    studentName,
    questions,
    answers,
    currentQuestion,
    markedQuestions,
    examData,
    studentData,
    isSubmitted,
    phase,
    securitySettings,
    maxViolations,
    duration,
    loading,
    navButtons,
    isCurrentMarked,
    isLastQuestion,
    totalQuestions,
    answeredCount,
    selectOption,
    updateEssay,
    toggleMark,
    goToQuestion,
    nextQuestion,
    prevQuestion,
  };
}

/**
 * useTimer — Countdown timer for exam.
 */
function useTimer() {
  const timeLeft = ref(0);
  let interval = null;
  let onExpire = null;

  const timerDisplay = computed(() => {
    const h = Math.floor(timeLeft.value / 3600);
    const m = Math.floor((timeLeft.value % 3600) / 60);
    const s = timeLeft.value % 60;
    const mm = String(m).padStart(2, "0");
    const ss = String(s).padStart(2, "0");
    return h > 0 ? `${String(h).padStart(2, "0")}:${mm}:${ss}` : `${mm}:${ss}`;
  });

  const isWarning = computed(() => timeLeft.value <= 300);

  function start(durationMinutes, expireCallback) {
    timeLeft.value = durationMinutes * 60;
    onExpire = expireCallback;
    interval = setInterval(() => {
      timeLeft.value--;
      if (timeLeft.value <= 0) {
        stop();
        if (onExpire) onExpire();
      }
    }, 1000);
  }

  function stop() {
    if (interval) clearInterval(interval);
    interval = null;
  }

  return { timeLeft, timerDisplay, isWarning, start, stop };
}

/**
 * useAgreement — Checkbox validation + countdown.
 */
function useAgreement() {
  const checkboxes = ref(Array(TOTAL_RULES).fill(false));
  const countdown = ref(0);
  const countdownActive = ref(false);
  const countdownFinished = ref(false);
  let countdownInterval = null;

  const allChecked = computed(() => checkboxes.value.every((v) => v));

  const canStart = computed(() => allChecked.value && countdownFinished.value);

  function startCountdown() {
    if (countdownActive.value) return;
    countdownActive.value = true;
    countdown.value = 10;

    countdownInterval = setInterval(() => {
      countdown.value--;
      if (countdown.value <= 0) {
        clearInterval(countdownInterval);
        countdownActive.value = false;
        countdownFinished.value = true;
      }
    }, 1000);
  }

  // Auto-start countdown when all checked
  watch(allChecked, (checked) => {
    if (checked && !countdownActive.value && !countdownFinished.value) {
      startCountdown();
    }
  });

  return {
    checkboxes,
    allChecked,
    countdown,
    countdownActive,
    countdownFinished,
    canStart,
  };
}

// ── COMPONENTS ───────────────────────────────────────────────

const ZoomModal = {
  name: "ZoomModal",
  props: { src: { type: String, default: "" } },
  emits: ["close"],
  template: `
    <div class="zoom-modal" @click="$emit('close')">
      <img :src="src" alt="Zoom" />
    </div>
  `,
};

const AgreementModal = {
  name: "AgreementModal",
  emits: ["start"],
  setup(props, { emit }) {
    const agreement = useAgreement();

    async function handleStart() {
      if (!agreement.canStart.value) return;
      emit("start");
    }

    return { ...agreement, RULE_SECTIONS, getRuleGlobalIndex, handleStart };
  },
  template: `
    <div class="agreement-modal">
      <div class="agreement-content">
        <div class="agreement-header">
          <h2><span>📋</span> Peraturan Ujian Online</h2>
          <p>Bacalah dengan teliti sebelum memulai ujian</p>
        </div>

        <div class="rules-scroll-container">
          <div v-for="(section, si) in RULE_SECTIONS" :key="si" class="rule-section">
            <div class="rule-section-title">
              <span>{{ section.icon }}</span><span>{{ section.title }}</span>
            </div>
            <div v-for="(rule, ri) in section.rules" :key="si + '-' + ri" class="rule-item">
              <input type="checkbox" v-model="checkboxes[getRuleGlobalIndex(si, ri)]" :id="'chk' + (getRuleGlobalIndex(si, ri) + 1)" />
              <label class="rule-text" :for="'chk' + (getRuleGlobalIndex(si, ri) + 1)">{{ rule }}</label>
            </div>
          </div>
        </div>

        <div class="agreement-footer">
          <div class="countdown-timer" :class="{ active: countdownActive }">
            Silakan baca dengan teliti... <span>{{ countdown }}</span> detik
          </div>
          <button class="btn-start-exam" :class="{ enabled: canStart }" :disabled="!canStart" @click="handleStart">
            {{ canStart ? '✓ Mulai Ujian' : 'Mulai Ujian' }}
          </button>
        </div>
      </div>
    </div>
  `,
};

const FullscreenPrompt = {
  name: "FullscreenPrompt",
  props: { loading: { type: Boolean, default: false } },
  emits: ["start"],
  template: `
    <div class="fs-prompt">
      <div class="fs-prompt-icon">🔒</div>
      <h2 class="fs-prompt-title">Mode Ujian Aman</h2>
      <p class="fs-prompt-desc">Ujian ini memerlukan mode layar penuh untuk menjaga integritas dan keamanan.</p>
      <button class="btn btn-primary btn-lg fs-start-btn" :disabled="loading" @click="$emit('start')">
        {{ loading ? 'Memuat...' : 'Mulai Ujian' }}
      </button>
    </div>
  `,
};

const ExamHeader = {
  name: "ExamHeader",
  props: {
    examData: { type: Object, default: null },
    studentData: { type: Object, default: null },
    questionCount: { type: Number, default: 0 },
    studentName: { type: String, default: "" },
    timerDisplay: { type: String, default: "00:00" },
    isWarning: { type: Boolean, default: false },
  },
  emits: ["toggle-grid"],
  template: `
    <div class="exam-header">
      <div class="header-left">
        <div class="header-logo">📝</div>
        <div class="header-info">
          <h1>{{ examData?.name || 'Memuat...' }}</h1>
          <div class="header-meta">
            <span class="meta-badge">{{ examData?.subject || '-' }}</span>
            <span class="meta-separator">•</span>
            <span class="meta-badge">{{ examData?.class || '-' }}</span>
            <span class="meta-separator">•</span>
            <span class="meta-badge">{{ questionCount }}</span>
            <span class="meta-text">soal</span>
            <span class="meta-separator">|</span>
            <span class="student-name">{{ studentName }}</span>
          </div>
        </div>
      </div>
      <div class="header-right">
        <div class="timer-pill" :class="{ warning: isWarning }">⏱️ {{ timerDisplay }}</div>
        <div class="nav-grid-trigger" title="Navigasi Soal" @click="$emit('toggle-grid')">☰</div>
      </div>
    </div>
  `,
};

const QuestionNav = {
  name: "QuestionNav",
  props: {
    navButtons: { type: Array, default: () => [] },
  },
  emits: ["navigate"],
  template: `
    <div class="q-nav-container">
      <div class="q-nav-scroll">
        <button
          v-for="btn in navButtons"
          :key="btn.idx"
          :id="'nav-q' + btn.idx"
          class="q-nav-btn"
          :class="{
            answered: btn.isAnswered,
            current: btn.isCurrent,
            marked: btn.isMarked,
          }"
          @click="$emit('navigate', btn.idx)"
        >
          {{ btn.idx + 1 }}
        </button>
      </div>
    </div>
  `,
};

const QuestionCard = {
  name: "QuestionCard",
  props: {
    question: { type: Object, required: true },
    idx: { type: Number, required: true },
    currentQuestion: { type: Number, required: true },
    answer: { default: undefined },
  },
  emits: ["select", "essay-input", "zoom"],
  setup(props, { emit }) {
    function isOptionSelected(optIdx) {
      const qType = props.question.question_type;
      const ans = props.answer;
      if (ans === undefined || ans === null) return false;
      if (qType === "checkbox") {
        return Array.isArray(ans) && ans.includes(optIdx.toString());
      }
      if (qType === "truefalse") {
        return ans === (optIdx === 0 ? "true" : "false");
      }
      return ans === optIdx.toString();
    }

    function handleSelect(optIdx) {
      emit("select", optIdx);
    }

    function handleEssayInput(event) {
      emit("essay-input", event.target.value);
    }

    function mediaUrl(url) {
      return escapeUrl(url.startsWith("http") ? url : "../" + url);
    }

    function optionImgUrl(img) {
      return escapeUrl("../" + img);
    }

    return {
      LETTERS,
      isOptionSelected,
      handleSelect,
      handleEssayInput,
      mediaUrl,
      optionImgUrl,
    };
  },
  template: `
    <div class="question-card" v-show="currentQuestion === idx" :id="'qcard-' + idx">
      <div class="q-meta">
        <div class="q-number">Soal {{ idx + 1 }}</div>
        <div class="q-type">{{ question.type_label }}</div>
      </div>

      <!-- Media Images -->
      <div v-if="question.media_urls.length > 0" class="question-media-container">
        <img
          v-for="(url, mi) in question.media_urls"
          :key="mi"
          :src="mediaUrl(url)"
          alt="Gambar Soal"
          @click="$emit('zoom', mediaUrl(url))"
        />
      </div>

      <!-- Question Text (rich HTML from Quill, server-sanitized) -->
      <div class="q-text" v-html="question.question_text"></div>

      <!-- Multiple Choice / True-False / Checkbox Options -->
      <div v-if="question.question_type !== 'essay'" class="options-list">
        <div
          v-for="(opt, oi) in question.processed_options"
          :key="oi"
          class="option-item"
          :class="{ selected: isOptionSelected(oi) }"
          @click="handleSelect(oi)"
        >
          <input
            :type="question.input_type"
            :name="'q' + idx"
            :value="oi"
            :id="'r' + idx + '-' + oi"
            :checked="isOptionSelected(oi)"
            @click.stop
          />
          <div class="opt-letter">{{ LETTERS[oi] }}</div>
          <div class="opt-text">
            {{ opt.text }}
            <div v-if="opt.image" class="option-img-container" @click.stop>
              <img
                :src="optionImgUrl(opt.image)"
                @click="$emit('zoom', optionImgUrl(opt.image))"
              />
            </div>
          </div>
        </div>
      </div>

      <!-- Essay Textarea -->
      <textarea
        v-else
        class="essay-textarea"
        placeholder="Tulis jawaban Anda di sini..."
        rows="5"
        :value="answer || ''"
        @input="handleEssayInput"
      ></textarea>
    </div>
  `,
};

const BottomNav = {
  name: "BottomNav",
  props: {
    currentQuestion: { type: Number, default: 0 },
    totalQuestions: { type: Number, default: 0 },
    isMarked: { type: Boolean, default: false },
  },
  emits: ["prev", "next", "mark", "finish"],
  computed: {
    isLast() {
      return this.currentQuestion === this.totalQuestions - 1;
    },
  },
  template: `
    <div class="bottom-nav">
      <button class="nav-btn btn-prev" @click="$emit('prev')">
        <span>Sebelumnya</span>
      </button>
      <button
        class="nav-btn btn-ragu"
        :class="{ active: isMarked }"
        @click="$emit('mark')"
      >
        <span>Ragu-ragu</span>
      </button>
      <button
        v-if="!isLast"
        class="nav-btn btn-next"
        @click="$emit('next')"
      >
        <span>Selanjutnya</span>
      </button>
      <button
        v-else
        class="nav-btn btn-finish"
        @click="$emit('finish')"
      >
        <span>Selesai</span>
      </button>
    </div>
  `,
};

const NavGridModal = {
  name: "NavGridModal",
  props: {
    navButtons: { type: Array, default: () => [] },
    show: { type: Boolean, default: false },
  },
  emits: ["close", "navigate", "submit"],
  template: `
    <div v-if="show" class="nav-grid-modal" @click="$emit('close')">
      <div class="nav-grid-content" @click.stop>
        <div class="grid-header">
          <div class="grid-title">Navigasi Soal</div>
          <div class="grid-close" @click="$emit('close')">&times;</div>
        </div>
        <div class="grid-items">
          <button
            v-for="btn in navButtons"
            :key="'grid-' + btn.idx"
            :id="'modal-nav-q' + btn.idx"
            class="q-nav-btn"
            :class="{
              answered: btn.isAnswered,
              current: btn.isCurrent,
              marked: btn.isMarked,
            }"
            @click="$emit('navigate', btn.idx)"
          >
            {{ btn.idx + 1 }}
          </button>
        </div>
        <div class="grid-footer">
          <button class="btn btn-success btn-block" @click="$emit('submit')">Kumpulkan Ujian</button>
        </div>
      </div>
    </div>
  `,
};

const ViolationOverlay = {
  name: "ViolationOverlay",
  props: {
    show: { type: Boolean, default: false },
    message: { type: String, default: "" },
    count: { type: Number, default: 0 },
    max: { type: Number, default: 3 },
  },
  template: `
    <div v-if="show" class="violation-overlay">
      <div class="violation-overlay-icon">⚠️</div>
      <h2>Pelanggaran Terdeteksi!</h2>
      <p class="violation-overlay-msg">{{ message }}</p>
      <div class="violation-count-badge">Pelanggaran {{ count }}/{{ max }}</div>
      <p class="violation-overlay-hint">Pengawas telah diberitahu. Jangan ulangi!</p>
    </div>
  `,
};

const ResultScreen = {
  name: "ResultScreen",
  template: `
    <div class="result-screen">
      <div class="result-screen-icon">✅</div>
      <h2>Ujian Selesai</h2>
      <div class="result-screen-score">Terima kasih telah mengerjakan ujian</div>
      <p class="result-screen-desc">Jawaban Anda telah disimpan. Hasil ujian akan diumumkan oleh guru.</p>
      <a href="../student/dashboard.php" class="btn btn-primary">Kembali ke Dashboard</a>
    </div>
  `,
};

// ── APP CREATION + MOUNT ─────────────────────────────────────

(function () {
  // Validate required data from PHP
  if (!window.EXAM_ID) {
    showToast("ID Ujian tidak ditemukan!", "error");
    setTimeout(() => (window.location.href = "dashboard.php"), 1500);
    return;
  }

  const app = createApp({
    setup() {
      // ── Composables ──
      const state = useExamState();
      const timer = useTimer();

      // ── Local State ──
      const zoomSrc = ref(null);
      const showGrid = ref(false);
      const showViolationOverlay = ref(false);
      const violationMessage = ref("");
      const violationCount = ref(0);
      const violationMax = ref(3);
      let violationTimeout = null;

      // ── Agreement → Fullscreen ──

      async function handleAgreementStart() {
        try {
          await StudentAPI.logAgreement(state.examId.value);
        } catch (e) {
          console.error("[Agreement] Failed to log:", e);
        }
        state.phase.value = "fullscreen";
      }

      // ── Fullscreen → Exam ──

      async function handleExamStart() {
        state.loading.value = true;
        try {
          // 1. Start exam session
          const startResult = await StudentAPI.startExam(state.examId.value);
          if (!startResult.success) {
            if (
              startResult.message &&
              startResult.message.includes("sudah menyelesaikan")
            ) {
              showToast(
                "Anda sudah menyelesaikan ujian ini sebelumnya.",
                "error"
              );
              setTimeout(() => (window.location.href = "dashboard.php"), 1500);
              return;
            }
            showToast(startResult.message || "Gagal memulai ujian", "error");
            state.loading.value = false;
            return;
          }

          // 2. Fetch exam data
          const examResult = await StudentAPI.getExam(state.examId.value);
          if (!examResult.success) {
            showToast(examResult.message || "Gagal memuat ujian", "error");
            state.loading.value = false;
            return;
          }

          // 3. Process & store data
          state.examData.value = examResult.exam;
          state.duration.value = parseInt(examResult.exam.duration) || 90;
          state.maxViolations.value = examResult.exam.max_violations || 3;
          state.questions.value = (examResult.questions || []).map((q, i) =>
            processQuestion(q, i)
          );

          // 4. Fetch student profile
          try {
            const profileResult = await StudentAPI.getProfile();
            if (profileResult.success) {
              state.studentData.value = profileResult.user;
            }
          } catch (e) {
            console.warn("[Exam] Failed to fetch profile:", e);
          }

          // 5. Configure security module
          ExamSecurity.setExamId(state.examId.value);
          ExamSecurity.configure(
            examResult.exam.security_settings,
            examResult.exam.max_violations
          );

          // 6. Transition to exam phase
          state.phase.value = "exam";

          // 7. Request fullscreen
          try {
            const el = document.documentElement;
            if (el.requestFullscreen) {
              el.requestFullscreen().catch(() => {});
            }
          } catch (e) {}

          // 8. Start security monitoring
          ExamSecurity.start();

          // 9. Start timer
          timer.start(state.duration.value, () => {
            // Timer expired — force submit
            submitExam(true, "Waktu habis");
          });

          // 10. Register violation callback
          ExamSecurity.onViolationCallback = handleViolation;
        } catch (error) {
          console.error("[Exam] Start error:", error);
          showToast("Terjadi kesalahan koneksi saat memulai ujian.", "error");
        } finally {
          state.loading.value = false;
        }
      }

      // ── Violation Handler (bridge from security.js) ──

      function handleViolation({ reason, count, maxViolations, autoStop }) {
        violationCount.value = count;
        violationMessage.value = reason;
        violationMax.value = maxViolations;

        // Show overlay briefly (3 seconds)
        showViolationOverlay.value = true;
        if (violationTimeout) clearTimeout(violationTimeout);
        violationTimeout = setTimeout(() => {
          showViolationOverlay.value = false;
        }, 3000);

        // Auto-submit if threshold reached
        if (autoStop && count >= maxViolations) {
          showViolationOverlay.value = false;
          submitExam(true, "Pelanggaran maksimal tercapai");
        }
      }

      // ── Submit Exam ──

      async function submitExam(forced = false, reason = "") {
        if (state.isSubmitted.value) return;

        if (!forced) {
          const answered = state.answeredCount.value;
          const total = state.totalQuestions.value;
          if (answered < total) {
            const ok = await confirm(
              `⚠️ Anda baru menjawab ${answered} dari ${total} soal.\nApakah Anda yakin ingin mengumpulkan?`
            );
            if (!ok) return;
          }
        }

        state.isSubmitted.value = true;
        timer.stop();

        // Stop security monitoring
        if (typeof ExamSecurity !== "undefined") {
          ExamSecurity.stop();
        }

        const timeTaken = state.duration.value * 60 - timer.timeLeft.value;

        try {
          const result = await StudentAPI.submitAnswers({
            examId: state.examId.value,
            answers: { ...state.answers },
            forced,
            timeTaken,
          });

          if (result.success) {
            state.phase.value = "result";
          } else {
            showToast("Gagal menyimpan jawaban: " + result.message, "error");
            state.isSubmitted.value = false;
          }
        } catch (error) {
          console.error("[Exam] Submit error:", error);
          showToast(
            "Terjadi kesalahan koneksi saat mengirim jawaban. Silakan hubungi pengawas.",
            "error"
          );
          state.isSubmitted.value = false;
        }
      }

      // ── Grid Modal ──

      function toggleGrid() {
        showGrid.value = !showGrid.value;
      }

      // ── Zoom ──

      function openZoom(url) {
        zoomSrc.value = url;
      }

      function closeZoom() {
        zoomSrc.value = null;
      }

      // ── Option Select Handler ──

      function handleSelectOption(qId, optIdx, qType) {
        state.selectOption(qId, optIdx, qType);
      }

      // ── Cleanup on unmount ──

      // (Not strictly needed — exam page doesn't unmount, but good practice)

      return {
        // State
        ...state,
        ...timer,
        zoomSrc,
        showGrid,
        showViolationOverlay,
        violationMessage,
        violationCount,
        violationMax,

        // Components
        ConfirmDialog,

        // Methods
        handleAgreementStart,
        handleExamStart,
        submitExam,
        toggleGrid,
        openZoom,
        closeZoom,
        handleSelectOption,
        handleViolation,
      };
    },
    template: `
      <div>
        <!-- Zoom Modal -->
        <zoom-modal v-if="zoomSrc" :src="zoomSrc" @close="closeZoom" />

        <!-- Agreement Phase -->
        <agreement-modal
          v-if="phase === 'agreement'"
          @start="handleAgreementStart"
        />

        <!-- Fullscreen Prompt Phase -->
        <fullscreen-prompt
          v-if="phase === 'fullscreen'"
          :loading="loading"
          @start="handleExamStart"
        />

        <!-- Violation Overlay -->
        <violation-overlay
          :show="showViolationOverlay"
          :message="violationMessage"
          :count="violationCount"
          :max="violationMax"
        />

        <!-- Exam Phase -->
        <template v-if="phase === 'exam'">
          <exam-header
            :exam-data="examData"
            :student-data="studentData"
            :question-count="totalQuestions"
            :student-name="studentName"
            :timer-display="timerDisplay"
            :is-warning="isWarning"
            @toggle-grid="toggleGrid"
          />

          <question-nav
            :nav-buttons="navButtons"
            @navigate="goToQuestion"
          />

          <div class="exam-container">
            <div id="questions-container">
              <question-card
                v-for="q in questions"
                :key="q.id"
                :question="q"
                :idx="q._idx"
                :current-question="currentQuestion"
                :answer="answers[q.id]"
                @select="handleSelectOption(q.id, $event, q.question_type)"
                @essay-input="updateEssay(q.id, $event)"
                @zoom="openZoom"
              />
            </div>
          </div>

          <bottom-nav
            :current-question="currentQuestion"
            :total-questions="totalQuestions"
            :is-marked="isCurrentMarked"
            @prev="prevQuestion"
            @next="nextQuestion"
            @mark="toggleMark"
            @finish="submitExam(false)"
          />
        </template>

        <!-- Nav Grid Modal -->
        <nav-grid-modal
          :nav-buttons="navButtons"
          :show="showGrid"
          @close="toggleGrid"
          @navigate="goToQuestion"
          @submit="submitExam(false)"
        />

        <!-- Result Phase -->
        <result-screen v-if="phase === 'result'" />

        <!-- Shared Confirm Dialog -->
        <confirm-dialog />
      </div>
    `,
  });

  // ── Register Components ──
  app.component("ZoomModal", ZoomModal);
  app.component("AgreementModal", AgreementModal);
  app.component("FullscreenPrompt", FullscreenPrompt);
  app.component("ExamHeader", ExamHeader);
  app.component("QuestionNav", QuestionNav);
  app.component("QuestionCard", QuestionCard);
  app.component("BottomNav", BottomNav);
  app.component("NavGridModal", NavGridModal);
  app.component("ViolationOverlay", ViolationOverlay);
  app.component("ResultScreen", ResultScreen);
  app.component("ConfirmDialog", ConfirmDialog);

  // ── Mount ──
  app.mount("#app");
})();
