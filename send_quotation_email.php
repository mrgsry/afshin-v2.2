<?php

/**
 * Mail Service for sending Quotation PDF via Gmail (PHPMailer)
 * Expected POST params: quotation_id, subject, body
 */
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/generate_quotation_pdf.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json; charset=utf-8');

$quotation_id = intval($_POST['quotation_id'] ?? 0);
$subject = trim($_POST['subject'] ?? 'Quotation from Afshin');
$body   = trim($_POST['body'] ?? 'Berikut terlampir quotation Anda.');

if ($quotation_id <= 0) {
    http_response_code(400);
    echo json_encode(['status'=>'error','message'=>'Invalid quotation ID']);
    exit;
}

// Fetch customer email & cc
$res = mysqli_query($mysqli, "SELECT email, cc_email FROM customers WHERE id = (SELECT customer_id FROM quotations WHERE id=$quotation_id) LIMIT 1");
if(!$cust = mysqli_fetch_assoc($res)) {
    http_response_code(404);
    echo json_encode(['status'=>'error','message'=>'Customer not found']);
    exit;
}

// Support multiple emails in "To" and "CC" separated by comma
$to_raw   = $cust['email'];
$cc_raw   = $cust['cc_email'];

$to_emails = array_filter(array_map('trim', explode(',', $to_raw)));
$cc_emails = array_filter(array_map('trim', explode(',', $cc_raw)));

if (empty($to_emails)) {
    http_response_code(400);
    echo json_encode(['status'=>'error','message'=>'No recipient email address found']);
    exit;
}

$validToEmails = array_values(array_filter($to_emails, static function ($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}));

if (empty($validToEmails)) {
    http_response_code(400);
    echo json_encode(['status'=>'error','message'=>'No valid recipient email address found']);
    exit;
}

$smtpUser = trim((string)($SMTP_USER ?? ''));
$smtpPass = preg_replace('/\s+/', '', (string)($SMTP_PASS ?? ''));

if ($smtpUser === '' || $smtpPass === '') {
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>'Mail service is not configured']);
    exit;
}

$attachments = [];
$allowedAttachmentTypes = [
    'pdf' => ['application/pdf'],
    'doc' => ['application/msword'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
    'xls' => ['application/vnd.ms-excel'],
    'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
    'jpg' => ['image/jpeg'],
    'jpeg' => ['image/jpeg'],
    'png' => ['image/png']
];
$maxAttachmentSize = 10 * 1024 * 1024;
$uploadedFiles = $_FILES['attachments'] ?? null;

if ($uploadedFiles && is_array($uploadedFiles['name'] ?? null)) {
    $fileCount = count(array_filter($uploadedFiles['name'], static function ($name) {
        return trim((string)$name) !== '';
    }));
    if ($fileCount > 5) {
        http_response_code(400);
        echo json_encode(['status'=>'error','message'=>'Maksimal 5 dokumen tambahan.']);
        exit;
    }

    $fileInfo = new finfo(FILEINFO_MIME_TYPE);
    foreach ($uploadedFiles['name'] as $index => $originalName) {
        if (trim((string)$originalName) === '') continue;

        $uploadError = (int)($uploadedFiles['error'][$index] ?? UPLOAD_ERR_NO_FILE);
        if ($uploadError !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['status'=>'error','message'=>'Dokumen tambahan gagal diupload.']);
            exit;
        }

        $tmpName = $uploadedFiles['tmp_name'][$index] ?? '';
        $fileSize = (int)($uploadedFiles['size'][$index] ?? 0);
        $extension = strtolower(pathinfo((string)$originalName, PATHINFO_EXTENSION));
        $mimeType = $tmpName !== '' && is_uploaded_file($tmpName) ? $fileInfo->file($tmpName) : false;

        if ($fileSize <= 0 || $fileSize > $maxAttachmentSize || !isset($allowedAttachmentTypes[$extension]) || !in_array($mimeType, $allowedAttachmentTypes[$extension], true)) {
            http_response_code(400);
            echo json_encode(['status'=>'error','message'=>'Tipe atau ukuran dokumen tambahan tidak diizinkan.']);
            exit;
        }

        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', basename((string)$originalName));
        $attachments[] = ['path' => $tmpName, 'name' => $safeName, 'type' => $mimeType];
    }
}

// Generate PDF
$pdf_path = generateQuotationPDF($quotation_id);
error_log('send_quotation_email pdf_path: ' . var_export($pdf_path, true));
if (!$pdf_path || !is_file($pdf_path) || !is_readable($pdf_path) || (int)filesize($pdf_path) <= 0) {
    error_log('send_quotation_email PDF validation failed: ' . var_export([
        'path' => $pdf_path,
        'is_file' => $pdf_path ? is_file($pdf_path) : false,
        'is_readable' => $pdf_path ? is_readable($pdf_path) : false,
        'size' => $pdf_path && is_file($pdf_path) ? filesize($pdf_path) : null,
    ], true));
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>'Failed to generate PDF']);
    exit;
}

$safeCcCount = count(array_filter($cc_emails, static function ($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}));
error_log('send_quotation_email preparing SMTP: ' . json_encode([
    'quotation_id' => $quotation_id,
    'to_count' => count($validToEmails),
    'cc_count' => $safeCcCount,
    'attachment_size' => filesize($pdf_path),
    'smtp_user_domain' => substr(strrchr($smtpUser, '@') ?: '', 1),
]));

$mail = new PHPMailer(true);
try {
    // Server settings
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = $smtpUser;
    $mail->Password = $smtpPass;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    $mail->SMTPDebug = 2;
    $mail->Debugoutput = static function ($message, $level) {
        error_log('PHPMailer SMTP [' . $level . ']: ' . trim($message));
    };

    // Recipients
    $mail->setFrom($smtpUser, 'CV Afshin Raya Teknik');
    
    // Add multiple recipients
    foreach ($validToEmails as $email) {
        $mail->addAddress($email);
    }
    
    // Add multiple CCs
    foreach($cc_emails as $cc) {
        if (filter_var($cc, FILTER_VALIDATE_EMAIL)) {
            $mail->addCC($cc);
        }
    }

    // Attachments
    $mail->addAttachment($pdf_path, basename($pdf_path));
    error_log('send_quotation_email PDF attachment added');
    foreach ($attachments as $attachment) {
        $mail->addAttachment($attachment['path'], $attachment['name'], PHPMailer::ENCODING_BASE64, $attachment['type']);
    }

    // Content
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $signature = '
<br><br>
<table style="font-family: Arial, sans-serif; font-size: 13px; color: #333;">
    <tr>
        <td style="padding-right: 15px; vertical-align: middle;">
            <img src="https://afshin.hnet-diigital.biz.id/img/afshin2.png" width="80" style="display:block;">
        </td>
        <td style="vertical-align: top; border-left: 3px solid #cc0000; padding-left: 12px;">
            <strong style="font-size: 14px;">CV. Afshin Rayan Teknik</strong><br>
            <span style="color: #555;">Penyedia Sparepart Mesin Bubut dan Milling,<br>Jasa Maintenance dan Kontruksi Gedung.</span><br><br>
            Kp. Ciketing, Jl. Kramat No. 75, RT. 004 RW. 011,<br>
            Desa/Kelurahan Mustikajaya, Kecamatan Mustikajaya,<br>
            Kota Bekasi, Jawa Barat<br>
            Tlp : +62 896 1464 7011<br>
            Email : <a href="mailto:cvafshinrayateknik@gmail.com" style="color:#cc0000;">cvafshinrayateknik@gmail.com</a>
        </td>
    </tr>
</table>';

$mail->Body    = nl2br(htmlspecialchars($body)) . $signature;
$mail->AltBody = $body . "\n\n--\nCV. Afshin Rayan Teknik\nTlp: +62 896 1464 7011\nEmail: cvafshinrayateknik@gmail.com";

    error_log('send_quotation_email calling SMTP send');
    $mail->send();
    error_log('send_quotation_email SMTP send completed');

    // Cleanup temp PDF
    @unlink($pdf_path);

    echo json_encode(['status'=>'success','message'=>'Email sent successfully']);
} catch (Throwable $e) {
    // Cleanup temp PDF even on error
    @unlink($pdf_path);
    error_log('send_quotation_email failed: ' . get_class($e) . ': ' . $e->getMessage());
    error_log('send_quotation_email PHPMailer ErrorInfo: ' . $mail->ErrorInfo);
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>'Mailer Error: '.$mail->ErrorInfo]);
}
?>