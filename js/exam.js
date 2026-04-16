/**
 * ExamSafe Exam Engine
 * Timer, soal acak, dan manajemen jawaban
 */

const ExamEngine = {
  escapeHtml(str) {
    if (!str) return "";
    const div = document.createElement("div");
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
  },

  decodeHtml(str) {
    if (!str) return "";
    const txt = document.createElement("textarea");
    txt.innerHTML = str;
    return txt.value;
  },

  escapeUrl(url) {
    return encodeURI(url);
  },

  examId: null,
  duration: 90, // menit
  timeLeft: 0, // detik
  timerInterval: null,
  currentQuestion: 0,
  answers: {},
  questions: [],
  isSubmitted: false,

  async init(examId) {
    this.examId = examId;
    console.log("[ExamEngine] Initializing exam ID:", examId);

    // Notify security module about examId
    if (typeof ExamSecurity !== "undefined" && ExamSecurity.setExamId) {
      ExamSecurity.setExamId(examId);
    }

    try {
      const response = await fetch(
        `../php/exam_api.php?action=get_exam&exam_id=${examId}`
      );
      const data = await response.json();

      if (!data.success) {
        alert("Gagal memuat ujian: " + data.message);
        window.location.href = "dashboard.php";
        return;
      }

      this.questions = data.questions;
      this.duration = parseInt(data.exam.duration) || 90;
      this.timeLeft = this.duration * 60;
      this.showResultsSetting =
        data.exam.show_results_setting || "direct_submit";
      this.examData = data.exam;

      // Update Header with Exam Info
      this.updateHeaderInfo();

      // Fetch student profile for header (using session data via API)
      const profileRes = await fetch("../php/exam_api.php?action=get_profile", {
        credentials: "include",
      });
      const profileData = await profileRes.json();
      if (profileData.success) {
        this.studentData = profileData.user;
        this.updateHeaderInfo(); // Refresh with student data
      }

      this.renderQuestions();
      this.startTimer();
      this.updateProgress();

      // Initialize Security after exam data is loaded
      if (typeof ExamSecurity !== "undefined") {
        // Pass debug flag to security module
        ExamSecurity.init(window.DEBUG_DISABLE_VIOLATIONS);
      }
    } catch (error) {
      console.error("[ExamEngine] Error loading exam:", error);
      alert("Terjadi kesalahan koneksi saat memuat ujian.");
    }
  },

  async startExam(examId) {
    console.log("[ExamEngine] Starting exam session for ID:", examId);
    
    try {
        const response = await fetch("../php/exam_api.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                action: "start_exam",
                exam_id: examId
            }),
            credentials: "include",
        });

        const data = await response.json();

        if (data.success) {
            console.log("[ExamEngine] Exam session started successfully");
            if (data.already_started) {
                console.log("[ExamEngine] Session already existed, continuing");
            }
            return true;
        } else {
            console.error("[ExamEngine] Failed to start exam:", data.message);
            if (data.message && data.message.includes("sudah menyelesaikan")) {
                alert("Anda sudah menyelesaikan ujian ini sebelumnya.");
                window.location.href = "dashboard.php";
            }
            return false;
        }
    } catch (error) {
        console.error("[ExamEngine] Error starting exam:", error);
        alert("Terjadi kesalahan saat memulai ujian. Silakan coba lagi.");
        return false;
    }
  },

  updateHeaderInfo() {
    // Update exam name and subject display
    const examNameEl = document.getElementById("exam-name-display");
    const examSubjectEl = document.getElementById("exam-subject-display");
    const examClassEl = document.getElementById("exam-class-display");
    const studentNameEl = document.getElementById("exam-student-display");
    const questionCountEl = document.getElementById("exam-question-count");

    if (this.examData) {
      if (examNameEl) examNameEl.textContent = this.examData.name || "Ujian";
      if (examSubjectEl)
        examSubjectEl.textContent = this.examData.subject || "-";
      if (examClassEl && this.examData.class)
        examClassEl.textContent = this.examData.class;
      if (questionCountEl && this.questions)
        questionCountEl.textContent = this.questions.length;
    }

    if (this.studentData && studentNameEl) {
      studentNameEl.textContent = `${this.studentData.full_name || "Siswa"}`;
    }
  },

  shuffleArray(arr) {
    for (let i = arr.length - 1; i > 0; i--) {
      const j = Math.floor(Math.random() * (i + 1));
      [arr[i], arr[j]] = [arr[j], arr[i]];
    }
    return arr;
  },

  startTimer() {
    this.updateTimerDisplay();
    this.timerInterval = setInterval(() => {
      this.timeLeft--;
      this.updateTimerDisplay();

      if (this.timeLeft <= 300) {
        // 5 minutes warning
        document.getElementById("exam-timer").classList.add("warning");
      }

      if (this.timeLeft <= 0) {
        this.submitExam(false, "Waktu habis");
      }
    }, 1000);
  },

  updateTimerDisplay() {
    const h = Math.floor(this.timeLeft / 3600);
    const m = Math.floor((this.timeLeft % 3600) / 60);
    const s = this.timeLeft % 60;
    const display =
      h > 0
        ? `${String(h).padStart(2, "0")}:${String(m).padStart(2, "0")}:${String(
            s
          ).padStart(2, "0")}`
        : `${String(m).padStart(2, "0")}:${String(s).padStart(2, "0")}`;
    const el = document.getElementById("exam-timer");
    if (el) el.textContent = display;
  },

  renderQuestions() {
    const container = document.getElementById("questions-container");
    if (!container || !this.questions.length) return;

    // Render all questions but hide them except the current one
    container.innerHTML = this.questions
      .map((q, idx) => {
        let qText = q.question_text || q.text || "Teks soal tidak tersedia";
        qText = this.decodeHtml(qText);
        const qType = q.question_type || q.type || "multiple";

        let typeLabel = "Pilihan Ganda";
        if (qType === "checkbox") typeLabel = "Pilihan Ganda Kompleks";
        if (qType === "truefalse") typeLabel = "Benar / Salah";
        if (qType === "essay") typeLabel = "Essay";

        // Parse options safely
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
            return this.decodeHtml(opt);
          } else if (typeof opt === "object" && opt !== null) {
            return {
              ...opt,
              text: opt.text ? this.decodeHtml(opt.text) : "",
              image: opt.image || "",
            };
          }
          return opt;
        });

        if (qType === "truefalse" && options.length === 0) {
          options = ["Benar", "Salah"];
        }

        return `
        <div class="question-card" id="qcard-${idx}" style="display: ${
          idx === this.currentQuestion ? "block" : "none"
        }">
          <div class="q-meta">
            <div class="q-number">Soal ${idx + 1}</div>
            <div class="q-type">${typeLabel}</div>
          </div>
          
          ${(() => {
            let media = [];
            try {
              if (
                q.media_url &&
                q.media_url.trim() !== "" &&
                q.media_url !== "[]"
              ) {
                const txt = document.createElement("textarea");
                txt.innerHTML = q.media_url;
                let decoded = txt.value.trim();
                if (decoded.startsWith("[") || decoded.startsWith("{")) {
                  try {
                    const parsed = JSON.parse(decoded);
                    media = Array.isArray(parsed) ? parsed : [parsed];
                  } catch (e) {
                    const matches = decoded.match(/uploads\/[^"'\s\]]+/g);
                    media = matches ? matches : [];
                  }
                } else {
                  media = [decoded];
                }
              }
            } catch (e) {
              media = [];
            }

            const finalMedia = [];
            if (Array.isArray(media)) {
              media.forEach((url) => {
                if (typeof url !== "string") return;
                const cleanUrl = url.trim();
                if (
                  cleanUrl.length > 5 &&
                  (cleanUrl.includes("uploads/") || cleanUrl.startsWith("http"))
                ) {
                  if (!cleanUrl.startsWith("[") && !cleanUrl.endsWith("]"))
                    finalMedia.push(cleanUrl);
                }
              });
            }

            if (finalMedia.length === 0) return "";

            return `
              <div class="question-media-container" style="display: flex; flex-direction: column; gap: 0.75em; margin-bottom: 1.5em; align-items: center;">
                ${finalMedia
                  .map((url) => {
                    const finalUrl = this.escapeUrl(
                      url.startsWith("http") ? url : "../" + url
                    );
                    return `
                    <img src="${finalUrl}" alt="Gambar Soal" 
                      onclick="ExamEngine.openZoom('${this.escapeUrl(
                        finalUrl
                      )}')"
                      style="max-width: 100%; max-height: 18.75rem; object-fit: contain; cursor: zoom-in; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08);">
                  `;
                  })
                  .join("")}
              </div>
            `;
          })()}

          <div class="q-text">${this.escapeHtml(qText)}</div>
          ${
            qType === "multiple" ||
            qType === "truefalse" ||
            qType === "checkbox"
              ? this.renderOptions(options, idx)
              : this.renderEssay(q, idx)
          }
        </div>
      `;
      })
      .join("");

    this.attachEventListeners();
  },

  attachEventListeners() {
    this.questions.forEach((q, idx) => {
      const qId = q.id;
      const qType = q.question_type || q.type || "multiple";
      if (qType === "multiple" || qType === "truefalse") {
        document.querySelectorAll(`[name="q${idx}"]`).forEach((radio) => {
          radio.addEventListener("change", () => {
            let val = radio.value;
            if (qType === "truefalse") {
              val = radio.value === "0" ? "true" : "false";
            }

            this.answers[qId] = val;
            this.updateProgress();
            this.highlightSelected(idx, parseInt(radio.value));
          });
        });
      } else if (qType === "checkbox") {
        document.querySelectorAll(`[name="q${idx}"]`).forEach((cb) => {
          cb.addEventListener("change", () => {
            const checked = Array.from(
              document.querySelectorAll(`[name="q${idx}"]:checked`)
            ).map((c) => c.value);
            if (checked.length > 0) {
              this.answers[qId] = checked;
            } else {
              delete this.answers[qId];
            }
            this.updateProgress();
            this.highlightSelected(idx, parseInt(cb.value));
          });
        });
      } else {
        const textarea = document.getElementById(`essay-${idx}`);
        if (textarea) {
          textarea.addEventListener("input", () => {
            this.answers[qId] = textarea.value;
            this.updateProgress();
          });
        }
      }
    });
  },

  renderOptions(options, idx) {
    const q = this.questions[idx];
    const qType = q.question_type || q.type || "multiple";
    const letters = ["A", "B", "C", "D", "E", "F"];
    const inputType = qType === "checkbox" ? "checkbox" : "radio";

    return `<div class="options-list">
      ${options
        .map((opt, i) => {
          let isChecked = false;
          if (qType === "multiple") {
            isChecked = this.answers[q.id] === i.toString();
          } else if (qType === "truefalse") {
            isChecked = this.answers[q.id] === (i === 0 ? "true" : "false");
          } else if (qType === "checkbox") {
            isChecked =
              Array.isArray(this.answers[q.id]) &&
              this.answers[q.id].includes(i.toString());
          }

          let optText = "";
          let optImg = "";

          if (typeof opt === "string") {
            optText = opt;
          } else if (typeof opt === "object" && opt !== null) {
            optText = opt.text || "";
            optImg = opt.image || "";
          }

          return `
          <div class="option-item ${
            isChecked ? "selected" : ""
          }" id="opt-${idx}-${i}" onclick="ExamEngine.selectOption(${idx}, ${i})">
            <input type="${inputType}" name="q${idx}" value="${i}" id="r${idx}-${i}" ${
            isChecked ? "checked" : ""
          } onclick="event.stopPropagation()">
            <div class="opt-letter">${letters[i]}</div>
            <div class="opt-text">
              ${this.escapeHtml(optText)}
              ${
                optImg
                  ? `
                <div class="option-img-container" style="margin-top:0.625em" onclick="event.stopPropagation()">
                  <img src="../${this.escapeUrl(
                    optImg
                  )}" style="max-width:100%; max-height:7.5rem; border-radius:8px; cursor:zoom-in;" 
                    onclick="ExamEngine.openZoom('../${this.escapeUrl(
                      optImg
                    )}')">
                </div>
              `
                  : ""
              }
            </div>
          </div>
        `;
        })
        .join("")}
    </div>`;
  },

  openZoom(url) {
    const modal = document.getElementById("zoom-modal");
    const img = document.getElementById("zoom-img");
    if (modal && img) {
      img.src = url;
      modal.style.display = "flex";
      event.stopPropagation();
    }
  },

  closeZoom() {
    const modal = document.getElementById("zoom-modal");
    if (modal) modal.style.display = "none";
  },

  nextQuestion() {
    if (this.currentQuestion < this.questions.length - 1) {
      this.goToQuestion(this.currentQuestion + 1);
    }
  },

  prevQuestion() {
    if (this.currentQuestion > 0) {
      this.goToQuestion(this.currentQuestion - 1);
    }
  },

  goToQuestion(idx) {
    const currentCard = document.getElementById(
      `qcard-${this.currentQuestion}`
    );
    if (currentCard) currentCard.style.display = "none";

    this.currentQuestion = idx;
    const nextCard = document.getElementById(`qcard-${this.currentQuestion}`);
    if (nextCard) {
      nextCard.style.display = "block";
      nextCard.scrollIntoView({ behavior: "smooth", block: "center" });
    }

    this.updateProgress();
  },

  renderEssay(q, idx) {
    return `<textarea class="form-control" id="essay-${idx}" 
      placeholder="Tulis jawaban Anda di sini..." rows="5"
      style="margin-top:0.5em">${this.answers[q.id] || ""}</textarea>`;
  },

  selectOption(qIdx, optIdx) {
    const q = this.questions[qIdx];
    const qType = q.question_type || q.type || "multiple";
    const optEl = document.getElementById(`opt-${qIdx}-${optIdx}`);
    const inputEl = document.getElementById(`r${qIdx}-${optIdx}`);

    if (qType === "checkbox") {
      inputEl.checked = !inputEl.checked;
      if (inputEl.checked) optEl.classList.add("selected");
      else optEl.classList.remove("selected");

      const checked = Array.from(
        document.querySelectorAll(`[name="q${qIdx}"]:checked`)
      ).map((c) => c.value);
      if (checked.length > 0) {
        this.answers[q.id] = checked;
      } else {
        delete this.answers[q.id];
      }
    } else {
      document
        .querySelectorAll(`[id^="opt-${qIdx}-"]`)
        .forEach((el) => el.classList.remove("selected"));
      if (optEl) optEl.classList.add("selected");
      if (inputEl) inputEl.checked = true;

      let val = optIdx.toString();
      if (qType === "truefalse") {
        val = optIdx === 0 ? "true" : "false";
      }
      this.answers[q.id] = val;
    }

    this.updateProgress();
  },

  highlightSelected(qIdx, optIdx) {
    const q = this.questions[qIdx];
    const qType = q.question_type || q.type || "multiple";

    if (qType !== "checkbox") {
      document
        .querySelectorAll(`[id^="opt-${qIdx}-"]`)
        .forEach((el) => el.classList.remove("selected"));
      const el = document.getElementById(`opt-${qIdx}-${optIdx}`);
      if (el) el.classList.add("selected");
    }
  },

  updateProgress() {
    if (typeof updateNavUI === "function") {
      updateNavUI();
    }
  },

  async submitExam(forced = false, reason = "") {
    if (this.isSubmitted) return;

    if (!forced) {
      const answered = Object.keys(this.answers).length;
      const total = this.questions.length;
      if (answered < total) {
        const confirm = window.confirm(
          `⚠️ Anda baru menjawab ${answered} dari ${total} soal.\nApakah Anda yakin ingin mengumpulkan?`
        );
        if (!confirm) return;
      }
    }

    this.isSubmitted = true;
    clearInterval(this.timerInterval);

    if (
      typeof ExamSecurity !== "undefined" &&
      !window.DEBUG_DISABLE_VIOLATIONS
    ) {
      ExamSecurity.stop();
    }

    const timeTaken = this.duration * 60 - this.timeLeft;

    try {
      const response = await fetch("../php/exam_api.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          action: "submit_answers",
          exam_id: this.examId,
          answers: this.answers,
          forced: forced,
          time_taken: timeTaken,
        }),
      });

      const data = await response.json();

      if (data.success) {
        const examContent = document.getElementById("exam-content");
        if (examContent) examContent.style.display = "none";

        // SECURITY: Always show completion message without scores
        // Never display score data to students regardless of show_results_setting
        const examResult = document.getElementById("exam-result");
        if (examResult) examResult.style.display = "flex";

        const rScore = document.getElementById("result-score");
        if (rScore) {
          rScore.textContent = "Selesai";
          rScore.style.fontSize = "2.5rem";
        }

        const rScoreLabel = document.getElementById("result-score-label");
        if (rScoreLabel) rScoreLabel.textContent = "Ujian telah dikumpulkan";

        const rStats = document.querySelector(".result-stats");
        if (rStats) rStats.style.display = "none";

        const rReason = document.getElementById("result-reason");
        if (rReason)
          rReason.textContent =
            "Hasil ujian belum tersedia. Hubungi guru untuk melihat nilai.";
      } else {
        alert("Gagal menyimpan jawaban: " + data.message);
      }
    } catch (error) {
      console.error("[ExamEngine] Submit error:", error);
      alert(
        "Terjadi kesalahan koneksi saat mengirim jawaban. Silakan hubungi pengawas."
      );
    }
  },

  async logAgreement(examId) {
    console.log("[ExamEngine] Logging agreement for exam:", examId);

    try {
      const response = await fetch("../php/exam_api.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          action: "log_agreement",
          exam_id: examId,
          timestamp: new Date().toISOString(),
        }),
      });

      const data = await response.json();

      if (data.success) {
        console.log("[ExamEngine] Agreement logged successfully");
        return true;
      } else {
        console.error("[ExamEngine] Failed to log agreement:", data.message);
        return false;
      }
    } catch (error) {
      console.error("[ExamEngine] Error logging agreement:", error);
      return false;
    }
  },
};
