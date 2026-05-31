<?php
require_once __DIR__ . '/config.php';

function sendSMS(string $mobile, string $message): array {
    $mobile = preg_replace('/\D+/', '', $mobile);
    if (strlen($mobile) === 10) $mobile = '63' . $mobile;
    elseif (strlen($mobile) === 11 && str_starts_with($mobile, '0')) $mobile = '63' . substr($mobile, 1);
    if (!str_starts_with($mobile, '+')) $mobile = '+' . $mobile;

    $ch = curl_init('https://skysms.skyio.site/api/v1/sms/send');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode(['phone_number' => $mobile, 'message' => $message]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER     => [
            'X-API-Key: ' . SKYSMS_API_KEY,
            'Content-Type: application/json',
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        return ['success' => false, 'message_id' => null, 'error' => $curlErr];
    }

    $decoded = json_decode($response, true);
    $isSuccess = $httpCode >= 200 && $httpCode < 300 && (
        ($decoded['status'] ?? '') === 'sent' ||
        ($decoded['success'] ?? false) === true ||
        isset($decoded['message_id'])
    );
    if ($isSuccess) {
        return ['success' => true, 'message_id' => $decoded['message_id'] ?? null, 'error' => null];
    }

    $errMsg = $decoded['message'] ?? $decoded['error'] ?? $response;
    return ['success' => false, 'message_id' => null, 'error' => "HTTP {$httpCode}: {$errMsg}"];
}

function generateAndSendOTP(string $mobile): bool {
    if (session_status() === PHP_SESSION_NONE) session_start();

    $otp = str_pad(random_int(0, (int)(10 ** OTP_LENGTH) - 1), OTP_LENGTH, '0', STR_PAD_LEFT);
    $_SESSION['otp_code']    = $otp;
    $_SESSION['otp_mobile']  = $mobile;
    $_SESSION['otp_expires'] = time() + OTP_EXPIRY_SECONDS;

    $message = "Your M.V. Masangkay Clinic verification code is: {$otp}. Valid for 5 minutes. Do not share this code.";
    $result  = sendSMS($mobile, $message);
    return $result['success'];
}

function verifyOTP(string $mobile, string $submitted): bool {
    if (session_status() === PHP_SESSION_NONE) session_start();

    if (empty($_SESSION['otp_code']) || empty($_SESSION['otp_mobile'])) return false;
    if ($_SESSION['otp_expires'] < time()) return false;

    $mobilesMatch = preg_replace('/\D+/', '', $mobile)
                 === preg_replace('/\D+/', '', $_SESSION['otp_mobile']);
    $codesMatch   = hash_equals($_SESSION['otp_code'], trim($submitted));

    if ($mobilesMatch && $codesMatch) {
        unset($_SESSION['otp_code'], $_SESSION['otp_mobile'], $_SESSION['otp_expires']);
        return true;
    }
    return false;
}
