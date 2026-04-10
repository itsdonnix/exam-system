<?php
/**
 * Create question_bank table jika belum ada
 */

require_once 'db.php';

try {
    $db = getDB();
    
    // Drop dan create ulang untuk memastikan clean
    echo "Checking question_bank table...\n";
    
    $sql = "
    CREATE TABLE IF NOT EXISTS question_bank (
        id              INT AUTO_INCREMENT PRIMARY KEY,
        teacher_id      INT NOT NULL,
        question_text   TEXT NOT NULL,
        question_type   ENUM('multiple','essay','truefalse','checkbox') DEFAULT 'multiple',
        options         JSON,
        correct_answer  VARCHAR(500),
        points          INT DEFAULT 1,
        difficulty      ENUM('easy','medium','hard') DEFAULT 'medium',
        category        VARCHAR(100),
        media_url       VARCHAR(500),
        created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
        INDEX idx_teacher_id (teacher_id),
        INDEX idx_category (category)
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    ";
    
    $db->exec($sql);
    echo "✅ Table question_bank berhasil dibuat/updated!\n";
    
    // Verify
    $checkTable = $db->query("SHOW TABLES LIKE 'question_bank'");
    if ($checkTable->rowCount() > 0) {
        echo "✅ Verifikasi: Table question_bank ada di database\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
