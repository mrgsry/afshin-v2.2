<?php
require_once __DIR__ . '/ai_config.php';
require_admin();
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $provider = ($_POST['provider'] ?? 'gemini') === 'openai_compatible' ? 'openai_compatible' : 'gemini';
        $config = ai_config($mysqli, $provider);
        $apiKey = trim($_POST['api_key'] ?? '') ?: $config['api_key'];
        if ($apiKey === '') throw new InvalidArgumentException('API key provider wajib diisi.');
        save_ai_config($mysqli, $provider, $apiKey, $_POST['model'] ?? '', $_POST['base_url'] ?? '');
        $message = 'Pengaturan AI berhasil disimpan.';
    } catch (Throwable $e) { $error = $e->getMessage(); }
}
$provider = ($provider ?? ($_GET['provider'] ?? 'gemini')) === 'openai_compatible' ? 'openai_compatible' : 'gemini';
$config = ai_config($mysqli, $provider);
include __DIR__ . '/header.php';
?>
<div class="container-fluid py-4">
    <h3 class="mb-3"><i class="fas fa-robot mr-2"></i>Pengaturan Provider AI</h3>
    <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <div class="card"><div class="card-body" style="max-width:720px">
        <p class="text-muted">API key disimpan terenkripsi di server dan tidak pernah dikirim ke browser.</p>
        <form method="post">
            <div class="form-group"><label for="provider">Provider</label><select class="form-control" id="provider" name="provider"><option value="gemini" <?= $provider === 'gemini' ? 'selected' : '' ?>>Gemini API</option><option value="openai_compatible" <?= $provider === 'openai_compatible' ? 'selected' : '' ?>>OpenAI Compatible (9Router)</option></select></div>
            <div class="form-group"><label for="api_key">API Key</label><input type="password" class="form-control" id="api_key" name="api_key" placeholder="<?= $config['configured'] ? 'API key tersimpan, isi jika ingin mengganti' : 'Masukkan API key' ?>" autocomplete="new-password"></div>
            <div class="form-group"><label for="base_url">Base URL</label><input type="url" class="form-control" id="base_url" name="base_url" value="<?= htmlspecialchars($config['base_url']) ?>"></div>
            <div class="form-group"><label for="model">Model</label><input class="form-control" id="model" name="model" value="<?= htmlspecialchars($config['model']) ?>"></div>
            <button class="btn btn-primary" type="submit"><i class="fas fa-save mr-1"></i>Simpan Pengaturan</button>
        </form>
    </div></div>
</div>
<?php include __DIR__ . '/footer.php'; ?>