<?php
define('JWT_SECRET', 'super_secret_expense_app_key_for_demo');

function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode($data) {
    return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', 3 - (3 + strlen($data)) % 4));
}

function jwt_sign($payload, $expiresInMinutes = 30) {
    $header = json_encode(['alg' => 'HS256', 'typ' => 'JWT']);
    
    $payload['exp'] = time() + ($expiresInMinutes * 60);
    $payload_json = json_encode($payload);
    
    $base64UrlHeader = base64url_encode($header);
    $base64UrlPayload = base64url_encode($payload_json);
    
    $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, JWT_SECRET, true);
    $base64UrlSignature = base64url_encode($signature);
    
    return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
}

function jwt_verify($token) {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;
    
    list($base64UrlHeader, $base64UrlPayload, $base64UrlSignature) = $parts;
    
    $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, JWT_SECRET, true);
    $expectedSignature = base64url_encode($signature);
    
    if (!hash_equals($expectedSignature, $base64UrlSignature)) {
        return null;
    }
    
    $payload = json_decode(base64url_decode($base64UrlPayload), true);
    
    if (isset($payload['exp']) && $payload['exp'] < time()) {
        return null; // Expired
    }
    
    return $payload;
}

function auth_middleware() {
    $headers = apache_request_headers();
    $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';
    
    if (!$authHeader && isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
    }

    if (strpos($authHeader, 'Bearer ') === 0) {
        $token = substr($authHeader, 7);
        $payload = jwt_verify($token);
        if ($payload) {
            return $payload;
        }
    }
    
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// polyfill for apache_request_headers
if (!function_exists('apache_request_headers')) {
    function apache_request_headers() {
        $arh = array();
        $rx_http = '/\AHTTP_/';
        foreach($_SERVER as $key => $val) {
            if(preg_match($rx_http, $key)) {
                $arh_key = preg_replace($rx_http, '', $key);
                $rx_matches = array();
                $rx_matches = explode('_', $arh_key);
                if(count($rx_matches) > 0 and strlen($arh_key) > 2) {
                    foreach($rx_matches as $ak_key => $ak_val) $rx_matches[$ak_key] = ucfirst(strtolower($ak_val));
                    $arh_key = implode('-', $rx_matches);
                }
                $arh[$arh_key] = $val;
            }
        }
        return $arh;
    }
}
