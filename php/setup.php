<?php
/**
 * Setup/Migration Script for ExamSafe
 * Buat tables yang mungkin belum ada
 */

require_once 'db.php';

try {
    $db = getDB();
    
    // Check if question_bank table exists
    $checkTable = $db->query("SHOW TABLES LIKE 'question_bank'");
    $tableExists = $checkTable->rowCount() > 0;
    
    echo "Checking question_bank table...\n";
    
    if (!$tableExists) {
        echo "❌ Table question_bank tidak ada. Membuat...\n";
        
        $sql = "
        CREATE TABLE question_bank (
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
        echo "✅ Table question_bank berhasil dibuat!\n";
    } else {
        echo "✅ Table question_bank sudah ada.\n";
        
        // Check columns
        $checkCols = $db->query("SHOW COLUMNS FROM question_bank");
        $columns = $checkCols->fetchAll(PDO::FETCH_COLUMN, 0);
        echo "\nKolom yang ada:\n";
        foreach ($columns as $col) {
            echo "  - $col\n";
        }
    }
    
    // Check subjects table
    echo "\n\nChecking subjects table...\n";
    $checkSubjects = $db->query("SHOW TABLES LIKE 'subjects'");
    $subjectsExists = $checkSubjects->rowCount() > 0;
    
    if ($subjectsExists) {
        echo "✅ Table subjects sudah ada.\n";
        
        $countSubjects = $db->query("SELECT COUNT(*) as cnt FROM subjects")->fetch();
        echo "Total subjects: " . $countSubjects['cnt'] . "\n";
        
        if ($countSubjects['cnt'] == 0) {
            echo "\nData subjects kosong! Menambahkan default data...\n";
            
            $subjects = [
                ['name' => 'Matematika', 'category' => 'IPA'],
                ['name' => 'Bahasa Indonesia', 'category' => 'Umum'],
                ['name' => 'Bahasa Inggris', 'category' => 'Umum'],
                ['name' => 'IPA', 'category' => 'IPA'],
                ['name' => 'IPS', 'category' => 'IPS'],
                ['name' => 'Sejarah', 'category' => 'IPS'],
            ];
            
            $stmt = $db->prepare("INSERT INTO subjects (name, category) VALUES (?, ?)");
            foreach ($subjects as $s) {
                $stmt->execute([$s['name'], $s['category']]);
                echo "  ✓ Ditambahkan: {$s['name']}\n";
            }
        }
    } else {
        echo "❌ Table subjects tidak ada.\n";
    }
    
    echo "\n\n✅ Setup selesai!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
