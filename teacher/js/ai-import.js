/**
 * AI Import Module for ExamSafe
 * Handles question extraction from text/files using Gemini API
 */

let aiImportModal = null;
let extractedQuestions = [];
let currentEditIndex = null;
let currentTab = 'paste';

// Initialize modal when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    createAIImportModal();
});

function createAIImportModal() {
    // Check if modal already exists
    if (document.getElementById('aiImportModal')) return;
    
    const modalHTML = `
        <div id="aiImportModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); z-index: 1001; align-items: center; justify-content: center; overflow-y: auto;">
            <div style="background: #fff; border-radius: 20px; width: 90%; max-width: 1000px; margin: 40px auto; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 40px rgba(0,0,0,0.2);">
                <div style="padding: 24px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                    <h2 style="color: var(--primary); margin: 0; font-size: 1.4rem;">🤖 Impor Soal dengan AI</h2>
                    <button onclick="closeAIImportModal()" style="background: none; border: none; font-size: 1.8rem; cursor: pointer; color: #64748b;">&times;</button>
                </div>
                
                <div style="padding: 20px 24px;">
                    <!-- Tabs -->
                    <div style="display: flex; gap: 12px; border-bottom: 2px solid #e2e8f0; margin-bottom: 24px;">
                        <button class="ai-tab-btn active" data-tab="paste" onclick="switchAITab('paste')" style="padding: 10px 20px; background: none; border: none; cursor: pointer; font-weight: 600; color: var(--primary); border-bottom: 2px solid var(--primary); margin-bottom: -2px;">📝 Tempel Teks</button>
                        <button class="ai-tab-btn" data-tab="upload" onclick="switchAITab('upload')" style="padding: 10px 20px; background: none; border: none; cursor: pointer; font-weight: 600; color: #94a3b8; border-bottom: 2px solid transparent;">📁 Unggah File</button>
                    </div>
                    
                    <!-- Paste Tab -->
                    <div id="ai-tab-paste" class="ai-tab-content" style="display: block;">
                        <div class="form-group">
                            <label>Tempel teks soal dari dokumen (PDF, Word, atau salinan teks)</label>
                            <textarea id="ai-paste-text" class="form-control" rows="10" placeholder="Contoh:&#10;1. Apa ibu kota Indonesia?&#10;   A. Jakarta ✓&#10;   B. Surabaya&#10;   C. Bandung&#10;   D. Medan&#10;&#10;2. Jelaskan proses fotosintesis!&#10;&#10;3. Benar/Salah: Bumi berbentuk datar. (Salah)"></textarea>
                        </div>
                    </div>
                    
                    <!-- Upload Tab -->
                    <div id="ai-tab-upload" class="ai-tab-content" style="display: none;">
                        <div class="upload-area" id="ai-upload-area" style="border: 2px dashed #cbd5e1; border-radius: 12px; padding: 48px; text-align: center; cursor: pointer; transition: all 0.2s;">
                            <input type="file" id="ai-file-input" accept=".pdf,.docx,.txt" style="display: none;">
                            <div style="font-size: 3rem; margin-bottom: 12px;">📄</div>
                            <p style="margin-bottom: 8px; font-weight: 600;">Klik atau seret file ke sini</p>
                            <p style="font-size: 0.85rem; color: #64748b;">PDF, DOCX, atau TXT (Maks. 10MB)</p>
                            <p id="ai-file-name" style="font-size: 0.8rem; color: var(--primary); margin-top: 12px; display: none;"></p>
                        </div>
                    </div>
                    
                    <!-- Loading Indicator -->
                    <div id="ai-loading" style="display: none; text-align: center; padding: 40px;">
                        <div style="width: 50px; height: 50px; border: 4px solid #e2e8f0; border-top-color: var(--primary); border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 16px;"></div>
                        <p>Memproses dengan AI Gemini... Mohon tunggu sebentar.</p>
                        <small style="color: #64748b;">Proses ini bisa memakan waktu 10-30 detik tergantung ukuran dokumen.</small>
                    </div>
                    
                    <div id="ai-error" style="display: none; background: #fee2e2; border-left: 4px solid #ef4444; padding: 16px; border-radius: 8px; margin-top: 20px;">
                        <strong style="color: #991b1b;">❌ Error:</strong>
                        <p id="ai-error-message" style="margin-top: 8px; color: #7f1d1d;"></p>
                        <div id="ai-raw-response" style="display: none; margin-top: 12px;">
                            <details>
                                <summary style="cursor: pointer; color: #64748b;">Lihat respons mentah AI</summary>
                                <pre id="ai-raw-content" style="background: #fff; padding: 12px; border-radius: 8px; margin-top: 8px; font-size: 0.8rem; overflow-x: auto;"></pre>
                            </details>
                        </div>
                    </div>
                </div>
                
                <div style="padding: 16px 24px; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between;">
                    <button class="btn btn-outline" onclick="closeAIImportModal()">Batal</button>
                    <button class="btn btn-primary" id="ai-process-btn" onclick="processWithAI()">🚀 Proses dengan AI</button>
                </div>
            </div>
        </div>
        
        <style>
            @keyframes spin {
                to { transform: rotate(360deg); }
            }
            .ai-tab-btn.active {
                color: var(--primary) !important;
                border-bottom-color: var(--primary) !important;
            }
            .upload-area.drag-over {
                border-color: var(--primary);
                background: #eff6ff;
            }
        </style>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    aiImportModal = document.getElementById('aiImportModal');
    
    // Setup file upload handlers
    const uploadArea = document.getElementById('ai-upload-area');
    const fileInput = document.getElementById('ai-file-input');
    
    if (uploadArea) {
        uploadArea.addEventListener('click', () => fileInput.click());
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('drag-over');
        });
        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('drag-over');
        });
        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('drag-over');
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                handleFileSelection(files[0]);
            }
        });
    }
    
    if (fileInput) {
        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                handleFileSelection(e.target.files[0]);
            }
        });
    }
}

function handleFileSelection(file) {
    const maxSize = 10 * 1024 * 1024; // 10MB
    if (file.size > maxSize) {
        alert('File terlalu besar! Maksimal 10MB.');
        return;
    }
    
    const allowedTypes = ['.pdf', '.docx', '.txt'];
    const ext = '.' + file.name.split('.').pop().toLowerCase();
    if (!allowedTypes.includes(ext)) {
        alert('Tipe file tidak didukung. Gunakan PDF, DOCX, atau TXT.');
        return;
    }
    
    const fileNameSpan = document.getElementById('ai-file-name');
    if (fileNameSpan) {
        fileNameSpan.textContent = `📄 ${file.name}`;
        fileNameSpan.style.display = 'block';
    }
}

function switchAITab(tab) {
    currentTab = tab;
    
    // Update tab buttons
    document.querySelectorAll('.ai-tab-btn').forEach(btn => {
        btn.classList.remove('active');
        btn.style.color = '#94a3b8';
        btn.style.borderBottomColor = 'transparent';
    });
    document.querySelector(`.ai-tab-btn[data-tab="${tab}"]`).classList.add('active');
    document.querySelector(`.ai-tab-btn[data-tab="${tab}"]`).style.color = 'var(--primary)';
    document.querySelector(`.ai-tab-btn[data-tab="${tab}"]`).style.borderBottomColor = 'var(--primary)';
    
    // Show/hide tab content
    document.getElementById('ai-tab-paste').style.display = tab === 'paste' ? 'block' : 'none';
    document.getElementById('ai-tab-upload').style.display = tab === 'upload' ? 'block' : 'none';
    
    // Hide error/loading
    document.getElementById('ai-loading').style.display = 'none';
    document.getElementById('ai-error').style.display = 'none';
}

function openAIImportModal() {
    if (!aiImportModal) createAIImportModal();
    aiImportModal.style.display = 'flex';
    
    // Reset state
    switchAITab('paste');
    document.getElementById('ai-paste-text').value = '';
    document.getElementById('ai-file-input').value = '';
    document.getElementById('ai-file-name').style.display = 'none';
    document.getElementById('ai-loading').style.display = 'none';
    document.getElementById('ai-error').style.display = 'none';
    extractedQuestions = [];
}

function closeAIImportModal() {
    if (aiImportModal) aiImportModal.style.display = 'none';
}

async function processWithAI() {
    const processBtn = document.getElementById('ai-process-btn');
    const loadingDiv = document.getElementById('ai-loading');
    const errorDiv = document.getElementById('ai-error');
    
    // Disable button and show loading
    processBtn.disabled = true;
    processBtn.textContent = '⏳ Memproses...';
    loadingDiv.style.display = 'block';
    errorDiv.style.display = 'none';
    
    let formData = new FormData();
    formData.append('action', 'extract');
    
    if (currentTab === 'paste') {
        const text = document.getElementById('ai-paste-text').value.trim();
        if (!text) {
            alert('Harap tempelkan teks soal terlebih dahulu.');
            processBtn.disabled = false;
            processBtn.textContent = '🚀 Proses dengan AI';
            loadingDiv.style.display = 'none';
            return;
        }
        formData.append('text', text);
    } else {
        const fileInput = document.getElementById('ai-file-input');
        if (!fileInput.files || fileInput.files.length === 0) {
            alert('Harap pilih file terlebih dahulu.');
            processBtn.disabled = false;
            processBtn.textContent = '🚀 Proses dengan AI';
            loadingDiv.style.display = 'none';
            return;
        }
        formData.append('file', fileInput.files[0]);
    }
    
    try {
        const response = await fetch('../php/ai_import.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            extractedQuestions = result.questions;
            closeAIImportModal();
            showPreviewEditor(extractedQuestions);
        } else {
            // Show error with raw response option
            let errorMsg = result.message || 'Terjadi kesalahan saat memproses.';
            document.getElementById('ai-error-message').textContent = errorMsg;
            
            if (result.raw_response) {
                const rawDiv = document.getElementById('ai-raw-response');
                const rawContent = document.getElementById('ai-raw-content');
                rawContent.textContent = result.raw_response;
                rawDiv.style.display = 'block';
            }
            
            errorDiv.style.display = 'block';
        }
    } catch (error) {
        console.error('AI Process error:', error);
        document.getElementById('ai-error-message').textContent = 'Error koneksi: ' + error.message;
        errorDiv.style.display = 'block';
    } finally {
        processBtn.disabled = false;
        processBtn.textContent = '🚀 Proses dengan AI';
        loadingDiv.style.display = 'none';
    }
}

function showPreviewEditor(questions) {
    // Create preview modal
    let previewModal = document.getElementById('ai-preview-modal');
    if (previewModal) previewModal.remove();
    
    const letters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
    
    const modalHTML = `
        <div id="ai-preview-modal" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); z-index: 1002; overflow-y: auto;">
            <div style="background: #fff; border-radius: 20px; width: 95%; max-width: 1200px; margin: 30px auto; max-height: 90vh; overflow-y: auto;">
                <div style="padding: 20px 24px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; background: #fff; z-index: 10;">
                    <h2 style="margin: 0; color: var(--primary);">📝 Preview & Edit Soal</h2>
                    <button onclick="closePreviewEditor()" style="background: none; border: none; font-size: 1.8rem; cursor: pointer;">&times;</button>
                </div>
                
                <div style="padding: 24px;">
                    <p style="margin-bottom: 20px; color: #64748b;">${questions.length} soal berhasil diekstrak. Edit jika perlu, lalu klik "Tambah ke Ujian".</p>
                    
                    <div id="ai-preview-list">
                        ${questions.map((q, idx) => `
                            <div class="preview-question-card" data-index="${idx}" style="background: #f8fafc; border-radius: 12px; padding: 20px; margin-bottom: 20px; border-left: 4px solid var(--primary-light);">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                                    <h3 style="margin: 0; font-size: 1rem;">Soal ${idx + 1} 
                                        <span style="background: #e2e8f0; padding: 2px 8px; border-radius: 12px; font-size: 0.7rem;">
                                            ${q.type === 'multiple' ? 'Pilihan Ganda' : q.type === 'checkbox' ? 'PG Kompleks' : q.type === 'truefalse' ? 'Benar/Salah' : 'Esai'}
                                        </span>
                                    </h3>
                                    <button class="btn btn-sm btn-outline" onclick="editQuestion(${idx})">✏️ Edit</button>
                                </div>
                                
                                <div class="preview-question-text" style="font-weight: 500; margin-bottom: 16px; color: #1e293b;">${escapeHtml(q.text)}</div>
                                
                                ${q.type !== 'essay' ? `
                                    <div class="preview-options" style="margin-left: 20px;">
                                        ${q.options.map((opt, optIdx) => {
                                            let isCorrect = false;
                                            if (q.type === 'checkbox') {
                                                isCorrect = q.correct_answers_checkbox && q.correct_answers_checkbox.includes(optIdx.toString());
                                            } else {
                                                isCorrect = q.correct === optIdx.toString();
                                            }
                                            return `
                                                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px; padding: 8px; background: ${isCorrect ? '#d1fae5' : '#fff'}; border-radius: 8px;">
                                                    <strong>${letters[optIdx] || optIdx + 1}.</strong>
                                                    <span>${escapeHtml(opt.text || '')}</span>
                                                    ${isCorrect ? '<span style="color: #10b981; font-size: 0.75rem;">✓ Benar</span>' : ''}
                                                </div>
                                            `;
                                        }).join('')}
                                    </div>
                                ` : `
                                    <div style="background: #fff3e0; padding: 12px; border-radius: 8px; margin-top: 8px;">
                                        <strong>Kunci Jawaban:</strong>
                                        <p style="margin-top: 8px; font-size: 0.9rem;">${escapeHtml(q.correct || '(Belum ada kunci jawaban)')}</p>
                                    </div>
                                `}
                            </div>
                        `).join('')}
                    </div>
                </div>
                
                <div style="padding: 16px 24px; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; position: sticky; bottom: 0; background: #fff;">
                    <button class="btn btn-outline" onclick="closePreviewEditor()">Batal</button>
                    <button class="btn btn-success" onclick="importQuestionsToExam()">✅ Tambahkan ${questions.length} Soal ke Ujian</button>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHTML);
}

function closePreviewEditor() {
    const modal = document.getElementById('ai-preview-modal');
    if (modal) modal.remove();
}

function editQuestion(index) {
    const q = extractedQuestions[index];
    if (!q) return;
    
    currentEditIndex = index;
    
    let editModal = document.getElementById('ai-edit-modal');
    if (editModal) editModal.remove();
    
    const letters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
    
    let optionsHtml = '';
    if (q.type !== 'essay') {
        optionsHtml = `
            <div class="form-group">
                <label>Pilihan Jawaban</label>
                <div id="edit-options-container">
                    ${q.options.map((opt, optIdx) => `
                        <div class="option-row" data-opt-index="${optIdx}">
                            <span class="option-label">${letters[optIdx] || optIdx + 1}.</span>
                            <input type="text" class="form-control option-text" value="${escapeHtml(opt.text || '')}" style="flex: 1;" onchange="updateOptionText(${optIdx}, this.value)">
                            <button class="btn btn-sm btn-danger" onclick="removeOption(${optIdx})">✕</button>
                        </div>
                    `).join('')}
                </div>
                <button class="btn btn-sm btn-outline" style="margin-top: 8px;" onclick="addEditOption()">+ Tambah Pilihan</button>
            </div>
            
            <div class="form-group">
                <label>Jawaban Benar</label>
                <div id="edit-correct-container">
                    ${q.type === 'checkbox' ? 
                        q.options.map((opt, optIdx) => `
                            <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                                <input type="checkbox" value="${optIdx}" ${q.correct_answers_checkbox && q.correct_answers_checkbox.includes(optIdx.toString()) ? 'checked' : ''} onchange="updateCorrectSelection(${optIdx}, this.checked)">
                                <span>${letters[optIdx] || optIdx + 1}. ${escapeHtml(opt.text || '')}</span>
                            </label>
                        `).join('')
                    :
                        q.options.map((opt, optIdx) => `
                            <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                                <input type="radio" name="edit-correct" value="${optIdx}" ${q.correct === optIdx.toString() ? 'checked' : ''} onchange="updateCorrectRadio('${optIdx}')">
                                <span>${letters[optIdx] || optIdx + 1}. ${escapeHtml(opt.text || '')}</span>
                            </label>
                        `).join('')
                    }
                </div>
            </div>
        `;
    } else {
        optionsHtml = `
            <div class="form-group">
                <label>Kunci Jawaban / Rubrik</label>
                <textarea id="edit-essay-answer" class="form-control" rows="4">${escapeHtml(q.correct || '')}</textarea>
            </div>
        `;
    }
    
    const editModalHTML = `
        <div id="ai-edit-modal" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); z-index: 1003; display: flex; align-items: center; justify-content: center;">
            <div style="background: #fff; border-radius: 16px; width: 90%; max-width: 800px; max-height: 85vh; overflow-y: auto;">
                <div style="padding: 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between;">
                    <h3>✏️ Edit Soal ${index + 1}</h3>
                    <button onclick="closeEditModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer;">&times;</button>
                </div>
                <div style="padding: 20px;">
                    <div class="form-group">
                        <label>Teks Soal</label>
                        <textarea id="edit-question-text" class="form-control" rows="3">${escapeHtml(q.text)}</textarea>
                    </div>
                    ${optionsHtml}
                </div>
                <div style="padding: 16px 20px; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 12px;">
                    <button class="btn btn-outline" onclick="closeEditModal()">Batal</button>
                    <button class="btn btn-primary" onclick="saveEditQuestion()">Simpan Perubahan</button>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', editModalHTML);
}

function closeEditModal() {
    const modal = document.getElementById('ai-edit-modal');
    if (modal) modal.remove();
    currentEditIndex = null;
}

function updateOptionText(optIndex, value) {
    if (currentEditIndex !== null && extractedQuestions[currentEditIndex]) {
        if (!extractedQuestions[currentEditIndex].options[optIndex]) {
            extractedQuestions[currentEditIndex].options[optIndex] = { text: '', image: null };
        }
        extractedQuestions[currentEditIndex].options[optIndex].text = value;
    }
}

function addEditOption() {
    if (currentEditIndex !== null && extractedQuestions[currentEditIndex]) {
        const newIndex = extractedQuestions[currentEditIndex].options.length;
        extractedQuestions[currentEditIndex].options.push({ text: '', image: null });
        
        // Refresh edit modal
        closeEditModal();
        editQuestion(currentEditIndex);
    }
}

function removeOption(optIndex) {
    if (currentEditIndex !== null && extractedQuestions[currentEditIndex]) {
        extractedQuestions[currentEditIndex].options.splice(optIndex, 1);
        
        // Adjust correct answers
        const q = extractedQuestions[currentEditIndex];
        if (q.type === 'checkbox') {
            q.correct_answers_checkbox = q.correct_answers_checkbox
                .map(idx => parseInt(idx))
                .filter(idx => idx !== optIndex)
                .map(idx => idx > optIndex ? (idx - 1).toString() : idx.toString());
        } else {
            const currentCorrect = parseInt(q.correct);
            if (currentCorrect === optIndex) {
                q.correct = '';
            } else if (currentCorrect > optIndex) {
                q.correct = (currentCorrect - 1).toString();
            }
        }
        
        closeEditModal();
        editQuestion(currentEditIndex);
    }
}

function updateCorrectSelection(optIndex, isChecked) {
    if (currentEditIndex !== null && extractedQuestions[currentEditIndex]) {
        const q = extractedQuestions[currentEditIndex];
        if (!q.correct_answers_checkbox) q.correct_answers_checkbox = [];
        
        const idxStr = optIndex.toString();
        if (isChecked && !q.correct_answers_checkbox.includes(idxStr)) {
            q.correct_answers_checkbox.push(idxStr);
        } else if (!isChecked) {
            q.correct_answers_checkbox = q.correct_answers_checkbox.filter(i => i !== idxStr);
        }
    }
}

function updateCorrectRadio(value) {
    if (currentEditIndex !== null && extractedQuestions[currentEditIndex]) {
        extractedQuestions[currentEditIndex].correct = value;
    }
}

function saveEditQuestion() {
    if (currentEditIndex !== null && extractedQuestions[currentEditIndex]) {
        const q = extractedQuestions[currentEditIndex];
        
        // Save question text
        q.text = document.getElementById('edit-question-text').value;
        
        if (q.type === 'essay') {
            q.correct = document.getElementById('edit-essay-answer').value;
        } else {
            // Options already updated via updateOptionText
            // Correct answers already updated via updateCorrectSelection/updateCorrectRadio
        }
        
        closeEditModal();
        
        // Refresh preview
        closePreviewEditor();
        showPreviewEditor(extractedQuestions);
    }
}

function importQuestionsToExam() {
    if (!extractedQuestions || extractedQuestions.length === 0) {
        alert('Tidak ada soal untuk diimpor.');
        return;
    }
    
    // Add each question to the exam
    for (const q of extractedQuestions) {
        // Convert to format expected by addQuestion()
        const newQuestion = {
            type: q.type,
            text: q.text,
            media_url: [],
            options: q.options || [],
            correct: q.type === 'checkbox' ? '' : (q.correct || ''),
            correct_answers_checkbox: q.type === 'checkbox' ? (q.correct_answers_checkbox || []) : [],
            points: q.points || 1,
            difficulty: q.difficulty || 'sedang'
        };
        
        // Call existing addQuestion function from create-exam.html
        if (typeof window.addQuestion === 'function') {
            window.addQuestion(q.type, newQuestion);
        } else {
            console.error('addQuestion function not found');
        }
    }
    
    closePreviewEditor();
    alert(`✅ ${extractedQuestions.length} soal berhasil ditambahkan ke ujian!`);
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.appendChild(document.createTextNode(text));
    return div.innerHTML;
}