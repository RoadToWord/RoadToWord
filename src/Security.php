<?php
declare(strict_types=1);

namespace App;

final class Security
{
    public static function csrfToken(): string
    {
        if (empty($_SESSION["_csrf_token"])) {
            $_SESSION["_csrf_token"] = bin2hex(random_bytes(32));
        }
        return (string) $_SESSION["_csrf_token"];
    }

    public static function assertCsrfForPost(): void
    {
        if ($_SERVER["REQUEST_METHOD"] !== "POST") {
            return;
        }
        $expected = (string) ($_SESSION["_csrf_token"] ?? "");
        $provided = (string) ($_POST["_csrf_token"] ?? "");
        if ($provided === "") {
            $provided = (string) ($_SERVER["HTTP_X_CSRF_TOKEN"] ?? "");
        }
        if ($expected === "" || !hash_equals($expected, $provided)) {
            http_response_code(400);
            echo "Invalid CSRF token.";
            exit;
        }
    }

    public static function verifyTurnstile(?string $responseToken, string $remoteIp): bool
    {
        $token = trim((string) $responseToken);
        if ($token === "") {
            return false;
        }
        $payload = http_build_query([
            "secret" => Config::turnstileSecret(),
            "response" => $token,
            "remoteip" => $remoteIp,
        ]);
        $ch = curl_init("https://challenges.cloudflare.com/turnstile/v0/siteverify");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        $raw = curl_exec($ch);
        curl_close($ch);
        if (!is_string($raw) || $raw === "") {
            return false;
        }
        $json = json_decode($raw, true);
        return is_array($json) && !empty($json["success"]);
    }
}
