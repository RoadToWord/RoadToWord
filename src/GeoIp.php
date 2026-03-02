<?php
declare(strict_types=1);

namespace App;

final class GeoIp
{
    private static ?object $cityReader = null;
    private static ?object $asnReader = null;
    private static bool $initialized = false;

    public static function lookup(string $ip): array
    {
        self::initReaders();
        $country = null;
        $city = null;
        $asnOrg = null;
        $asnNumber = null;

        if (self::$cityReader) {
            try {
                $res = self::cityLookup(self::$cityReader, $ip);
                $country = $res["country"] ?? null;
                $city = $res["city"] ?? null;
            } catch (\Throwable $e) {
            }
        }
        if (self::$asnReader) {
            try {
                $res = self::asnLookup(self::$asnReader, $ip);
                $asnOrg = $res["org"] ?? null;
                $asnNumber = $res["asn"] ?? null;
            } catch (\Throwable $e) {
            }
        }

        return [
            "country" => $country,
            "city" => $city,
            "asn_org" => $asnOrg,
            "asn_number" => $asnNumber,
        ];
    }

    private static function initReaders(): void
    {
        if (self::$initialized) {
            return;
        }
        self::$initialized = true;
        $base = defined("BASE_DIR") ? BASE_DIR : dirname(__DIR__);
        $cityPath = $base . "/GeoLite2-City.mmdb";
        $asnPath = $base . "/GeoLite2-ASN.mmdb";

        if (is_file($cityPath)) {
            self::$cityReader = self::makeReader($cityPath);
        }
        if (is_file($asnPath)) {
            self::$asnReader = self::makeReader($asnPath);
        }
    }

    private static function makeReader(string $path): ?object
    {
        if (class_exists("\\GeoIp2\\Database\\Reader")) {
            return new \GeoIp2\Database\Reader($path);
        }
        if (!class_exists("\\MaxMind\\Db\\Reader")) {
            self::requireMaxMindReader();
        }
        if (class_exists("\\MaxMind\\Db\\Reader")) {
            return new \MaxMind\Db\Reader($path);
        }
        return null;
    }

    private static function requireMaxMindReader(): void
    {
        $base = defined("BASE_DIR") ? BASE_DIR : dirname(__DIR__);
        $readerPath = $base . "/vendor/maxmind-db-reader/MaxMind/Db/Reader.php";
        if (is_file($readerPath)) {
            require_once $readerPath;
            $dir = dirname($readerPath);
            $subs = [
                $dir . "/Reader/Decoder.php",
                $dir . "/Reader/InvalidDatabaseException.php",
                $dir . "/Reader/Metadata.php",
                $dir . "/Reader/Util.php",
                $dir . "/Reader/InvalidArgumentException.php",
            ];
            foreach ($subs as $file) {
                if (is_file($file)) {
                    require_once $file;
                }
            }
        }
    }

    private static function cityLookup(object $reader, string $ip): array
    {
        if ($reader instanceof \GeoIp2\Database\Reader) {
            $rec = $reader->city($ip);
            return [
                "country" => $rec->country->name ?: null,
                "city" => $rec->city->name ?: null,
            ];
        }
        if ($reader instanceof \MaxMind\Db\Reader) {
            $rec = $reader->get($ip);
            return [
                "country" => $rec["country"]["names"]["en"] ?? null,
                "city" => $rec["city"]["names"]["en"] ?? null,
            ];
        }
        return [];
    }

    private static function asnLookup(object $reader, string $ip): array
    {
        if ($reader instanceof \GeoIp2\Database\Reader) {
            $rec = $reader->asn($ip);
            return [
                "org" => $rec->autonomousSystemOrganization ?: null,
                "asn" => $rec->autonomousSystemNumber ?: null,
            ];
        }
        if ($reader instanceof \MaxMind\Db\Reader) {
            $rec = $reader->get($ip);
            return [
                "org" => $rec["autonomous_system_organization"] ?? null,
                "asn" => $rec["autonomous_system_number"] ?? null,
            ];
        }
        return [];
    }
}
