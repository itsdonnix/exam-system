<?php
// Shared sidebar for teacher pages
// Requires $teacherData array and $activePage variable to be available
?>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="layout">
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-avatar-section">
      <div
        class="sidebar-avatar"
        id="sidebarAvatar"
        style="background: var(--accent)">
        <?= htmlspecialchars($teacherData['avatar_initial']) ?>
      </div>
      <div class="sidebar-avatar-name" id="sidebarAvatarName">
        <?= htmlspecialchars($teacherData['full_name_with_gelar']) ?>
      </div>
      <div class="sidebar-avatar-role" id="sidebarAvatarRole">Guru</div>
    </div>
    <ul class="sidebar-menu">
      <li>
        <a href="dashboard.php" class="<?= $activePage === 'dashboard' ? 'active' : '' ?>">
          <span class="icon">🏠</span> Dashboard
        </a>
      </li>
      <li>
        <a href="create-exam.html" class="<?= $activePage === 'create-exam' ? 'active' : '' ?>">
          <span class="icon">➕</span> Buat Ujian Baru
        </a>
      </li>
      <li>
        <a href="question-bank.html" class="<?= $activePage === 'question-bank' ? 'active' : '' ?>">
          <span class="icon">📚</span> Bank Soal
        </a>
      </li>
      <li>
        <a href="results.html" class="<?= $activePage === 'results' ? 'active' : '' ?>">
          <span class="icon">📊</span> Hasil Ujian
        </a>
      </li>
      <li>
        <a href="students.php" class="<?= $activePage === 'students' ? 'active' : '' ?>"><span class="icon">👥</span> Data Siswa</a>
      </li>
      <li>
        <a href="settings.php" class="<?= $activePage === 'settings' ? 'active' : '' ?>">
          <span class="icon">⚙️</span> Pengaturan
        </a>
      </li>
    </ul>
  </aside>
