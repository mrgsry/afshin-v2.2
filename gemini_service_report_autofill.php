<?php
require_once __DIR__ . '/ai_config.php';
require_module_access('service_report', 'full');
header('Content-Type: application/json; charset=utf-8');

function service_report_ai_error($message, $status = 400) {
    http_response_code($status);
    echo json_encode(['ok' => false, 'message' => $message]);
    exit;
}

function service_report_clean_text($value) {
    $value = trim(preg_replace('/\s+/', ' ', (string)$value));
    if ($value === '') return '';
    if (preg_match('/[A-Z]/', $value) && strtoupper($value) === $value) {
        $technicalTokens = [];
        $value = preg_replace_callback('/\b[A-Z0-9]*\d[A-Z0-9-]*\b/', function ($match) use (&$technicalTokens) {
            $key = '__TECHNICAL_TOKEN_' . count($technicalTokens) . '__';
            $technicalTokens[$key] = $match[0];
            return $key;
        }, $value);
        $value = strtolower($value);
        foreach ($technicalTokens as $key => $token) $value = str_replace(strtolower($key), $token, $value);
    }
    return strtoupper(substr($value, 0, 1)) . substr($value, 1);
}

function service_report_title_case($value) {
    $value = trim(preg_replace('/\s+/', ' ', (string)$value));
    return $value === '' ? '' : mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
}

function service_report_upper_code($value) {
    return strtoupper(trim(preg_replace('/\s+/', ' ', (string)$value)));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') service_report_ai_error('Method tidak diizinkan.', 405);
$input = json_decode(file_get_contents('php://input'), true);
$brief = trim((string)($input['brief'] ?? ''));
if ($brief === '' || strlen($brief) > 8000) service_report_ai_error('Prompt wajib diisi dan maksimal 8000 karakter.');

$customerResult = mysqli_query($mysqli, "SELECT id, name, customer_no FROM customers ORDER BY name ASC");
$customers = [];
while ($customerResult && ($customer = mysqli_fetch_assoc($customerResult))) {
    $customers[] = ['id' => (int)$customer['id'], 'name' => trim((string)$customer['name']), 'customer_no' => trim((string)$customer['customer_no'])];
}
$customerCatalog = json_encode($customers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$provider = trim((string)($input['provider'] ?? ''));
$config = $provider === 'openai_compatible' ? ai_config($mysqli, 'openai_compatible') : gemini_config($mysqli);
if (!$config['configured']) service_report_ai_error('Provider AI belum dikonfigurasi oleh admin.', 503);

$prompt = "Anda membantu membuat draft service report teknis. Balas HANYA JSON valid tanpa markdown dengan struktur tepat: {\"customer_id\":0,\"date_doc\":\"YYYY-MM-DD\",\"requested_by\":\"\",\"type_of_service\":\"REPAIR\",\"prod_code\":\"\",\"remark_general\":\"\",\"phenomena\":\"\",\"cause\":\"\",\"steps_taken\":\"\",\"activities\":[{\"date\":\"YYYY-MM-DD\",\"part_number\":\"\",\"serial_number\":\"\",\"alarm\":\"\",\"remark\":\"\"}],\"times\":[{\"date\":\"YYYY-MM-DD\",\"start\":\"HH:MM\",\"out\":\"\",\"end\":\"HH:MM\",\"back\":\"\"}],\"models\":[{\"model_number\":\"\",\"serial_number\":\"\",\"mtb\":\"\",\"mc_model\":\"\"}]}. Pilih customer_id hanya dari katalog. Tanggal boleh ditafsirkan dari format DD-MM-YYYY atau DD/MM/YYYY; jika tidak ada gunakan tanggal hari ini. Untuk prompt yang hanya memiliki Start dan Out serta service time, gunakan Out sebagai end dan kosongkan out. Gunakan type_of_service REPAIR, SERVICE, atau ENGINEERING. ATURAN PENTING PART NUMBER: seluruh isi setelah label Part number harus dimasukkan utuh ke field part_number, termasuk kata, spasi, huruf, angka, dan kode; contoh \"MC repair servo A06B-6117-H105\" harus tetap menjadi satu part number lengkap, jangan hanya mengambil A06B-6117-H105 dan jangan memindahkan kata MC repair servo ke remark. Jika ada beberapa part number, buat satu activity untuk masing-masing part number. Rapikan kapitalisasi: nama/PIC gunakan Title Case, kode part/model/serial/alarm gunakan UPPERCASE, dan teks teknis gunakan kalimat normal. Pisahkan setiap langkah menjadi baris baru pada steps_taken dan isi activities dengan part number serta remark. Katalog customer: " . $customerCatalog . "\nPrompt:\n" . $brief;

$headers = ['Content-Type: application/json'];
$providerLabel = $config['provider'] === 'openai_compatible' ? 'OpenAI Compatible' : 'Gemini';
if ($config['provider'] === 'openai_compatible') {
    $payload = json_encode(['model' => $config['model'], 'messages' => [['role' => 'system', 'content' => 'Balas hanya JSON valid tanpa markdown.'], ['role' => 'user', 'content' => $prompt]], 'temperature' => 0.1, 'max_tokens' => 4096, 'response_format' => ['type' => 'json_object']]);
    $url = rtrim($config['base_url'], '/') . '/chat/completions';
    $headers[] = 'Authorization: Bearer ' . $config['api_key'];
} else {
    $payload = json_encode(['contents' => [['parts' => [['text' => $prompt]]]], 'generationConfig' => ['responseMimeType' => 'application/json', 'temperature' => 0.1, 'maxOutputTokens' => 4096]]);
    $url = rtrim($config['base_url'], '/') . '/models/' . rawurlencode($config['model']) . ':generateContent?key=' . rawurlencode($config['api_key']);
}

$ch = curl_init($url);
curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload, CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 15, CURLOPT_TIMEOUT => 45, CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1]);
$response = curl_exec($ch);
$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);
if ($response === false || $curlError) service_report_ai_error($providerLabel . ' tidak dapat dihubungi.', 502);

$data = json_decode($response, true);
if ($httpCode < 200 || $httpCode >= 300) service_report_ai_error($data['error']['message'] ?? ($providerLabel . ' menolak permintaan (HTTP ' . $httpCode . ').'), 502);
$text = $config['provider'] === 'openai_compatible' ? ($data['choices'][0]['message']['content'] ?? '') : ($data['candidates'][0]['content']['parts'][0]['text'] ?? '');
if (is_array($text)) $text = implode('', array_map(function ($part) { return is_array($part) ? (string)($part['text'] ?? $part['content'] ?? '') : (string)$part; }, $text));
$text = trim((string)$text);
if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/is', $text, $matches)) $text = trim($matches[1]);
$result = json_decode($text, true);
if (!is_array($result)) service_report_ai_error('Respons ' . $providerLabel . ' tidak sesuai format JSON service report.', 502);

$customerIds = array_column($customers, 'id');
$result['customer_id'] = in_array((int)($result['customer_id'] ?? 0), $customerIds, true) ? (int)$result['customer_id'] : 0;
$result['date_doc'] = trim((string)($result['date_doc'] ?? ''));
$result['requested_by'] = service_report_title_case($result['requested_by'] ?? '');
$result['type_of_service'] = in_array(strtoupper((string)($result['type_of_service'] ?? '')), ['REPAIR', 'SERVICE', 'ENGINEERING'], true) ? strtoupper($result['type_of_service']) : 'REPAIR';
$result['prod_code'] = service_report_upper_code($result['prod_code'] ?? '');
foreach (['remark_general', 'phenomena', 'cause'] as $field) $result[$field] = service_report_clean_text($result[$field] ?? '');
$result['steps_taken'] = implode("\n", array_values(array_filter(array_map('service_report_clean_text', preg_split('/\R/', (string)($result['steps_taken'] ?? ''))))));

foreach (['activities', 'times', 'models'] as $collection) {
    $result[$collection] = is_array($result[$collection] ?? null) ? array_values(array_slice($result[$collection], 0, 30)) : [];
}
foreach ($result['activities'] as &$activity) {
    $activity = is_array($activity) ? $activity : [];
    foreach (['date', 'part_number', 'serial_number', 'alarm'] as $field) $activity[$field] = service_report_upper_code($activity[$field] ?? '');
    $activity['remark'] = service_report_clean_text($activity['remark'] ?? '');
}
unset($activity);
foreach ($result['times'] as &$time) {
    $time = is_array($time) ? $time : [];
    foreach (['date', 'start', 'out', 'end', 'back'] as $field) $time[$field] = trim((string)($time[$field] ?? ''));
}
unset($time);
foreach ($result['models'] as &$model) {
    $model = is_array($model) ? $model : [];
    foreach (['model_number', 'serial_number', 'mtb', 'mc_model'] as $field) $model[$field] = service_report_upper_code($model[$field] ?? '');
}
unset($model);

echo json_encode(['ok' => true, 'data' => $result], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
