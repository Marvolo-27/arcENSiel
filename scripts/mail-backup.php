<?php
// Override paths
$ROOT = __DIR__ . '/..';
$HOME = __DIR__ . '/../../..';
$mail_dir = $HOME . '/data/mail';

// Database connection and common variables
require_once __DIR__ . "/../config/config.php";

// Override db connection
try {
	$pdo = new PDO($db_server_connection, $db_mail_user, $db_mail_pass, $options);
} catch (PDOException $e) {
	http_response_code(500);
	echo "No connection to database could be established. Access to website impossible.";
	logger('/log/sql.log', json_encode($e), '[ERROR] Failed: establish connection to database as mailer.', true, false);
	exit;
}

// Dependencies
use \ZBateson\MailMimeParser\MailMimeParser;

function saveAttach($attachement, $hash) {
    global $mail_dir;

    $data = $attachement->getContent();
    $size = strlen($data);
    $type = $attachement->getContentType();
    $file = preg_replace('/[^a-zA-Z0-9-_.]/', '_', $attachement->getFilename());

    $dest = $mail_dir . '/attachments/' . $hash;
    mkdir($dest, 0700, true);
    $attachement->saveContent($dest . '/' . $file);

    return ['filename' => $file, 'size' => $size, 'mime' => $type, 'content' => base64_encode($data)];
}

$untreated_mails = glob($mail_dir . '/new/*');

foreach ($untreated_mails as $mail_file) {
    // get mail hash for uniqueness
    $data = file_get_contents($mail_file);
    $hash = hash('sha256', $data);
    $size = strlen($data);

    // parse mail content
    $mail = new MailMimeParser()->parse(fopen($mail_file, 'r'), true);
    
    $id         = $mail->getHeaderValue('Message-ID');
    $from       = json_encode([($mail->getHeader('From')->getPersonName() ?? '<'.$mail->getHeaderValue('From').'>') => $mail->getHeaderValue('From')], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    $recipient  = json_encode([$mail->getHeaderValue('X-Original-To') => $mail->getHeaderValue('X-Original-To')]);
    $to         = json_encode(array_reduce($mail->getHeader('To')?->getAddresses() ?? [], fn($carry, $item) => [...$carry, (preg_replace('/<?'.preg_quote($item->getEmail(), '/').'>?/', '', $item->getValue()) ?: '<'.$item->getEmail().'>') => $item->getEmail()], []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);    
    $cc         = json_encode(array_reduce($mail->getHeader('Cc')?->getAddresses() ?? [], fn($carry, $item) => [...$carry, (preg_replace('/<?'.preg_quote($item->getEmail(), '/').'>?/', '', $item->getValue()) ?: '<'.$item->getEmail().'>') => $item->getEmail()], []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    $subject    = $mail->getHeaderValue('Subject');
    $text       = $mail->getTextContent();
    $html       = $mail->getHtmlContent();
    $attach     = json_encode(array_reduce($mail->getAllAttachmentParts() ?? [], fn($carry, $item) => [...$carry,  $item->getFilename() => saveAttach($item, $hash)], []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    $sent       = $mail->getHeader('Date')->getDateTime()?->format('Y-m-d H:i:s');
    $received   = $mail->getHeader('Received')->getDateTime()?->format('Y-m-d H:i:s');
    
    $sql = "INSERT INTO mails (sha256, message_id, sent_from, send_to, recipients, cc, subject, sent_at, received_at, text_body, html_body, attachments, size) VALUES(:sha, :id, :from, :recipient, :to, :cc, :subject, :sent, :received, :text, :html, :attach, CAST(:size AS UNSIGNED));";
    $params = [
                ':sha' =>       $hash,
                ':id'  =>       $id,
                ':from' =>      $from,
                ':recipient' => $recipient,
                ':to' =>        $to,
                ':cc' =>        $cc,
                ':subject' =>   $subject,
                ':text' =>      $text,
                ':html' =>      $html,
                ':size' =>      $size,
                ':sent' =>      $sent,
                ':received' =>  $received,
                ':attach' =>    $attach
                ];

    try {
        $query = $pdo->prepare($sql);
        $query->execute($params);
        logger('/log/mail.log', "Mail {{$id}} inserted in database.", 'Insert mail');

        rename($mail_file, $mail_dir . '/cur/' . end(explode('/', $mail_file)));
    
    } catch (PDOException $e) {
        logger('/log/mail.log', "Could not insert mail {{$id}} in database: ".json_encode($e), '[ERROR] Failed: Insert mail');
    }

    // Redirect mail if necessary
    if ($mail->getHeaderValue('X-Original-To') === 'contact@arcensiel.org') {
        $response = sendMail(from: [array_key_first(json_decode($from, true)) => 'contact-request@arcensiel.org'], to: ['arcensiel.bureau@ens-lyon.fr'], reply: $from, subject: $subject, body: $html, attachments: json_decode($attach, true));

        if ($reponse['status'] === 0) {
            logger('/log/mail.log', "Transferred mail {{$id}} to arcENSiel mailbox: ", 'Transfer mail');
        } else {
            logger('/log/mail.log', "Could not transfer mail {{$id}} to arcENSiel mailbox. Check logs for more details.", '[ERROR] Failed: Transfer mail');
        }
    }
}

?>