<?php
session_start();

/**
 * Generate a 6-digit numeric verification code.
 */
function generateVerificationCode(): string {
    return sprintf('%06d', mt_rand(100000, 999999));
}

/**
 * Send a verification code to an email.
 */
function sendVerificationEmail(string $email, string $code): bool {
    $subject = "Your Verification Code";
    $body = "<p>Your verification code is: <strong>$code</strong></p>";
    
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: no-reply@example.com\r\n";
    $headers .= "Reply-To: no-reply@example.com\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    return mail($email, $subject, $body, $headers);
}

/**
 * Send unsubscribe verification code to an email.
 */
function sendUnsubscribeVerificationEmail(string $email, string $code): bool {
    $subject = "Confirm Unsubscription";
    $body = "<p>To confirm unsubscription, use this code: <strong>$code</strong></p>";
    
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: no-reply@example.com\r\n";
    $headers .= "Reply-To: no-reply@example.com\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    return mail($email, $subject, $body, $headers);
}

/**
 * Check if an email is registered.
 */
function isEmailRegistered(string $email): bool {
    $file = __DIR__ . '/registered_emails.txt';
    
    if (!file_exists($file)) {
        return false;
    }
    
    $content = file_get_contents($file);
    if (!$content) {
        return false;
    }
    
    $emails = explode("\n", trim($content));
    return in_array(trim($email), array_map('trim', $emails));
}

/**
 * Store verification code associated with email.
 */
function storeVerificationCode(string $email, string $code): bool {
    $file = __DIR__ . '/verification_codes.json';

    $codes = [];
    if (file_exists($file)) {
        $content = file_get_contents($file);
        if ($content) {
            $codes = json_decode($content, true) ?? [];
        }
    }

    // Store code associated with the email
    $codes[$email] = [
        'code' => $code,
        'timestamp' => time()
    ];

    return file_put_contents($file, json_encode($codes, JSON_PRETTY_PRINT)) !== false;
}

/**
 * Verify the code for an email.
 */
function verifyCode(string $email, string $code): bool {
    $file = __DIR__ . '/verification_codes.json';
    
    if (!file_exists($file)) {
        return false;
    }
    
    $content = file_get_contents($file);
    if (!$content) {
        return false;
    }
    
    $codes = json_decode($content, true);
    if (!$codes || !isset($codes[$email])) {
        return false;
    }
    
    $storedData = $codes[$email];
    
    // Check if code matches and is not expired (valid for 15 minutes)
    if ($storedData['code'] === $code && (time() - $storedData['timestamp']) < 900) {
        return true;
    }
    
    return false;
}

/**
 * Remove verification code after successful verification.
 */
function removeVerificationCode(string $email): bool {
    $file = __DIR__ . '/verification_codes.json';
    
    if (!file_exists($file)) {
        return false;
    }
    
    $content = file_get_contents($file);
    if (!$content) {
        return false;
    }
    
    $codes = json_decode($content, true);
    if (!$codes) {
        return false;
    }
    
    unset($codes[$email]);
    
    return file_put_contents($file, json_encode($codes, JSON_PRETTY_PRINT)) !== false;
}

/**
 * Register an email by storing it in a file.
 */
function registerEmail(string $email): bool {
    $file = __DIR__ . '/registered_emails.txt';
    $emails = [];

    if (file_exists($file)) {
        $content = file_get_contents($file);
        if (!empty(trim($content))) {
            $emails = explode("\n", trim($content));
        }
    }

    $email = trim($email);
    if (!in_array($email, $emails)) {
        $emails[] = $email;
        file_put_contents($file, implode("\n", $emails) . "\n");
        return true;
    }

    return false;
}

/**
 * Unsubscribe an email by removing it from the list.
 */
function unsubscribeEmail(string $email): bool {
    $file = __DIR__ . '/registered_emails.txt';
    
    if (!file_exists($file)) {
        return false;
    }
    
    $content = file_get_contents($file);
    if (!$content) {
        return false;
    }
    
    $emails = explode("\n", trim($content));
    $originalCount = count($emails);

    $emails = array_filter($emails, function($e) use ($email) {
        return trim($e) !== trim($email);
    });

    $newCount = count($emails);

    if ($newCount < $originalCount) {
        // Email was removed
        if (empty($emails)) {
            file_put_contents($file, '');
        } else {
            file_put_contents($file, implode("\n", $emails) . "\n");
        }
        return true;
    }

    return false;
}

/**
 * Fetch GitHub timeline data.
 */
function fetchGitHubTimeline() {
    $url = "https://api.github.com/events/public";
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 30,
            'user_agent' => 'GitHub Timeline Subscriber v1.0',
            'header' => "Accept: application/vnd.github.v3+json\r\n"
        ]
    ]);
    
    $json = @file_get_contents($url, false, $context);
    if ($json === false) {
        error_log("Failed to fetch GitHub timeline: " . error_get_last()['message']);
        return false;
    }
    
    $data = json_decode($json, true);
    return $data ? $data : false;
}

/**
 * Format GitHub data as HTML.
 */
function formatGitHubData($data): string {
    if (!$data || !is_array($data)) {
        return "<h2>GitHub Timeline Updates</h2><p>No updates available</p><p><a href=\"http://localhost/unsubscribe.php\" id=\"unsubscribe-button\">Unsubscribe</a></p>";
    }
    
    $html = "<h2>GitHub Timeline Updates</h2>\n";
    $html .= "<table border=\"1\">\n";
    $html .= "  <tr><th>Event</th><th>User</th></tr>\n";
    
    // Limit to first 10 events
    $events = array_slice($data, 0, 10);
    
    foreach ($events as $event) {
        $eventType = isset($event['type']) ? htmlspecialchars($event['type']) : 'Unknown';
        $username = isset($event['actor']['login']) ? htmlspecialchars($event['actor']['login']) : 'Unknown';
        
        $html .= "  <tr><td>$eventType</td><td>$username</td></tr>\n";
    }
    
    $html .= "</table>\n";
    $html .= "<p><a href=\"http://localhost/unsubscribe.php\" id=\"unsubscribe-button\">Unsubscribe</a></p>";
    
    return $html;
}

/**
 * Send GitHub updates to all registered users.
 */
function sendGitHubUpdatesToSubscribers(): bool {
    $file = __DIR__ . '/registered_emails.txt';
    
    if (!file_exists($file)) {
        error_log("No registered emails file found");
        return false;
    }
    
    $content = file_get_contents($file);
    if (empty(trim($content))) {
        error_log("No registered emails found");
        return false;
    }
    
    $emails = explode("\n", trim($content));
    $githubData = fetchGitHubTimeline();
    
    if ($githubData === false) {
        error_log("Failed to fetch GitHub timeline data");
        return false;
    }
    
    $htmlContent = formatGitHubData($githubData);
    
    $subject = "Latest GitHub Updates";
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: no-reply@example.com\r\n";
    $headers .= "Reply-To: no-reply@example.com\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    $successCount = 0;
    $totalEmails = 0;
    
    foreach ($emails as $email) {
        $email = trim($email);
        if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $totalEmails++;
            if (mail($email, $subject, $htmlContent, $headers)) {
                $successCount++;
            } else {
                error_log("Failed to send email to: $email");
            }
        }
    }
    
    error_log("GitHub updates sent to $successCount out of $totalEmails subscribers");
    return $successCount > 0;
}

/**
 * Initialize empty files if they don't exist.
 */
function initializeFiles(): void {
    $files = [
        __DIR__ . '/registered_emails.txt',
        __DIR__ . '/verification_codes.json'
    ];
    
    foreach ($files as $file) {
        if (!file_exists($file)) {
            if (strpos($file, '.json') !== false) {
                file_put_contents($file, '{}');
            } else {
                file_put_contents($file, '');
            }
        }
    }
}

// Initialize files when this script is included
initializeFiles();
?>