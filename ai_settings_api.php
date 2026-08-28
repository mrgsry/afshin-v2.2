<?php
require_once __DIR__ . '/ai_config.php';
require_admin();
header('Content-Type: application/json; charset=utf-8');
function ai_settings_response($ok, $message, $data = []) { echo json_encode(['ok' => $ok, 'message' => $message, 'data' => $data]); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') ai_settings_response(false, 'Method tidak diizinkan.');
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$action = $input['action'] ?? 'save';
$provider = ($input['provider'] ?? 'gemini') === 'openai_compatible' ? 'openai_compatible' : 'gemini';
$config = ai_config($mysqli, $provider);
$apiKey = trim((string)($input['api_key'] ?? '')) ?: $config['api_key'];
$baseUrl = trim((string)($input['base_url'] ?? $config['base_url']));
if ($apiKey === '') ai_settings_response(false, 'API key wajib diisi.');
if ($action === 'models') {
    $url = rtrim($baseUrl, '/') . '/models';
    $headers = ['Accept: application/json'];
    if ($provider === 'gemini') { $url .= '?key=' . rawurlencode($apiKey); } else { $headers[] = 'Authorization: Bearer ' . $apiKey; }
    $ch = curl_init($url);
    if ($ch === false) ai_settings_response(false, 'Gagal menyiapkan koneksi ke provider AI.');
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
    ]);
    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $curlErrno = curl_errno($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        $detail = $curlError !== '' ? ' cURL #' . $curlErrno . ': ' . $curlError : '';
        ai_settings_response(false, 'Tidak dapat terhubung ke provider AI.' . $detail);
    }

    $data = json_decode($response, true);
    if ($code < 200 || $code >= 300) {
        if ($code === 401 || $code === 403) {
            ai_settings_response(false, 'Provider menolak request (HTTP ' . $code . '). Periksa API key dan pastikan dikirim sebagai Bearer token.');
        }
        if ($code === 0) ai_settings_response(false, 'Provider tidak mengembalikan status HTTP. Periksa akses keluar server dan port 2266.');
        ai_settings_response(false, 'Provider mengembalikan HTTP ' . $code . '.');
    }
    if (!is_array($data)) {
        ai_settings_response(false, 'Respons provider bukan JSON yang valid (HTTP ' . $code . ').');
    }
    if ($provider !== 'gemini' && (!isset($data['data']) || !is_array($data['data']))) {
        ai_settings_response(false, 'Format respons provider tidak sesuai: field data harus berupa array.');
    }
    $models = [];
    foreach (($provider === 'gemini' ? ($data['models'] ?? []) : ($data['data'] ?? [])) as $item) {
        if ($provider === 'gemini') {
            $name = preg_replace('/^models\\//', '', (string)($item['name'] ?? ''));
            if ($name && in_array('generateContent', $item['supportedGenerationMethods'] ?? [], true)) $models[] = ['id' => $name, 'label' => $name . (!empty($item['displayName']) ? ' - ' . $item['displayName'] : '')];
        } elseif (!empty($item['id'])) {
            $models[] = ['id' => (string)$item['id'], 'label' => (string)$item['id']];
        }
    }
    if ($models === []) ai_settings_response(false, 'Provider mengembalikan daftar model kosong atau tidak memiliki model dengan ID yang valid.');
    ai_settings_response(true, 'Model berhasil dimuat.', ['models' => $models]);
}
try { save_ai_config($mysqli, $provider, $apiKey, trim((string)($input['model'] ?? '')), $baseUrl); ai_settings_response(true, 'Pengaturan AI berhasil disimpan.'); }
catch (Throwable $e) { ai_settings_response(false, $e->getMessage()); }