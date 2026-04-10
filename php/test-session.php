<?php
/**
 * Debug Session & API - Investigasi issue navbar
 */

require_once 'db.php';
session_start();

// Debug: Print session
echo "<h2>🔍 Debug Session Information</h2>";
echo "<pre>";
echo "SESSION DATA:\n";
print_r($_SESSION);
echo "\nSESSION ID: " . session_id() . "\n";
echo "\nChecking:\n";
echo "✓ user_id: " . (isset($_SESSION['user_id']) ? "YES (ID: {$_SESSION['user_id']})" : "NO") . "\n";
echo "✓ role: " . (isset($_SESSION['role']) ? "YES (Role: {$_SESSION['role']})" : "NO") . "\n";
echo "✓ full_name: " . (isset($_SESSION['full_name']) ? "YES (Name: {$_SESSION['full_name']})" : "NO") . "\n";
echo "</pre>";

// Test database directly
echo "<h2>🗄️ Database Check</h2>";
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT id, full_name, gelar, approval_status, is_active FROM teachers WHERE id = ?");
    $stmt->execute([$_SESSION['user_id'] ?? 0]);
    $teacher = $stmt->fetch();
    echo "<pre>";
    echo "Teacher Record:\n";
    print_r($teacher);
    echo "</pre>";
} catch (Exception $e) {
    echo "<p style='color:red'>Database Error: " . $e->getMessage() . "</p>";
}

// Test API endpoints with JavaScript
echo "<h2>📡 Testing API (JavaScript fetch)</h2>";
?>

<style>
  body { font-family: Arial; margin: 20px; background: #f5f5f5; }
  .test-result { background: white; padding: 15px; margin: 10px 0; border-radius: 5px; border-left: 4px solid #2563eb; }
  .success { border-left-color: #10b981; }
  .error { border-left-color: #ef4444; }
  .loading { border-left-color: #f59e0b; }
  pre { background: #f1f5f9; padding: 10px; overflow-x: auto; border-radius: 3px; }
  button { padding: 10px 15px; background: #2563eb; color: white; border: none; border-radius: 5px; cursor: pointer; }
  button:hover { background: #1d4ed8; }
</style>

<div class="test-result loading">
  <h3>Test 1: fetch get_profile (WITHOUT credentials)</h3>
  <button onclick="testGetProfile()">Run Test</button>
  <pre id="result-profile">Waiting...</pre>
</div>

<div class="test-result loading">
  <h3>Test 2: fetch get_subjects (WITHOUT credentials)</h3>
  <button onclick="testGetSubjects()">Run Test</button>
  <pre id="result-subjects">Waiting...</pre>
</div>

<div class="test-result loading">
  <h3>Test 3: Check navbar update</h3>
  <button onclick="testNavbarUpdate()">Simulate Update</button>
  <pre id="result-navbar">Waiting...</pre>
</div>

<script>
async function testGetProfile() {
  const result = document.getElementById('result-profile');
  result.textContent = '⏳ Fetching...';
  try {
    const response = await fetch('./exam_api.php?action=get_profile');
    const data = await response.json();
    result.textContent = JSON.stringify(data, null, 2);
    result.parentElement.className = data.success ? 'test-result success' : 'test-result error';
  } catch (e) {
    result.textContent = 'ERROR: ' + e.message;
    result.parentElement.className = 'test-result error';
  }
}

async function testGetSubjects() {
  const result = document.getElementById('result-subjects');
  result.textContent = '⏳ Fetching...';
  try {
    const response = await fetch('./exam_api.php?action=get_subjects');
    const data = await response.json();
    result.textContent = JSON.stringify(data, null, 2);
    result.parentElement.className = data.success ? 'test-result success' : 'test-result error';
  } catch (e) {
    result.textContent = 'ERROR: ' + e.message;
    result.parentElement.className = 'test-result error';
  }
}

function testNavbarUpdate() {
  const result = document.getElementById('result-navbar');
  try {
    const span = document.querySelector('.nav-user span');
    const avatar = document.querySelector('.nav-avatar');
    
    if (span && avatar) {
      result.textContent = '✅ Selectors found!\n' +
        'Current span text: "' + span.textContent + '"\n' +
        'Current avatar text: "' + avatar.textContent + '"\n\n' +
        'Trying to update...\n' +
        '(But need actual profile data to do this)\n\n' +
        'This means HTML structure is OK, issue is in fetch or logic.';
      result.parentElement.className = 'test-result success';
    } else {
      result.textContent = '❌ Selectors NOT found!\n' +
        '.nav-user span found: ' + (span ? 'YES' : 'NO') + '\n' +
        '.nav-avatar found: ' + (avatar ? 'YES' : 'NO');
      result.parentElement.className = 'test-result error';
    }
  } catch (e) {
    result.textContent = 'ERROR: ' + e.message;
    result.parentElement.className = 'test-result error';
  }
}
</script>

<h2>🔗 Quick Links</h2>
<ul>
  <li><a href="../teacher/create-exam.html">Create Exam Page</a></li>
  <li><a href="../teacher/dashboard.html">Dashboard (untuk perbandingan)</a></li>
  <li><a href="../index.html">Logout & Test Login Lagi</a></li>
</ul>

<?php
echo "<h2>✅ Debug Page Complete</h2>";
echo "<p style='color: #666;'>";
echo "Buka browser DevTools (F12) dan cek:<br>";
echo "1. Buka tab Console<br>";
echo "2. Klik 'Run Test' buttons di atas<br>";
echo "3. Lihat response dari setiap test<br>";
echo "4. Report hasilnya ke saya";
echo "</p>";
?>
