<?php
/**
 * URL-safe authenticated encryption helpers.
 * Set AFSHIN_ENCRYPTION_KEY to a random secret in the server environment.
 */

function afshin_encryption_key()
{
    $secret = getenv('AFSHIN_ENCRYPTION_KEY');
    if ($secret === false || trim($secret) === '') {
        $secret = $_SERVER['AFSHIN_ENCRYPTION_KEY'] ?? '';
    }

    // Keeps local development functional; always configure a private key in production.
    if (!is_string($secret) || trim($secret) === '') {
        $secret = 'afshin-v3-local-development-key-change-in-production';
    }

    return hash('sha256', $secret, true);
}

function base64_url_encode($value)
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function base64_url_decode($value)
{
    if (!is_string($value) || $value === '' || preg_match('/[^A-Za-z0-9_-]/', $value)) {
        return false;
    }

    $padding = strlen($value) % 4;
    if ($padding > 0) {
        $value .= str_repeat('=', 4 - $padding);
    }

    return base64_decode(strtr($value, '-_', '+/'), true);
}

function encrypt_url_token($plaintext)
{
    if (!is_scalar($plaintext) || !function_exists('openssl_encrypt')) {
        throw new RuntimeException('OpenSSL tidak tersedia atau data enkripsi tidak valid.');
    }

    $cipher = 'aes-256-gcm';
    $ivLength = openssl_cipher_iv_length($cipher);
    $iv = random_bytes($ivLength);
    $tag = '';
    $encrypted = openssl_encrypt(
        (string) $plaintext,
        $cipher,
        afshin_encryption_key(),
        OPENSSL_RAW_DATA,
        $iv,
        $tag,
        '',
        16
    );

    if ($encrypted === false || strlen($tag) !== 16) {
        throw new RuntimeException('Data gagal dienkripsi.');
    }

    return base64_url_encode($iv . $tag . $encrypted);
}

function decrypt_url_token($token)
{
    if (!function_exists('openssl_decrypt')) {
        return null;
    }

    $payload = base64_url_decode($token);
    $ivLength = openssl_cipher_iv_length('aes-256-gcm');
    if ($payload === false || strlen($payload) <= $ivLength + 16) {
        return null;
    }

    $iv = substr($payload, 0, $ivLength);
    $tag = substr($payload, $ivLength, 16);
    $encrypted = substr($payload, $ivLength + 16);
    $decrypted = openssl_decrypt(
        $encrypted,
        'aes-256-gcm',
        afshin_encryption_key(),
        OPENSSL_RAW_DATA,
        $iv,
        $tag
    );

    return $decrypted === false ? null : $decrypted;
}

function encrypt_url_id($id)
{
    $id = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) {
        throw new InvalidArgumentException('ID tidak valid.');
    }

    return encrypt_url_token((string) $id);
}

function decrypt_url_id($token)
{
    $value = decrypt_url_token($token);
    if ($value === null || filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
        return null;
    }

    return (int) $value;
}

function ensure_quotation_public_token_column($mysqli)
{
    static $checked = false;
    if ($checked) {
        return;
    }

    $checked = true;
    $column = mysqli_query($mysqli, "SHOW COLUMNS FROM quotations LIKE 'public_token'");
    if ($column && mysqli_num_rows($column) > 0) {
        return;
    }

    $res1 = mysqli_query($mysqli, "ALTER TABLE quotations ADD COLUMN public_token VARCHAR(64) NULL AFTER quotation_no");
    if (!$res1) {
        error_log("Failed to add public_token column: " . mysqli_error($mysqli));
    }
    $res2 = mysqli_query($mysqli, "ALTER TABLE quotations ADD UNIQUE KEY public_token (public_token)");
    if (!$res2) {
        error_log("Failed to add unique key for public_token: " . mysqli_error($mysqli));
    }
}

function generate_public_quotation_token()
{
    return base64_url_encode(random_bytes(24));
}

function get_or_create_quotation_public_token($mysqli, $quotationId)
{
    ensure_quotation_public_token_column($mysqli);

    $quotationId = filter_var($quotationId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($quotationId === false) {
        return null;
    }

    $res = mysqli_query($mysqli, "SELECT public_token FROM quotations WHERE id = " . (int) $quotationId . " LIMIT 1");
    if ($res && ($row = mysqli_fetch_assoc($res)) && !empty($row['public_token'])) {
        return $row['public_token'];
    }

    for ($i = 0; $i < 5; $i++) {
        $token = generate_public_quotation_token();
        $safeToken = mysqli_real_escape_string($mysqli, $token);
        if (mysqli_query($mysqli, "UPDATE quotations SET public_token = '$safeToken' WHERE id = " . (int) $quotationId . " AND (public_token IS NULL OR public_token = '')")) {
            return $token;
        }
    }

    return null;
}

function resolve_public_quotation_by_token($mysqli, $token)
{
    ensure_quotation_public_token_column($mysqli);

    $token = trim((string) $token);
    if ($token === '') {
        return null;
    }

    $safeToken = mysqli_real_escape_string($mysqli, $token);
    $res = mysqli_query($mysqli, "
        SELECT q.*, c.name AS customer_name, c.customer_no, c.address, c.pic
        FROM quotations q
        LEFT JOIN customers c ON q.customer_id = c.id
        WHERE q.public_token = '$safeToken'
        LIMIT 1
    ");

    if ($res && ($quote = mysqli_fetch_assoc($res))) {
        return $quote;
    }

    $id = decrypt_url_id($token);
    if ($id === null || $id <= 0) {
        return null;
    }

    $id = (int) $id;
    $res = mysqli_query($mysqli, "
        SELECT q.*, c.name AS customer_name, c.customer_no, c.address, c.pic
        FROM quotations q
        LEFT JOIN customers c ON q.customer_id = c.id
        WHERE q.id = $id
        LIMIT 1
    ");

    if (!$res || !($quote = mysqli_fetch_assoc($res))) {
        return null;
    }

    // Ensure this quotation gets a stable public token for future requests.
    if (empty($quote['public_token'])) {
        $newToken = get_or_create_quotation_public_token($mysqli, $id);
        if ($newToken !== null) {
            $quote['public_token'] = $newToken;
        }
    }

    return $quote;
}