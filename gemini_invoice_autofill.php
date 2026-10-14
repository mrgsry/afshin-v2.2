<?php
require_once __DIR__ . '/ai_config.php';
require_module_access('invoice', 'full');
header('Content-Type: application/json; charset=utf-8');

function invoice_ai_error($message, $status = 400) { http_response_code($status); echo json_encode(['ok' => false, 'message' => $message]); exit; }
function invoice_ai_text($content) {
    if (is_string($content)) return $content;
    if (!is_array($content)) return '';
    if (array_keys($content) !== range(0, count($content) - 1)) {
        foreach (['text', 'content', 'value'] as $key) if (array_key_exists($key, $content)) return invoice_ai_text($content[$key]);
        return json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }
    $text = '';
    foreach ($content as $part) $text .= is_string($part) ? $part : (string)($part['text'] ?? $part['content'] ?? $part['value'] ?? '');
    return $text;
}
function invoice_ai_response_text($response, $data) {
    $choice = $data['choices'][0] ?? [];
    $message = is_array($choice['message'] ?? null) ? $choice['message'] : [];
    $text = invoice_ai_text($message['content'] ?? '');
    if ($text === '') $text = invoice_ai_text($choice['text'] ?? $data['output_text'] ?? $data['output'] ?? '');
    if ($text !== '') return $text;
    foreach (preg_split('/\R/', (string)$response) as $line) {
        if (stripos(trim($line), 'data:') !== 0) continue;
        $chunk = json_decode(trim(substr(trim($line), 5)), true);
        if (is_array($chunk)) $text .= invoice_ai_text($chunk['choices'][0]['delta']['content'] ?? $chunk['choices'][0]['text'] ?? '');
    }
    return $text;
}
function invoice_ai_json($text) {
    $text = trim((string)$text);
    if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/is', $text, $matches)) $text = trim($matches[1]);
    $result = json_decode($text, true);
    if (!is_array($result)) {
        $start = strpos($text, '{'); $end = strrpos($text, '}');
        if ($start === false || $end <= $start) return null;
        $result = json_decode(substr($text, $start, $end - $start + 1), true);
    }
    foreach (['data', 'output', 'result', 'invoice'] as $key) if (isset($result[$key]) && is_array($result[$key])) return $result[$key];
    return $result;
}
function invoice_ai_date($value) {
    if (!is_scalar($value)) return '';
    $value = trim((string)$value);
    if ($value === '') return '';
    foreach (['!Y-m-d', '!d/m/Y', '!d-m-Y', '!Y/m/d', '!d.m.Y', '!j/n/Y', '!j-n-Y'] as $format) {
        $date = DateTimeImmutable::createFromFormat($format, $value);
        $errors = DateTimeImmutable::getLastErrors();
        if ($date && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) return $date->format('Y-m-d');
    }
    return '';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') invoice_ai_error('Method tidak diizinkan.', 405);
$input = json_decode(file_get_contents('php://input'), true);
$brief = trim((string)($input['brief'] ?? ''));
if ($brief === '' || strlen($brief) > 6000) invoice_ai_error('Brief wajib diisi dan maksimal 6000 karakter.');

$customerResult = mysqli_query($mysqli, 'SELECT id, name, customer_no FROM customers ORDER BY name ASC');
$customers = [];
while ($customerResult && ($customer = mysqli_fetch_assoc($customerResult))) $customers[] = ['id' => (int)$customer['id'], 'name' => trim($customer['name']), 'customer_no' => trim($customer['customer_no'])];
$config = (($input['provider'] ?? '') === 'openai_compatible') ? ai_config($mysqli, 'openai_compatible') : gemini_config($mysqli);
if (!$config['configured']) invoice_ai_error('Provider AI belum dikonfigurasi oleh admin.', 503);
$catalog = json_encode($customers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$prompt = 'Anda membantu membuat draft invoice teknis. Balas HANYA JSON valid tanpa markdown dengan struktur: {"customer_id":1,"invoice_date":"YYYY-MM-DD","po_number":"","note":"","items":[{"description":"","qty":1,"unit":"Unit","unit_price":0}]}. Ambil invoice_date dan po_number dari brief. Pertahankan nomor PO persis seperti tertulis. Format invoice_date wajib YYYY-MM-DD. Jika tanggal atau nomor PO tidak disebutkan, isi string kosong; jangan mengarang. Pilih customer_id hanya dari katalog customer berdasarkan brief, atau 0 jika tidak cocok. Buat 1-20 item relevan. Harga harus angka rupiah tanpa format mata uang. Unit harus salah satu dari Unit, Pcs, Pack, Set, Koli, Box, LOT, Pallet, Meter, Kg, Liter, Hour. Katalog customer: ' . $catalog . "\nBrief:\n" . $brief;
$providerLabel = $config['provider'] === 'openai_compatible' ? 'OpenAI Compatible' : 'Gemini';
$headers = ['Content-Type: application/json'];
if ($config['provider'] === 'openai_compatible') {
    $payload = json_encode(['model' => $config['model'], 'messages' => [['role' => 'system', 'content' => 'Balas hanya JSON valid tanpa markdown.'], ['role' => 'user', 'content' => $prompt]], 'temperature' => 0.2, 'max_tokens' => 4096]);
    $url = rtrim($config['base_url'], '/') . '/chat/completions'; $headers[] = 'Authorization: Bearer ' . $config['api_key'];
} else {
    $payload = json_encode(['contents' => [['parts' => [['text' => $prompt]]]], 'generationConfig' => ['responseMimeType' => 'application/json', 'temperature' => 0.2, 'maxOutputTokens' => 4096]]);
    $url = rtrim($config['base_url'], '/') . '/models/' . rawurlencode($config['model']) . ':generateContent?key=' . rawurlencode($config['api_key']);
}
$ch = curl_init($url); curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload, CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 15, CURLOPT_TIMEOUT => 45, CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1]);
$response = curl_exec($ch); $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $curlError = curl_error($ch); curl_close($ch);
if ($response === false || $curlError) invoice_ai_error($providerLabel . ' tidak dapat dihubungi.', 502);
$data = json_decode((string)$response, true);
if ($httpCode < 200 || $httpCode >= 300) invoice_ai_error($data['error']['message'] ?? ($providerLabel . ' menolak permintaan.'), 502);
$text = $config['provider'] === 'openai_compatible' ? invoice_ai_response_text($response, $data) : ($data['candidates'][0]['content']['parts'][0]['text'] ?? '');
$result = invoice_ai_json($text);
if (!is_array($result) || !is_array($result['items'] ?? null)) invoice_ai_error('Respons ' . $providerLabel . ' tidak sesuai format JSON invoice.', 502);
$result['invoice_date'] = invoice_ai_date($result['invoice_date'] ?? $result['date'] ?? $result['created_at'] ?? '');
$poNumber = $result['po_number'] ?? $result['po'] ?? $result['purchase_order_number'] ?? '';
$result['po_number'] = is_scalar($poNumber) ? trim((string)$poNumber) : '';
$result['note'] = trim((string)($result['note'] ?? ''));
$ids = array_column($customers, 'id'); $result['customer_id'] = in_array((int)($result['customer_id'] ?? 0), $ids, true) ? (int)$result['customer_id'] : 0;
$units = ['Unit','Pcs','Pack','Set','Koli','Box','LOT','Pallet','Meter','Kg','Liter','Hour'];
$result['items'] = array_values(array_filter(array_slice($result['items'], 0, 20), fn($item) => is_array($item) && trim((string)($item['description'] ?? '')) !== ''));
foreach ($result['items'] as &$item) {
    $price = $item['unit_price'] ?? 0; if (is_string($price)) $price = preg_replace('/[^0-9.\-]/', '', str_replace(',', '.', $price));
    $item = ['description' => trim((string)$item['description']), 'qty' => max(0.01, (float)($item['qty'] ?? 1)), 'unit' => in_array($item['unit'] ?? '', $units, true) ? $item['unit'] : 'Unit', 'unit_price' => max(0, (float)$price)];
}
unset($item); echo json_encode(['ok' => true, 'data' => $result]);