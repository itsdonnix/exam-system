<?php
/**
 * Insert sample questions to question_bank table
 */

require_once 'db.php';

try {
    $db = getDB();
    
    // Get first teacher's ID (usually 1)
    $stmt = $db->query("SELECT id FROM teachers LIMIT 1");
    $teacher = $stmt->fetch();
    
    if (!$teacher) {
        die("❌ Tidak ada guru di database. Silakan login sebagai guru terlebih dahulu.");
    }
    
    $teacherId = $teacher['id'];
    echo "Menggunakan teacher_id: $teacherId (Darwin Miharaja, S.Pd)\n\n";
    
    // Sample questions
    $questions = [
        [
            'question_text' => 'Berapakah hasil dari 5 + 3 × 2?',
            'question_type' => 'multiple',
            'options' => json_encode(['11', '16', '13', '9']),
            'correct_answer' => '11',
            'points' => 10,
            'difficulty' => 'easy',
            'category' => 'Matematika',
            'media_url' => ''
        ],
        [
            'question_text' => 'Siapakah pengarang novel "Laskar Pelangi"?',
            'question_type' => 'multiple',
            'options' => json_encode(['Andrea Hirata', 'Pramoedya Ananta Toer', 'Soe Hok Gie', 'Budi Dharma']),
            'correct_answer' => 'Andrea Hirata',
            'points' => 10,
            'difficulty' => 'medium',
            'category' => 'Bahasa Indonesia',
            'media_url' => ''
        ],
        [
            'question_text' => 'What is the capital of France?',
            'question_type' => 'multiple',
            'options' => json_encode(['Paris', 'London', 'Berlin', 'Madrid']),
            'correct_answer' => 'Paris',
            'points' => 10,
            'difficulty' => 'easy',
            'category' => 'Bahasa Inggris',
            'media_url' => ''
        ],
        [
            'question_text' => 'Proses fotosintesis pada tumbuhan menghasilkan apa?',
            'question_type' => 'multiple',
            'options' => json_encode(['Oksigen dan Glukosa', 'Karbon Dioksida', 'Nitrogen', 'Sulfur']),
            'correct_answer' => 'Oksigen dan Glukosa',
            'points' => 15,
            'difficulty' => 'medium',
            'category' => 'IPA',
            'media_url' => ''
        ],
        [
            'question_text' => 'Jakarta merupakan ibu kota Indonesia. (Benar/Salah)',
            'question_type' => 'truefalse',
            'options' => json_encode(['Benar', 'Salah']),
            'correct_answer' => 'Benar',
            'points' => 5,
            'difficulty' => 'easy',
            'category' => 'IPS',
            'media_url' => ''
        ]
    ];
    
    $stmt = $db->prepare("
        INSERT INTO question_bank 
        (teacher_id, question_text, question_type, options, correct_answer, points, difficulty, category, media_url)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    foreach ($questions as $q) {
        $result = $stmt->execute([
            $teacherId,
            $q['question_text'],
            $q['question_type'],
            $q['options'],
            $q['correct_answer'],
            $q['points'],
            $q['difficulty'],
            $q['category'],
            $q['media_url']
        ]);
        
        if ($result) {
            echo "✅ Ditambahkan: {$q['question_text']}\n";
        } else {
            echo "❌ GAGAL: {$q['question_text']}\n";
        }
    }
    
    // Check total
    $countStmt = $db->prepare("SELECT COUNT(*) as cnt FROM question_bank WHERE teacher_id = ?");
    $countStmt->execute([$teacherId]);
    $count = $countStmt->fetch();
    
    echo "\n✅ Total soal di bank sekarang: " . $count['cnt'] . "\n";
    echo "\nSilakan buka halaman Bank Soal untuk melihat soal-soal tersebut!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
