<?php

/**
 * AI Import endpoint - Process text or file upload and extract questions using Gemini API
 * Uses Guzzle HTTP Client for REST API calls
 * 
 * POST:
 *   - action: 'extract' (required)
 *   - text: string (optional, for pasted text)
 *   - file: uploaded file (optional, for PDF/DOCX/TXT)
 *   - csrf_token: string (required for all actions)
 *   - action: 'test' - Test connection with detailed diagnostics
 * 
 * Returns JSON with extracted questions or error
 */

session_start();
require_once 'db.php';

// Load CSRF protection
require_once '../includes/csrf.php';

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

header('Content-Type: application/json');

// Setup logging
function logAIMessage($level, $message, $context = [])
{
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $logFile = $logDir . '/ai_import.log';
    $timestamp = date('Y-m-d H:i:s');
    // Sanitize context to prevent log injection
    $sanitizedContext = [];
    foreach ($context as $key => $value) {
        if (is_string($value) && strlen($value) > 500) {
            $sanitizedContext[$key] = substr($value, 0, 500) . '...[truncated]';
        } else {
            $sanitizedContext[$key] = $value;
        }
    }
    $contextStr = !empty($sanitizedContext) ? ' ' . json_encode($sanitizedContext) : '';
    $logEntry = "[{$timestamp}] [{$level}] {$message}{$contextStr}" . PHP_EOL;

    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

// Helper function to validate CSRF token from request
function validateCSRFTokenFromRequest()
{
    // Check JSON input first
    $input = json_decode(file_get_contents('php://input'), true);
    $token = $input['csrf_token'] ?? $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';

    if (empty($token)) {
        logAIMessage('WARNING', 'CSRF token missing in request');
        return false;
    }

    if (!verifyCSRFToken($token, $_SESSION['csrf_token'])) {
        logAIMessage('WARNING', 'CSRF token validation failed');
        return false;
    }

    return true;
}

// Enhanced file validation with MIME type checking
function validateUploadedFile($file)
{
    $maxSize = 10 * 1024 * 1024; // 10MB

    if ($file['size'] > $maxSize) {
        return ['success' => false, 'message' => 'File terlalu besar. Maksimal 10MB.'];
    }

    $allowedExtensions = ['pdf', 'docx', 'txt'];
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($extension, $allowedExtensions)) {
        return ['success' => false, 'message' => 'Tipe file tidak didukung. Gunakan PDF, DOCX, atau TXT.'];
    }

    // MIME type validation using finfo
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowedMimeTypes = [
            'pdf' => 'application/pdf',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'txt' => 'text/plain'
        ];

        $expectedMime = $allowedMimeTypes[$extension] ?? null;
        if ($expectedMime && $mimeType !== $expectedMime) {
            logAIMessage('WARNING', 'MIME type mismatch', [
                'expected' => $expectedMime,
                'actual' => $mimeType,
                'filename' => $file['name']
            ]);
            return ['success' => false, 'message' => 'Tipe file tidak valid. File mungkin rusak atau bukan format yang benar.'];
        }
    }

    // Additional safety: Check for PHP execution prevention
    $dangerousContent = ['<?php', '<?=', '<%', '<script', '<?xml'];
    $content = file_get_contents($file['tmp_name'], false, null, 0, 1024); // Read first 1KB only
    if ($content !== false) {
        foreach ($dangerousContent as $pattern) {
            if (stripos($content, $pattern) !== false && $extension !== 'txt') {
                logAIMessage('WARNING', 'Suspicious content detected in file', ['filename' => $file['name']]);
                return ['success' => false, 'message' => 'File mengandung konten yang tidak diizinkan.'];
            }
        }
    }

    return ['success' => true];
}

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
    logAIMessage('WARNING', 'Unauthorized access attempt');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get teacher's Gemini settings
function getTeacherGeminiSettings($teacherId)
{
    try {
        $db = getDB();

        // Check if table exists
        $checkTable = $db->query("SHOW TABLES LIKE 'teacher_settings'");
        if ($checkTable->rowCount() === 0) {
            logAIMessage('ERROR', 'teacher_settings table not found');
            return ['error' => 'Teacher settings table not found. Please contact administrator.'];
        }

        $stmt = $db->prepare("SELECT gemini_api_key, gemini_model FROM teacher_settings WHERE teacher_id = ?");
        $stmt->execute([$teacherId]);
        $settings = $stmt->fetch();

        if (!$settings || empty($settings['gemini_api_key'])) {
            logAIMessage('WARNING', "Teacher ID {$teacherId} has no Gemini API key configured");
            return ['error' => 'API Key Gemini belum dikonfigurasi. Silakan atur di halaman Pengaturan.'];
        }

        return [
            'api_key' => $settings['gemini_api_key'],
            'model' => $settings['gemini_model']
        ];
    } catch (Exception $e) {
        logAIMessage('ERROR', 'Database error in getTeacherGeminiSettings', ['error' => $e->getMessage()]);
        return ['error' => 'Database error: ' . $e->getMessage()];
    }
}

// Extract text from uploaded file
function extractTextFromFile($filePath, $originalName)
{
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    // Handle TXT files
    if ($extension === 'txt') {
        return file_get_contents($filePath);
    }

    // Handle PDF files
    if ($extension === 'pdf') {
        // Try smalot/pdfparser first
        if (class_exists('Smalot\PdfParser\Parser')) {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($filePath);
            return $pdf->getText();
        }

        // Fallback: try setasign/fpdi
        if (class_exists('setasign\Fpdi\Fpdi')) {
            $text = '';
            $pdf = new \setasign\Fpdi\Fpdi();

            try {
                $pageCount = $pdf->setSourceFile($filePath);
                for ($i = 1; $i <= $pageCount; $i++) {
                    $tpl = $pdf->importPage($i);
                    // Note: FPDI is for creating PDFs, it doesn't natively extract text.
                    $text .= " [Page $i] ";
                }
            } catch (Exception $e) {
                logAIMessage('WARNING', 'PDF extraction failed with FPDI', ['error' => $e->getMessage()]);
                $text = "PDF extraction failed. Please copy text manually.";
            }
            return $text;
        }

        logAIMessage('ERROR', 'PDF parsing libraries not installed');
        return "PDF parsing libraries not installed. Please run 'composer install' or copy text manually.";
    }

    // Handle DOCX files
    if ($extension === 'docx') {
        if (class_exists('PhpOffice\PhpWord\IOFactory')) {
            $phpWord = \PhpOffice\PhpWord\IOFactory::load($filePath);
            $text = '';
            foreach ($phpWord->getSections() as $section) {
                foreach ($section->getElements() as $element) {
                    $text .= extractPhpWordText($element);
                }
            }
            return $text;
        }
        logAIMessage('ERROR', 'DOCX parsing libraries not installed');
        return "DOCX parsing library not installed. Please run 'composer install' or copy text manually.";
    }

    logAIMessage('WARNING', 'Unsupported file type uploaded', ['extension' => $extension]);
    return "Unsupported file type. Please upload PDF, DOCX, or TXT files.";
}

/**
 * Helper function to recursively extract text from PHPWord elements
 */
function extractPhpWordText($element)
{
    $result = '';
    if (method_exists($element, 'getText')) {
        $result .= $element->getText() . " ";
    } elseif (method_exists($element, 'getElements')) {
        foreach ($element->getElements() as $childElement) {
            $result .= extractPhpWordText($childElement);
        }
    } elseif (method_exists($element, 'getRows')) { // Handle Tables
        foreach ($element->getRows() as $row) {
            foreach ($row->getCells() as $cell) {
                foreach ($cell->getElements() as $cellElement) {
                    $result .= extractPhpWordText($cellElement);
                }
            }
        }
    }
    return $result;
}

// Call Gemini API using Guzzle HTTP Client with retry logic
function callGeminiAPI($text, $apiKey, $model)
{
    // Create retry middleware (3 retries with 2 second delay)
    $retryMiddleware = Middleware::retry(
        function ($retries, RequestInterface $request, ?ResponseInterface $response = null, ?RequestException $exception = null) {
            // Retry up to 3 times
            if ($retries >= 3) {
                return false;
            }

            // Retry on connection errors or server errors (5xx)
            if ($exception instanceof ConnectException) {
                logAIMessage('WARNING', "Connection error, retry {$retries}/3", ['message' => $exception->getMessage()]);
                return true;
            }

            if ($response && $response->getStatusCode() >= 500) {
                logAIMessage('WARNING', "Server error {$response->getStatusCode()}, retry {$retries}/3");
                return true;
            }

            return false;
        },
        function ($retries) {
            // Fixed 2 second delay between retries
            return 2000000; // 2 seconds in microseconds
        }
    );

    $handlerStack = HandlerStack::create();
    $handlerStack->push($retryMiddleware);

    $client = new Client([
        'timeout' => 120,        // Request timeout: 120 seconds
        'connect_timeout' => 5,  // Connection timeout: 5 seconds
        'handler' => $handlerStack,
        'http_errors' => false   // We'll handle errors manually
    ]);

    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

    // Build prompt in Indonesian
    $prompt = "
Ekstrak semua soal dari teks berikut dan return HANYA JSON valid (tanpa markdown, tanpa teks lain).

TEKS:
\"\"\"
{$text}
\"\"\"

TIPE SOAL:
- multiple: pilihan ganda, 1 jawaban benar
- checkbox: pilihan ganda, >1 jawaban benar
- truefalse: benar/salah
- essay: uraian

OUTPUT SCHEMA:
{
  \"questions\": [
    {
      \"type\": \"multiple|checkbox|truefalse|essay\",
      \"text\": \"<teks soal>\",
      \"options\": [{\"text\": \"<teks pilihan>\", \"image\": null}],
      \"correct\": \"0\" | [\"0\",\"2\"],
      \"correct_answers_checkbox\": [\"0\",\"2\"],
      \"difficulty\": \"mudah|sedang|sulit\",
      \"points\": 1
    }
  ]
}

ATURAN:
- options: isi untuk multiple/checkbox/truefalse, kosong [] untuk essay
- correct: index string (\"0\"=A/Benar, \"1\"=B/Salah, dst); array untuk checkbox
- correct_answers_checkbox: hanya untuk tipe checkbox, sama nilainya dengan correct
- Jawaban benar ditandai *, ✓, atau tanda unik lain, atau kata kunci/jawaban di teks asli
- Default jika tidak diketahui: type=multiple, correct=\"0\", difficulty=sedang, points=1
";

    try {
        logAIMessage('INFO', 'Calling Gemini API', ['model' => $model, 'text_length' => strlen($text)]);

        $response = $client->post($url, [
            'json' => [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => 0.2,
                    'topK' => 1,
                    'topP' => 0.8,
                ]
            ]
        ]);

        $statusCode = $response->getStatusCode();
        $responseBody = $response->getBody()->getContents();

        if ($statusCode !== 200) {
            logAIMessage('ERROR', 'Gemini API returned non-200 status', [
                'status_code' => $statusCode,
                'response' => substr($responseBody, 0, 500)
            ]);
            return [
                'error' => "Gemini API Error (HTTP {$statusCode}): " . substr($responseBody, 0, 500)
            ];
        }

        $result = json_decode($responseBody, true);

        if (!isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            logAIMessage('ERROR', 'Invalid Gemini API response structure', ['response' => substr($responseBody, 0, 500)]);
            return [
                'error' => 'Invalid response from Gemini API',
                'raw_response' => $responseBody
            ];
        }

        $aiText = $result['candidates'][0]['content']['parts'][0]['text'];

        if (empty($aiText)) {
            logAIMessage('ERROR', 'Empty response from Gemini API');
            return ['error' => 'Empty response from Gemini API'];
        }

        logAIMessage('INFO', 'Gemini API response received', ['response_length' => strlen($aiText)]);

        // Clean response - remove markdown code blocks if present
        $aiText = preg_replace('/```json\s*/', '', $aiText);
        $aiText = preg_replace('/```\s*$/', '', $aiText);
        $aiText = trim($aiText);

        // Parse JSON
        $parsed = json_decode($aiText, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            logAIMessage('ERROR', 'Failed to parse AI response as JSON', [
                'json_error' => json_last_error_msg(),
                'raw_response' => substr($aiText, 0, 1000)
            ]);
            return [
                'error' => 'Failed to parse AI response as JSON',
                'raw_response' => $aiText,
                'json_error' => json_last_error_msg()
            ];
        }

        if (!isset($parsed['questions']) || !is_array($parsed['questions'])) {
            logAIMessage('ERROR', 'AI response missing questions array', ['parsed' => json_encode($parsed)]);
            return [
                'error' => 'AI response missing "questions" array',
                'raw_response' => $aiText
            ];
        }

        // Normalize questions
        foreach ($parsed['questions'] as &$q) {
            // Ensure required fields
            $q['type'] = $q['type'] ?? 'multiple';
            $q['text'] = $q['text'] ?? '';
            $q['options'] = $q['options'] ?? [];
            $q['points'] = intval($q['points'] ?? 1);
            $q['difficulty'] = $q['difficulty'] ?? 'sedang';

            // Normalize options to {text, image} format
            if (is_array($q['options'])) {
                $normalizedOptions = [];
                foreach ($q['options'] as $opt) {
                    if (is_string($opt)) {
                        $normalizedOptions[] = ['text' => $opt, 'image' => null];
                    } elseif (is_array($opt)) {
                        $normalizedOptions[] = [
                            'text' => $opt['text'] ?? '',
                            'image' => $opt['image'] ?? null
                        ];
                    }
                }
                $q['options'] = $normalizedOptions;
            }

            // Handle correct answers
            if ($q['type'] === 'checkbox') {
                $q['correct_answers_checkbox'] = $q['correct_answers_checkbox'] ?? ($q['correct'] ?? []);
                if (!is_array($q['correct_answers_checkbox'])) {
                    $q['correct_answers_checkbox'] = [$q['correct_answers_checkbox']];
                }
                $q['correct'] = '';
            } else {
                $q['correct'] = is_array($q['correct']) ? ($q['correct'][0] ?? '0') : ($q['correct'] ?? '0');
                $q['correct_answers_checkbox'] = [];
            }
        }

        logAIMessage('INFO', 'Successfully extracted questions', ['count' => count($parsed['questions'])]);

        return ['success' => true, 'questions' => $parsed['questions']];
    } catch (ConnectException $e) {
        logAIMessage('ERROR', 'Connection error to Gemini API', ['message' => $e->getMessage()]);
        return [
            'error' => 'Tidak dapat terhubung ke Gemini API. Periksa koneksi internet Anda.',
            'raw_response' => null
        ];
    } catch (RequestException $e) {
        logAIMessage('ERROR', 'Request error to Gemini API', ['message' => $e->getMessage()]);
        return [
            'error' => 'Request error: ' . $e->getMessage(),
            'raw_response' => null
        ];
    } catch (Exception $e) {
        logAIMessage('ERROR', 'Unexpected error in callGeminiAPI', ['message' => $e->getMessage()]);
        return [
            'error' => 'Unexpected error: ' . $e->getMessage(),
            'raw_response' => null
        ];
    }
}

// ENHANCED: Test Gemini connection with detailed diagnostics
function testGeminiConnection()
{
    $diagnostics = [
        'timestamp' => date('Y-m-d H:i:s'),
        'steps' => [],
        'success' => false,
        'message' => '',
        'suggestions' => []
    ];

    // Step 1: Check authentication
    $startTime = microtime(true);
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
        $diagnostics['steps'][] = [
            'name' => 'Authentication',
            'status' => 'failed',
            'message' => 'User not authenticated or not a teacher',
            'latency_ms' => round((microtime(true) - $startTime) * 1000, 2)
        ];
        $diagnostics['message'] = 'Authentication failed. Please login again.';
        $diagnostics['suggestions'][] = 'Log out and log back in as a teacher';
        echo json_encode($diagnostics);
        return;
    }

    $diagnostics['steps'][] = [
        'name' => 'Authentication',
        'status' => 'passed',
        'message' => 'Teacher session valid',
        'latency_ms' => round((microtime(true) - $startTime) * 1000, 2)
    ];

    // Step 2: Check database connection
    $dbStart = microtime(true);
    try {
        $db = getDB();
        $diagnostics['steps'][] = [
            'name' => 'Database Connection',
            'status' => 'passed',
            'message' => 'Connected successfully',
            'latency_ms' => round((microtime(true) - $dbStart) * 1000, 2)
        ];
    } catch (Exception $e) {
        $diagnostics['steps'][] = [
            'name' => 'Database Connection',
            'status' => 'failed',
            'message' => $e->getMessage(),
            'latency_ms' => round((microtime(true) - $dbStart) * 1000, 2)
        ];
        $diagnostics['message'] = 'Database connection failed';
        $diagnostics['suggestions'][] = 'Check database configuration in db.php';
        echo json_encode($diagnostics);
        return;
    }

    // Step 3: Check teacher_settings table
    $tableStart = microtime(true);
    $checkTable = $db->query("SHOW TABLES LIKE 'teacher_settings'");
    if ($checkTable->rowCount() === 0) {
        $diagnostics['steps'][] = [
            'name' => 'Teacher Settings Table',
            'status' => 'failed',
            'message' => 'teacher_settings table does not exist',
            'latency_ms' => round((microtime(true) - $tableStart) * 1000, 2)
        ];
        $diagnostics['message'] = 'Teacher settings table not found';
        $diagnostics['suggestions'][] = 'Run database migration to create teacher_settings table';
        $diagnostics['suggestions'][] = 'Contact system administrator';
        echo json_encode($diagnostics);
        return;
    }

    $diagnostics['steps'][] = [
        'name' => 'Teacher Settings Table',
        'status' => 'passed',
        'message' => 'Table exists',
        'latency_ms' => round((microtime(true) - $tableStart) * 1000, 2)
    ];

    // Step 4: Get API settings
    $settingsStart = microtime(true);
    $stmt = $db->prepare("SELECT gemini_api_key, gemini_model FROM teacher_settings WHERE teacher_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $settings = $stmt->fetch();

    if (!$settings || empty($settings['gemini_api_key'])) {
        $diagnostics['steps'][] = [
            'name' => 'API Key Configuration',
            'status' => 'failed',
            'message' => 'No API key found for this teacher',
            'latency_ms' => round((microtime(true) - $settingsStart) * 1000, 2)
        ];
        $diagnostics['message'] = 'Gemini API key not configured';
        $diagnostics['suggestions'][] = 'Go to AI Settings tab and enter your Gemini API key';
        $diagnostics['suggestions'][] = 'Get a free API key from https://aistudio.google.com/';
        echo json_encode($diagnostics);
        return;
    }

    $apiKey = $settings['gemini_api_key'];
    $model = $settings['gemini_model'] ?? 'gemini-2.5-flash-lite';
    $hasKey = !empty($apiKey);

    $diagnostics['steps'][] = [
        'name' => 'API Key Configuration',
        'status' => 'passed',
        'message' => 'API key present' . ($hasKey ? ' (masked: ' . substr($apiKey, 0, 8) . '...)' : ''),
        'latency_ms' => round((microtime(true) - $settingsStart) * 1000, 2)
    ];

    // Step 5: Validate model
    $validModels = ['gemini-2.5-pro', 'gemini-2.5-flash', 'gemini-2.5-flash-lite', 'gemini-2.0-flash', 'gemini-2.0-flash-lite'];
    if (!in_array($model, $validModels)) {
        $diagnostics['steps'][] = [
            'name' => 'Model Validation',
            'status' => 'warning',
            'message' => "Model '{$model}' may not be valid",
            'latency_ms' => 0
        ];
        $diagnostics['suggestions'][] = 'Select a different model from the dropdown';
    } else {
        $diagnostics['steps'][] = [
            'name' => 'Model Validation',
            'status' => 'passed',
            'message' => "Model '{$model}' is valid",
            'latency_ms' => 0
        ];
    }

    // Step 6: Test API call with a small prompt
    $apiStart = microtime(true);
    $testPrompt = "Test: Apa ibu kota Indonesia? A. Jakarta B. Surabaya C. Bandung D. Medan";

    $client = new Client([
        'timeout' => 30,
        'connect_timeout' => 10,
        'http_errors' => false
    ]);

    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

    try {
        $response = $client->post($url, [
            'json' => [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => "Extract the question from this text and return as JSON. Text: {$testPrompt}"]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => 0.1,
                    'maxOutputTokens' => 500
                ]
            ]
        ]);

        $statusCode = $response->getStatusCode();
        $responseBody = $response->getBody()->getContents();
        $apiLatency = round((microtime(true) - $apiStart) * 1000, 2);

        if ($statusCode === 200) {
            $result = json_decode($responseBody, true);
            $hasContent = isset($result['candidates'][0]['content']['parts'][0]['text']);

            $diagnostics['steps'][] = [
                'name' => 'Gemini API Call',
                'status' => 'passed',
                'message' => "HTTP 200 OK - Response received in {$apiLatency}ms",
                'latency_ms' => $apiLatency
            ];

            // Try to parse the response
            if ($hasContent) {
                $aiText = $result['candidates'][0]['content']['parts'][0]['text'];
                $diagnostics['steps'][] = [
                    'name' => 'Response Parsing',
                    'status' => 'passed',
                    'message' => 'Valid response received',
                    'latency_ms' => 0
                ];

                // Sample response preview
                $diagnostics['sample_response'] = substr($aiText, 0, 500);
                $diagnostics['token_estimate'] = round(strlen($testPrompt) / 4);
                $diagnostics['model_used'] = $model;
                $diagnostics['api_latency_ms'] = $apiLatency;
                $diagnostics['success'] = true;
                $diagnostics['message'] = 'Connection successful! Gemini API is working correctly.';
            } else {
                $diagnostics['steps'][] = [
                    'name' => 'Response Parsing',
                    'status' => 'warning',
                    'message' => 'Response received but unexpected format',
                    'latency_ms' => 0
                ];
                $diagnostics['message'] = 'API responded but response format unexpected';
                $diagnostics['sample_response'] = substr($responseBody, 0, 500);
                $diagnostics['suggestions'][] = 'Check if API key has Gemini API access enabled';
            }
        } else {
            $diagnostics['steps'][] = [
                'name' => 'Gemini API Call',
                'status' => 'failed',
                'message' => "HTTP {$statusCode} - " . substr($responseBody, 0, 200),
                'latency_ms' => $apiLatency
            ];
            $diagnostics['message'] = "API returned error (HTTP {$statusCode})";

            // Add specific suggestions based on status code
            if ($statusCode === 401) {
                $diagnostics['suggestions'][] = 'Invalid API key. Check your Gemini API key';
                $diagnostics['suggestions'][] = 'Get a new key from https://aistudio.google.com/';
            } elseif ($statusCode === 403) {
                $diagnostics['suggestions'][] = 'API key may not have access to this model';
                $diagnostics['suggestions'][] = 'Try a different model (e.g., gemini-2.0-flash)';
            } elseif ($statusCode === 429) {
                $diagnostics['suggestions'][] = 'Rate limit exceeded. Wait a few minutes and try again';
                $diagnostics['suggestions'][] = 'Consider upgrading your API plan';
            } elseif ($statusCode === 404) {
                $diagnostics['suggestions'][] = "Model '{$model}' not found. Select a different model";
            } else {
                $diagnostics['suggestions'][] = 'Check your internet connection';
                $diagnostics['suggestions'][] = 'Verify the API key is correct';
            }
        }
    } catch (ConnectException $e) {
        $diagnostics['steps'][] = [
            'name' => 'Gemini API Call',
            'status' => 'failed',
            'message' => 'Connection timeout or network error',
            'latency_ms' => round((microtime(true) - $apiStart) * 1000, 2)
        ];
        $diagnostics['message'] = 'Cannot connect to Gemini API';
        $diagnostics['suggestions'][] = 'Check your server\'s internet connection';
        $diagnostics['suggestions'][] = 'Verify firewall allows outbound HTTPS connections';
        $diagnostics['suggestions'][] = 'Try again in a few moments';
    } catch (Exception $e) {
        $diagnostics['steps'][] = [
            'name' => 'Gemini API Call',
            'status' => 'failed',
            'message' => $e->getMessage(),
            'latency_ms' => round((microtime(true) - $apiStart) * 1000, 2)
        ];
        $diagnostics['message'] = 'Unexpected error during API call';
        $diagnostics['suggestions'][] = 'Check PHP error logs for details';
    }

    echo json_encode($diagnostics);
}

// ============================================================================
// MAIN HANDLER - WITH CSRF PROTECTION
// ============================================================================

// First check if JSON input was sent
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_POST['action'] ?? $_GET['action'] ?? '';

// Validate CSRF for all actions
if (!validateCSRFTokenFromRequest()) {
    echo json_encode([
        'success' => false,
        'message' => 'Validasi keamanan gagal. Silakan refresh halaman dan coba lagi.',
        'csrf_error' => true
    ]);
    exit;
}

switch ($action) {
    case 'extract':
        // Get teacher's Gemini settings
        $geminiSettings = getTeacherGeminiSettings($_SESSION['user_id']);
        if (isset($geminiSettings['error'])) {
            echo json_encode(['success' => false, 'message' => $geminiSettings['error']]);
            exit;
        }

        $apiKey = $geminiSettings['api_key'];
        $model = $geminiSettings['model'];

        // Get input text or file
        $inputText = '';
        $source = '';

        // Check for file upload
        if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['file'];

            // Validate file with enhanced checks
            $validation = validateUploadedFile($file);
            if (!$validation['success']) {
                echo json_encode(['success' => false, 'message' => $validation['message']]);
                exit;
            }

            // Save to temp directory with sanitized name (prevent path traversal)
            $safeFilename = preg_replace('/[^a-zA-Z0-9._-]/', '', $file['name']);
            if (empty($safeFilename)) {
                $safeFilename = 'upload_' . uniqid();
            }
            $tempFile = sys_get_temp_dir() . '/ai_import_' . uniqid() . '_' . $safeFilename;

            if (move_uploaded_file($file['tmp_name'], $tempFile)) {
                $inputText = extractTextFromFile($tempFile, $file['name']);
                unlink($tempFile);
                $source = 'file';
                logAIMessage('INFO', 'File uploaded and processed', ['filename' => $file['name'], 'size' => $file['size']]);
            } else {
                logAIMessage('ERROR', 'Failed to save uploaded file');
                echo json_encode(['success' => false, 'message' => 'Gagal menyimpan file yang diunggah.']);
                exit;
            }
        } else {
            // Check for text input - first from JSON, then from POST
            if ($input && isset($input['text'])) {
                $inputText = trim($input['text']);
            } else {
                $inputText = trim($_POST['text'] ?? '');
            }
            $source = 'text';
            logAIMessage('INFO', 'Text input received', ['length' => strlen($inputText)]);
        }

        if (empty($inputText)) {
            echo json_encode(['success' => false, 'message' => 'Tidak ada teks yang diproses. Tempelkan teks atau unggah file.']);
            exit;
        }

        // Limit text length (approx 30,000 characters for Gemini)
        if (strlen($inputText) > 30000) {
            $inputText = substr($inputText, 0, 30000);
            logAIMessage('INFO', 'Text truncated to 30000 characters');
        }

        // Call Gemini API
        $result = callGeminiAPI($inputText, $apiKey, $model);

        if (isset($result['error'])) {
            echo json_encode([
                'success' => false,
                'message' => $result['error'],
                'raw_response' => $result['raw_response'] ?? null,
                'source' => $source
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'questions' => $result['questions'],
                'count' => count($result['questions']),
                'source' => $source,
                'model_used' => $model
            ]);
        }
        break;

    case 'test':
        testGeminiConnection();
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action. Use action=extract or action=test']);
        exit;
}
