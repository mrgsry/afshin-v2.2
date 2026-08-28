<?php
require_once __DIR__ . '/functions.php';

function gemini_models()
{
    return [
        'gemini-2.5-flash' => 'Gemini 2.5 Flash (recommended)',
        'gemini-2.5-pro' => 'Gemini 2.5 Pro',
        'gemini-2.0-flash' => 'Gemini 2.0 Flash',
    ];
}

function ai_default_base_url()
{
    return 'https://generativelanguage.googleapis.com/v1beta';
}

function ai_config($mysqli, $provider = 'gemini')
{
    $provider = $provider === 'openai_compatible' ? 'openai_compatible' : 'gemini';
    $result = mysqli_query($mysqli, "SELECT provider, base_url, model, api_key_encrypted FROM ai_settings WHERE provider = '" . mysqli_real_escape_string($mysqli, $provider) . "' LIMIT 1");
    if (!$result || !($row = mysqli_fetch_assoc($result))) {
        return ['provider' => $provider, 'base_url' => $provider === 'gemini' ? ai_default_base_url() : '', 'model' => $provider === 'gemini' ? 'gemini-2.5-flash' : '', 'api_key' => '', 'configured' => false];
    }
    $apiKey = decrypt_url_token($row['api_key_encrypted'] ?? '') ?: '';
    return ['provider' => $row['provider'], 'base_url' => rtrim(trim($row['base_url'] ?: ($provider === 'gemini' ? ai_default_base_url() : '')), '/'), 'model' => trim($row['model']) ?: ($provider === 'gemini' ? 'gemini-2.5-flash' : ''), 'api_key' => $apiKey, 'configured' => $apiKey !== ''];
}

function gemini_config($mysqli)
{
    return ai_config($mysqli, 'gemini');
}

function save_ai_config($mysqli, $provider, $apiKey, $model, $baseUrl = '')
{
    $provider = $provider === 'openai_compatible' ? 'openai_compatible' : 'gemini';
    $baseUrl = rtrim(trim($baseUrl ?: ($provider === 'gemini' ? ai_default_base_url() : '')), '/');
    if (!filter_var($baseUrl, FILTER_VALIDATE_URL) || !preg_match('/^https?:\/\//i', $baseUrl)) throw new InvalidArgumentException('Base URL tidak valid.');
    if ($model === '' || strlen($model) > 150 || !preg_match('/^[A-Za-z0-9._:@\/-]+$/', $model)) throw new InvalidArgumentException('Model AI tidak valid.');
    $apiKey = trim((string)$apiKey);
    $encrypted = '';
    if ($apiKey === '') {
        $existing = ai_config($mysqli, $provider);
        if (!$existing['configured']) throw new InvalidArgumentException('API key provider wajib diisi.');
        $existingResult = mysqli_query($mysqli, "SELECT api_key_encrypted FROM ai_settings WHERE provider = '" . mysqli_real_escape_string($mysqli, $provider) . "' LIMIT 1");
        $existingRow = $existingResult ? mysqli_fetch_assoc($existingResult) : null;
        $encrypted = trim((string)($existingRow['api_key_encrypted'] ?? ''));
        if ($encrypted === '') throw new InvalidArgumentException('API key provider wajib diisi.');
    } else {
        $encrypted = encrypt_url_token($apiKey);
    }
    $stmt = mysqli_prepare($mysqli, "INSERT INTO ai_settings (provider, base_url, model, api_key_encrypted) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE base_url = VALUES(base_url), model = VALUES(model), api_key_encrypted = VALUES(api_key_encrypted), updated_at = CURRENT_TIMESTAMP");
    if (!$stmt) throw new RuntimeException('Database gagal menyiapkan konfigurasi AI.');
    mysqli_stmt_bind_param($stmt, 'ssss', $provider, $baseUrl, $model, $encrypted);
    if (!mysqli_stmt_execute($stmt)) throw new RuntimeException('Konfigurasi AI gagal disimpan.');
}

function save_gemini_config($mysqli, $apiKey, $model, $baseUrl = '')
{
    save_ai_config($mysqli, 'gemini', $apiKey, $model, $baseUrl);
}