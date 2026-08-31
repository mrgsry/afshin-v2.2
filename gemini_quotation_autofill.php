<?php
require_once __DIR__ . '/ai_config.php';
require_module_access('quotation', 'full');
header('Content-Type: application/json; charset=utf-8');

function ai_json_error($message, $status = 400) { http_response_code($status); echo json_encode(['ok' => false, 'message' => $message]); exit; }
function ai_extract_json_object($text) {
    $text = trim((string)$text);
    if ($text === '') return null;
    $text = preg_replace('/(?:^|\R)\s*data:\s*\[DONE\]\s*$/i', '', $text);
    if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/is', $text, $matches)) $text = trim($matches[1]);
    $result = json_decode($text, true);
    if (is_array($result) && (array_key_exists('items', $result) || array_key_exists('customer_id', $result))) return $result;
    if (is_array($result)) {
        foreach (['data', 'output', 'result', 'quotation'] as $key) {
            if (isset($result[$key]) && is_array($result[$key])) return $result[$key];
        }
    }
    $start = strpos($text, '{');
    $end = strrpos($text, '}');
    if ($start === false || $end === false || $end <= $start) return null;
    $result = json_decode(substr($text, $start, $end - $start + 1), true);
    if (!is_array($result)) return null;
    foreach (['data', 'output', 'result', 'quotation'] as $key) {
        if (isset($result[$key]) && is_array($result[$key])) return $result[$key];
    }
    return $result;
}
function ai_text_from_content($content) {
    if (is_string($content)) return $content;
    if (!is_array($content)) return '';
    if (array_keys($content) !== range(0, count($content) - 1)) {
        foreach (['text', 'content', 'value'] as $key) {
            if (array_key_exists($key, $content)) return ai_text_from_content($content[$key]);
        }
        $encoded = json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $encoded === false ? '' : $encoded;
    }
    $text = '';
    foreach ($content as $part) {
        if (is_string($part)) $text .= $part;
        elseif (is_array($part)) $text .= (string)($part['text'] ?? $part['content'] ?? $part['value'] ?? '');
    }
    return $text;
}
function ai_openai_response_text($response, $data) {
    $text = '';
    if (is_array($data)) {
        $choice = $data['choices'][0] ?? [];
        $message = is_array($choice['message'] ?? null) ? $choice['message'] : [];
        $text = ai_text_from_content($message['content'] ?? '');
        if ($text === '') $text = ai_text_from_content($choice['text'] ?? '');
        if ($text === '') $text = ai_text_from_content($message['reasoning_content'] ?? '');
        if ($text === '') $text = ai_text_from_content($data['output_text'] ?? $data['output'] ?? '');
    }
    if ($text !== '') return $text;
    foreach (preg_split('/\R/', (string)$response) as $line) {
        $line = trim($line);
        if (stripos($line, 'data:') !== 0) continue;
        $chunk = trim(substr($line, 5));
        if ($chunk === '' || strtoupper($chunk) === '[DONE]') continue;
        $chunkData = json_decode($chunk, true);
        if (!is_array($chunkData)) continue;
        $delta = $chunkData['choices'][0]['delta'] ?? [];
        $piece = ai_text_from_content($delta['content'] ?? '');
        if ($piece === '') $piece = ai_text_from_content($chunkData['choices'][0]['text'] ?? '');
        $text .= $piece;
    }
    return $text;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') ai_json_error('Method tidak diizinkan.', 405);
$input = json_decode(file_get_contents('php://input'), true);
$brief = trim((string)($input['brief'] ?? ''));
if ($brief === '' || strlen($brief) > 6000) ai_json_error('Brief wajib diisi dan maksimal 6000 karakter.');
$customerResult = mysqli_query($mysqli, "SELECT id, name, customer_no FROM customers ORDER BY name ASC");
$customers = [];
while ($customerResult && ($customer = mysqli_fetch_assoc($customerResult))) {
    $customers[] = ['id' => (int)$customer['id'], 'name' => trim((string)$customer['name']), 'customer_no' => trim((string)$customer['customer_no'])];
}
$customerCatalog = json_encode($customers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$provider = trim((string)($input['provider'] ?? ''));
$config = $provider === 'openai_compatible' ? ai_config($mysqli, 'openai_compatible') : gemini_config($mysqli);
if (!$config['configured']) ai_json_error('Provider AI belum dikonfigurasi oleh admin.', 503);

$prompt = "Anda membantu membuat draft quotation teknis. Balas HANYA JSON valid tanpa markdown dengan struktur: {\"customer_id\":1,\"control_model\":\"\",\"mtb\":\"\",\"note\":\"\",\"items\":[{\"description\":\"\",\"qty\":1,\"unit\":\"Unit\",\"unit_price\":0}]}. Pilih customer_id hanya dari katalog customer yang diberikan berdasarkan nama/customer_no yang disebut di brief. Jika tidak ada customer yang cocok, gunakan 0. Harga boleh diisi sebagai estimasi angka rupiah berdasarkan brief, jangan gunakan format mata uang atau titik pemisah. Unit harus salah satu dari Unit, Pcs, Pack, Set, Koli, Box, Buah, Pallet. Buat 1-20 item yang relevan. Katalog customer: " . $customerCatalog . "\nBrief:\n" . $brief;
$headers = ['Content-Type: application/json'];
$providerLabel = $config['provider'] === 'openai_compatible' ? 'OpenAI Compatible' : 'Gemini';
if ($config['provider'] === 'openai_compatible') {
    $payload = json_encode(['model' => $config['model'], 'messages' => [['role' => 'system', 'content' => 'Balas hanya JSON valid tanpa markdown. Jangan gunakan code fence atau penjelasan tambahan.'], ['role' => 'user', 'content' => $prompt]], 'temperature' => 0.2, 'max_tokens' => 4096]);
    $url = rtrim($config['base_url'], '/') . '/chat/completions';
    $headers[] = 'Authorization: Bearer ' . $config['api_key'];
} else {
    $payload = json_encode(['contents' => [['parts' => [['text' => $prompt]]]], 'generationConfig' => ['responseMimeType' => 'application/json', 'temperature' => 0.2, 'maxOutputTokens' => 4096]]);
    $url = rtrim($config['base_url'], '/') . '/models/' . rawurlencode($config['model']) . ':generateContent?key=' . rawurlencode($config['api_key']);
}
$ch = curl_init($url);
curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload, CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 15, CURLOPT_TIMEOUT => 45, CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1]);
$response = curl_exec($ch); $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $curlError = curl_error($ch); curl_close($ch);
if ($config['provider'] === 'openai_compatible' && $httpCode >= 400 && $httpCode < 500) {
    $fallbackPayload = json_encode(['model' => $config['model'], 'messages' => [['role' => 'system', 'content' => 'Balas hanya JSON valid tanpa markdown. Jangan gunakan code fence atau penjelasan tambahan.'], ['role' => 'user', 'content' => $prompt]], 'temperature' => 0.2, 'max_tokens' => 4096]);
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $fallbackPayload, CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 15, CURLOPT_TIMEOUT => 45, CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1]);
    $response = curl_exec($ch); $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $curlError = curl_error($ch); curl_close($ch);
}
if ($response === false || $curlError) {
    $detail = trim((string)$curlError);
    ai_json_error($providerLabel . ' tidak dapat dihubungi' . ($detail !== '' ? ': ' . $detail : '.'), 502);
}
$response = (string)$response;
$data = json_decode($response, true);
if ($httpCode < 200 || $httpCode >= 300) ai_json_error($data['error']['message'] ?? ($providerLabel . ' menolak permintaan (HTTP ' . $httpCode . ').'), 502);
$text = $config['provider'] === 'openai_compatible'
    ? ai_openai_response_text($response, $data)
    : ($data['candidates'][0]['content']['parts'][0]['text'] ?? '');
$result = ai_extract_json_object($text);
if (!is_array($result) || !is_array($result['items'] ?? null)) ai_json_error('Respons ' . $providerLabel . ' tidak sesuai format JSON quotation.', 502);
$result['control_model'] = trim((string)($result['control_model'] ?? ''));
$result['mtb'] = trim((string)($result['mtb'] ?? ''));
$result['note'] = trim((string)($result['note'] ?? ''));
$customerIds = array_column($customers, 'id');
$result['customer_id'] = in_array((int)($result['customer_id'] ?? 0), $customerIds, true) ? (int)$result['customer_id'] : 0;
$units = ['Unit','Pcs','Pack','Set','Koli','Box','Buah','Pallet'];
$result['items'] = array_values(array_filter(array_slice($result['items'], 0, 20), function ($item) { return is_array($item) && trim((string)($item['description'] ?? '')) !== ''; }));
foreach ($result['items'] as &$item) {
    $price = $item['unit_price'] ?? 0;
    if (is_string($price)) $price = preg_replace('/[^0-9.\-]/', '', str_replace(',', '.', $price));
    $item = ['description' => trim((string)$item['description']), 'qty' => max(1, (float)($item['qty'] ?? 1)), 'unit' => in_array($item['unit'] ?? '', $units, true) ? $item['unit'] : 'Unit', 'unit_price' => max(0, (float)$price)];
}
unset($item);
echo json_encode(['ok' => true, 'data' => $result]);