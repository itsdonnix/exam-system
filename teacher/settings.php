<?php
require_once 'includes/init.php';
$activePage = 'settings';

// Refresh session timeout
$_SESSION['login_time'] = time();

// Generate CSRF tokens for forms
require_once '../includes/csrf.php';
$csrf_token_profile = generateCSRFToken();
$csrf_token_ai = generateCSRFToken();

// Store specific tokens in session for validation
$_SESSION['csrf_token_profile'] = $csrf_token_profile;
$_SESSION['csrf_token_ai'] = $csrf_token_ai;
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Pengaturan — ExamSafe</title>
    <link rel="stylesheet" href="../css/style.css" />
    <style>
        .settings-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 24px;
            border-bottom: 2px solid #e2e8f0;
        }

        .settings-tab {
            padding: 10px 20px;
            background: none;
            border: none;
            cursor: pointer;
            font-weight: 600;
            color: #64748b;
            font-family: "Poppins", sans-serif;
            transition: all 0.2s;
        }

        .settings-tab.active {
            color: var(--primary);
            border-bottom: 2px solid var(--primary);
            margin-bottom: -2px;
        }

        .settings-tab-content {
            display: none;
        }

        .settings-tab-content.active {
            display: block;
        }

        .api-key-status {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            margin-left: 8px;
        }

        .api-key-status.configured {
            background: #d1fae5;
            color: #065f46;
        }

        .api-key-status.not-configured {
            background: #fee2e2;
            color: #991b1b;
        }

        /* Enhanced Diagnostic Styles */
        .diagnostic-container {
            margin-top: 20px;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }

        .diagnostic-header {
            background: #f8fafc;
            padding: 12px 16px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        .diagnostic-title {
            font-weight: 700;
            color: #1e293b;
        }

        .diagnostic-steps {
            padding: 0;
        }

        .diagnostic-step {
            padding: 12px 16px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.9rem;
        }

        .diagnostic-step:last-child {
            border-bottom: none;
        }

        .step-status {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            flex-shrink: 0;
        }

        .step-status.passed {
            background: #d1fae5;
            color: #065f46;
        }

        .step-status.failed {
            background: #fee2e2;
            color: #991b1b;
        }

        .step-status.warning {
            background: #fef3c7;
            color: #92400e;
        }

        .step-status.pending {
            background: #e2e8f0;
            color: #64748b;
        }

        .step-name {
            font-weight: 600;
            color: #334155;
            min-width: 140px;
        }

        .step-message {
            flex: 1;
            color: #475569;
            font-size: 0.85rem;
        }

        .step-latency {
            color: #94a3b8;
            font-size: 0.75rem;
            font-family: monospace;
        }

        .diagnostic-summary {
            background: #f1f5f9;
            padding: 12px 16px;
            border-top: 1px solid #e2e8f0;
        }

        .suggestions-list {
            margin-top: 8px;
            padding-left: 20px;
        }

        .suggestions-list li {
            color: #dc2626;
            font-size: 0.85rem;
            margin-bottom: 4px;
        }

        .sample-response {
            background: #1e293b;
            color: #e2e8f0;
            padding: 12px;
            border-radius: 8px;
            font-size: 0.8rem;
            font-family: monospace;
            overflow-x: auto;
            white-space: pre-wrap;
            word-break: break-word;
            max-height: 200px;
            overflow-y: auto;
        }

        .btn-copy {
            background: none;
            border: 1px solid #cbd5e1;
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 0.75rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-copy:hover {
            background: #e2e8f0;
        }

        .diagnostic-loading {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid #e2e8f0;
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
            margin-right: 8px;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }
    </style>
</head>

<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <div>
                <div class="page-title">⚙️ Pengaturan Akun</div>
                <div class="page-subtitle">
                    Kelola profil dan konfigurasi AI untuk impor soal otomatis
                </div>
            </div>
        </div>

        <div class="settings-tabs">
            <button class="settings-tab active" data-tab="profile">👤 Profil</button>
            <button class="settings-tab" data-tab="ai">🤖 AI (Gemini)</button>
        </div>

        <div id="settings-profile" class="settings-tab-content active">
            <div class="card">
                <h3>Profil Guru</h3>
                <p style="margin-bottom: 20px; color: #64748b">
                    Ubah data profil Anda di bawah ini.
                </p>
                <div id="alert-settings"></div>
                <form id="settings-form" onsubmit="saveProfile(event)">
                    <input type="hidden" id="csrf-profile-token" value="<?php echo htmlspecialchars($csrf_token_profile); ?>">
                    <div class="form-group">
                        <label>Nama Lengkap (Termasuk Gelar)</label>
                        <input type="text" class="form-control" id="profile-name" required />
                    </div>
                    <div class="form-group">
                        <label>Email .id.Belajar</label>
                        <input type="email" class="form-control" id="profile-email" required />
                    </div>
                    <div class="form-group">
                        <label>Nomor HP</label>
                        <input type="tel" class="form-control" id="profile-phone" />
                    </div>
                    <hr style="margin: 20px 0; border-top: 1px solid #e2e8f0" />
                    <div class="form-group">
                        <label>Ganti Password (Opsional)</label>
                        <input type="password" class="form-control" id="new-password" placeholder="Biarkan kosong jika tidak ingin ganti" />
                    </div>
                    <button type="submit" class="btn btn-primary">💾 Simpan Perubahan</button>
                </form>
            </div>
        </div>

        <div id="settings-ai" class="settings-tab-content">
            <div class="card">
                <h3>🤖 Pengaturan AI (Gemini)</h3>
                <p style="margin-bottom: 20px; color: #64748b">
                    Konfigurasi API Key dan model untuk fitur
                    <strong>Impor Soal Otomatis</strong> di halaman
                    <a href="create-exam.php">Buat Ujian</a>.
                </p>
                <div id="alert-ai-settings"></div>
                <form id="ai-settings-form" onsubmit="saveAISettings(event)">
                    <input type="hidden" id="csrf-ai-token" value="<?php echo htmlspecialchars($csrf_token_ai); ?>">
                    <div class="form-group">
                        <label>Gemini API Key</label>
                        <input type="password" class="form-control" id="gemini-api-key" placeholder="Masukkan API Key dari Google AI Studio" />
                        <small>
                            Dapatkan API Key gratis di
                            <a href="https://aistudio.google.com/" target="_blank">Google AI Studio</a>
                            <span id="api-key-status" class="api-key-status not-configured">Belum dikonfigurasi</span>
                        </small>
                    </div>
                    <div class="form-group">
                        <label>Model Gemini</label>
                        <select class="form-control" id="gemini-model">
                            <optgroup label="Gemini 2.5 (Rekomendasi)">
                                <option value="gemini-2.5-pro">Gemini 2.5 Pro (Kualitas Tertinggi)</option>
                                <option value="gemini-2.5-flash">Gemini 2.5 Flash (Standar)</option>
                                <option value="gemini-2.5-flash-lite">Gemini 2.5 Flash Lite (Cepat & Hemat)</option>
                            </optgroup>
                            <optgroup label="Gemini 2.0 (Legacy)">
                                <option value="gemini-2.0-flash">Gemini 2.0 Flash</option>
                                <option value="gemini-2.0-flash-lite">Gemini 2.0 Flash Lite</option>
                            </optgroup>
                        </select>
                    </div>
                    <hr style="margin: 20px 0; border-top: 1px solid #e2e8f0" />
                    <div class="form-group">
                        <label>Batasan File</label>
                        <ul style="margin-top: 8px; padding-left: 20px; color: #64748b">
                            <li>Maksimal ukuran file: <strong>10 MB</strong></li>
                            <li>Format didukung: <strong>PDF, DOCX, TXT</strong></li>
                            <li>Maksimal teks yang diproses: <strong>~30.000 karakter</strong></li>
                        </ul>
                    </div>
                    <div style="display: flex; gap: 12px">
                        <button type="submit" class="btn btn-primary">💾 Simpan Pengaturan AI</button>
                        <button type="button" class="btn btn-outline" id="test-connection-btn" onclick="testAIConnection()">🔌 Test Koneksi</button>
                    </div>
                </form>
                <div id="diagnostic-results" style="display: none"></div>
            </div>
        </div>
    </main>

    <script src="../js/utils.js"></script>
    <script src="../js/teacher-layout.js"></script>
    <script>
        // CSRF tokens injected from PHP
        const csrfTokenProfile = document.getElementById('csrf-profile-token').value;
        const csrfTokenAI = document.getElementById('csrf-ai-token').value;

        // Tab switching
        document.querySelectorAll(".settings-tab").forEach((tab) => {
            tab.addEventListener("click", () => {
                const tabName = tab.dataset.tab;
                document.querySelectorAll(".settings-tab").forEach((t) => t.classList.remove("active"));
                tab.classList.add("active");
                document.querySelectorAll(".settings-tab-content").forEach((content) => content.classList.remove("active"));
                document.getElementById(`settings-${tabName}`).classList.add("active");
            });
        });

        document.addEventListener("DOMContentLoaded", () => {
            fetchProfile();
            fetchAISettings();
        });

        async function fetchProfile() {
            try {
                const response = await fetch("../php/exam_api.php?action=get_profile");
                const data = await response.json();
                if (data.success) {
                    const user = data.user;
                    document.getElementById("profile-name").value = user.full_name;
                    document.getElementById("profile-email").value = user.email;
                    document.getElementById("profile-phone").value = user.phone || "";
                }
            } catch (error) {
                console.error("Error fetching profile:", error);
            }
        }

        async function saveProfile(e) {
            e.preventDefault();
            const alertEl = document.getElementById("alert-settings");
            alertEl.innerHTML = '<div class="alert alert-info">Menyimpan...</div>';

            const formData = {
                action: "update_profile",
                csrf_token: csrfTokenProfile,
                full_name: document.getElementById("profile-name").value,
                email: document.getElementById("profile-email").value,
                phone: document.getElementById("profile-phone").value,
                new_password: document.getElementById("new-password").value,
            };

            try {
                const response = await fetch("../php/exam_api.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify(formData),
                });
                const result = await response.json();
                if (result.success) {
                    alertEl.innerHTML = '<div class="alert alert-success">✅ Profil berhasil diperbarui!</div>';
                    if (formData.new_password) document.getElementById("new-password").value = "";
                    fetchProfile();
                } else {
                    alertEl.innerHTML = `<div class="alert alert-danger">❌ ${result.message}</div>`;
                }
            } catch (error) {
                console.error("Save profile error:", error);
                alertEl.innerHTML = '<div class="alert alert-danger">❌ Terjadi kesalahan koneksi.</div>';
            }
        }

        async function fetchAISettings() {
            try {
                const response = await fetch("../php/get_ai_settings.php");
                const data = await response.json();
                if (data.success) {
                    const settings = data.settings;
                    if (settings.has_key) {
                        const statusSpan = document.getElementById("api-key-status");
                        statusSpan.textContent = "✓ Terkonfigurasi";
                        statusSpan.className = "api-key-status configured";
                        document.getElementById("gemini-api-key").placeholder = "•••••••• (API Key sudah tersimpan)";
                    }
                    document.getElementById("gemini-model").value = settings.gemini_model;
                }
            } catch (error) {
                console.error("Error fetching AI settings:", error);
            }
        }

        async function saveAISettings(e) {
            e.preventDefault();
            const alertEl = document.getElementById("alert-ai-settings");
            alertEl.innerHTML = '<div class="alert alert-info">Menyimpan pengaturan AI...</div>';

            const apiKey = document.getElementById("gemini-api-key").value;
            const model = document.getElementById("gemini-model").value;

            try {
                const response = await fetch("../php/save_ai_settings.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify({
                        gemini_api_key: apiKey,
                        gemini_model: model,
                        csrf_token: csrfTokenAI
                    }),
                });
                const result = await response.json();
                if (result.success) {
                    alertEl.innerHTML = '<div class="alert alert-success">✅ Pengaturan AI berhasil disimpan!</div>';
                    fetchAISettings();
                } else {
                    alertEl.innerHTML = `<div class="alert alert-danger">❌ ${result.message}</div>`;
                }
            } catch (error) {
                alertEl.innerHTML = '<div class="alert alert-danger">❌ Gagal menyimpan pengaturan. Cek koneksi.</div>';
            }
        }

        async function testAIConnection() {
            const alertEl = document.getElementById("alert-ai-settings");
            const diagnosticContainer = document.getElementById("diagnostic-results");
            alertEl.innerHTML = '<div class="alert alert-info">🔌 <span class="diagnostic-loading"></span> Menguji koneksi ke Gemini API...</div>';
            diagnosticContainer.style.display = "none";

            const testBtn = document.getElementById("test-connection-btn");
            testBtn.disabled = true;
            testBtn.textContent = "⏳ Menguji...";

            try {
                const response = await fetch("../php/ai_import.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify({
                        action: "test"
                    }),
                });
                const result = await response.json();
                if (result.success) {
                    alertEl.innerHTML = '<div class="alert alert-success">✅ ' + result.message + "</div>";
                } else {
                    alertEl.innerHTML = '<div class="alert alert-warning">⚠️ ' + (result.message || "Test completed with issues") + "</div>";
                }
                renderDiagnostics(result);
                diagnosticContainer.style.display = "block";
            } catch (error) {
                alertEl.innerHTML = '<div class="alert alert-danger">❌ Gagal terhubung ke server. Periksa koneksi internet.</div>';
                diagnosticContainer.style.display = "none";
            } finally {
                testBtn.disabled = false;
                testBtn.textContent = "🔌 Test Koneksi";
            }
        }

        function renderDiagnostics(data) {
            const container = document.getElementById("diagnostic-results");
            if (!data || !data.steps) {
                container.innerHTML = '<div class="alert alert-danger">Tidak ada data diagnostik</div>';
                return;
            }

            const getStatusIcon = (status) =>
                status === "passed" ? "✅" : status === "failed" ? "❌" : status === "warning" ? "⚠️" : "⏳";
            const getStatusClass = (status) =>
                status === "passed" ? "passed" : status === "failed" ? "failed" : status === "warning" ? "warning" : "pending";

            const stepsHtml = data.steps.map(step => `
                <div class="diagnostic-step">
                    <div class="step-status ${getStatusClass(step.status)}">${getStatusIcon(step.status)}</div>
                    <div class="step-name">${step.name}</div>
                    <div class="step-message">${escapeHtml(step.message)}</div>
                    ${step.latency_ms ? `<div class="step-latency">${step.latency_ms}ms</div>` : ""}
                </div>
            `).join("");

            const suggestionsHtml = data.suggestions && data.suggestions.length > 0 ?
                `<div style="margin-top: 12px;"><strong>💡 Saran Perbaikan:</strong><ul class="suggestions-list">${data.suggestions.map(s => `<li>🔧 ${escapeHtml(s)}</li>`).join("")}</ul></div>` :
                "";

            const summaryHtml = `
                <div class="diagnostic-summary">
                    <div><strong>📊 Ringkasan:</strong> ${escapeHtml(data.message || "")}</div>
                    ${data.api_latency_ms ? `<div><strong>⏱️ Latency API:</strong> ${data.api_latency_ms}ms</div>` : ""}
                    ${data.model_used ? `<div><strong>🤖 Model digunakan:</strong> ${escapeHtml(data.model_used)}</div>` : ""}
                    ${data.token_estimate ? `<div><strong>📝 Perkiraan token:</strong> ~${data.token_estimate}</div>` : ""}
                    ${suggestionsHtml}
                </div>
            `;

            const sampleHtml = data.sample_response ?
                `<div style="padding: 12px 16px; border-top: 1px solid #e2e8f0;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <strong>📄 Contoh Respons AI:</strong>
                        <button class="btn-copy" onclick="copyToClipboard(this)">📋 Salin</button>
                    </div>
                    <div class="sample-response">${escapeHtml(data.sample_response)}</div>
                </div>` :
                "";

            container.innerHTML = `
                <div class="diagnostic-container">
                    <div class="diagnostic-header">
                        <span class="diagnostic-title">🔍 Hasil Diagnostik Koneksi</span>
                        <button class="btn-copy" onclick="copyDiagnosticReport()">📋 Salin Laporan</button>
                    </div>
                    <div class="diagnostic-steps">${stepsHtml}</div>
                    ${summaryHtml}
                    ${sampleHtml}
                </div>
            `;
            window.lastDiagnosticData = data;
        }

        function copyToClipboard(btn) {
            const sampleDiv = btn.parentElement.nextElementSibling;
            navigator.clipboard.writeText(sampleDiv.innerText).then(() => {
                const originalText = btn.textContent;
                btn.textContent = "✅ Tersalin!";
                setTimeout(() => {
                    btn.textContent = originalText;
                }, 2000);
            });
        }

        function copyDiagnosticReport() {
            if (!window.lastDiagnosticData) return;
            const data = window.lastDiagnosticData;
            let report = `=== DIAGNOSTIC REPORT ===\nTimestamp: ${data.timestamp || new Date().toISOString()}\nSuccess: ${data.success ? "Yes" : "No"}\nMessage: ${data.message}\n\n--- STEPS ---\n`;
            data.steps.forEach(step => {
                report += `${step.name}: ${step.status} - ${step.message}${step.latency_ms ? ` (${step.latency_ms}ms)` : ""}\n`;
            });
            if (data.suggestions && data.suggestions.length) {
                report += `\n--- SUGGESTIONS ---\n`;
                data.suggestions.forEach(s => report += `- ${s}\n`);
            }
            if (data.sample_response) report += `\n--- SAMPLE RESPONSE ---\n${data.sample_response}\n`;
            navigator.clipboard.writeText(report).then(() => alert("✅ Laporan diagnostik telah disalin ke clipboard"));
        }
    </script>
</body>

</html>
