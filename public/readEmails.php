<?php
ini_set('max_execution_time', 300);
header('Content-Type: text/html; charset=UTF-8');

/* ==========================================================
   1) CONFIG
   ========================================================== */

// ---- Gmail IMAP ----
$hostname = '{imap.gmail.com:993/imap/ssl}INBOX';
$username = 'zepselvijs18@gmail.com';
$password = 'glskanxhpyypphee'; // <-- rotate if exposed, paste without spaces

// ---- MySQL (XAMPP defaults) ----
$dbHost = '127.0.0.1';
$dbName = 'jobtracker';
$dbUser = 'root';
$dbPass = ''; // XAMPP usually empty

/* ==========================================================
   2) DB CONNECT + TABLE
   ========================================================== */
try {
    // Create DB if it doesn't exist
    $pdoServer = new PDO("mysql:host=$dbHost;charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    $pdoServer->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    // Connect to DB
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // Create table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS job_emails (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            email_uid BIGINT UNSIGNED NOT NULL,
            source VARCHAR(50) NOT NULL,
            sender VARCHAR(255) NOT NULL,
            subject TEXT NOT NULL,
            received_at DATETIME NULL,
            status VARCHAR(20) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_email_uid (email_uid),
            KEY idx_status (status),
            KEY idx_received_at (received_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

} catch (PDOException $e) {
    die("<h2>DB connection failed</h2><pre>" . htmlspecialchars($e->getMessage()) . "</pre>");
}

// prepared statements (fast)
$checkUidStmt = $pdo->prepare("SELECT 1 FROM job_emails WHERE email_uid = ? LIMIT 1");
$insertStmt = $pdo->prepare("
    INSERT INTO job_emails (email_uid, source, sender, subject, received_at, status)
    VALUES (?, ?, ?, ?, ?, ?)
");

/* ==========================================================
   3) IMAP CONNECT
   ========================================================== */
$inbox = imap_open($hostname, $username, $password)
    or die('Cannot connect: ' . imap_last_error());

$emails = imap_search($inbox, 'SINCE "01-JAN-2026"');
if (!$emails) die("No emails found.");

rsort($emails);

/* ==========================================================
   4) YOUR FILTERS (unchanged)
   ========================================================== */
$sources = ['@cv.lv', '@cvmarket.lv', '@linkedin.com'];

$ignoreSignals = [
    'job alert','recommended jobs','new jobs','top jobs','companies hiring',
    'daily','weekly','your job alert','recently posted','share their thoughts','view job','see job',
    'jobs you may be interested in','share','build a job',

    'job offer','we are pleased to offer','offer','congratulations',
    'piedāvā','darba piedāvājums','is hiring'
];

$jobSignals = [
    'apply','applied','application',
    'application received','thank you for applying',

    'pieteikums','pieteikums nosūtīts','pieteikums saņemts',
    'paldies par pieteikumu',
    'tika apskatīts jūsu pieteikums',
    'jūsu pieteikums tika apskatīts',

    'interview','schedule','meeting',
    'saruna','intervij',

    'rejected','regret','unfortunately','diemžēl'
];

$keywords = [
    'interview' => [
        'interview','schedule','meeting',
        'saruna','intervij','uzaicinām uz sarunu','uzaicinam uz sarunu'
    ],
    'rejected' => [
        'we regret','unfortunately','not selected','rejected',
        'diemžēl','neesam izvēlējušies','neesam izvelejusi'
    ],
    'in progress' => [
        'application received','thank you for applying','your application',

        'pieteikums nosūtīts','pieteikums nosutits',
        'pieteikums saņemts','pieteikums sanemts',
        'paldies par pieteikumu',
        'tika apskatīts jūsu pieteikums',
        'tika apskatits jusu pieteikums'
    ]
];

/* ==========================================================
   5) SCAN + SAVE TO DB
   ========================================================== */
echo "<h1>Job Applications</h1>";

$inserted = 0;
$skippedExisting = 0;

foreach ($emails as $email_number) {
    $overview = imap_fetch_overview($inbox, $email_number, 0)[0];

    $from = $overview->from ?? '';
    $date = $overview->date ?? '';

    // fast sender filter
    $isJobSource = false;
    foreach ($sources as $source) {
        if (stripos($from, $source) !== false) {
            $isJobSource = true;
            break;
        }
    }
    if (!$isJobSource) continue;

    // UID for dedupe (critical for speed)
    $emailUid = imap_uid($inbox, $email_number);
    if (!$emailUid) continue;

    $checkUidStmt->execute([$emailUid]);
    if ($checkUidStmt->fetchColumn()) {
        $skippedExisting++;
        continue; // already in DB
    }

    // decode subject safely
    $subject = '';
    foreach (imap_mime_header_decode($overview->subject ?? '') as $part) {
        $charset = strtoupper($part->charset ?? 'UTF-8');
        if ($charset === 'DEFAULT' || !in_array($charset, mb_list_encodings(), true)) {
            $charset = 'UTF-8';
        }
        $subject .= mb_convert_encoding($part->text, 'UTF-8', $charset);
    }

    // fetch body
    $structure = imap_fetchstructure($inbox, $email_number);
    $body = '';

    if (!isset($structure->parts)) {
        $body = quoted_printable_decode(imap_body($inbox, $email_number));
    } else {
        foreach ($structure->parts as $i => $part) {
            if ($part->type == 0) {
                $partBody = imap_fetchbody($inbox, $email_number, $i + 1);
                if ($part->encoding == 3) $partBody = base64_decode($partBody);
                elseif ($part->encoding == 4) $partBody = quoted_printable_decode($partBody);
                $body .= $partBody;
            }
        }
    }

    $body = trim(strip_tags($body));
    $text = strtolower($subject . ' ' . $body);

    // ignore alerts & offers
    foreach ($ignoreSignals as $ig) {
        if (strpos($text, $ig) !== false) continue 2;
    }

    // must look like an application
    $looksLikeApplication = false;
    foreach ($jobSignals as $sig) {
        if (strpos($text, strtolower($sig)) !== false) {
            $looksLikeApplication = true;
            break;
        }
    }
    if (!$looksLikeApplication) continue;

    // categorize
    $status = 'in progress';
    foreach ($keywords as $key => $phrases) {
        foreach ($phrases as $phrase) {
            if (stripos($text, $phrase) !== false) {
                $status = $key;
                break 2;
            }
        }
    }

    // source label
    $sourceLabel = 'other';
    if (stripos($from, '@cv.lv') !== false) $sourceLabel = 'cv.lv';
    elseif (stripos($from, '@cvmarket.lv') !== false) $sourceLabel = 'cvmarket.lv';
    elseif (stripos($from, '@linkedin.com') !== false) $sourceLabel = 'linkedin';

    // date -> MySQL datetime
    $receivedAt = null;
    $ts = strtotime($date);
    if ($ts !== false) $receivedAt = date('Y-m-d H:i:s', $ts);

    // INSERT into DB
    $insertStmt->execute([
        $emailUid,
        $sourceLabel,
        $from,
        $subject,
        $receivedAt,
        $status
    ]);

    $inserted++;

    // Print (optional, you can remove this echo if you only want DB)
    echo "<strong>From:</strong> " . htmlspecialchars($from) . "<br>";
    echo "<strong>Subject:</strong> " . htmlspecialchars($subject) . "<br>";
    echo "<strong>Date:</strong> " . htmlspecialchars($date) . "<br>";
    echo "<strong>Status:</strong> " . htmlspecialchars($status) . "<br><hr>";
}

imap_close($inbox);

/* ==========================================================
   6) SHOW RESULTS FROM DB (FAST VIEW)
   ========================================================== */
echo "<h2>DB Summary</h2>";
echo "<p><strong>Inserted new:</strong> $inserted | <strong>Skipped existing:</strong> $skippedExisting</p>";

$counts = $pdo->query("SELECT status, COUNT(*) c FROM job_emails GROUP BY status")->fetchAll();
echo "<ul>";
foreach ($counts as $c) {
    echo "<li><strong>" . htmlspecialchars($c['status']) . ":</strong> " . (int)$c['c'] . "</li>";
}
echo "</ul>";

echo "<h2>Latest saved (DB)</h2>";
$rows = $pdo->query("
    SELECT source, sender, subject, received_at, status
    FROM job_emails
    ORDER BY received_at DESC, id DESC
    LIMIT 100
")->fetchAll();

foreach ($rows as $r) {
    echo "<strong>Source:</strong> " . htmlspecialchars($r['source']) . "<br>";
    echo "<strong>From:</strong> " . htmlspecialchars($r['sender']) . "<br>";
    echo "<strong>Subject:</strong> " . htmlspecialchars($r['subject']) . "<br>";
    echo "<strong>Date:</strong> " . htmlspecialchars($r['received_at'] ?? '') . "<br>";
    echo "<strong>Status:</strong> " . htmlspecialchars($r['status']) . "<br><hr>";
}
?>
