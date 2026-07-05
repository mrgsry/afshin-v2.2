<?php

/**
 * Mail Service for sending Quotation PDF via Gmail (PHPMailer)
 * Expected POST params: quotation_id, subject, body
 */
require_once 'vendor/autoload.php';
require_once 'db.php';
require_once 'generate_quotation_pdf.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Config – replace with your actual Gmail credentials
$SMTP_USER = 'cvafshinrayateknik@gmail.com';
$SMTP_PASS = 'isch kxdm blsl xwxv'; // App password / key

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

// Generate PDF
$pdf_path = generateQuotationPDF($quotation_id);
file_put_contents(__DIR__ . '/debug.log', 
    date('Y-m-d H:i:s') . ' pdf_path: ' . var_export($pdf_path, true) . "\n", 
    FILE_APPEND
);
if(!$pdf_path) {
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>'Failed to generate PDF']);
    exit;
}

$mail = new PHPMailer(true);
try {
    // Server settings
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = $SMTP_USER;
    $mail->Password = $SMTP_PASS;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
$mail->Port = 587;

    // Recipients
    $mail->setFrom($SMTP_USER, 'CV Afshin Raya Teknik');
    
    // Add multiple recipients
    foreach($to_emails as $email) {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $mail->addAddress($email);
        }
    }
    
    // Add multiple CCs
    foreach($cc_emails as $cc) {
        if (filter_var($cc, FILTER_VALIDATE_EMAIL)) {
            $mail->addCC($cc);
        }
    }

    // Attachments
    $mail->addAttachment($pdf_path, basename($pdf_path));

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

    $mail->send();

    // Cleanup temp PDF
    @unlink($pdf_path);

    echo json_encode(['status'=>'success','message'=>'Email sent successfully']);
} catch (Exception $e) {
    // Cleanup temp PDF even on error
    @unlink($pdf_path);
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>'Mailer Error: '.$mail->ErrorInfo]);
}
?>