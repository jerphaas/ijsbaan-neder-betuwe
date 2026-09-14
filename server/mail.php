<?php
declare(strict_types=1);
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

class SponsorSMTP extends SMTP {
    public $dataStarted = false;
    public function data($message) {
        $this->dataStarted = true;
        return parent::data($message);
    }
}

function sponsor_deliver(int $id): void {
    $db = sponsor_db();
    $config = sponsor_config();
    $q = $db->prepare('SELECT * FROM sponsor_applications WHERE id = ?');
    $q->execute([$id]);
    $app = $q->fetch();
    if (!$app) throw new RuntimeException('Application missing');
    $snapshot = json_decode($app['snapshot_json'], true, 512, JSON_THROW_ON_ERROR);
    $edition = $snapshot['edition']['place'] . ' ' . $snapshot['edition']['season'];
    $amount = '€ ' . number_format((float)$app['package_amount'], 0, ',', '.');
    $test = (bool)$app['is_test'];
    foreach (['organizer', 'sponsor'] as $kind) {
        // One sender may claim a message. Ambiguous interrupted deliveries stay 'sending' for manual inspection.
        $claim = $db->prepare("UPDATE sponsor_mail SET state = 'sending', attempts = attempts + 1, attempted_at = UTC_TIMESTAMP() WHERE application_id = ? AND recipient_kind = ? AND state = 'pending' AND attempts < 5");
        $claim->execute([$id, $kind]);
        if ($claim->rowCount() !== 1) continue;
        $smtp = new SponsorSMTP();
        try {
            $mail = new PHPMailer(true);
            $mail->setSMTPInstance($smtp);
            $mail->isSMTP();
            $mail->Host = $config['smtp']['host'];
            $mail->Port = (int)$config['smtp']['port'];
            $mail->SMTPAuth = true;
            $mail->Username = $config['smtp']['user'];
            $mail->Password = $config['smtp']['password'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->SMTPOptions = ['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false]];
            $mail->Timeout = 12;
            $mail->Timelimit = 20;
            $mail->CharSet = 'UTF-8';
            $mail->setFrom($config['from_email'], 'IJsbaan Neder-Betuwe');
            $mail->MessageID = '<' . strtolower($app['reference']) . '.' . $kind . '@ijsbaannederbetuwe.nl>';
            if ($kind === 'organizer') {
                $mail->addAddress($config['organizer_email'], 'Ton Keuken');
                $mail->addReplyTo($app['email'], $app['contact_name']);
                $intro = "Beste Ton,\n\nEr is een nieuwe sponsoraanvraag voor " . $edition . '. Je kunt op deze e-mail antwoorden om contact op te nemen met de sponsor.';
                $subject = 'Nieuwe sponsoraanvraag ' . $amount . ' — ' . $app['company'];
                if ($app['logo_file']) $mail->addAttachment(dirname(__DIR__) . '/uploads/' . $app['logo_file'], 'sponsorlogo.png', PHPMailer::ENCODING_BASE64, 'image/png');
            } else {
                $mail->addAddress($app['email'], $app['contact_name']);
                $mail->addReplyTo($config['organizer_email'], 'Ton Keuken');
                $intro = 'Beste ' . $app['contact_name'] . ",\n\nWat fijn dat jullie de winterpret een zetje willen geven. We hebben jullie sponsoraanvraag voor " . $edition . ' ontvangen. Ton Keuken neemt contact op om de bijdrage en verdere invulling af te stemmen.';
                $subject = 'Je sponsoraanvraag is ontvangen — ' . $app['reference'];
            }
            $details = "Referentie: {$app['reference']}\nPakket: {$amount}\nBedrijf / organisatie: {$app['company']}\nContactpersoon: {$app['contact_name']}\nE-mail: {$app['email']}\nTelefoon: " . ($app['phone'] ?: 'Niet ingevuld') . "\nLogo: " . ($app['logo_file'] ? 'Ontvangen en opgeslagen voor de organisatie.' : 'Niet toegevoegd; kan later in overleg.') . "\n\nBij dit pakket:\n• " . implode("\n• ", $snapshot['package']['benefits']) . "\n\nOpmerking / wensen:\n" . ($app['notes'] ?: 'Geen opmerkingen.') . "\n\nDit is een bevestiging van de aanvraag. De organisatie stemt de verdere afhandeling met jullie af.\n\nHartelijke groet,\nIJsbaan Neder-Betuwe\nTon Keuken · 06 53 63 87 78\n" . $config['site_url'];
            $prefix = $test ? "TECHNISCHE TEST — GEEN SPONSORVERZOEK\nDit is een eenmalige controle van het nieuwe formulier. Er hoeft niets te worden verwerkt of gefactureerd.\n\n" : '';
            $mail->Subject = ($test ? '[TECHNISCHE TEST] ' : '') . $subject;
            $mail->AltBody = $prefix . $intro . "\n\n" . $details;
            $escape = function (string $text): string { return nl2br(htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')); };
            $mail->isHTML(true);
            $mail->Body = '<!doctype html><html lang="nl"><meta charset="utf-8"><body style="margin:0;background:#f6f3eb;color:#193e32;font-family:Arial,sans-serif"><div style="max-width:600px;margin:24px auto;background:white;border-radius:18px;overflow:hidden"><div style="background:#104a38;padding:28px;color:white"><p style="font-size:13px;letter-spacing:2px">IJSBAAN NEDER-BETUWE</p><h1 style="font-size:28px;margin:12px 0">Winter vier je samen.</h1></div><div style="padding:28px;line-height:1.7">' . ($test ? '<p style="color:#a33;font-weight:bold">' . $escape($prefix) . '</p>' : '') . '<p>' . $escape($intro) . '</p><div style="background:#f6f3eb;padding:20px;border-radius:12px">' . $escape($details) . '</div></div></div></body></html>';
            $mail->send();
            $done = $db->prepare("UPDATE sponsor_mail SET state = 'sent', sent_at = UTC_TIMESTAMP(), failure_code = NULL WHERE application_id = ? AND recipient_kind = ?");
            $done->execute([$id, $kind]);
        } catch (Throwable $error) {
            // Do not log SMTP transcripts or supplied personal data.
            // A disconnect during/after DATA might still mean accepted mail: never resend that blindly.
            $failed = $db->prepare("UPDATE sponsor_mail SET state = ?, failure_code = ? WHERE application_id = ? AND recipient_kind = ? AND state = 'sending'");
            $failed->execute([$smtp->dataStarted ? 'uncertain' : 'pending', $smtp->dataStarted ? 'smtp_result_uncertain' : 'smtp_delivery_failed', $id, $kind]);
            error_log('Sponsor SMTP delivery pending: ' . $id . ' / ' . $kind);
        }
    }
}
