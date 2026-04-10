# 🎓 ExamSafe — Sistem Ujian Online SMA

Website ujian online yang aman dan anti-menyontek untuk SMA.

---

## 📁 Struktur Proyek

```
exam-system/
├── index.html              ← Halaman Login (Siswa / Guru / Admin)
├── css/
│   └── style.css           ← Global CSS (Poppins font, komponen UI)
├── js/
│   ├── security.js         ← Modul keamanan anti-menyontek
│   └── exam.js             ← Engine ujian (timer, soal acak, submit)
├── student/
│   ├── dashboard.html      ← Dashboard siswa (daftar ujian, nilai)
│   └── exam.html           ← Halaman ujian (fullscreen + security)
├── teacher/
│   ├── dashboard.html      ← Dashboard guru (kelola ujian)
│   ├── create-exam.html    ← Buat/edit soal ujian
│   ├── results.html        ← Laporan hasil ujian siswa
│   └── register.html       ← Pendaftaran akun guru baru
├── admin/
│   └── dashboard.html      ← Panel admin (approval guru, monitoring)
├── php/
│   ├── db.php              ← Koneksi database & helper
│   ├── login.php           ← API login (semua role)
│   ├── register.php        ← API registrasi guru
│   ├── exam_api.php        ← API ujian (get soal, submit, hasil)
│   ├── notify_supervisor.php ← API notifikasi pelanggaran
│   └── database.sql        ← Schema & seed data MySQL
└── uploads/                ← Upload file soal (gambar, audio, video)
```

---

## 🚀 Cara Menjalankan

### Prasyarat
- PHP 8.0+
- MySQL 8.0+
- Web server (Apache/Nginx) atau XAMPP/WAMP

### Langkah Setup

1. **Copy folder** `exam-system/` ke `htdocs/` (XAMPP) atau `www/` (WAMP)

2. **Import database:**
   ```sql
   mysql -u root -p < php/database.sql
   ```

3. **Konfigurasi database** di `php/db.php`:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'root');
   define('DB_PASS', 'your_password');
   define('DB_NAME', 'examsafe');
   ```

4. **Akses website:** `http://localhost/exam-system/`

---

## 🔐 Demo Login

| Role  | Username           | Password  | Kode Ujian  |
|-------|--------------------|-----------|-------------|
| Siswa | `siswa001`         | `siswa123`| `UJIAN2024` |
| Guru  | `guru@sma.sch.id`  | `guru123` | —           |
| Admin | `admin`            | `admin123`| —           |

---

## 🛡️ Fitur Keamanan Anti-Menyontek

| Fitur | Deskripsi |
|-------|-----------|
| 🔒 Fullscreen Wajib | Ujian hanya bisa dikerjakan dalam mode layar penuh |
| ⌨️ Blokir Shortcut | Ctrl+T, Ctrl+N, Ctrl+C, F12, Alt+Tab, dll. diblokir |
| 🚫 Blokir Copy-Paste | Copy, paste, cut, dan select-all dinonaktifkan |
| 👁️ Deteksi Tab Switch | Perpindahan tab/jendela langsung tercatat sebagai pelanggaran |
| 🔧 Blokir DevTools | Developer tools tidak bisa dibuka |
| ⚠️ Notifikasi Pengawas | Setiap pelanggaran langsung dikirim ke server |
| ⏹️ Auto-Stop | Ujian dihentikan otomatis setelah 3 pelanggaran |
| 🔀 Soal Acak | Urutan soal dan pilihan jawaban diacak per siswa |
| ⏱️ Timer Otomatis | Ujian otomatis dikumpulkan saat waktu habis |

---

## 👨‍🏫 Fitur Guru

- ✅ Registrasi dengan verifikasi email + approval admin
- 📝 Buat soal: pilihan ganda, esai, benar/salah
- 📎 Upload gambar/audio/video untuk soal
- ⚙️ Atur waktu, durasi, jumlah soal, KKM
- 📊 Laporan nilai dengan grafik distribusi
- 👁️ Monitor ujian real-time
- 📥 Export nilai ke Excel

---

## 🛠️ Teknologi

- **Frontend:** HTML5, CSS3, JavaScript (Vanilla)
- **Backend:** PHP 8.0+
- **Database:** MySQL 8.0+
- **Font:** Google Fonts (Poppins)
- **Security:** Fullscreen API, Visibility API, BeforeUnload Event

---

## 📞 Kontak

Dikembangkan untuk SMA Negeri 1 — Sistem Ujian Online ExamSafe
