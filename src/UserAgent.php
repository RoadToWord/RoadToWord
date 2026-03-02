<?php
declare(strict_types=1);

namespace App;

final class UserAgent
{
    public static function parse(string $ua): array
    {
        $ua = trim($ua);
        if ($ua === "") {
            return ["browser" => null, "os" => null];
        }

        $uap = self::parseWithUap($ua);
        if ($uap !== null) {
            return $uap;
        }

        $browser = self::parseBrowser($ua);
        $os = self::parseOs($ua);

        return ["browser" => $browser, "os" => $os];
    }

    private static function parseWithUap(string $ua): ?array
    {
        $base = defined("BASE_DIR") ? BASE_DIR : dirname(__DIR__);
        $parserPath = $base . "/vendor/uap-php/Parser.php";
        if (!is_file($parserPath)) {
            return null;
        }
        foreach (glob($base . "/vendor/uap-php/*.php") ?: [] as $p) {
            require_once $p;
        }
        foreach (glob($base . "/vendor/uap-php/Result/*.php") ?: [] as $p) {
            require_once $p;
        }
        foreach (glob($base . "/vendor/uap-php/Exception/*.php") ?: [] as $p) {
            require_once $p;
        }
        if (class_exists("\\UAParser\\Parser")) {
            $regexFile = $base . "/vendor/uap-php/resources/regexes.php";
            if (property_exists("\\UAParser\\Parser", "defaultFile")) {
                \UAParser\Parser::$defaultFile = $regexFile;
            }
            try {
                $parser = \UAParser\Parser::create($regexFile);
                $client = $parser->parse($ua);
                $browser = trim((string) ($client->ua->family ?? ""));
                $browserVersion = trim((string) ($client->ua->toVersion() ?? ""));
                $os = trim((string) ($client->os->family ?? ""));
                $osVersion = trim((string) ($client->os->toVersion() ?? ""));
                $browserLabel = $browser !== "" ? $browser . ($browserVersion !== "" ? " (" . $browserVersion . ")" : "") : null;
                $osLabel = $os !== "" ? $os . ($osVersion !== "" ? " " . $osVersion : "") : null;
                return ["browser" => $browserLabel, "os" => $osLabel];
            } catch (\Throwable $e) {
                return null;
            }
        }
        return null;
    }

    private static function parseBrowser(string $ua): ?string
    {
        if (preg_match('/Edg\\/([0-9\\.]+)/', $ua, $m)) {
            return "Edge (" . $m[1] . ")";
        }
        if (preg_match('/Chrome\\/([0-9\\.]+)/', $ua, $m) && !str_contains($ua, "Edg/")) {
            return "Chrome (" . $m[1] . ")";
        }
        if (preg_match('/Firefox\\/([0-9\\.]+)/', $ua, $m)) {
            return "Firefox (" . $m[1] . ")";
        }
        if (preg_match('/Version\\/([0-9\\.]+).*Safari/', $ua, $m)) {
            return "Safari (" . $m[1] . ")";
        }
        if (preg_match('/OPR\\/([0-9\\.]+)/', $ua, $m)) {
            return "Opera (" . $m[1] . ")";
        }
        if (preg_match('/MSIE\\s([0-9\\.]+)/', $ua, $m)) {
            return "IE (" . $m[1] . ")";
        }
        if (preg_match('/Trident\\/.*rv:([0-9\\.]+)/', $ua, $m)) {
            return "IE (" . $m[1] . ")";
        }
        return null;
    }

    private static function parseOs(string $ua): ?string
    {
        if (preg_match('/Windows NT ([0-9\\.]+)/', $ua, $m)) {
            $ver = $m[1];
            return match ($ver) {
                "10.0" => "Windows 10/11",
                "6.3" => "Windows 8.1",
                "6.2" => "Windows 8",
                "6.1" => "Windows 7",
                "6.0" => "Windows Vista",
                "5.1" => "Windows XP",
                default => "Windows " . $ver,
            };
        }
        if (preg_match('/Mac OS X ([0-9_\\.]+)/', $ua, $m)) {
            $v = str_replace("_", ".", $m[1]);
            return "Mac OS X " . $v;
        }
        if (preg_match('/Android ([0-9\\.]+)/', $ua, $m)) {
            return "Android " . $m[1];
        }
        if (preg_match('/iPhone OS ([0-9_]+)/', $ua, $m)) {
            return "iOS " . str_replace("_", ".", $m[1]);
        }
        if (str_contains($ua, "Linux")) {
            return "Linux";
        }
        return null;
    }
}
