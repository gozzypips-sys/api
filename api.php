<?php
/**
 * Gozzy Group — API Endpoint
 * Handles: Contact form, Telegram webhook, Discord notifications
 * Deploy this on a PHP host (Hostinger, Railway, Render, etc.)
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ============================================================
// 1. CONFIGURATION
// ============================================================
$CONFIG = [
    'email_to'            => 'Gozzypips@gmail.com',
    'email_from'          => 'noreply@gozzygroup.com',
    'email_subject'       => 'New Project Inquiry — Gozzy Group',

    'telegram_bot_token'  => '8578906720:AAGRzce4EXBjYlxl4AjiGNiWV5PiDjn-iwE',
    'telegram_chat_id'    => '8149610939',

    'discord_webhook_url' => 'YOUR_DISCORD_WEBHOOK_URL_HERE',

    'telegram_webhook_secret' => 'gozzysecret123',
    'gozzy_group_chat_id'     => 'YOUR_GOZZY_GROUP_CHAT_ID_HERE',

    'rate_limit_seconds' => 60,
    'rate_limit_file'    => sys_get_temp_dir() . '/gozzy_rate.json',
    'log_file'           => __DIR__ . '/inquiries.log',

    'public_phone_display' => '09021317870',
    'public_email'         => 'Gozzypips@gmail.com',
    'public_telegram'      => 'https://t.me/+2349021317870',
    'public_website'       => 'https://gozzygroup.com',
];

// ============================================================
// 2. HELPERS
// ============================================================
function jsonResponse($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function isRateLimited($ip, $seconds, $file) {
    $now = time();
    $data = [];
    if (file_exists($file)) {
        $raw = @file_get_contents($file);
        if ($raw) $data = json_decode($raw, true) ?: [];
    }
    foreach ($data as $key => $ts) {
        if ($now - $ts > $seconds * 10) unset($data[$key]);
    }
    if (isset($data[$ip]) && ($now - $data[$ip]) < $seconds) return true;
    $data[$ip] = $now;
    @file_put_contents($file, json_encode($data), LOCK_EX);
    return false;
}

function logInquiry($logFile, $entry) {
    $line = '[' . date('Y-m-d H:i:s') . '] ' . json_encode($entry) . PHP_EOL;
    @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

function sendTelegram($botToken, $chatId, $text, $parseMode = 'HTML') {
    if (empty($botToken) || empty($chatId)) return false;
    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
    return httpPost($url, [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => $parseMode,
        'disable_web_page_preview' => true,
    ]);
}

function sendDiscord($webhookUrl, $content) {
    if (empty($webhookUrl)) return false;
    return httpPost($webhookUrl, ['content' => $content]);
}

function httpPost($url, $data) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $result = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($err) { error_log("Gozzy cURL error: $err"); return false; }
    return $result;
}

function clean($value) {
    return htmlspecialchars(trim((string)$value), ENT_QUOTES, 'UTF-8');
}

// ============================================================
// 3. TELEGRAM WEBHOOK
// ============================================================
if (isset($_GET['webhook']) && $_GET['webhook'] === 'telegram') {
    $secretHeader = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
    if ($CONFIG['telegram_webhook_secret'] && $secretHeader !== $CONFIG['telegram_webhook_secret']) {
        jsonResponse(['ok' => false, 'error' => 'unauthorized'], 403);
    }

    $raw = file_get_contents('php://input');
    $update = json_decode($raw, true);
    if (!$update) jsonResponse(['ok' => false, 'error' => 'invalid payload'], 400);

    if (isset($update['message'])) {
        $msg       = $update['message'];
        $chatId    = $msg['chat']['id'] ?? null;
        $text      = $msg['text'] ?? '';
        $firstName = $msg['from']['first_name'] ?? 'there';

        if ($text === '/start') {
            sendTelegram($CONFIG['telegram_bot_token'], $chatId,
                "👋 <b>Welcome to Gozzy Group</b>, {$firstName}!\n\n" .
                "Available commands:\n" .
                "/start — Show this message\n" .
                "/services — What we build\n" .
                "/contact — How to reach us\n" .
                "/project — Start a project inquiry\n" .
                "/group — Join the Gozzy Group chat room"
            );
        } elseif ($text === '/services') {
            sendTelegram($CONFIG['telegram_bot_token'], $chatId,
                "🛠 <b>Gozzy Group Services</b>\n\n" .
                "• Custom Business Software\n• APIs & Integrations\n" .
                "• Automation & Bots\n• Trading Technology (MT4/MT5)\n" .
                "• Web3 & Blockchain\n• E-commerce Platforms"
            );
        } elseif ($text === '/contact') {
            sendTelegram($CONFIG['telegram_bot_token'], $chatId,
                "📬 <b>Contact Gozzy Group</b>\n\n" .
                "📧 Email: {$CONFIG['public_email']}\n" .
                "📱 Phone: {$CONFIG['public_phone_display']}\n" .
                "💬 Telegram: <a href=\"{$CONFIG['public_telegram']}\">Chat with us</a>\n" .
                "🌐 Web: {$CONFIG['public_website']}"
            );
        } elseif ($text === '/project') {
            sendTelegram($CONFIG['telegram_bot_token'], $chatId,
                "🚀 <b>Ready to start?</b>\n\n" .
                "Visit our contact form:\n{$CONFIG['public_website']}/#contact"
            );
        } elseif ($text === '/group') {
            sendTelegram($CONFIG['telegram_bot_token'], $chatId,
                "💬 <b>Gozzy Group Chat Room</b>\n\n" .
                "Join the community: {$CONFIG['public_telegram']}"
            );
        } else {
            if ($CONFIG['gozzy_group_chat_id'] && $chatId != $CONFIG['gozzy_group_chat_id']) {
                $forwardText = "📩 <b>New message from {$firstName}</b>\n" .
                               "Chat ID: <code>{$chatId}</code>\n\n" . $text;
                sendTelegram($CONFIG['telegram_bot_token'], $CONFIG['gozzy_group_chat_id'], $forwardText);
            }
            sendTelegram($CONFIG['telegram_bot_token'], $chatId,
                "Thanks for your message! A Gozzy Group team member will get back to you shortly."
            );
        }
    }

    jsonResponse(['ok' => true]);
}

// ============================================================
// 4. CONTACT FORM
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_type']) && $_POST['form_type'] === 'contact') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    if (isRateLimited($ip, $CONFIG['rate_limit_seconds'], $CONFIG['rate_limit_file'])) {
        jsonResponse(['status' => 'error', 'message' => 'Too many requests. Please wait a minute.'], 429);
    }

    $name    = clean($_POST['name']    ?? '');
    $email   = clean($_POST['email']   ?? '');
    $company = clean($_POST['company'] ?? '');
    $service = clean($_POST['service'] ?? '');
    $budget  = clean($_POST['budget']  ?? '');
    $message = clean($_POST['message'] ?? '');

    if (empty($name) || empty($email) || empty($message)) {
        jsonResponse(['status' => 'error', 'message' => 'Please fill in all required fields.'], 400);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['status' => 'error', 'message' => 'Please enter a valid email address.'], 400);
    }

    // Email
    $body = "New Project Inquiry — Gozzy Group\n\n" .
            "Name:     {$name}\nEmail:    {$email}\n" .
            "Company:  {$company}\nService:  {$service}\n" .
            "Budget:   {$budget}\nIP:       {$ip}\n\n" .
            "Message:\n{$message}\n";
    $headers = "From: {$CONFIG['email_from']}\r\n" .
               "Reply-To: {$email}\r\n" .
               "Content-Type: text/plain; charset=UTF-8\r\n";
    @mail($CONFIG['email_to'], $CONFIG['email_subject'], $body, $headers);

    // Telegram
    sendTelegram($CONFIG['telegram_bot_token'], $CONFIG['telegram_chat_id'],
        "🚀 <b>New Project Inquiry — Gozzy Group</b>\n\n" .
        "<b>Name:</b> {$name}\n<b>Email:</b> {$email}\n" .
        "<b>Company:</b> " . ($company ?: '—') . "\n" .
        "<b>Service:</b> " . ($service ?: '—') . "\n" .
        "<b>Budget:</b> " . ($budget ?: '—') . "\n\n" .
        "<b>Message:</b>\n{$message}"
    );

    // Discord
    sendDiscord($CONFIG['discord_webhook_url'],
        "**🚀 New Project Inquiry — Gozzy Group**\n" .
        "**Name:** {$name}\n**Email:** {$email}\n" .
        "**Company:** " . ($company ?: '—') . "\n" .
        "**Service:** " . ($service ?: '—') . "\n" .
        "**Budget:** " . ($budget ?: '—') . "\n" .
        "**Message:** {$message}"
    );

    // Log
    logInquiry($CONFIG['log_file'], [
        'name' => $name, 'email' => $email, 'company' => $company,
        'service' => $service, 'budget' => $budget, 'message' => $message,
        'ip' => $ip, 'time' => date('c'),
    ]);

    jsonResponse([
        'status' => 'success',
        'message' => 'Project request received. Thanks for reaching out to Gozzy Group. We will review your project details and get back to you shortly.'
    ]);
}

// Default
jsonResponse(['status' => 'error', 'message' => 'Invalid request'], 400);
