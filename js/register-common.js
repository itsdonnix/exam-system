// Register Module - Shared functionality for student/teacher registration

const RegisterUI = {
  showAlert(containerId, message, type = "danger") {
    const container = document.getElementById(containerId);
    if (!container) return;

    const alertClass =
      type === "success"
        ? "alert-success"
        : type === "warning"
        ? "alert-warning"
        : "alert-danger";

    container.innerHTML = `<div class="alert ${alertClass}">${message}</div>`;

    // Auto-hide after 5 seconds for success/info messages
    if (type !== "danger") {
      setTimeout(() => {
        if (container.innerHTML.includes(message)) {
          container.innerHTML = "";
        }
      }, 5000);
    }
  },

  clearAlert(containerId) {
    const container = document.getElementById(containerId);
    if (container) container.innerHTML = "";
  },

  setLoading(buttonElement, isLoading, originalText = null) {
    if (!buttonElement) return;

    if (isLoading) {
      buttonElement.dataset.originalText =
        originalText || buttonElement.textContent;
      buttonElement.disabled = true;
      buttonElement.textContent = "Memproses...";
    } else {
      buttonElement.disabled = false;
      buttonElement.textContent =
        buttonElement.dataset.originalText || originalText || "Submit";
    }
  },
};

const RegisterValidation = {
  validateEmail(email, requiredDomain = null) {
    if (!email) return "Email wajib diisi";

    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) return "Format email tidak valid";

    if (requiredDomain && !email.endsWith(requiredDomain)) {
      return `Email harus menggunakan domain ${requiredDomain}`;
    }

    return null;
  },

  validatePasswordStrength(password) {
    if (!password)
      return {
        valid: false,
        strength: "none",
        message: "Password wajib diisi",
      };

    if (password.length < 6) {
      return {
        valid: false,
        strength: "weak",
        message: "Password minimal 6 karakter",
      };
    }

    if (
      password.length < 10 ||
      !/[A-Z]/.test(password) ||
      !/[0-9]/.test(password)
    ) {
      return { valid: true, strength: "medium", message: "Sedang" };
    }

    return { valid: true, strength: "strong", message: "Kuat" };
  },

  getPasswordStrengthText(strength) {
    const styles = {
      weak: { color: "var(--danger)", text: "● Lemah" },
      medium: { color: "var(--warning)", text: "● Sedang" },
      strong: { color: "var(--success)", text: "● Kuat" },
    };
    return styles[strength] || { color: "var(--danger)", text: "● Lemah" };
  },

  validateRequired(value, fieldName) {
    if (!value || value.trim() === "") {
      return `${fieldName} wajib diisi`;
    }
    return null;
  },

  validateConfirmPassword(password, confirmPassword) {
    if (password !== confirmPassword) {
      return "Password tidak cocok";
    }
    return null;
  },
};

const RegisterAPI = {
  async registerStudent(data) {
    return ApiClient.post("../php/student_register.php", data);
  },

  async registerTeacher(data) {
    return ApiClient.post("../php/register.php", data);
  },

  async fetchClasses(selectElementId) {
    try {
      const result = await ApiClient.get(
        "../php/exam_api.php?action=get_classes"
      );

      if (result.success && result.classes) {
        const select = document.getElementById(selectElementId);
        if (select) {
          select.innerHTML = '<option value="">-- Pilih Kelas --</option>';
          result.classes.forEach((cls) => {
            const option = document.createElement("option");
            option.value = cls.name;
            option.textContent = cls.name;
            select.appendChild(option);
          });
        }
        return result.classes;
      }
      return [];
    } catch (error) {
      console.error("Error fetching classes:", error);
      return [];
    }
  },

  async fetchSubjects(selectElementId) {
    try {
      const result = await ApiClient.get(
        "../php/exam_api.php?action=get_subjects"
      );

      if (result.success && result.subjects) {
        const select = document.getElementById(selectElementId);
        if (select) {
          select.innerHTML =
            '<option value="">-- Pilih Mata Pelajaran --</option>';
          result.subjects.forEach((subj) => {
            const option = document.createElement("option");
            option.value = subj.name;
            option.textContent = subj.name;
            select.appendChild(option);
          });
        }
        return result.subjects;
      }
      return [];
    } catch (error) {
      console.error("Error fetching subjects:", error);
      return [];
    }
  },
};

const RegisterWizard = {
  currentStep: 1,
  totalSteps: 3,
  onStepChange: null,

  init(totalSteps, onStepChangeCallback) {
    this.totalSteps = totalSteps;
    this.onStepChange = onStepChangeCallback;
    this.showStep(1);
  },

  showStep(step) {
    // Hide all steps
    for (let i = 1; i <= this.totalSteps; i++) {
      const stepEl = document.getElementById(`reg-step-${i}`);
      if (stepEl) stepEl.style.display = "none";

      const dotEl = document.getElementById(`dot-${i}`);
      if (dotEl) dotEl.className = "dot";
    }

    // Show current step
    const currentStepEl = document.getElementById(`reg-step-${step}`);
    if (currentStepEl) currentStepEl.style.display = "block";

    // Update dots
    for (let i = 1; i <= this.totalSteps; i++) {
      const dotEl = document.getElementById(`dot-${i}`);
      if (dotEl) {
        if (i === step) dotEl.className = "dot active";
        else if (i < step) dotEl.className = "dot done";
        else dotEl.className = "dot";
      }
    }

    this.currentStep = step;

    if (this.onStepChange) {
      this.onStepChange(step);
    }
  },

  nextStep(validateCallback) {
    if (validateCallback && !validateCallback(this.currentStep)) {
      return false;
    }

    if (this.currentStep < this.totalSteps) {
      this.showStep(this.currentStep + 1);
      return true;
    }
    return false;
  },

  prevStep() {
    if (this.currentStep > 1) {
      this.showStep(this.currentStep - 1);
      return true;
    }
    return false;
  },

  goToStep(step) {
    if (step >= 1 && step <= this.totalSteps) {
      this.showStep(step);
      return true;
    }
    return false;
  },
};
