<?php
declare(strict_types=1);

namespace App;

use RuntimeException;

final class App
{
    private ?string $cspNonce = null;
    private ?array $cachedMeta = null;
    private bool $privacyColumnReady = false;
    private const QUIZ_LIVES_MAX = 5;
    private const QUIZ_LIVES_RESET_SECONDS = 900;
    private const QUIZ_MODES = ["turkish", "definition", "synonym", "antonym", "typing", "mixed", "timed", "review"];
    private const TIMED_MODE_SECONDS = 15;
    private const REVIEW_INTERVALS = [3600, 14400, 43200, 86400, 172800, 345600, 604800, 1209600, 2592000];
    private const ROAD_STREAK_FREEZE_COST = 20;
    private const ROAD_NAME_GLOW_COST = 35;
    private const ROAD_THEME_PACK_COST = 60;
    private const ROAD_CHAT_BUBBLE_COST = 30;
    private const ROAD_PROFILE_FRAME_CYAN_COST = 25;
    private const ROAD_PROFILE_FRAME_GOLD_COST = 40;
    private const ROAD_REFERRAL_BONUS = 25;
    private const DAILY_MISSION_QUESTIONS_TARGET = 30;
    private const DAILY_MISSION_QUESTIONS_REWARD = 15;
    private const DAILY_MISSION_STREAK_TARGET = 5;
    private const DAILY_MISSION_STREAK_REWARD = 10;
    private const WEEKLY_CHALLENGE_TARGET = 200;
    private const WEEKLY_CHALLENGE_REWARD = 40;

    public function run(): void
    {
        Security::assertCsrfForPost();
        $this->applySecurityHeaders();
        register_shutdown_function(function (): void {
            $this->logRequestAndMaybeBot();
        });
        $method = $_SERVER["REQUEST_METHOD"] ?? "GET";
        $uri = $this->requestPath();

        if ($uri === "/") {
            if ($method === "GET") {
                $start = $_GET["start"] ?? null;
                if ($start === "1") {
                    $user = $this->currentUser();
                    $meta = $user ? $this->ensureStudentMetaForUser($user) : null;
                    $roadBalanceText = $meta ? $this->roadBalanceText((int) ($meta["road_tokens"] ?? 0)) : null;
                    $teachers = Db::pdo()->query("SELECT id, username, full_name, role FROM user WHERE role='teacher' ORDER BY full_name, username")->fetchAll();
                    $modes = [
                        "turkish" => "Turkish meaning",
                        "definition" => "Definition",
                        "synonym" => "Synonym",
                        "antonym" => "Antonym",
                        "typing" => "Typing",
                        "mixed" => "Mixed",
                        "timed" => "Timed",
                        "review" => "Review",
                    ];
                    $this->render("quiz_start.php", [
                        "csrf_token" => Security::csrfToken(),
                        "turnstile_sitekey" => Config::turnstileSiteKey(),
                        "google_enabled" => $this->googleOauthEnabled(),
                        "road_balance_text" => $roadBalanceText,
                        "teachers" => $teachers,
                        "modes" => $modes,
                        "referrer_username" => (string) ($_GET["ref"] ?? ""),
                        "session_username" => $_SESSION["username"] ?? null,
                        "session_role" => $_SESSION["role"] ?? null,
                    ]);
                    return;
                }
                $this->render("start.php", ["welcome" => $this->getWelcomePayload()]);
                return;
            }
            if ($method === "POST") {
                $this->handleStartPracticePost();
                return;
            }
        }

        if ($uri === "/start/verify" && $method === "POST") {
            $this->redirect("/?start=1");
            return;
        }

        if ($uri === "/login") {
            if ($method === "GET") {
                $this->render("login.php", ["csrf_token" => Security::csrfToken(), "turnstile_sitekey" => Config::turnstileSiteKey(), "google_enabled" => $this->googleOauthEnabled()]);
                return;
            }
            if ($method === "POST") {
                $this->handleLoginPost();
                return;
            }
        }
        if ($uri === "/login/google" && ($method === "GET" || $method === "POST")) {
            $this->handleLoginGoogle();
            return;
        }
        if ($uri === "/auth/google/callback" && $method === "GET") {
            $this->handleGoogleCallback();
            return;
        }

        if ($uri === "/logout") {
            session_destroy();
            $this->redirect("/");
            return;
        }

        if (preg_match('#^/quiz/([^/]+)$#', $uri, $m) === 1) {
            $student = $m[1];
            if ($method === "GET") {
                $this->handleQuizGet($student);
                return;
            }
            if ($method === "POST") {
                $this->handleQuizPost($student);
                return;
            }
        }

        if ($uri === "/about") {
            $this->render("about.php", []);
            return;
        }
        if ($uri === "/sitemap.xml" && $method === "GET") {
            header("Content-Type: application/xml; charset=utf-8");
            echo '<?xml version="1.0" encoding="UTF-8"?>'
                . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
                . '<url><loc>' . htmlspecialchars($this->baseUrl() . "/", ENT_QUOTES) . '</loc></url>'
                . '<url><loc>' . htmlspecialchars($this->baseUrl() . "/words", ENT_QUOTES) . '</loc></url>'
                . '<url><loc>' . htmlspecialchars($this->baseUrl() . "/leaderboard", ENT_QUOTES) . '</loc></url>'
                . '</urlset>';
            return;
        }
        if ($uri === "/robots.txt" && $method === "GET") {
            header("Content-Type: text/plain; charset=utf-8");
            echo "User-agent: *\nAllow: /\nSitemap: " . $this->baseUrl() . "/sitemap.xml\n";
            return;
        }
        if ($uri === "/favicon.ico" && $method === "GET") {
            $icoRoot = BASE_DIR . "/favicon.ico";
            $ico = BASE_DIR . "/static/favicon.ico";
            $png = BASE_DIR . "/static/icon.png";
            if (is_file($icoRoot)) {
                header("Content-Type: image/x-icon");
                readfile($icoRoot);
                return;
            }
            if (is_file($ico)) {
                header("Content-Type: image/x-icon");
                readfile($ico);
                return;
            }
            if (is_file($png)) {
                header("Content-Type: image/png");
                readfile($png);
                return;
            }
            http_response_code(404);
            return;
        }
        if ($uri === "/manifest.json" && $method === "GET") {
            header("Content-Type: application/json; charset=utf-8");
            echo json_encode([
                "name" => "RoadToWord",
                "short_name" => "RoadToWord",
                "start_url" => "/",
                "id" => "/",
                "scope" => "/",
                "display" => "standalone",
                "theme_color" => "#38bdf8",
                "background_color" => "#0b1020",
                "icons" => [
                    [
                        "src" => "/static/icon-192.png",
                        "sizes" => "192x192",
                        "type" => "image/png",
                        "purpose" => "any maskable",
                    ],
                    [
                        "src" => "/static/icon-512.png",
                        "sizes" => "512x512",
                        "type" => "image/png",
                        "purpose" => "any maskable",
                    ],
                    [
                        "src" => "/static/icon.png",
                        "sizes" => "512x512",
                        "type" => "image/png",
                        "purpose" => "any",
                    ],
                ],
            ], JSON_UNESCAPED_SLASHES);
            return;
        }
        if ($uri === "/sw.js" && $method === "GET") {
            header("Content-Type: application/javascript; charset=utf-8");
            $swStatic = BASE_DIR . "/static/sw.js";
            if (is_file($swStatic)) {
                readfile($swStatic);
            } else {
                echo "self.addEventListener('install',()=>self.skipWaiting());self.addEventListener('activate',e=>e.waitUntil(self.clients.claim()));";
            }
            return;
        }
        if ($uri === "/api/server-date" && $method === "GET") {
            $this->json(["date" => gmdate("Y-m-d")]);
            return;
        }
        if ($uri === "/api/notification-state" && $method === "GET") {
            $user = $this->currentUser();
            if (!$user || (string) ($user["role"] ?? "") !== "student") {
                $this->json(["completed" => true]);
                return;
            }
            $meta = $this->studentMetaByUser($user);
            if (!$meta) {
                $this->json(["completed" => true]);
                return;
            }
            $meta = $this->ensureDailyRollover($meta);
            $dailyDone = (int) ($meta["daily_done"] ?? 0);
            $dailyTarget = (int) ($meta["daily_target"] ?? 0);
            $this->json([
                "daily_done" => $dailyDone,
                "daily_target" => $dailyTarget,
                "completed" => $dailyDone >= $dailyTarget,
            ]);
            return;
        }
        if ($uri === "/api/rabbit-feed" && $method === "POST") {
            $this->requireLogin();
            $this->handleRabbitFeedApi();
            return;
        }
        if ($uri === "/api/latest-alert" && $method === "GET") {
            $user = $this->currentUser();
            if (!$user) {
                $this->json(["messages" => []]);
                return;
            }
            $pdo = Db::pdo();
            $st = $pdo->prepare("SELECT id, message FROM notification WHERE user_id = :u AND is_read = 0 ORDER BY created_at ASC");
            $st->execute([":u" => (int) $user["id"]]);
            $rows = $st->fetchAll();
            if (!$rows) {
                $this->json(["messages" => []]);
                return;
            }
            $payload = [];
            foreach ($rows as $row) {
                if (!isset($row["id"]) || !isset($row["message"])) {
                    continue;
                }
                $payload[] = ["id" => $row["id"], "message" => $row["message"]];
            }
            $this->json(["messages" => $payload]);
            return;
        }
        if ($uri === "/api/latest-audit" && $method === "GET") {
            $user = $this->currentUser();
            if (!$user || (string) ($user["role"] ?? "") !== "admin") {
                $this->json(["id" => null, "message" => ""]);
                return;
            }
            $pdo = Db::pdo();
            $row = $pdo->query("SELECT id, action, entity_type, entity_id, details FROM audit_log ORDER BY created_at DESC LIMIT 1")->fetch();
            if (!$row) {
                $this->json(["id" => null, "message" => ""]);
                return;
            }
            $parts = array_filter([
                (string) ($row["action"] ?? ""),
                (string) ($row["entity_type"] ?? ""),
                (string) ($row["entity_id"] ?? ""),
            ], fn($v) => $v !== "");
            $msg = trim(implode(" ", $parts));
            $details = (string) ($row["details"] ?? "");
            if ($details !== "") {
                $msg = $msg !== "" ? ($msg . " | " . $details) : $details;
            }
            $this->json(["id" => $row["id"] ?? null, "message" => $msg]);
            return;
        }
        if ($uri === "/api/latest-message" && $method === "GET") {
            $user = $this->currentUser();
            if (!$user) {
                $this->json(["id" => null]);
                return;
            }
            $pdo = Db::pdo();
            $st = $pdo->prepare("SELECT id, from_user_id, content FROM `message` WHERE to_user_id = :uid ORDER BY created_at DESC LIMIT 1");
            $st->execute([":uid" => (int) $user["id"]]);
            $msg = $st->fetch();
            if (!$msg) {
                $this->json(["id" => null]);
                return;
            }
            $from = "@unknown";
            if (!empty($msg["from_user_id"])) {
                $sender = $this->findUserById((int) $msg["from_user_id"]);
                if ($sender && !empty($sender["username"])) {
                    $from = "@" . $sender["username"];
                }
            }
            $this->json([
                "id" => $msg["id"] ?? null,
                "from" => $from,
                "content" => $msg["content"] ?? "",
            ]);
            return;
        }
        if ($uri === "/api/push/subscribe" && $method === "POST") {
            $this->handlePushSubscribePost();
            return;
        }
        if ($uri === "/api/admin/test-push" && $method === "POST") {
            $this->requireAdmin();
            $this->handleAdminTestPushPost();
            return;
        }
        if ($uri === "/api/vocab-lite" && $method === "GET") {
            $pdo = Db::pdo();
            $rows = $pdo->query("SELECT word, turkish, level FROM vocab ORDER BY id DESC LIMIT 50")->fetchAll();
            $this->json(["ok" => true, "items" => $rows]);
            return;
        }
        if ($uri === "/tos" && $method === "GET") {
            $this->render("tos.php", []);
            return;
        }
        if ($uri === "/privpolicy" && $method === "GET") {
            $this->render("privpolicy.php", []);
            return;
        }
        if ($uri === "/donate" && $method === "GET") {
            $this->render("donate.php", []);
            return;
        }
        if ($uri === "/coffee" && $method === "GET") {
            $this->render("coffee.php", []);
            return;
        }
        if ($uri === "/rabbit" && $method === "GET") {
            $user = $this->currentUser();
            $meta = $user ? $this->ensureStudentMetaForUser($user) : null;
            $today = gmdate("Y-m-d");
            $alreadyFed = is_array($meta) && ((string) ($meta["rabbit_last_fed_day"] ?? "")) === $today;
            $this->render("rabbit.php", [
                "user" => $user,
                "meta" => $meta,
                "already_fed" => $alreadyFed,
            ]);
            return;
        }
        if ($uri === "/words" && $method === "GET") {
            $this->handleWordsGet();
            return;
        }
        if (preg_match('#^/word/([^/]+)$#', $uri, $m) === 1 && $method === "GET") {
            $this->handleWordGet($m[1]);
            return;
        }

        if ($uri === "/account") {
            $this->requireLogin();
            if ($method === "GET") {
                $this->handleAccountGet();
                return;
            }
            if ($method === "POST") {
                $this->handleAccountPost();
                return;
            }
        }
        if ($uri === "/account/delete" && $method === "POST") {
            $this->requireLogin();
            $this->redirect("/account/delete/confirm");
            return;
        }
        if ($uri === "/account/delete/confirm") {
            $this->requireLogin();
            if ($method === "GET") {
                $this->handleAccountDeleteConfirmGet(null);
                return;
            }
            if ($method === "POST") {
                $this->handleAccountDeleteConfirmPost();
                return;
            }
        }

        if ($uri === "/shop") {
            $this->requireLogin();
            if ($method === "GET") {
                $this->handleShopGet();
                return;
            }
            if ($method === "POST") {
                $this->handleShopPost();
                return;
            }
        }

        if ($uri === "/leaderboard" && $method === "GET") {
            $this->handleLeaderboardGet();
            return;
        }

        if ($uri === "/admin/broadcast") {
            $this->requireLogin();
            $this->requireAdmin();
            if ($method === "GET") {
                $this->handleAdminBroadcastGet();
                return;
            }
            if ($method === "POST") {
                $this->handleAdminBroadcastPost();
                return;
            }
        }
        if ($uri === "/admin/audit" && $method === "GET") {
            $this->requireLogin();
            $this->requireAdmin();
            $this->handleAdminAuditGet();
            return;
        }
        if ($uri === "/admin/logs" && $method === "GET") {
            $this->requireLogin();
            $this->requireAdmin();
            $this->handleAdminLogsGet();
            return;
        }
        if ($uri === "/admin/keywords" && $method === "GET") {
            $this->requireLogin();
            $this->requireAdmin();
            $this->handleAdminKeywordsGet();
            return;
        }
        if ($uri === "/admin/keywords/new") {
            $this->requireLogin();
            $this->requireAdmin();
            if ($method === "GET") {
                $this->handleAdminKeywordFormGet("new", null);
                return;
            }
            if ($method === "POST") {
                $this->handleAdminKeywordNewPost();
                return;
            }
        }
        if ($uri === "/admin/keywords/import") {
            $this->requireLogin();
            $this->requireAdmin();
            if ($method === "GET") {
                $this->handleAdminKeywordImportGet(null, 0);
                return;
            }
            if ($method === "POST") {
                $this->handleAdminKeywordImportPost();
                return;
            }
        }
        if (preg_match('#^/admin/keywords/(\d+)/edit$#', $uri, $m) === 1) {
            $this->requireLogin();
            $this->requireAdmin();
            if ($method === "GET") {
                $this->handleAdminKeywordFormGet("edit", (int) $m[1]);
                return;
            }
            if ($method === "POST") {
                $this->handleAdminKeywordEditPost((int) $m[1]);
                return;
            }
        }
        if (preg_match('#^/admin/keywords/(\d+)/delete$#', $uri, $m) === 1 && $method === "POST") {
            $this->requireLogin();
            $this->requireAdmin();
            $this->handleAdminKeywordDeletePost((int) $m[1]);
            return;
        }
        if ($uri === "/admin/vocab" && $method === "GET") {
            $this->requireLogin();
            $this->requireAdmin();
            $this->handleAdminVocabListGet();
            return;
        }
        if ($uri === "/admin/vocab/new") {
            $this->requireLogin();
            $this->requireAdmin();
            if ($method === "GET") {
                $this->handleAdminVocabFormGet("new", null);
                return;
            }
            if ($method === "POST") {
                $this->handleAdminVocabNewPost();
                return;
            }
        }
        if (preg_match('#^/admin/vocab/(\d+)/edit$#', $uri, $m) === 1) {
            $this->requireLogin();
            $this->requireAdmin();
            if ($method === "GET") {
                $this->handleAdminVocabFormGet("edit", (int) $m[1]);
                return;
            }
            if ($method === "POST") {
                $this->handleAdminVocabEditPost((int) $m[1]);
                return;
            }
        }
        if (preg_match('#^/admin/vocab/(\d+)/delete$#', $uri, $m) === 1 && $method === "POST") {
            $this->requireLogin();
            $this->requireAdmin();
            $this->handleAdminVocabDeletePost((int) $m[1]);
            return;
        }
        if ($uri === "/admin/vocab/export" && $method === "GET") {
            $this->requireLogin();
            $this->requireAdmin();
            $this->handleAdminVocabExportGet();
            return;
        }
        if ($uri === "/admin/vocab/import") {
            $this->requireLogin();
            $this->requireAdmin();
            if ($method === "GET") {
                $this->handleAdminVocabImportGet(null);
                return;
            }
            if ($method === "POST") {
                $this->handleAdminVocabImportPost();
                return;
            }
        }
        if ($uri === "/admin/packs") {
            $this->requireLogin();
            $this->requireAdmin();
            if ($method === "GET") {
                $this->handleAdminPacksGet();
                return;
            }
            if ($method === "POST") {
                $this->handleAdminPacksPost();
                return;
            }
        }
        if ($uri === "/admin/export-all" && $method === "GET") {
            $this->requireLogin();
            $this->requireAdmin();
            $this->handleAdminExportAllGet();
            return;
        }
        if (preg_match('#^/share/([^/]+)/csv$#', $uri, $m) === 1 && $method === "GET") {
            $this->handleShareProgressCsvGet($m[1]);
            return;
        }
        if (preg_match('#^/share/([^/]+)$#', $uri, $m) === 1 && $method === "GET") {
            $this->handleShareProgressGet($m[1]);
            return;
        }
        if ($uri === "/admin/users" && $method === "GET") {
            $this->requireLogin();
            $this->requireAdmin();
            $this->handleAdminUsersGet();
            return;
        }
        if (preg_match('#^/admin/users/(\d+)/verify$#', $uri, $m) === 1 && $method === "POST") {
            $this->requireLogin();
            $this->requireAdmin();
            $this->handleAdminUserVerifyPost((int) $m[1]);
            return;
        }
        if (preg_match('#^/admin/users/(\d+)/grant-road$#', $uri, $m) === 1 && $method === "POST") {
            $this->requireLogin();
            $this->requireAdmin();
            $this->handleAdminUserGrantRoadPost((int) $m[1]);
            return;
        }
        if ($uri === "/admin/users/new") {
            $this->requireLogin();
            $this->requireAdmin();
            if ($method === "GET") {
                $this->handleAdminUserFormGet("new", null);
                return;
            }
            if ($method === "POST") {
                $this->handleAdminUserNewPost();
                return;
            }
        }
        if (preg_match('#^/admin/users/(\d+)/edit$#', $uri, $m) === 1) {
            $this->requireLogin();
            $this->requireAdmin();
            if ($method === "GET") {
                $this->handleAdminUserFormGet("edit", (int) $m[1]);
                return;
            }
            if ($method === "POST") {
                $this->handleAdminUserEditPost((int) $m[1]);
                return;
            }
        }
        if (preg_match('#^/admin/users/(\d+)/delete$#', $uri, $m) === 1 && $method === "POST") {
            $this->requireLogin();
            $this->requireAdmin();
            $this->handleAdminUserDeletePost((int) $m[1]);
            return;
        }
        if ($uri === "/admin" && $method === "GET") {
            $this->requireLogin();
            $this->requireAdmin();
            $this->handleAdminHome();
            return;
        }
        if ($uri === "/admin/maintenance") {
            $this->requireLogin();
            $this->requireAdmin();
            if ($method === "GET") {
                $this->handleAdminMaintenanceGet();
                return;
            }
            if ($method === "POST") {
                $this->handleAdminMaintenancePost();
                return;
            }
        }
        if ($uri === "/admin/manage-server") {
            $this->requireLogin();
            $this->requireAdmin();
            if ($method === "GET") {
                $this->handleAdminManageServerGet();
                return;
            }
            if ($method === "POST") {
                $this->handleAdminManageServerPost();
                return;
            }
        }
        if ($uri === "/admin/students" && $method === "GET") {
            $this->requireLogin();
            $this->requireAdmin();
            $this->handleAdminStudentsGet();
            return;
        }
        if (preg_match('#^/admin/students/([^/]+)/edit$#', $uri, $m) === 1) {
            $this->requireLogin();
            $this->requireAdmin();
            if ($method === "GET") {
                $this->handleAdminStudentEditGet($m[1], null);
                return;
            }
            if ($method === "POST") {
                $this->handleAdminStudentEditPost($m[1]);
                return;
            }
        }
        if (preg_match('#^/admin/students/([^/]+)/reset$#', $uri, $m) === 1 && $method === "POST") {
            $this->requireLogin();
            $this->requireAdmin();
            $this->handleAdminStudentResetPost($m[1]);
            return;
        }
        if ($uri === "/admin/students/reset_all" && $method === "POST") {
            $this->requireLogin();
            $this->requireAdmin();
            $this->handleAdminStudentsResetAllPost();
            return;
        }
        if ($uri === "/admin/students/purge_all" && $method === "POST") {
            $this->requireLogin();
            $this->requireAdmin();
            $this->handleAdminStudentsPurgeAllPost();
            return;
        }
        if ($uri === "/dashboard" && $method === "GET") {
            $this->requireLogin();
            $this->handleDashboardGet();
            return;
        }
        if ($uri === "/classrooms") {
            $this->requireLogin();
            $this->requireAdmin();
            if ($method === "GET") {
                $this->handleClassroomsGet();
                return;
            }
            if ($method === "POST") {
                $this->handleClassroomsPost();
                return;
            }
        }
        if (preg_match('#^/classrooms/(\d+)$#', $uri, $m) === 1) {
            $this->requireLogin();
            $this->requireAdmin();
            if ($method === "GET") {
                $this->handleClassroomDetailGet((int) $m[1]);
                return;
            }
            if ($method === "POST") {
                $this->handleClassroomDetailPost((int) $m[1]);
                return;
            }
        }
        if (preg_match('#^/classrooms/(\d+)/dashboard$#', $uri, $m) === 1 && $method === "GET") {
            $this->requireLogin();
            $this->requireAdmin();
            $this->handleClassroomDashboardGet((int) $m[1]);
            return;
        }
        if ($uri === "/start" && $method === "GET") {
            $this->redirect("/?start=1");
            return;
        }
        if ($uri === "/offline" && $method === "GET") {
            $this->render("offline.php", []);
            return;
        }
        if ($uri === "/credits" && $method === "GET") {
            $this->render("credits_page.php", []);
            return;
        }
        if ($uri === "/under-construction" && $method === "GET") {
            $this->render("under_construction.php", []);
            return;
        }
        if ($uri === "/suspended" && $method === "GET") {
            $this->render("suspended_page.php", []);
            return;
        }
        if ($uri === "/destroyed" && $method === "GET") {
            $this->render("destroyed_page.php", []);
            return;
        }
        if ($uri === "/about-dev" && $method === "GET") {
            $this->render("about_dev.php", []);
            return;
        }
        if ($uri === "/contact" && $method === "GET") {
            $this->render("contact.php", []);
            return;
        }    
        if ($uri === "/404" && $method === "GET") {
            http_response_code(404);
            $this->render("404.php", []);
            return;
        }
        if ($uri === "/400" && $method === "GET") {
            http_response_code(400);
            $this->render("400.php", []);
            return;
        }
         if ($uri === "/401" && $method === "GET") {
            http_response_code(401);
            $this->render("401.php", []);
            return;
        }
          if ($uri === "/403" && $method === "GET") {
            http_response_code(403);
            $this->render("403.php", []);
            return;
        }
         if ($uri === "/503" && $method === "GET") {
            http_response_code(503);
            $this->render("503.php", []);
            return;
        }         
        if ($uri === "/google54f81d3509f0cda8.html" && $method === "GET") {
            header("Content-Type: text/plain; charset=utf-8");
            echo "google-site-verification: google54f81d3509f0cda8.html";
            return;
        }
        if ($uri === "/static/credits.txt" && $method === "GET") {
            $p = BASE_DIR . "/static/credits.txt";
            if (is_file($p)) {
                header("Content-Type: text/plain; charset=utf-8");
                readfile($p);
                return;
            }
            http_response_code(404);
            return;
        }

        if ($uri === "/notifications") {
            $this->requireLogin();
            if ($method === "POST") {
                $this->markNotificationsRead();
            }
            $this->handleNotificationsGet();
            return;
        }

        if ($uri === "/users" && $method === "GET") {
            $this->requireLogin();
            $this->handleUsersGet();
            return;
        }

        if (preg_match('#^/@([^/]+)$#', $uri, $m) === 1 && $method === "GET") {
            $this->handleProfileGet($m[1]);
            return;
        }

        if ($uri === "/friends" && $method === "GET") {
            $this->requireLogin();
            $this->handleFriendsGet();
            return;
        }
        if ($uri === "/friends/request" && $method === "POST") {
            $this->requireLogin();
            $this->handleFriendRequestPost();
            return;
        }
        if ($uri === "/friends/accept" && $method === "POST") {
            $this->requireLogin();
            $this->handleFriendAcceptPost();
            return;
        }
        if ($uri === "/friends/decline" && $method === "POST") {
            $this->requireLogin();
            $this->handleFriendDeclinePost();
            return;
        }
        if ($uri === "/friends/remove" && $method === "POST") {
            $this->requireLogin();
            $this->handleFriendRemovePost();
            return;
        }
        if ($uri === "/friends/grant-badge" && $method === "POST") {
            $this->requireLogin();
            $this->handleFriendGrantBadgePost();
            return;
        }

        if ($uri === "/messages" && $method === "GET") {
            $this->requireLogin();
            $this->redirect("/friends");
            return;
        }

        if (preg_match('#^/messages/([^/]+)$#', $uri, $m) === 1) {
            $this->requireLogin();
            if ($method === "GET") {
                $this->handleMessagesGet($m[1]);
                return;
            }
            if ($method === "POST") {
                $this->handleMessagesPost($m[1]);
                return;
            }
        }
        if (preg_match('#^/call/([^/]+)$#', $uri, $m) === 1 && $method === "GET") {
            $this->requireLogin();
            $this->handleCallGet($m[1]);
            return;
        }
        if (preg_match('#^/messages/attachment/(\d+)$#', $uri, $m) === 1 && $method === "GET") {
            $this->requireLogin();
            $this->handleMessageAttachmentGet((int) $m[1]);
            return;
        }

        http_response_code(404);
        $this->render("404.php", []);
    }

    private function handleLoginPost(): void
    {
        $ok = Security::verifyTurnstile($_POST["cf-turnstile-response"] ?? null, $this->clientIp());
        if (!$ok) {
            $this->render("login.php", [
                "csrf_token" => Security::csrfToken(),
                "turnstile_sitekey" => Config::turnstileSiteKey(),
                "google_enabled" => $this->googleOauthEnabled(),
                "error" => "Invalid CAPTCHA.",
            ]);
            return;
        }
        $username = trim((string) ($_POST["username"] ?? ""));
        $password = (string) ($_POST["password"] ?? "");
        $pdo = Db::pdo();
        $st = $pdo->prepare("SELECT id, username, role, password FROM user WHERE username = :u LIMIT 1");
        $st->execute([":u" => $username]);
        $user = $st->fetch();
        if (!$user || (string) ($user["password"] ?? "") !== $password) {
            $this->render("login.php", [
                "csrf_token" => Security::csrfToken(),
                "turnstile_sitekey" => Config::turnstileSiteKey(),
                "google_enabled" => $this->googleOauthEnabled(),
                "error" => "Invalid username or password.",
            ]);
            return;
        }
        $_SESSION["username"] = (string) $user["username"];
        $_SESSION["role"] = (string) $user["role"];
        $fresh = $this->currentUser();
        if ($fresh) {
            $this->sendBotIntroIfNeeded($fresh);
        }
        $this->redirect("/?start=1");
    }

    private function googleOauthEnabled(): bool
    {
        return trim(Config::googleClientId()) !== "" && trim(Config::googleClientSecret()) !== "" && trim(Config::googleRedirectUri()) !== "";
    }

    private function handleLoginGoogle(): void
    {
        if (!$this->googleOauthEnabled()) {
            $this->render("login.php", [
                "csrf_token" => Security::csrfToken(),
                "turnstile_sitekey" => Config::turnstileSiteKey(),
                "google_enabled" => false,
                "error" => "Google login is not configured.",
            ]);
            return;
        }
        if (($_SERVER["REQUEST_METHOD"] ?? "GET") !== "POST") {
            $this->render("login.php", [
                "csrf_token" => Security::csrfToken(),
                "turnstile_sitekey" => Config::turnstileSiteKey(),
                "google_enabled" => true,
                "error" => "Please use the Google button after completing CAPTCHA.",
            ]);
            return;
        }
        $ok = Security::verifyTurnstile($_POST["cf-turnstile-response"] ?? null, $this->clientIp());
        if (!$ok) {
            $this->render("login.php", [
                "csrf_token" => Security::csrfToken(),
                "turnstile_sitekey" => Config::turnstileSiteKey(),
                "google_enabled" => true,
                "error" => "Invalid CAPTCHA.",
            ]);
            return;
        }
        $agreeRaw = (string) (($_POST["agree"] ?? "") ?: ($_GET["agree"] ?? ""));
        $_SESSION["google_signup_consent"] = ($agreeRaw === "1");
        $state = bin2hex(random_bytes(24));
        $states = $_SESSION["google_oauth_states"] ?? [];
        if (!is_array($states)) {
            $states = [];
        }
        $states[] = $state;
        $_SESSION["google_oauth_states"] = array_slice($states, -10);
        $_SESSION["google_oauth_state"] = $state;
        $params = http_build_query([
            "client_id" => Config::googleClientId(),
            "redirect_uri" => Config::googleRedirectUri(),
            "response_type" => "code",
            "scope" => "openid email profile",
            "state" => $state,
            "prompt" => "select_account",
        ]);
        $this->redirect("https://accounts.google.com/o/oauth2/v2/auth?" . $params);
    }

    private function handleGoogleCallback(): void
    {
        if (!$this->googleOauthEnabled()) {
            $this->redirect("/login");
            return;
        }
        $providerError = trim((string) ($_GET["error"] ?? ""));
        if ($providerError !== "") {
            $this->render("login.php", [
                "csrf_token" => Security::csrfToken(),
                "turnstile_sitekey" => Config::turnstileSiteKey(),
                "google_enabled" => true,
                "error" => "Google login failed (" . $providerError . ").",
            ]);
            return;
        }
        $state = (string) ($_GET["state"] ?? "");
        $expectedStates = $_SESSION["google_oauth_states"] ?? [];
        if (!is_array($expectedStates)) { $expectedStates = []; }
        $single = (string) ($_SESSION["google_oauth_state"] ?? "");
        $valid = ($state !== "") && (in_array($state, $expectedStates, true) || ($single !== "" && $single === $state));
        if (!$valid) {
            $this->render("login.php", [
                "csrf_token" => Security::csrfToken(),
                "turnstile_sitekey" => Config::turnstileSiteKey(),
                "google_enabled" => true,
                "error" => "Google login failed (state mismatch).",
            ]);
            return;
        }
        $_SESSION["google_oauth_states"] = array_values(array_filter($expectedStates, fn($s) => $s !== $state));
        if ($single === $state) {
            unset($_SESSION["google_oauth_state"]);
        }
        $code = (string) ($_GET["code"] ?? "");
        if ($code === "") {
            $this->render("login.php", [
                "csrf_token" => Security::csrfToken(),
                "turnstile_sitekey" => Config::turnstileSiteKey(),
                "google_enabled" => true,
                "error" => "Google login failed (missing code).",
            ]);
            return;
        }

        try {
            $token = $this->googleFetchJson(
                "https://oauth2.googleapis.com/token",
                [
                    "code" => $code,
                    "client_id" => Config::googleClientId(),
                    "client_secret" => Config::googleClientSecret(),
                    "redirect_uri" => Config::googleRedirectUri(),
                    "grant_type" => "authorization_code",
                ],
                null
            );
            $accessToken = (string) ($token["access_token"] ?? "");
            if ($accessToken === "") {
                throw new \RuntimeException("token");
            }
            $profile = $this->googleFetchJson(
                "https://www.googleapis.com/oauth2/v3/userinfo",
                null,
                ["Authorization: Bearer " . $accessToken]
            );
            $email = strtolower(trim((string) ($profile["email"] ?? "")));
            $fullName = trim((string) ($profile["name"] ?? ""));
            if ($email === "") {
                throw new \RuntimeException("email");
            }
        } catch (\Throwable $e) {
            $this->render("login.php", [
                "csrf_token" => Security::csrfToken(),
                "turnstile_sitekey" => Config::turnstileSiteKey(),
                "google_enabled" => true,
                "error" => "Google login failed (provider error).",
            ]);
            return;
        }

        $pdo = Db::pdo();
        $st = $pdo->prepare("SELECT * FROM user WHERE LOWER(email) = LOWER(:e) LIMIT 1");
        $st->execute([":e" => $email]);
        $user = $st->fetch();

        try {
            if (!$user) {
                $agreed = !empty($_SESSION["google_signup_consent"]);
                unset($_SESSION["google_signup_consent"]);
                if (!$agreed) {
                    $this->render("login.php", [
                        "csrf_token" => Security::csrfToken(),
                        "turnstile_sitekey" => Config::turnstileSiteKey(),
                        "google_enabled" => true,
                        "error" => "Please accept Terms of Service and Privacy Policy before signing up.",
                    ]);
                    return;
                }
                $username = $this->buildGoogleUsername($email, $fullName);
                $student = $this->findStudentByName($username);
                if (!$student) {
                    $insS = $pdo->prepare("INSERT INTO student (name, created_at) VALUES (:n, UTC_TIMESTAMP())");
                    $insS->execute([":n" => $username]);
                    $student = $this->findStudentById((int) $pdo->lastInsertId());
                }
                $insU = $pdo->prepare("INSERT INTO user (username, password, email, full_name, role, student_id) VALUES (:u, NULL, :e, :f, 'student', :sid)");
                $insU->execute([
                    ":u" => $username,
                    ":e" => $email,
                    ":f" => ($fullName !== "" ? $fullName : $username),
                    ":sid" => $student ? (int) $student["id"] : 0,
                ]);
                $user = $this->findUserById((int) $pdo->lastInsertId());
                if ($user && !empty($user["student_id"])) {
                    $this->ensureStudentMetaByStudentId((int) $user["student_id"]);
                    $this->createNotification((int) $user["id"], "Welcome! Your learner account has been created.", "info");
                    $this->sendBotIntroIfNeeded($user);
                }
            } else {
                $up = $pdo->prepare("UPDATE user SET full_name = :f WHERE id = :id");
                $up->execute([":f" => ($fullName !== "" ? $fullName : (string) ($user["full_name"] ?? "")), ":id" => (int) $user["id"]]);
                $user = $this->findUserById((int) $user["id"]);
                if ($user) {
                    $this->sendBotIntroIfNeeded($user);
                }
            }
        } catch (\Throwable $e) {
            $this->render("login.php", [
                "csrf_token" => Security::csrfToken(),
                "turnstile_sitekey" => Config::turnstileSiteKey(),
                "google_enabled" => true,
                "error" => "Google login failed (account sync).",
            ]);
            return;
        }

        if (!$user) {
            $this->render("login.php", [
                "csrf_token" => Security::csrfToken(),
                "turnstile_sitekey" => Config::turnstileSiteKey(),
                "google_enabled" => true,
                "error" => "Google login failed (account sync).",
            ]);
            return;
        }
        $_SESSION["username"] = (string) $user["username"];
        $_SESSION["role"] = (string) $user["role"];
        $this->redirect(((string) ($user["role"] ?? "")) === "student" ? "/" : "/dashboard");
    }

    private function googleFetchJson(string $url, ?array $postFields = null, ?array $headers = null): array
    {
        if (function_exists("curl_init")) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 12);
            if ($headers) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            }
            if ($postFields !== null) {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($headers ?? [], ["Content-Type: application/x-www-form-urlencoded"]));
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
            }
            $resp = curl_exec($ch);
            if ($resp === false) {
                $err = curl_error($ch);
                curl_close($ch);
                throw new \RuntimeException($err);
            }
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);
            if ($status >= 400) {
                throw new \RuntimeException("HTTP " . $status);
            }
            $parsed = json_decode((string) $resp, true);
            if (!is_array($parsed)) {
                throw new \RuntimeException("Invalid JSON");
            }
            return $parsed;
        }
        $opts = ["http" => ["method" => $postFields !== null ? "POST" : "GET", "timeout" => 12]];
        $hdrs = $headers ?? [];
        if ($postFields !== null) {
            $hdrs[] = "Content-Type: application/x-www-form-urlencoded";
            $opts["http"]["content"] = http_build_query($postFields);
        }
        if ($hdrs) {
            $opts["http"]["header"] = implode("\r\n", $hdrs);
        }
        $ctx = stream_context_create($opts);
        $resp = @file_get_contents($url, false, $ctx);
        if ($resp === false) {
            throw new \RuntimeException("request");
        }
        $parsed = json_decode((string) $resp, true);
        if (!is_array($parsed)) {
            throw new \RuntimeException("Invalid JSON");
        }
        return $parsed;
    }

    private function buildGoogleUsername(string $email, string $fullName): string
    {
        $base = "";
        if ($fullName !== "") {
            $base = preg_replace('/[^A-Za-z0-9]+/', '', $fullName) ?? "";
        }
        if ($base === "") {
            $base = preg_replace('/[^A-Za-z0-9]+/', '', strstr($email, "@", true) ?: $email) ?? "user";
        }
        if ($base === "") {
            $base = "user";
        }
        $base = substr($base, 0, 24);
        $candidate = $base;
        $i = 1;
        while ($this->findUserByUsername($candidate) !== null) {
            $i++;
            $suffix = (string) $i;
            $candidate = substr($base, 0, max(1, 24 - strlen($suffix))) . $suffix;
        }
        return $candidate;
    }

    private function handleStartPracticePost(): void
    {
        $sessionUser = $_SESSION["username"] ?? null;
        if (!$sessionUser) {
            $ok = Security::verifyTurnstile($_POST["cf-turnstile-response"] ?? null, $this->clientIp());
            if (!$ok) {
                $this->render("quiz_start.php", [
                    "csrf_token" => Security::csrfToken(),
                    "turnstile_sitekey" => Config::turnstileSiteKey(),
                    "google_enabled" => $this->googleOauthEnabled(),
                    "error" => "Invalid CAPTCHA.",
                    "modes" => array_combine(self::QUIZ_MODES, array_map('ucfirst', self::QUIZ_MODES)),
                ]);
                return;
            }
        }
        $student = $sessionUser ? (string) $sessionUser : trim((string) ($_POST["student"] ?? ""));
        $password = (string) ($_POST["password"] ?? "");
        $mode = $this->normalizeQuizMode((string) ($_POST["mode"] ?? "turkish"));
        if ($student === "" || (!$sessionUser && $password === "")) {
            $this->render("quiz_start.php", [
                "csrf_token" => Security::csrfToken(),
                "turnstile_sitekey" => Config::turnstileSiteKey(),
                "google_enabled" => $this->googleOauthEnabled(),
                "error" => "Username and password are required.",
                "modes" => array_combine(self::QUIZ_MODES, array_map('ucfirst', self::QUIZ_MODES)),
            ]);
            return;
        }
        if (!$sessionUser) {
            $consent = (string) ($_POST["signup_consent"] ?? "");
            if ($consent !== "on" && $consent !== "1") {
                $this->render("quiz_start.php", [
                    "csrf_token" => Security::csrfToken(),
                    "turnstile_sitekey" => Config::turnstileSiteKey(),
                    "google_enabled" => $this->googleOauthEnabled(),
                    "error" => "Please agree to the Terms of Service and Privacy Policy.",
                    "modes" => array_combine(self::QUIZ_MODES, array_map('ucfirst', self::QUIZ_MODES)),
                ]);
                return;
            }
        }
        $pdo = Db::pdo();
        $st = $pdo->prepare("SELECT id, username, password, role FROM user WHERE username = :u LIMIT 1");
        $st->execute([":u" => $student]);
        $user = $st->fetch();
        $created = false;
        if (!$user) {
            $fullName = trim((string) ($_POST["full_name"] ?? ""));
            $email = trim((string) ($_POST["email"] ?? ""));
            $teacherId = (string) ($_POST["teacher_id"] ?? "");
            if ($fullName === "") {
                $this->render("quiz_start.php", [
                    "csrf_token" => Security::csrfToken(),
                    "turnstile_sitekey" => Config::turnstileSiteKey(),
                    "google_enabled" => $this->googleOauthEnabled(),
                    "error" => "Full name is required.",
                ]);
                return;
            }
            $cols = "username, password, role, full_name, email";
            $vals = ":u, :p, 'student', :f, :e";
            if ($teacherId === "individual") {
                $cols .= ", teacher_id";
                $vals .= ", NULL";
            } elseif (ctype_digit($teacherId)) {
                $cols .= ", teacher_id";
                $vals .= ", :tid";
            }
            $ins = $pdo->prepare("INSERT INTO user ({$cols}) VALUES ({$vals})");
            $params = [":u" => $student, ":p" => $password, ":f" => $fullName, ":e" => ($email !== "" ? $email : null)];
            if (ctype_digit($teacherId)) {
                $params[":tid"] = (int) $teacherId;
            }
            $ins->execute($params);
            $created = true;
        } elseif (!$sessionUser && (string) ($user["password"] ?? "") !== $password) {
            $this->render("quiz_start.php", [
                "csrf_token" => Security::csrfToken(),
                "turnstile_sitekey" => Config::turnstileSiteKey(),
                "google_enabled" => $this->googleOauthEnabled(),
                "error" => "Invalid password.",
            ]);
            return;
        }
        $_SESSION["username"] = $student;
        $_SESSION["role"] = "student";
        if ($created) {
            $fresh = $this->currentUser();
            if ($fresh) {
                $this->sendBotIntroIfNeeded($fresh);
                $this->maybeApplyReferralBonus($fresh, (string) ($_POST["referrer_username"] ?? ""));
            }
        }
        $this->redirect("/quiz/" . rawurlencode($student) . "?mode=" . rawurlencode($mode));
    }

    private function handleQuizGet(string $student): void
    {
        if (($this->sessionUsername() ?? "") !== $student) {
            $this->redirect("/login");
            return;
        }
        $user = $this->currentUser();
        $pdo = Db::pdo();
        $st = $pdo->prepare("SELECT * FROM student WHERE name = :n LIMIT 1");
        $st->execute([":n" => $student]);
        $studentRow = $st->fetch();
        if (!$studentRow) {
            $pdo->prepare("INSERT INTO student (name, created_at) VALUES (:n, UTC_TIMESTAMP())")->execute([":n" => $student]);
            $studentId = (int) $pdo->lastInsertId();
        } else {
            $studentId = (int) ($studentRow["id"] ?? 0);
        }
        $meta = $user ? $this->ensureStudentMetaForUser($user) : $this->ensureStudentMetaByStudentId($studentId);
        if ($meta) {
            $meta = $this->ensureDailyRollover($meta);
            if ($this->ensureQuizLives($meta)) {
                $this->persistMetaAfterQuiz($meta);
            }
        }
        $allowedModes = $meta ? $this->getAllowedModes($meta) : array_combine(self::QUIZ_MODES, self::QUIZ_MODES);
        $baseMode = (string) ($_GET["mode"] ?? "turkish");
        if (!isset($allowedModes[$baseMode])) {
            $baseMode = "turkish";
        }
        $questionMode = $baseMode;
        $mixedCandidates = array_values(array_filter(array_keys($allowedModes), fn($m) => $m !== "mixed" && $m !== "review"));
        if ($baseMode === "mixed") {
            $questionMode = $mixedCandidates ? $mixedCandidates[array_rand($mixedCandidates)] : "turkish";
        }
        $question = $studentId > 0 ? $this->buildQuestionFromVocabRow($this->chooseWord($studentId, $baseMode) ?? [], $questionMode) : null;
        $totalCorrect = 0;
        $totalWrong = 0;
        if ($studentId > 0) {
            [$totalCorrect, $totalWrong] = $this->getStudentTotals($studentId);
        }
        $badge = $user ? $this->getBadgeLabel($user, $totalCorrect) : null;
        $roadTokens = (int) ($meta["road_tokens"] ?? 0);
        $roadBalanceText = $this->roadBalanceText($roadTokens);
        $livesRemaining = (int) ($meta["lives_remaining"] ?? self::QUIZ_LIVES_MAX);
        $livesMax = self::QUIZ_LIVES_MAX;
        $livesResetIn = null;
        if (!empty($meta["lives_reset_at"])) {
            $ts = strtotime((string) $meta["lives_reset_at"] . " UTC");
            if ($ts) {
                $livesResetIn = max(0, $ts - time());
            }
        }
        $streakCtx = $meta ? $this->getDailyStreakContext($meta) : ["message" => "", "broke" => false];
        if (!$question) {
            $this->render("quiz_play.php", [
                "csrf_token" => Security::csrfToken(),
                "student" => $student,
                "modes" => $allowedModes,
                "selected_mode" => $questionMode,
                "base_mode" => $baseMode,
                "question_mode" => $questionMode,
                "error" => "No vocabulary available.",
                "badge" => $badge,
                "streak" => (int) ($meta["streak"] ?? 0),
                "best_streak" => (int) ($meta["best_streak"] ?? 0),
                "daily_done" => (int) ($meta["daily_done"] ?? 0),
                "daily_target" => (int) ($meta["daily_target"] ?? 20),
                "daily_streak" => (int) ($meta["daily_streak"] ?? 0),
                "best_daily_streak" => (int) ($meta["best_daily_streak"] ?? 0),
                "streak_message" => $streakCtx["message"],
                "streak_broke" => $streakCtx["broke"],
                "road_tokens" => $roadTokens,
                "road_balance_text" => $roadBalanceText,
                "lives_remaining" => $livesRemaining,
                "lives_max" => $livesMax,
                "lives_reset_in" => $livesResetIn,
                "timed_seconds" => self::TIMED_MODE_SECONDS,
                "result" => null,
            ]);
            return;
        }
        $this->render("quiz_play.php", [
            "csrf_token" => Security::csrfToken(),
            "student" => $student,
            "modes" => $allowedModes,
            "selected_mode" => $questionMode,
            "base_mode" => $baseMode,
            "question_mode" => $questionMode,
            "question" => $question,
            "options" => $question["options"] ?? [],
            "question_label" => $this->getQuestionLabel($questionMode),
            "word" => $question["word"] ?? "",
            "extra_info" => $question,
            "badge" => $badge,
            "streak" => (int) ($meta["streak"] ?? 0),
            "best_streak" => (int) ($meta["best_streak"] ?? 0),
            "daily_done" => (int) ($meta["daily_done"] ?? 0),
            "daily_target" => (int) ($meta["daily_target"] ?? 20),
            "daily_streak" => (int) ($meta["daily_streak"] ?? 0),
            "best_daily_streak" => (int) ($meta["best_daily_streak"] ?? 0),
            "streak_message" => $streakCtx["message"],
            "streak_broke" => $streakCtx["broke"],
            "road_tokens" => $roadTokens,
            "road_balance_text" => $roadBalanceText,
            "lives_remaining" => $livesRemaining,
            "lives_max" => $livesMax,
            "lives_reset_in" => $livesResetIn,
            "timed_seconds" => self::TIMED_MODE_SECONDS,
            "result" => null,
        ]);
    }

    private function handleQuizPost(string $student): void
    {
        if (($this->sessionUsername() ?? "") !== $student) {
            $this->redirect("/login");
            return;
        }
        $user = $this->currentUser();
        $meta = $user ? $this->ensureStudentMetaForUser($user) : null;
        if (!$meta) {
            $this->redirect("/login");
            return;
        }
        $meta = $this->ensureDailyRollover($meta);
        if ($this->ensureQuizLives($meta)) {
            $this->persistMetaAfterQuiz($meta);
        }
        $allowedModes = $this->getAllowedModes($meta);
        $baseMode = (string) (($_POST["mode"] ?? "") ?: ($_GET["mode"] ?? "turkish"));
        if (!isset($allowedModes[$baseMode])) {
            $baseMode = "turkish";
        }
        $questionMode = $baseMode;
        $mixedCandidates = array_values(array_filter(array_keys($allowedModes), fn($m) => $m !== "mixed" && $m !== "review"));
        if ($baseMode === "mixed") {
            $questionMode = (string) ($_POST["question_mode"] ?? "");
            if (!in_array($questionMode, $mixedCandidates, true)) {
                $questionMode = $mixedCandidates ? $mixedCandidates[array_rand($mixedCandidates)] : "turkish";
            }
        }
        $livesRemaining = (int) ($meta["lives_remaining"] ?? self::QUIZ_LIVES_MAX);
        if ($livesRemaining <= 0) {
            $livesResetIn = null;
            if (!empty($meta["lives_reset_at"])) {
                $ts = strtotime((string) $meta["lives_reset_at"] . " UTC");
                if ($ts) {
                    $livesResetIn = max(0, $ts - time());
                }
            }
            $this->render("quiz_play.php", [
                "csrf_token" => Security::csrfToken(),
                "student" => $student,
                "modes" => $allowedModes,
                "selected_mode" => $questionMode,
                "base_mode" => $baseMode,
                "question_mode" => $questionMode,
                "error" => "No lives left. Please try again later.",
                "badge" => null,
                "streak" => (int) ($meta["streak"] ?? 0),
                "best_streak" => (int) ($meta["best_streak"] ?? 0),
                "timed_seconds" => self::TIMED_MODE_SECONDS,
                "daily_done" => (int) ($meta["daily_done"] ?? 0),
                "daily_target" => (int) ($meta["daily_target"] ?? 20),
                "daily_streak" => (int) ($meta["daily_streak"] ?? 0),
                "best_daily_streak" => (int) ($meta["best_daily_streak"] ?? 0),
                "streak_message" => $this->getDailyStreakContext($meta)["message"],
                "streak_broke" => $this->getDailyStreakContext($meta)["broke"],
                "road_tokens" => (int) ($meta["road_tokens"] ?? 0),
                "road_balance_text" => $this->roadBalanceText((int) ($meta["road_tokens"] ?? 0)),
                "lives_remaining" => $livesRemaining,
                "lives_max" => self::QUIZ_LIVES_MAX,
                "lives_reset_in" => $livesResetIn,
            ]);
            return;
        }

        $word = $this->sanitizeText((string) ($_POST["word"] ?? ""));
        $clicked = trim((string) ($_POST["answer"] ?? ""));
        $spoken = trim((string) ($_POST["spoken_answer"] ?? ""));
        $typed = trim((string) ($_POST["typed_answer"] ?? ""));
        $answerText = mb_strtolower($this->sanitizeText($spoken ?: ($typed ?: $clicked)));
        $timedTimeout = ($questionMode === "timed" && $answerText === "");

        if ($word === "") {
            $this->render("quiz_play.php", [
                "csrf_token" => Security::csrfToken(),
                "student" => $student,
                "modes" => $allowedModes,
                "selected_mode" => $questionMode,
                "base_mode" => $baseMode,
                "question_mode" => $questionMode,
                "error" => "Missing word reference.",
                "road_tokens" => (int) ($meta["road_tokens"] ?? 0),
                "road_balance_text" => $this->roadBalanceText((int) ($meta["road_tokens"] ?? 0)),
                "lives_remaining" => $livesRemaining,
                "lives_max" => self::QUIZ_LIVES_MAX,
            ]);
            return;
        }

        $pdo = Db::pdo();
        $st = $pdo->prepare("SELECT * FROM vocab WHERE word = :w LIMIT 1");
        $st->execute([":w" => $word]);
        $item = $st->fetch();
        if (!$item) {
            $this->render("quiz_play.php", [
                "csrf_token" => Security::csrfToken(),
                "student" => $student,
                "modes" => $allowedModes,
                "selected_mode" => $questionMode,
                "base_mode" => $baseMode,
                "question_mode" => $questionMode,
                "error" => "Word not found, try again.",
                "road_tokens" => (int) ($meta["road_tokens"] ?? 0),
                "road_balance_text" => $this->roadBalanceText((int) ($meta["road_tokens"] ?? 0)),
                "lives_remaining" => $livesRemaining,
                "lives_max" => self::QUIZ_LIVES_MAX,
            ]);
            return;
        }
        $correctAnswer = mb_strtolower(trim($this->getValueForMode($item, $questionMode)));
        $wasCorrect = ($answerText !== "" && $answerText === $correctAnswer && !$timedTimeout);

        $stat = $pdo->prepare("SELECT * FROM student_word_stat WHERE student_id = :sid AND vocab_id = :vid LIMIT 1");
        $stat->execute([":sid" => (int) ($meta["student_id"] ?? 0), ":vid" => (int) ($item["id"] ?? 0)]);
        $sws = $stat->fetch();
        if (!$sws) {
            $this->syncStudentVocab((int) ($meta["student_id"] ?? 0));
            $stat->execute([":sid" => (int) ($meta["student_id"] ?? 0), ":vid" => (int) ($item["id"] ?? 0)]);
            $sws = $stat->fetch();
        }
        $modeStat = $pdo->prepare("SELECT * FROM student_mode_stat WHERE stat_id = :sid AND mode = :m LIMIT 1");
        $modeStat->execute([":sid" => (int) ($sws["id"] ?? 0), ":m" => $questionMode]);
        $ms = $modeStat->fetch();
        if (!$ms) {
            $pdo->prepare("INSERT INTO student_mode_stat (stat_id, mode, correct, wrong) VALUES (:sid,:m,0,0)")
                ->execute([":sid" => (int) ($sws["id"] ?? 0), ":m" => $questionMode]);
        }

        $pronScore = null;
        if ($spoken !== "") {
            $pronScore = null;
        }

        if ($wasCorrect) {
            $newCorrect = (int) ($sws["correct"] ?? 0) + 1;
            $interval = $this->nextIntervalForCorrect($newCorrect);
            $pdo->prepare("UPDATE student_word_stat SET correct = :c, interval = :i, next_time = :n WHERE id = :id")
                ->execute([":c" => $newCorrect, ":i" => $interval, ":n" => time() + $interval, ":id" => (int) ($sws["id"] ?? 0)]);
            $pdo->prepare("UPDATE student_mode_stat SET correct = correct + 1 WHERE stat_id = :sid AND mode = :m")
                ->execute([":sid" => (int) ($sws["id"] ?? 0), ":m" => $questionMode]);
        } else {
            $newWrong = (int) ($sws["wrong"] ?? 0) + 1;
            $interval = $timedTimeout ? (30 * 60) : (60 * 60);
            $pdo->prepare("UPDATE student_word_stat SET wrong = :w, interval = :i, next_time = :n WHERE id = :id")
                ->execute([":w" => $newWrong, ":i" => $interval, ":n" => time() + $interval, ":id" => (int) ($sws["id"] ?? 0)]);
            $pdo->prepare("UPDATE student_mode_stat SET wrong = wrong + 1 WHERE stat_id = :sid AND mode = :m")
                ->execute([":sid" => (int) ($sws["id"] ?? 0), ":m" => $questionMode]);
        }

        $this->recordDailyStat((int) ($meta["student_id"] ?? 0), $wasCorrect);

        $result = "";
        if ($wasCorrect) {
            $result = "Correct! +1 Road";
        } else {
            $result = ($timedTimeout ? "Time is up!" : "Wrong!") . " Correct answer: " . $correctAnswer;
        }

        $road = (int) ($meta["road_tokens"] ?? 0);
        if ($wasCorrect) {
            $road += 1;
            $meta["streak"] = (int) ($meta["streak"] ?? 0) + 1;
            $meta["best_streak"] = max((int) ($meta["best_streak"] ?? 0), (int) $meta["streak"]);
            $meta["daily_done"] = (int) ($meta["daily_done"] ?? 0) + 1;
            $meta["last_day"] = gmdate("Y-m-d");
            if ((int) $meta["daily_done"] >= (int) ($meta["daily_target"] ?? 20)) {
                $today = (string) $meta["last_day"];
                if ((string) ($meta["last_goal_day"] ?? "") !== $today) {
                    $meta["daily_streak"] = (int) ($meta["daily_streak"] ?? 0) + 1;
                    $goalBonus = max(0, (int) $meta["daily_streak"]);
                    if ($goalBonus > 0) {
                        $road += $goalBonus;
                        $result .= " Daily goal complete! +{$goalBonus} Road bonus";
                    }
                }
                $meta["last_goal_day"] = $today;
                $meta["best_daily_streak"] = max((int) ($meta["best_daily_streak"] ?? 0), (int) ($meta["daily_streak"] ?? 0));
            }
        } else {
            $meta["streak"] = ((string) ($meta["streak_mode"] ?? "strict") === "lenient") ? max((int) ($meta["streak"] ?? 0) - 1, 0) : 0;
            $meta["lives_remaining"] = max(0, (int) ($meta["lives_remaining"] ?? self::QUIZ_LIVES_MAX) - 1);
        }

        $meta["road_tokens"] = $road;
        $missionRewards = $this->applyMissionAndWeeklyRewards($meta, (int) ($meta["student_id"] ?? 0), $wasCorrect);
        if (!empty($missionRewards)) {
            $result .= " " . implode(" | ", $missionRewards);
        }
        $this->persistMetaAfterQuiz($meta);

        $streakCtx = $this->getDailyStreakContext($meta);
        [$totalCorrect, $totalWrong] = $this->getStudentTotals((int) ($meta["student_id"] ?? 0));
        $badge = $this->getBadgeLabel($user, $totalCorrect);
        $extraInfo = [
            "word" => (string) ($item["word"] ?? ""),
            "turkish" => (string) ($item["turkish"] ?? ""),
            "synonyms" => $this->decodeJsonList($item["synonyms"] ?? null),
            "antonyms" => $this->decodeJsonList($item["antonyms"] ?? null),
            "definition" => (string) ($item["definition"] ?? ""),
            "example_en" => (string) ($item["example_en"] ?? ""),
            "example_tr" => (string) ($item["example_tr"] ?? ""),
        ];
        $livesResetIn = null;
        if (!empty($meta["lives_reset_at"])) {
            $ts = strtotime((string) $meta["lives_reset_at"] . " UTC");
            if ($ts) {
                $livesResetIn = max(0, $ts - time());
            }
        }
        $this->render("quiz_play.php", [
            "csrf_token" => Security::csrfToken(),
            "student" => $student,
            "modes" => $allowedModes,
            "selected_mode" => $questionMode,
            "base_mode" => $baseMode,
            "question_mode" => $questionMode,
            "question_label" => $this->getQuestionLabel($questionMode),
            "word" => null,
            "extra_info" => $extraInfo,
            "badge" => $badge,
            "streak" => (int) ($meta["streak"] ?? 0),
            "best_streak" => (int) ($meta["best_streak"] ?? 0),
            "daily_done" => (int) ($meta["daily_done"] ?? 0),
            "daily_target" => (int) ($meta["daily_target"] ?? 20),
            "daily_streak" => (int) ($meta["daily_streak"] ?? 0),
            "best_daily_streak" => (int) ($meta["best_daily_streak"] ?? 0),
            "streak_message" => $streakCtx["message"],
            "streak_broke" => $streakCtx["broke"],
            "road_tokens" => (int) ($meta["road_tokens"] ?? 0),
            "road_balance_text" => $this->roadBalanceText((int) ($meta["road_tokens"] ?? 0)),
            "lives_remaining" => (int) ($meta["lives_remaining"] ?? self::QUIZ_LIVES_MAX),
            "lives_max" => self::QUIZ_LIVES_MAX,
            "lives_reset_in" => $livesResetIn,
            "timed_seconds" => self::TIMED_MODE_SECONDS,
            "result" => $result,
            "pronunciation_score" => $pronScore,
        ]);
    }

    private function quizState(string $student): array
    {
        $pdo = Db::pdo();
        $st = $pdo->prepare("
            SELECT sm.id, sm.lives_remaining, sm.lives_reset_at, s.id AS student_id
            FROM student s
            LEFT JOIN student_meta sm ON sm.student_id = s.id
            WHERE s.name = :n
            LIMIT 1
        ");
        $st->execute([":n" => $student]);
        $row = $st->fetch();
        if (!$row) {
            $insS = $pdo->prepare("INSERT INTO student (name, created_at) VALUES (:n, UTC_TIMESTAMP())");
            $insS->execute([":n" => $student]);
            $studentId = (int) $pdo->lastInsertId();
            $insM = $pdo->prepare("
                INSERT INTO student_meta (
                    student_id, lives_remaining, lives_reset_at, daily_target,
                    streak_freezes, road_tokens, theme_pack_owned, preferred_theme, name_glow_enabled,
                    owned_profile_frames, profile_frame_style, owned_chat_bubbles, chat_bubble_style, mission_claimed
                )
                VALUES (
                    :sid, :l, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 15 MINUTE), 20,
                    2, 0, 0, 'default', 0,
                    :opf, 'default', :ocb, 'default', :mclaimed
                )
            ");
            $insM->execute([
                ":sid" => $studentId,
                ":l" => self::QUIZ_LIVES_MAX,
                ":opf" => json_encode(["default"], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ":ocb" => json_encode(["default"], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ":mclaimed" => json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
            return ["lives" => self::QUIZ_LIVES_MAX, "reset_in" => self::QUIZ_LIVES_RESET_SECONDS];
        }
        $lives = (int) ($row["lives_remaining"] ?? self::QUIZ_LIVES_MAX);
        $resetAt = (string) ($row["lives_reset_at"] ?? "");
        $resetTs = $resetAt !== "" ? strtotime($resetAt . " UTC") : 0;
        $now = time();
        if ($resetTs <= 0 || $now >= $resetTs) {
            $lives = self::QUIZ_LIVES_MAX;
            $nextReset = $now + self::QUIZ_LIVES_RESET_SECONDS;
            $upd = $pdo->prepare("
                UPDATE student_meta
                SET lives_remaining = :l, lives_reset_at = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 15 MINUTE)
                WHERE student_id = :sid
            ");
            $upd->execute([":l" => $lives, ":sid" => (int) $row["student_id"]]);
            return ["lives" => $lives, "reset_in" => self::QUIZ_LIVES_RESET_SECONDS];
        }
        return ["lives" => $lives, "reset_in" => max(0, $resetTs - $now)];
    }

    private function updateLives(string $student, int $lives): void
    {
        $pdo = Db::pdo();
        $st = $pdo->prepare("
            UPDATE student_meta sm
            INNER JOIN student s ON s.id = sm.student_id
            SET sm.lives_remaining = :l
            WHERE s.name = :n
        ");
        $st->execute([":l" => $lives, ":n" => $student]);
    }

    private function pickQuestion(string $student, string $mode = "turkish"): ?array
    {
        $pdo = Db::pdo();
        $effectiveMode = $this->resolveQuizQuestionMode($mode);
        for ($attempt = 0; $attempt < 12; $attempt++) {
            $q = $pdo->query("SELECT id, word, turkish, definition, synonyms, antonyms, level FROM vocab ORDER BY RAND() LIMIT 1");
            $row = $q->fetch();
            if (!$row) {
                return null;
            }
            $question = $this->buildQuestionFromVocabRow($row, $effectiveMode);
            if ($question !== null) {
                return $question;
            }
        }
        return null;
    }

    private function normalizeQuizMode(string $mode): string
    {
        $m = strtolower(trim($mode));
        return in_array($m, self::QUIZ_MODES, true) ? $m : "turkish";
    }

    private function resolveQuizQuestionMode(string $mode): string
    {
        $mode = $this->normalizeQuizMode($mode);
        if ($mode === "mixed") {
            $pool = ["turkish", "definition", "synonym", "antonym"];
            return $pool[array_rand($pool)];
        }
        if ($mode === "timed" || $mode === "review") {
            return "turkish";
        }
        return $mode;
    }

    private function getAllowedModes(array $meta): array
    {
        $allowed = $this->decodeJsonList($meta["allowed_modes"] ?? null);
        if (empty($allowed)) {
            $allowed = self::QUIZ_MODES;
        }
        $out = [];
        foreach (self::QUIZ_MODES as $m) {
            if (in_array($m, $allowed, true)) {
                $out[$m] = $m;
            }
        }
        return $out ?: array_combine(self::QUIZ_MODES, self::QUIZ_MODES);
    }

    private function getQuestionLabel(string $mode): string
    {
        $labels = [
            "turkish" => "What is the Turkish meaning of",
            "synonym" => "Which is a synonym of",
            "antonym" => "Which is an antonym of",
            "definition" => "Which definition matches",
            "typing" => "Type the Turkish meaning of",
            "timed" => "Answer before time runs out - Turkish meaning of",
            "review" => "Review - Turkish meaning of",
        ];
        return $labels[$mode] ?? "Meaning of";
    }

    private function getValueForMode(array $row, string $mode): string
    {
        if (in_array($mode, ["turkish", "timed", "typing", "review"], true)) {
            return (string) ($row["turkish"] ?? "");
        }
        if ($mode === "synonym") {
            $syns = $this->decodeJsonList($row["synonyms"] ?? null);
            return (string) ($syns[0] ?? ($row["word"] ?? ""));
        }
        if ($mode === "antonym") {
            $ants = $this->decodeJsonList($row["antonyms"] ?? null);
            return (string) ($ants[0] ?? ($row["word"] ?? ""));
        }
        if ($mode === "definition") {
            return (string) (($row["definition"] ?? "") ?: ($row["turkish"] ?? ""));
        }
        return (string) ($row["turkish"] ?? "");
    }

    private function nextIntervalForCorrect(int $correctCount): int
    {
        $idx = max($correctCount - 1, 0);
        $idx = min($idx, count(self::REVIEW_INTERVALS) - 1);
        return self::REVIEW_INTERVALS[$idx];
    }

    private function getStudentTotals(int $studentId): array
    {
        $pdo = Db::pdo();
        $rows = $pdo->prepare("SELECT COALESCE(SUM(correct),0) AS c, COALESCE(SUM(wrong),0) AS w FROM student_word_stat WHERE student_id = :sid");
        $rows->execute([":sid" => $studentId]);
        $row = $rows->fetch();
        return [(int) ($row["c"] ?? 0), (int) ($row["w"] ?? 0)];
    }

    private function chooseWord(int $studentId, ?string $mode = null): ?array
    {
        $pdo = Db::pdo();
        $stats = $pdo->prepare("
            SELECT sws.*, v.word, v.turkish, v.definition, v.synonyms, v.antonyms, v.example_en, v.example_tr
            FROM student_word_stat sws
            INNER JOIN vocab v ON v.id = sws.vocab_id
            WHERE sws.student_id = :sid
        ");
        $stats->execute([":sid" => $studentId]);
        $rows = $stats->fetchAll();
        if (!$rows) {
            return null;
        }
        $now = time();
        if ($mode === "review") {
            $due = array_filter($rows, fn($r) => (float) ($r["next_time"] ?? 0) <= $now);
            $pool = $due ?: $rows;
            $scored = [];
            foreach ($pool as $r) {
                $c = (int) ($r["correct"] ?? 0);
                $w = (int) ($r["wrong"] ?? 0);
                $t = $c + $w;
                $acc = $t > 0 ? ($c / $t) : 0.0;
                $scored[] = [$acc, $w, (float) ($r["next_time"] ?? 0), $r];
            }
            usort($scored, fn($a, $b) => $a[0] <=> $b[0] ?: $b[1] <=> $a[1] ?: $a[2] <=> $b[2]);
            $top = array_slice(array_map(fn($s) => $s[3], $scored), 0, 10);
            $pool = $top ?: $pool;
            return $pool[array_rand($pool)];
        }

        $levels = ["A1", "A2", "B1", "B2", "C1", "C2"];
        $byLevel = [];
        foreach ($levels as $lvl) {
            $byLevel[$lvl] = [];
        }
        foreach ($rows as $r) {
            $lvl = strtoupper((string) ($r["level"] ?? "A1"));
            if (isset($byLevel[$lvl])) {
                $byLevel[$lvl][] = $r;
            }
        }
        $currentLevel = "A1";
        foreach ($levels as $lvl) {
            if (empty($byLevel[$lvl])) {
                continue;
            }
            $hasZero = false;
            foreach ($byLevel[$lvl] as $s) {
                if ((int) ($s["correct"] ?? 0) <= 0) {
                    $hasZero = true;
                    break;
                }
            }
            if ($hasZero) {
                $currentLevel = $lvl;
                break;
            }
            $currentLevel = $lvl;
        }
        $levelItems = $byLevel[$currentLevel] ?? $rows;
        $due = array_filter($levelItems, fn($r) => (float) ($r["next_time"] ?? 0) <= $now);
        $pool = $due ?: $levelItems;
        $weak = [];
        foreach ($pool as $r) {
            $c = (int) ($r["correct"] ?? 0);
            $w = (int) ($r["wrong"] ?? 0);
            $t = $c + $w;
            if ($t >= 3) {
                $acc = $t > 0 ? ($c / $t) : 0.0;
                if ($acc < 0.7) {
                    $weak[] = [$acc, $r];
                }
            }
        }
        if ($weak) {
            usort($weak, fn($a, $b) => $a[0] <=> $b[0]);
            $weakPool = array_slice(array_map(fn($w) => $w[1], $weak), 0, 5);
            return $weakPool[array_rand($weakPool)];
        }
        return $pool[array_rand($pool)];
    }

    private function buildQuestionFromVocabRow(array $row, string $questionMode): ?array
    {
        $effectiveMode = $questionMode;
        if (in_array($questionMode, ["timed", "review"], true)) {
            $effectiveMode = "turkish";
        }
        $word = (string) ($row["word"] ?? "");
        $turkish = trim((string) ($row["turkish"] ?? ""));
        $definition = trim((string) ($row["definition"] ?? ""));
        $synonyms = array_values(array_filter(array_map('trim', $this->decodeJsonList($row["synonyms"] ?? null)), fn($v) => $v !== "" && strtolower($v) !== "null"));
        $antonyms = array_values(array_filter(array_map('trim', $this->decodeJsonList($row["antonyms"] ?? null)), fn($v) => $v !== "" && strtolower($v) !== "null"));

        $base = [
            "id" => (int) ($row["id"] ?? 0),
            "word" => $word,
            "turkish" => $turkish,
            "definition" => $definition,
            "synonyms" => $synonyms,
            "antonyms" => $antonyms,
            "example_en" => (string) ($row["example_en"] ?? ""),
            "example_tr" => (string) ($row["example_tr"] ?? ""),
            "level" => (string) ($row["level"] ?? "A1"),
            "question_mode" => $questionMode,
            "input_type" => "options",
            "options" => [],
        ];

        if ($effectiveMode === "typing") {
            if ($turkish === "") { return null; }
            $base["input_type"] = "text";
            $base["prompt_label"] = $this->getQuestionLabel("typing");
            $base["prompt_text"] = $word;
            return $base;
        }

        if ($effectiveMode === "turkish") {
            if ($turkish === "") { return null; }
            $base["prompt_label"] = $this->getQuestionLabel($questionMode);
            $base["prompt_text"] = $word;
            $base["options"] = $this->quizOptionPool("turkish", $turkish);
            return count($base["options"]) >= 2 ? $base : null;
        }
        if ($effectiveMode === "definition") {
            if ($definition === "") { return null; }
            $base["prompt_label"] = $this->getQuestionLabel("definition");
            $base["prompt_text"] = $word;
            $base["options"] = $this->quizOptionPool("definition", $definition);
            return count($base["options"]) >= 2 ? $base : null;
        }
        if ($effectiveMode === "synonym") {
            if (!$synonyms) { return null; }
            $base["prompt_label"] = $this->getQuestionLabel("synonym");
            $base["prompt_text"] = $word;
            $base["options"] = $this->quizOptionPool("synonym", $synonyms[0]);
            return count($base["options"]) >= 2 ? $base : null;
        }
        if ($effectiveMode === "antonym") {
            if (!$antonyms) { return null; }
            $base["prompt_label"] = $this->getQuestionLabel("antonym");
            $base["prompt_text"] = $word;
            $base["options"] = $this->quizOptionPool("antonym", $antonyms[0]);
            return count($base["options"]) >= 2 ? $base : null;
        }

        return null;
    }

    private function quizOptionPool(string $kind, string $correct): array
    {
        $pdo = Db::pdo();
        $opts = [$correct];
        if ($kind === "turkish") {
            $rows = $pdo->query("SELECT turkish AS val FROM vocab ORDER BY RAND() LIMIT 40")->fetchAll();
            foreach ($rows as $r) {
                $v = trim((string) ($r["val"] ?? ""));
                if ($v !== "" && !in_array($v, $opts, true)) { $opts[] = $v; }
                if (count($opts) >= 4) { break; }
            }
        } elseif ($kind === "definition") {
            $rows = $pdo->query("SELECT definition AS val FROM vocab WHERE definition IS NOT NULL AND definition <> '' ORDER BY RAND() LIMIT 40")->fetchAll();
            foreach ($rows as $r) {
                $v = trim((string) ($r["val"] ?? ""));
                if ($v !== "" && !in_array($v, $opts, true)) { $opts[] = $v; }
                if (count($opts) >= 4) { break; }
            }
        } elseif ($kind === "synonym" || $kind === "antonym") {
            $col = $kind === "synonym" ? "synonyms" : "antonyms";
            $rows = $pdo->query("SELECT {$col} AS raw FROM vocab ORDER BY RAND() LIMIT 80")->fetchAll();
            foreach ($rows as $r) {
                foreach ($this->decodeJsonList($r["raw"] ?? null) as $v0) {
                    $v = trim((string) $v0);
                    if ($v === "" || strtolower($v) === "null") { continue; }
                    if (!in_array($v, $opts, true)) { $opts[] = $v; }
                    if (count($opts) >= 4) { break 2; }
                }
            }
        }
        shuffle($opts);
        return $opts;
    }

    private function quizAnswerMatches(array $row, string $questionMode, string $answerLower): bool
    {
        $questionMode = $this->resolveQuizQuestionMode($questionMode);
        if ($questionMode === "typing" || $questionMode === "turkish") {
            return mb_strtolower(trim((string) ($row["turkish"] ?? ""))) === $answerLower;
        }
        if ($questionMode === "definition") {
            return mb_strtolower(trim((string) ($row["definition"] ?? ""))) === $answerLower;
        }
        if ($questionMode === "synonym") {
            foreach ($this->decodeJsonList($row["synonyms"] ?? null) as $v) {
                if (mb_strtolower(trim((string) $v)) === $answerLower) { return true; }
            }
            return false;
        }
        if ($questionMode === "antonym") {
            foreach ($this->decodeJsonList($row["antonyms"] ?? null) as $v) {
                if (mb_strtolower(trim((string) $v)) === $answerLower) { return true; }
            }
            return false;
        }
        return false;
    }

    private function recordQuizAnswer(array $user, array $vocabRow, bool $correct): array
    {
        $meta = $this->ensureStudentMetaForUser($user);
        if (!$meta) {
            return ["daily_bonus" => 0];
        }
        $meta = $this->ensureDailyRollover($meta);
        $pdo = Db::pdo();
        $studentId = (int) ($meta["student_id"] ?? 0);
        if ($studentId <= 0) {
            return ["daily_bonus" => 0];
        }

        $today = gmdate("Y-m-d");
        $dailyBonus = 0;
        try {
            $pdo->beginTransaction();

            $stat = $pdo->prepare("SELECT * FROM student_word_stat WHERE student_id = :sid AND vocab_id = :vid LIMIT 1");
            $stat->execute([":sid" => $studentId, ":vid" => (int) ($vocabRow["id"] ?? 0)]);
            $sws = $stat->fetch();
            if ($sws) {
                $c = (int) ($sws["correct"] ?? 0);
                $w = (int) ($sws["wrong"] ?? 0);
                $interval = (float) ($sws["interval"] ?? 1.0);
                if ($correct) {
                    $c++;
                    $interval = min(2592000.0, max(1.0, $interval * 2.0));
                } else {
                    $w++;
                    $interval = 1.0;
                }
                $nextTime = (float) time() + $interval;
                $upd = $pdo->prepare("UPDATE student_word_stat SET interval=:i, next_time=:n, correct=:c, wrong=:w, level=:lvl WHERE id=:id");
                $upd->execute([
                    ":i" => $interval,
                    ":n" => $nextTime,
                    ":c" => $c,
                    ":w" => $w,
                    ":lvl" => (string) (($vocabRow["level"] ?? "") ?: "A1"),
                    ":id" => (int) $sws["id"],
                ]);
            } else {
                $ins = $pdo->prepare("INSERT INTO student_word_stat (student_id, vocab_id, interval, next_time, correct, wrong, level) VALUES (:sid,:vid,:i,:n,:c,:w,:lvl)");
                $ins->execute([
                    ":sid" => $studentId,
                    ":vid" => (int) ($vocabRow["id"] ?? 0),
                    ":i" => $correct ? 2.0 : 1.0,
                    ":n" => (float) time() + ($correct ? 2.0 : 1.0),
                    ":c" => $correct ? 1 : 0,
                    ":w" => $correct ? 0 : 1,
                    ":lvl" => (string) (($vocabRow["level"] ?? "") ?: "A1"),
                ]);
            }

            $d = $pdo->prepare("SELECT * FROM student_daily_stat WHERE student_id = :sid AND day = :day LIMIT 1");
            $d->execute([":sid" => $studentId, ":day" => $today]);
            $daily = $d->fetch();
            if ($daily) {
                $updD = $pdo->prepare("UPDATE student_daily_stat SET correct = :c, wrong = :w WHERE id = :id");
                $updD->execute([
                    ":c" => (int) ($daily["correct"] ?? 0) + ($correct ? 1 : 0),
                    ":w" => (int) ($daily["wrong"] ?? 0) + ($correct ? 0 : 1),
                    ":id" => (int) $daily["id"],
                ]);
            } else {
                $insD = $pdo->prepare("INSERT INTO student_daily_stat (student_id, day, correct, wrong, created_at) VALUES (:sid, :day, :c, :w, UTC_TIMESTAMP())");
                $insD->execute([
                    ":sid" => $studentId,
                    ":day" => $today,
                    ":c" => $correct ? 1 : 0,
                    ":w" => $correct ? 0 : 1,
                ]);
            }

            $metaNowQ = $pdo->prepare("SELECT * FROM student_meta WHERE id = :id LIMIT 1");
            $metaNowQ->execute([":id" => (int) $meta["id"]]);
            $metaNow = $metaNowQ->fetch() ?: $meta;

            $road = (int) ($metaNow["road_tokens"] ?? 0);
            $dailyDone = (int) ($metaNow["daily_done"] ?? 0);
            $dailyTarget = max(1, (int) ($metaNow["daily_target"] ?? 20));
            $dailyStreak = (int) ($metaNow["daily_streak"] ?? 0);
            $bestDailyStreak = (int) ($metaNow["best_daily_streak"] ?? 0);
            $lastGoalDay = (string) ($metaNow["last_goal_day"] ?? "");
            $lastDay = (string) ($metaNow["last_day"] ?? "");
            $streak = (int) ($metaNow["streak"] ?? 0);
            $bestStreak = (int) ($metaNow["best_streak"] ?? 0);
            $missionQuestions = (int) ($metaNow["mission_questions_done"] ?? 0);
            $missionCurCorrect = (int) ($metaNow["mission_current_correct_streak"] ?? 0);
            $missionBestCorrect = (int) ($metaNow["mission_best_correct_streak"] ?? 0);
            $missionDay = (string) ($metaNow["mission_day"] ?? "");
            $missionClaimed = $this->decodeJsonList($metaNow["mission_claimed"] ?? null);
            $weeklyBonusWeek = (string) ($metaNow["weekly_bonus_week"] ?? "");

            if ($missionDay !== $today) {
                $missionQuestions = 0;
                $missionCurCorrect = 0;
                $missionBestCorrect = 0;
                $missionClaimed = [];
                $missionDay = $today;
            }
            $missionQuestions++;
            if ($correct) {
                $missionCurCorrect++;
                $missionBestCorrect = max($missionBestCorrect, $missionCurCorrect);
            } else {
                $missionCurCorrect = 0;
            }

            if ($lastDay !== $today) {
                $yesterday = gmdate("Y-m-d", time() - 86400);
                if ($lastDay === $yesterday) {
                    $streak += 1;
                } else {
                    $streak = 1;
                }
                $bestStreak = max($bestStreak, $streak);
            }

            if ($correct) {
                $road += 1;
                $dailyDone += 1;
                $justCompletedGoal = $dailyDone >= $dailyTarget && $lastGoalDay !== $today;
                if ($justCompletedGoal) {
                    $yesterday = gmdate("Y-m-d", time() - 86400);
                    $dailyStreak = ($lastGoalDay === $yesterday) ? ($dailyStreak + 1) : 1;
                    $bestDailyStreak = max($bestDailyStreak, $dailyStreak);
                    $dailyBonus = max(1, $dailyStreak);
                    $road += $dailyBonus;
                    $lastGoalDay = $today;
                }
            }

            $claimedMap = array_flip($missionClaimed);
            if ($missionQuestions >= self::DAILY_MISSION_QUESTIONS_TARGET && !isset($claimedMap["questions30"])) {
                $road += self::DAILY_MISSION_QUESTIONS_REWARD;
                $claimedMap["questions30"] = true;
            }
            if ($missionBestCorrect >= self::DAILY_MISSION_STREAK_TARGET && !isset($claimedMap["correct5"])) {
                $road += self::DAILY_MISSION_STREAK_REWARD;
                $claimedMap["correct5"] = true;
            }
            $missionClaimed = array_keys($claimedMap);

            $weekKey = $this->weekKey(new \DateTimeImmutable($today, new \DateTimeZone("UTC")));
            if ($weeklyBonusWeek !== $weekKey) {
                $weekStart = (new \DateTimeImmutable($today, new \DateTimeZone("UTC")))->modify("-" . ((int) (new \DateTimeImmutable($today))->format("N") - 1) . " days");
                $ws = $pdo->prepare("
                    SELECT COALESCE(SUM(correct),0) AS c, COALESCE(SUM(wrong),0) AS w
                    FROM student_daily_stat
                    WHERE student_id = :sid AND day >= :start AND day <= :end
                ");
                $ws->execute([
                    ":sid" => $studentId,
                    ":start" => $weekStart->format("Y-m-d"),
                    ":end" => $today,
                ]);
                $wrow = $ws->fetch();
                $weeklyCount = (int) ($wrow["c"] ?? 0) + (int) ($wrow["w"] ?? 0);
                if ($weeklyCount >= self::WEEKLY_CHALLENGE_TARGET) {
                    $road += self::WEEKLY_CHALLENGE_REWARD;
                    $weeklyBonusWeek = $weekKey;
                }
            }

            $updMeta = $pdo->prepare("
                UPDATE student_meta
                SET road_tokens = :road,
                    daily_done = :daily_done,
                    last_day = :last_day,
                    streak = :streak,
                    best_streak = :best_streak,
                    daily_streak = :daily_streak,
                    best_daily_streak = :best_daily_streak,
                    last_goal_day = :last_goal_day,
                    mission_day = :mission_day,
                    mission_questions_done = :mq,
                    mission_current_correct_streak = :mccs,
                    mission_best_correct_streak = :mbcs,
                    mission_claimed = :mclaimed,
                    weekly_bonus_week = :wbw
                WHERE id = :id
            ");
            $updMeta->execute([
                ":road" => $road,
                ":daily_done" => $dailyDone,
                ":last_day" => $today,
                ":streak" => $streak,
                ":best_streak" => $bestStreak,
                ":daily_streak" => $dailyStreak,
                ":best_daily_streak" => $bestDailyStreak,
                ":last_goal_day" => ($lastGoalDay !== "" ? $lastGoalDay : null),
                ":mission_day" => $missionDay,
                ":mq" => $missionQuestions,
                ":mccs" => $missionCurCorrect,
                ":mbcs" => $missionBestCorrect,
                ":mclaimed" => !empty($missionClaimed) ? json_encode(array_values($missionClaimed), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                ":wbw" => ($weeklyBonusWeek !== "" ? $weeklyBonusWeek : null),
                ":id" => (int) $meta["id"],
            ]);

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
        }

        return ["daily_bonus" => $dailyBonus];
    }

    private function applyMissionAndWeeklyRewards(array &$meta, int $studentId, bool $correct): array
    {
        $today = gmdate("Y-m-d");
        if ((string) ($meta["mission_day"] ?? "") !== $today) {
            $meta["mission_day"] = $today;
            $meta["mission_questions_done"] = 0;
            $meta["mission_current_correct_streak"] = 0;
            $meta["mission_best_correct_streak"] = 0;
            $meta["mission_claimed"] = json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $meta["mission_questions_done"] = (int) ($meta["mission_questions_done"] ?? 0) + 1;
        if ($correct) {
            $meta["mission_current_correct_streak"] = (int) ($meta["mission_current_correct_streak"] ?? 0) + 1;
        } else {
            $meta["mission_current_correct_streak"] = 0;
        }
        $meta["mission_best_correct_streak"] = max(
            (int) ($meta["mission_best_correct_streak"] ?? 0),
            (int) ($meta["mission_current_correct_streak"] ?? 0)
        );

        $rewards = [];
        $claimed = $this->decodeJsonList($meta["mission_claimed"] ?? null);
        $claimedMap = array_flip($claimed);
        if ((int) ($meta["mission_questions_done"] ?? 0) >= self::DAILY_MISSION_QUESTIONS_TARGET && !isset($claimedMap["questions30"])) {
            $meta["road_tokens"] = (int) ($meta["road_tokens"] ?? 0) + self::DAILY_MISSION_QUESTIONS_REWARD;
            $claimedMap["questions30"] = true;
            $rewards[] = "+" . self::DAILY_MISSION_QUESTIONS_REWARD . " Road (Daily Mission: " . self::DAILY_MISSION_QUESTIONS_TARGET . " questions)";
        }
        if ((int) ($meta["mission_best_correct_streak"] ?? 0) >= self::DAILY_MISSION_STREAK_TARGET && !isset($claimedMap["correct5"])) {
            $meta["road_tokens"] = (int) ($meta["road_tokens"] ?? 0) + self::DAILY_MISSION_STREAK_REWARD;
            $claimedMap["correct5"] = true;
            $rewards[] = "+" . self::DAILY_MISSION_STREAK_REWARD . " Road (Daily Mission: " . self::DAILY_MISSION_STREAK_TARGET . " correct streak)";
        }
        $meta["mission_claimed"] = json_encode(array_keys($claimedMap), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $weekKey = $this->weekKey(new \DateTimeImmutable($today, new \DateTimeZone("UTC")));
        $weeklyBonusWeek = (string) ($meta["weekly_bonus_week"] ?? "");
        if ($weeklyBonusWeek !== $weekKey) {
            $weekStart = (new \DateTimeImmutable($today, new \DateTimeZone("UTC")))
                ->modify("-" . ((int) (new \DateTimeImmutable($today, new \DateTimeZone("UTC")))->format("N") - 1) . " days");
            $pdo = Db::pdo();
            $ws = $pdo->prepare("
                SELECT COALESCE(SUM(correct),0) AS c, COALESCE(SUM(wrong),0) AS w
                FROM student_daily_stat
                WHERE student_id = :sid AND day >= :start AND day <= :end
            ");
            $ws->execute([
                ":sid" => $studentId,
                ":start" => $weekStart->format("Y-m-d"),
                ":end" => $today,
            ]);
            $row = $ws->fetch();
            $weeklyCount = (int) ($row["c"] ?? 0) + (int) ($row["w"] ?? 0);
            if ($weeklyCount >= self::WEEKLY_CHALLENGE_TARGET) {
                $meta["road_tokens"] = (int) ($meta["road_tokens"] ?? 0) + self::WEEKLY_CHALLENGE_REWARD;
                $meta["weekly_bonus_week"] = $weekKey;
                $rewards[] = "+" . self::WEEKLY_CHALLENGE_REWARD . " Road (Weekly Challenge complete)";
            }
        }

        return $rewards;
    }

    private function ensureQuizLives(array &$meta): bool
    {
        $changed = false;
        if (!isset($meta["lives_remaining"])) {
            $meta["lives_remaining"] = self::QUIZ_LIVES_MAX;
            $changed = true;
        }
        $resetAt = (string) ($meta["lives_reset_at"] ?? "");
        $resetTs = $resetAt !== "" ? strtotime($resetAt . " UTC") : 0;
        $now = time();
        if ($resetTs <= 0 || $now >= $resetTs) {
            $meta["lives_remaining"] = self::QUIZ_LIVES_MAX;
            $meta["lives_reset_at"] = gmdate("Y-m-d H:i:s", $now + self::QUIZ_LIVES_RESET_SECONDS);
            $changed = true;
        }
        return $changed;
    }

    private function persistMetaAfterQuiz(array $meta): void
    {
        if (empty($meta["id"])) {
            return;
        }
        $pdo = Db::pdo();
        $st = $pdo->prepare("
            UPDATE student_meta
            SET road_tokens = :road,
                streak = :streak,
                best_streak = :best_streak,
                daily_done = :daily_done,
                daily_target = :daily_target,
                daily_streak = :daily_streak,
                best_daily_streak = :best_daily_streak,
                last_day = :last_day,
                last_goal_day = :last_goal_day,
                lives_remaining = :lives_remaining,
                lives_reset_at = :lives_reset_at,
                mission_day = :mission_day,
                mission_questions_done = :mq,
                mission_current_correct_streak = :mccs,
                mission_best_correct_streak = :mbcs,
                mission_claimed = :mclaimed,
                weekly_bonus_week = :wbw
            WHERE id = :id
        ");
        $st->execute([
            ":road" => (int) ($meta["road_tokens"] ?? 0),
            ":streak" => (int) ($meta["streak"] ?? 0),
            ":best_streak" => (int) ($meta["best_streak"] ?? 0),
            ":daily_done" => (int) ($meta["daily_done"] ?? 0),
            ":daily_target" => (int) ($meta["daily_target"] ?? 20),
            ":daily_streak" => (int) ($meta["daily_streak"] ?? 0),
            ":best_daily_streak" => (int) ($meta["best_daily_streak"] ?? 0),
            ":last_day" => ($meta["last_day"] ?? null),
            ":last_goal_day" => ($meta["last_goal_day"] ?? null),
            ":lives_remaining" => (int) ($meta["lives_remaining"] ?? self::QUIZ_LIVES_MAX),
            ":lives_reset_at" => ($meta["lives_reset_at"] ?? null),
            ":mission_day" => ($meta["mission_day"] ?? null),
            ":mq" => (int) ($meta["mission_questions_done"] ?? 0),
            ":mccs" => (int) ($meta["mission_current_correct_streak"] ?? 0),
            ":mbcs" => (int) ($meta["mission_best_correct_streak"] ?? 0),
            ":mclaimed" => ($meta["mission_claimed"] ?? null),
            ":wbw" => ($meta["weekly_bonus_week"] ?? null),
            ":id" => (int) $meta["id"],
        ]);
    }

    private function handleRabbitFeedApi(): void
    {
        $user = $this->currentUser();
        if (!$user) {
            $this->json(["ok" => false, "message" => "Login required."], 401);
            return;
        }
        $meta = $this->ensureStudentMetaForUser($user);
        if (!$meta) {
            $this->json(["ok" => false, "message" => "Learner account required."], 400);
            return;
        }
        $today = gmdate("Y-m-d");
        if (((string) ($meta["rabbit_last_fed_day"] ?? "")) === $today) {
            $this->json([
                "ok" => true,
                "awarded" => 0,
                "message" => "Already rewarded today.",
                "road_tokens" => (int) ($meta["road_tokens"] ?? 0),
                "road_balance_text" => $this->roadBalanceText((int) ($meta["road_tokens"] ?? 0)),
            ]);
            return;
        }

        $pdo = Db::pdo();
        $metaId = (int) ($meta["id"] ?? 0);
        $beforeRoad = (int) ($meta["road_tokens"] ?? 0);
        $afterRoad = $beforeRoad + 2;
        $st = $pdo->prepare("UPDATE student_meta SET rabbit_last_fed_day = UTC_DATE(), road_tokens = :r WHERE id = :id");
        $st->execute([":r" => $afterRoad, ":id" => $metaId]);
        $this->createNotification((int) $user["id"], "You fed Rabb-it and earned +2 Roads.", "success");
        $this->json([
            "ok" => true,
            "awarded" => 2,
            "message" => "You earned +2 Road.",
            "road_tokens" => $afterRoad,
            "road_balance_text" => $this->roadBalanceText($afterRoad),
        ]);
    }

    private function requireLogin(): void
    {
        if ($this->sessionUsername() === null) {
            $this->redirect("/login");
        }
    }

    private function currentUser(): ?array
    {
        $username = $this->sessionUsername();
        if ($username === null) {
            return null;
        }
        $pdo = Db::pdo();
        $st = $pdo->prepare("SELECT * FROM user WHERE username = :u LIMIT 1");
        $st->execute([":u" => $username]);
        $row = $st->fetch();
        return is_array($row) ? $row : null;
    }

    private function studentMetaByUser(array $user): ?array
    {
        if (($user["role"] ?? "") !== "student") {
            return null;
        }
        $pdo = Db::pdo();
        $studentId = (int) ($user["student_id"] ?? 0);
        if ($studentId <= 0) {
            $stS = $pdo->prepare("SELECT id FROM student WHERE name = :n LIMIT 1");
            $stS->execute([":n" => (string) $user["username"]]);
            $s = $stS->fetch();
            if (!$s) {
                return null;
            }
            $studentId = (int) $s["id"];
            $up = $pdo->prepare("UPDATE user SET student_id = :sid WHERE id = :uid");
            $up->execute([":sid" => $studentId, ":uid" => (int) $user["id"]]);
        }
        $st = $pdo->prepare("SELECT * FROM student_meta WHERE student_id = :sid LIMIT 1");
        $st->execute([":sid" => $studentId]);
        $meta = $st->fetch();
        return is_array($meta) ? $meta : null;
    }

    private function handleAccountGet(): void
    {
        $user = $this->currentUser();
        if (!$user) {
            $this->redirect("/login");
            return;
        }
        $meta = $this->studentMetaByUser($user);
        $this->renderAccountPage($user, $meta, null, null);
    }

    private function renderAccountPage(array $user, ?array $meta, ?string $message, ?string $error): void
    {
        $this->ensureProfilePrivacyColumn();
        $streakCtx = null;
        $streakCalendar = [];
        $streakFreezes = null;
        $roadBalanceText = null;
        $dailyMissions = [];
        $weeklyProgress = null;
        $achievements = [];
        $cefrProgress = [];
        $nameGlowEnabled = false;
        $themePackOwned = false;
        $preferredTheme = "default";
        $profileFrameStyle = "default";
        $ownedProfileFrames = ["default"];
        $ownedChatBubbles = ["default"];
        $chatBubbleStyle = "default";
        if ($meta) {
            $meta = $this->ensureDailyRollover($meta);
            $streakCtx = $this->getDailyStreakContext($meta);
            $streakCalendar = $this->getStreakCalendar($meta);
            $streakFreezes = (int) ($meta["streak_freezes"] ?? 0);
            $roadBalanceText = $this->roadBalanceText((int) ($meta["road_tokens"] ?? 0));
            $dailyMissions = $this->buildDailyMissions($meta);
            $weeklyProgress = $this->buildWeeklyProgress((int) ($meta["student_id"] ?? 0), $meta);
            $nameGlowEnabled = !empty($meta["name_glow_enabled"]);
            $themePackOwned = !empty($meta["theme_pack_owned"]);
            $preferredTheme = (string) ($meta["preferred_theme"] ?? "default");
            $profileFrameStyle = (string) ($meta["profile_frame_style"] ?? "default");
            $chatBubbleStyle = (string) ($meta["chat_bubble_style"] ?? "default");
            $ownedProfileFrames = $this->normalizeOwnedList($meta["owned_profile_frames"] ?? null, ["default"]);
            $ownedChatBubbles = $this->normalizeOwnedList($meta["owned_chat_bubbles"] ?? null, ["default"]);
            $report = $this->buildStudentReport((string) ($user["username"] ?? ""));
            if ($report) {
                $achievements = $this->buildAchievements($meta, $report);
                $cefrProgress = $this->getCefrProgress((int) ($meta["student_id"] ?? 0));
            }
        }
        $teacherOptions = Db::pdo()->query("SELECT id, username, full_name, role FROM user WHERE role='teacher' ORDER BY full_name, username")->fetchAll();
        $this->render("account.php", [
            "csrf_token" => Security::csrfToken(),
            "user" => $user,
            "meta" => $meta,
            "message" => $message,
            "error" => $error,
            "streak_ctx" => $streakCtx,
            "streak_calendar" => $streakCalendar,
            "streak_freezes" => $streakFreezes,
            "road_balance_text" => $roadBalanceText,
            "name_glow_enabled" => $nameGlowEnabled,
            "theme_pack_owned" => $themePackOwned,
            "preferred_theme" => $preferredTheme,
            "profile_frame_style" => $profileFrameStyle,
            "owned_profile_frames" => $ownedProfileFrames,
            "owned_chat_bubbles" => $ownedChatBubbles,
            "chat_bubble_style" => $chatBubbleStyle,
            "daily_missions" => $dailyMissions,
            "weekly_progress" => $weeklyProgress,
            "achievements" => $achievements,
            "cefr_progress" => $cefrProgress,
            "teacher_options" => $teacherOptions,
        ]);
    }

    private function handleAccountPost(): void
    {
        $user = $this->currentUser();
        if (!$user) {
            $this->redirect("/login");
            return;
        }
        $this->ensureProfilePrivacyColumn();
        $username = trim((string) ($_POST["username"] ?? ""));
        $email = trim((string) ($_POST["email"] ?? ""));
        $fullName = trim((string) ($_POST["full_name"] ?? ""));
        $password = (string) ($_POST["password"] ?? "");
        $confirmPassword = (string) ($_POST["confirm_password"] ?? "");
        $profilePublic = ((string) ($_POST["profile_public"] ?? "1")) === "1";
        $dailyTarget = isset($_POST["daily_target"]) ? (int) $_POST["daily_target"] : null;
        $teacherId = (string) ($_POST["teacher_id"] ?? "");
        if ($username === "" || $fullName === "") {
            $this->renderAccountPage($user, $this->studentMetaByUser($user), null, "Username and full name are required.");
            return;
        }
        if ($password !== "" && $password !== $confirmPassword) {
            $this->renderAccountPage($user, $this->studentMetaByUser($user), null, "Password confirmation does not match.");
            return;
        }
        $pdo = Db::pdo();
        $imagePath = null;
        if (!empty($_FILES["profile_image"]["tmp_name"])) {
            $tmp = (string) $_FILES["profile_image"]["tmp_name"];
            $name = (string) ($_FILES["profile_image"]["name"] ?? "upload");
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, ["png", "jpg", "jpeg", "gif", "webp"], true)) {
                $ext = "png";
            }
            $dir = BASE_DIR . "/static/uploads";
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $fileName = preg_replace('/[^A-Za-z0-9_-]+/', '_', $username) . "_" . time() . "." . $ext;
            $dest = $dir . "/" . $fileName;
            if (@move_uploaded_file($tmp, $dest)) {
                $imagePath = "uploads/" . $fileName;
            }
        }
        $query = "UPDATE user SET username = :u, email = :e, full_name = :f, profile_public = :pp";
        if ($password !== "") {
            $query .= ", password = :p";
        }
        if ($imagePath !== null) {
            $query .= ", profile_image = :img";
        }
        if ($teacherId !== "" && (string) ($user["role"] ?? "") === "student") {
            if ($teacherId === "individual") {
                $query .= ", teacher_id = NULL";
            } elseif (ctype_digit($teacherId)) {
                $query .= ", teacher_id = :tid";
            }
        }
        $query .= " WHERE id = :id";
        $st = $pdo->prepare($query);
        $params = [
            ":u" => $username,
            ":e" => $email !== "" ? $email : null,
            ":f" => $fullName,
            ":pp" => $profilePublic ? 1 : 0,
            ":id" => (int) $user["id"],
        ];
        if ($password !== "") {
            $params[":p"] = $password;
        }
        if ($imagePath !== null) {
            $params[":img"] = $imagePath;
        }
        if ($teacherId !== "" && (string) ($user["role"] ?? "") === "student" && ctype_digit($teacherId)) {
            $params[":tid"] = (int) $teacherId;
        }
        $st->execute($params);
        if ((string) ($user["role"] ?? "") === "student" && $dailyTarget !== null) {
            $dailyTarget = max(1, min(200, $dailyTarget));
            $meta = $this->studentMetaByUser($user);
            if ($meta) {
                $pdo->prepare("UPDATE student_meta SET daily_target = :d WHERE id = :id")
                    ->execute([":d" => $dailyTarget, ":id" => (int) $meta["id"]]);
            }
        }
        $_SESSION["username"] = $username;
        $fresh = $this->currentUser();
        if ($fresh) {
            $this->renderAccountPage($fresh, $this->studentMetaByUser($fresh), "Account updated.", null);
            return;
        }
        $this->redirect("/account");
    }

    private function handleAccountDeleteConfirmGet(?string $error): void
    {
        $this->render("account_delete_confirm.php", [
            "csrf_token" => Security::csrfToken(),
            "turnstile_sitekey" => Config::turnstileSiteKey(),
            "error" => $error,
        ]);
    }

    private function handleAccountDeleteConfirmPost(): void
    {
        $user = $this->currentUser();
        if (!$user) {
            $this->redirect("/login");
            return;
        }
        $confirmText = trim((string) ($_POST["confirm_text"] ?? ""));
        $captchaOk = $this->turnstileEnabled()
            ? Security::verifyTurnstile($_POST["cf-turnstile-response"] ?? null, $this->clientIp())
            : true;
        if (!$captchaOk) {
            $this->handleAccountDeleteConfirmGet("Invalid CAPTCHA.");
            return;
        }
        if ($confirmText !== "DELETE") {
            $this->handleAccountDeleteConfirmGet('Type DELETE to confirm.');
            return;
        }
        if (!$this->deleteAccountInternal($user)) {
            http_response_code(403);
            $this->render("blocked.php", []);
            return;
        }
        session_destroy();
        $this->redirect("/");
    }

    private function deleteAccountInternal(array $user): bool
    {
        if ($this->isFounder($user)) {
            return false;
        }
        $pdo = Db::pdo();
        try {
            $pdo->beginTransaction();
            $uid = (int) $user["id"];
            $studentId = (int) ($user["student_id"] ?? 0);
            $pdo->prepare("UPDATE user SET teacher_id = NULL WHERE teacher_id = :id")->execute([":id" => $uid]);
            $pdo->prepare("DELETE FROM notification WHERE user_id = :id")->execute([":id" => $uid]);
            $pdo->prepare("DELETE FROM friend_request WHERE from_user_id = :id OR to_user_id = :id")->execute([":id" => $uid]);
            $pdo->prepare("DELETE FROM friend WHERE user_id = :id OR friend_user_id = :id")->execute([":id" => $uid]);
            $pdo->prepare("DELETE FROM `message` WHERE from_user_id = :id OR to_user_id = :id")->execute([":id" => $uid]);
            $pdo->prepare("DELETE FROM user WHERE id = :id")->execute([":id" => $uid]);
            if ((string) ($user["role"] ?? "") === "student" && $studentId > 0) {
                try {
                    $pdo->prepare("DELETE FROM student_mode_stat WHERE stat_id IN (SELECT id FROM student_word_stat WHERE student_id = :sid)")->execute([":sid" => $studentId]);
                } catch (\Throwable $e) {}
                $pdo->prepare("DELETE FROM student_word_stat WHERE student_id = :sid")->execute([":sid" => $studentId]);
                $pdo->prepare("DELETE FROM student_daily_stat WHERE student_id = :sid")->execute([":sid" => $studentId]);
                $pdo->prepare("DELETE FROM student_meta WHERE student_id = :sid")->execute([":sid" => $studentId]);
                $pdo->prepare("DELETE FROM classroom_member WHERE student_id = :sid")->execute([":sid" => $studentId]);
                $pdo->prepare("DELETE FROM student WHERE id = :sid")->execute([":sid" => $studentId]);
            }
            $pdo->commit();
            return true;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return false;
        }
    }

    private function handleShopGet(): void
    {
        $user = $this->currentUser();
        if (!$user) {
            $this->redirect("/login");
            return;
        }
        $isStudent = (string) ($user["role"] ?? "") === "student";
        $meta = $isStudent ? $this->studentMetaByUser($user) : null;
        if ($meta) {
            $meta = $this->ensureDailyRollover($meta);
        }
        $products = [
            ["id" => "streak_freeze", "name" => "Streak Freeze", "desc" => "Protect your streak for one missed day.", "cost" => self::ROAD_STREAK_FREEZE_COST, "active" => true],
            ["id" => "freeze_pack", "name" => "Freeze Pack (x3)", "desc" => "Get 3 streak freezes at a discounted rate.", "cost" => 50, "active" => true],
            ["id" => "name_glow", "name" => "Name Glow", "desc" => "Animated glow around your displayed name.", "cost" => self::ROAD_NAME_GLOW_COST, "active" => true],
            ["id" => "theme_pack", "name" => "Theme Pack", "desc" => "Unlock Aurora and Ocean themes for your account.", "cost" => self::ROAD_THEME_PACK_COST, "active" => true],
            ["id" => "frame_cyan", "name" => "Cyan Frame", "desc" => "Neon cyan profile frame.", "cost" => self::ROAD_PROFILE_FRAME_CYAN_COST, "active" => true],
            ["id" => "frame_gold", "name" => "Gold Frame", "desc" => "Premium gold profile frame.", "cost" => self::ROAD_PROFILE_FRAME_GOLD_COST, "active" => true],
            ["id" => "bubble_ocean", "name" => "Ocean Bubble", "desc" => "A cool aqua chat bubble style.", "cost" => self::ROAD_CHAT_BUBBLE_COST, "active" => true],
            ["id" => "bubble_sunset", "name" => "Sunset Bubble", "desc" => "Warm gradient chat bubble style.", "cost" => self::ROAD_CHAT_BUBBLE_COST, "active" => true],
        ];
        $roadBalanceText = $meta ? $this->roadBalanceText((int) ($meta["road_tokens"] ?? 0)) : "0 Road";
        $this->render("shop.php", [
            "csrf_token" => Security::csrfToken(),
            "user" => $user,
            "meta" => $meta,
            "is_student" => $isStudent,
            "products" => $products,
            "message" => null,
            "error" => null,
            "road_balance_text" => $roadBalanceText,
            "streak_freezes" => $meta ? (int) ($meta["streak_freezes"] ?? 0) : 0,
            "name_glow_enabled" => $meta ? !empty($meta["name_glow_enabled"]) : false,
            "theme_pack_owned" => $meta ? !empty($meta["theme_pack_owned"]) : false,
            "preferred_theme" => $meta ? (string) ($meta["preferred_theme"] ?? "default") : "default",
            "owned_profile_frames" => $meta ? $this->normalizeOwnedList($meta["owned_profile_frames"] ?? null, ["default"]) : ["default"],
            "profile_frame_style" => $meta ? (string) ($meta["profile_frame_style"] ?? "default") : "default",
            "owned_chat_bubbles" => $meta ? $this->normalizeOwnedList($meta["owned_chat_bubbles"] ?? null, ["default"]) : ["default"],
            "chat_bubble_style" => $meta ? (string) ($meta["chat_bubble_style"] ?? "default") : "default",
        ]);
    }

    private function handleShopPost(): void
    {
        $user = $this->currentUser();
        if (!$user) {
            $this->redirect("/login");
            return;
        }
        $meta = $this->studentMetaByUser($user);
        if (!$meta) {
            $this->handleShopGet();
            return;
        }
        $meta = $this->ensureDailyRollover($meta);
        $item = (string) ($_POST["item"] ?? "");
        $road = (int) ($meta["road_tokens"] ?? 0);
        $freeze = (int) ($meta["streak_freezes"] ?? 0);
        $message = null;
        $error = null;
        $ownedFrames = $this->normalizeOwnedList($meta["owned_profile_frames"] ?? null, ["default"]);
        $ownedBubbles = $this->normalizeOwnedList($meta["owned_chat_bubbles"] ?? null, ["default"]);
        $profileFrameStyle = (string) ($meta["profile_frame_style"] ?? "default");
        $chatBubbleStyle = (string) ($meta["chat_bubble_style"] ?? "default");
        $themePackOwned = !empty($meta["theme_pack_owned"]);
        $preferredTheme = (string) ($meta["preferred_theme"] ?? "default");

        if ($item === "streak_freeze") {
            if ($road < self::ROAD_STREAK_FREEZE_COST) {
                $error = "Not enough Road. You need " . self::ROAD_STREAK_FREEZE_COST . " Roads.";
            } else {
                $road -= self::ROAD_STREAK_FREEZE_COST;
                $freeze += 1;
                $message = "Purchased: Streak Freeze (+1).";
            }
        } elseif ($item === "freeze_pack") {
            $cost = 50;
            if ($road < $cost) {
                $error = "Not enough Road. You need {$cost} Roads.";
            } else {
                $road -= $cost;
                $freeze += 3;
                $message = "Purchased: Freeze Pack (+3).";
            }
        } elseif ($item === "name_glow") {
            if (!empty($meta["name_glow_enabled"])) {
                $message = "Name Glow is already active.";
            } elseif ($road < self::ROAD_NAME_GLOW_COST) {
                $error = "Not enough Road. You need " . self::ROAD_NAME_GLOW_COST . " Roads.";
            } else {
                $road -= self::ROAD_NAME_GLOW_COST;
                $meta["name_glow_enabled"] = 1;
                $message = "Purchased: Name Glow is now active.";
            }
        } elseif ($item === "theme_pack") {
            if ($themePackOwned) {
                $message = "Theme Pack is already unlocked.";
            } elseif ($road < self::ROAD_THEME_PACK_COST) {
                $error = "Not enough Road. You need " . self::ROAD_THEME_PACK_COST . " Roads.";
            } else {
                $road -= self::ROAD_THEME_PACK_COST;
                $themePackOwned = true;
                $preferredTheme = "aurora";
                $message = "Purchased: Theme Pack unlocked (Aurora/Ocean).";
            }
        } elseif (str_starts_with($item, "equip_theme:")) {
            if (!$themePackOwned) {
                $error = "Theme Pack is required to use extra themes.";
            } else {
                $selected = strtolower(trim(substr($item, strlen("equip_theme:"))));
                if (!in_array($selected, ["default", "aurora", "ocean"], true)) {
                    $error = "Invalid theme selection.";
                } else {
                    $preferredTheme = $selected;
                    $message = "Theme updated to " . ucfirst($selected) . ".";
                }
            }
        } elseif ($item === "frame_cyan") {
            if (in_array("cyan", $ownedFrames, true)) {
                $message = "Cyan Frame is already owned.";
            } elseif ($road < self::ROAD_PROFILE_FRAME_CYAN_COST) {
                $error = "Not enough Road. You need " . self::ROAD_PROFILE_FRAME_CYAN_COST . " Roads.";
            } else {
                $road -= self::ROAD_PROFILE_FRAME_CYAN_COST;
                $ownedFrames[] = "cyan";
                $message = "Purchased: Cyan Frame.";
            }
        } elseif ($item === "frame_gold") {
            if (in_array("gold", $ownedFrames, true)) {
                $message = "Gold Frame is already owned.";
            } elseif ($road < self::ROAD_PROFILE_FRAME_GOLD_COST) {
                $error = "Not enough Road. You need " . self::ROAD_PROFILE_FRAME_GOLD_COST . " Roads.";
            } else {
                $road -= self::ROAD_PROFILE_FRAME_GOLD_COST;
                $ownedFrames[] = "gold";
                $message = "Purchased: Gold Frame.";
            }
        } elseif ($item === "bubble_ocean") {
            if (in_array("ocean", $ownedBubbles, true)) {
                $message = "Ocean Bubble is already owned.";
            } elseif ($road < self::ROAD_CHAT_BUBBLE_COST) {
                $error = "Not enough Road. You need " . self::ROAD_CHAT_BUBBLE_COST . " Roads.";
            } else {
                $road -= self::ROAD_CHAT_BUBBLE_COST;
                $ownedBubbles[] = "ocean";
                $message = "Purchased: Ocean Bubble.";
            }
        } elseif ($item === "bubble_sunset") {
            if (in_array("sunset", $ownedBubbles, true)) {
                $message = "Sunset Bubble is already owned.";
            } elseif ($road < self::ROAD_CHAT_BUBBLE_COST) {
                $error = "Not enough Road. You need " . self::ROAD_CHAT_BUBBLE_COST . " Roads.";
            } else {
                $road -= self::ROAD_CHAT_BUBBLE_COST;
                $ownedBubbles[] = "sunset";
                $message = "Purchased: Sunset Bubble.";
            }
        } elseif (str_starts_with($item, "equip_frame:")) {
            $selected = strtolower(trim(substr($item, strlen("equip_frame:"))));
            if (!in_array($selected, $ownedFrames, true)) {
                $error = "Frame not owned.";
            } else {
                $profileFrameStyle = $selected;
                $message = "Profile frame updated.";
            }
        } elseif (str_starts_with($item, "equip_bubble:")) {
            $selected = strtolower(trim(substr($item, strlen("equip_bubble:"))));
            if (!in_array($selected, $ownedBubbles, true)) {
                $error = "Chat bubble not owned.";
            } else {
                $chatBubbleStyle = $selected;
                $message = "Chat bubble updated.";
            }
        } else {
            $error = "Unknown item.";
        }

        if ($error === null) {
            $pdo = Db::pdo();
            $st = $pdo->prepare("
                UPDATE student_meta
                SET road_tokens = :r,
                    streak_freezes = :s,
                    name_glow_enabled = :ng,
                    theme_pack_owned = :tp,
                    preferred_theme = :pt,
                    owned_profile_frames = :opf,
                    profile_frame_style = :pfs,
                    owned_chat_bubbles = :ocb,
                    chat_bubble_style = :cbs
                WHERE id = :id
            ");
            $st->execute([
                ":r" => $road,
                ":s" => $freeze,
                ":ng" => !empty($meta["name_glow_enabled"]) ? 1 : 0,
                ":tp" => $themePackOwned ? 1 : 0,
                ":pt" => $preferredTheme,
                ":opf" => json_encode(array_values($ownedFrames), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ":pfs" => $profileFrameStyle,
                ":ocb" => json_encode(array_values($ownedBubbles), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ":cbs" => $chatBubbleStyle,
                ":id" => (int) $meta["id"],
            ]);
        }

        $this->handleShopGet();
    }

    private function handleLeaderboardGet(): void
    {
        $pdo = Db::pdo();
        $q = $pdo->query("
            SELECT u.username, u.full_name, u.role, u.badge_override, u.verified_badge,
                   COALESCE(sm.road_tokens, 0) AS road_tokens,
                   COALESCE(sm.daily_streak, 0) AS daily_streak,
                   COALESCE(SUM(sws.correct),0) AS total_correct
            FROM user u
            LEFT JOIN student_meta sm ON sm.student_id = u.student_id
            LEFT JOIN student_word_stat sws ON sws.student_id = sm.student_id
            WHERE u.role = 'student'
            GROUP BY u.id
            ORDER BY road_tokens DESC, daily_streak DESC, u.username ASC
            LIMIT 200
        ");
        $rows = $q->fetchAll();
        $rank = 1;
        foreach ($rows as &$r) {
            $r["rank"] = $rank++;
            $r["badge"] = $this->getBadgeLabel($r, (int) ($r["total_correct"] ?? 0));
        }
        unset($r);
        $this->render("leaderboard.php", ["rows" => $rows]);
    }

    private function handleNotificationsGet(): void
    {
        $user = $this->currentUser();
        if (!$user) {
            $this->redirect("/login");
            return;
        }
        $pdo = Db::pdo();
        $st = $pdo->prepare("SELECT message, kind, is_read, created_at FROM notification WHERE user_id = :uid ORDER BY created_at DESC LIMIT 200");
        $st->execute([":uid" => (int) $user["id"]]);
        $rows = $st->fetchAll();
        $this->render("notifications.php", [
            "csrf_token" => Security::csrfToken(),
            "rows" => $rows,
        ]);
    }

    private function markNotificationsRead(): void
    {
        $user = $this->currentUser();
        if (!$user) {
            return;
        }
        $pdo = Db::pdo();
        $st = $pdo->prepare("UPDATE notification SET is_read = 1 WHERE user_id = :uid AND is_read = 0");
        $st->execute([":uid" => (int) $user["id"]]);
    }

    private function handleUsersGet(): void
    {
        $this->ensureProfilePrivacyColumn();
        $q = trim((string) ($_GET["q"] ?? ""));
        $pdo = Db::pdo();
        if ($q === "") {
            $st = $pdo->query("
                SELECT u.username, u.full_name, u.role, u.verified_badge, u.badge_override, u.student_id, u.profile_image,
                       COALESCE(u.profile_public,1) AS profile_public,
                       COALESCE(SUM(sws.correct),0) AS total_correct
                FROM user u
                LEFT JOIN student_word_stat sws ON sws.student_id = u.student_id
                GROUP BY u.id
                ORDER BY u.username ASC
                LIMIT 200
            ");
            $rows = $st->fetchAll();
        } else {
            $st = $pdo->prepare("
                SELECT u.username, u.full_name, u.role, u.verified_badge, u.badge_override, u.student_id, u.profile_image,
                       COALESCE(u.profile_public,1) AS profile_public,
                       COALESCE(SUM(sws.correct),0) AS total_correct
                FROM user u
                LEFT JOIN student_word_stat sws ON sws.student_id = u.student_id
                WHERE u.username LIKE :q OR u.full_name LIKE :q
                GROUP BY u.id
                ORDER BY u.username ASC
                LIMIT 200
            ");
            $st->execute([":q" => "%" . $q . "%"]);
            $rows = $st->fetchAll();
        }
        foreach ($rows as &$row) {
            $row["special_badge_label"] = $this->specialBadgeLabel(
                (string) ($row["username"] ?? ""),
                (string) ($row["role"] ?? ""),
                isset($row["badge_override"]) ? (string) $row["badge_override"] : null
            );
            $row["badge"] = $this->getBadgeLabel($row, (int) ($row["total_correct"] ?? 0));
        }
        unset($row);
        $viewer = $this->currentUser();
        $this->render("users.php", ["rows" => $rows, "q" => $q, "viewer" => $viewer]);
    }

    private function handleProfileGet(string $username): void
    {
        $this->ensureProfilePrivacyColumn();
        $pdo = Db::pdo();
        $st = $pdo->prepare("
            SELECT u.username, u.full_name, u.role, u.badge_override, u.verified_badge, u.profile_image,
                   COALESCE(u.profile_public,1) AS profile_public,
                   s.id AS student_id,
                   COALESCE(sm.streak,0) AS streak, COALESCE(sm.best_streak,0) AS best_streak,
                   COALESCE(sm.daily_done,0) AS daily_done, COALESCE(sm.daily_target,20) AS daily_target,
                   COALESCE(sm.road_tokens,0) AS road_tokens,
                   sm.streak_freezes, sm.daily_streak, sm.best_daily_streak, sm.name_glow_enabled,
                   sm.profile_frame_style, sm.streak_history, sm.mission_day, sm.mission_questions_done,
                   sm.mission_best_correct_streak, sm.mission_claimed, sm.weekly_bonus_week
            FROM user u
            LEFT JOIN student s ON s.id = u.student_id
            LEFT JOIN student_meta sm ON sm.student_id = s.id
            WHERE u.username = :u
            LIMIT 1
        ");
        $st->execute([":u" => $username]);
        $u = $st->fetch();
        if (!$u) {
            http_response_code(404);
            $this->render("404.php", []);
            return;
        }
        $viewer = $this->currentUser();
        $viewerIsOwner = $viewer && strtolower((string) ($viewer["username"] ?? "")) === strtolower((string) ($u["username"] ?? ""));
        $viewerIsAdmin = $viewer && (string) ($viewer["role"] ?? "") === "admin";
        if (!(bool) ($u["profile_public"] ?? 1) && !$viewerIsOwner && !$viewerIsAdmin) {
            http_response_code(403);
            $this->render("profile_private.php", ["username" => (string) ($u["username"] ?? "")]);
            return;
        }
        $u["special_badge_label"] = $this->specialBadgeLabel(
            (string) ($u["username"] ?? ""),
            isset($u["role"]) ? (string) $u["role"] : null,
            isset($u["badge_override"]) ? (string) $u["badge_override"] : null
        );
        $report = null;
        $streakCalendar = [];
        $dailyMissions = [];
        $weeklyProgress = null;
        $achievements = [];
        $cefrProgress = [];
        $badge = null;
        $roadBalanceText = null;
        $nameGlowEnabled = !empty($u["name_glow_enabled"]);
        $profileFrameStyle = (string) ($u["profile_frame_style"] ?? "default");
        if (!empty($u["student_id"])) {
            $meta = $this->studentMetaByUser($u);
            if ($meta) {
                $meta = $this->ensureDailyRollover($meta);
                $report = $this->buildStudentReport((string) ($u["username"] ?? ""));
                $streakCalendar = $this->getStreakCalendar($meta);
                $dailyMissions = $this->buildDailyMissions($meta);
                $weeklyProgress = $this->buildWeeklyProgress((int) ($meta["student_id"] ?? 0), $meta);
                if ($report) {
                    $achievements = $this->buildAchievements($meta, $report);
                }
                $cefrProgress = $this->getCefrProgress((int) ($meta["student_id"] ?? 0));
                $roadBalanceText = $this->roadBalanceText((int) ($meta["road_tokens"] ?? 0));
                $badge = $report ? ($report["badge"] ?? null) : null;
            }
        }
        $this->render("profile.php", [
            "u" => $u,
            "report" => $report,
            "streak_calendar" => $streakCalendar,
            "streak_freezes" => isset($u["streak_freezes"]) ? (int) $u["streak_freezes"] : null,
            "daily_missions" => $dailyMissions,
            "weekly_progress" => $weeklyProgress,
            "achievements" => $achievements,
            "cefr_progress" => $cefrProgress,
            "badge" => $badge,
            "road_balance_text" => $roadBalanceText,
            "name_glow_enabled" => $nameGlowEnabled,
            "profile_frame_style" => $profileFrameStyle,
        ]);
    }

    private function handleWordsGet(): void
    {
        $q = trim((string) ($_GET["q"] ?? ""));
        $pdo = Db::pdo();
        if ($q === "") {
            $st = $pdo->query("SELECT keyword, slug, description FROM seo_keyword ORDER BY keyword ASC LIMIT 300");
            $rows = $st->fetchAll();
        } else {
            $st = $pdo->prepare("SELECT keyword, slug, description FROM seo_keyword WHERE keyword LIKE :q OR slug LIKE :q OR description LIKE :q ORDER BY keyword ASC LIMIT 300");
            $st->execute([":q" => "%" . $q . "%"]);
            $rows = $st->fetchAll();
        }
        $this->render("words.php", ["rows" => $rows, "q" => $q]);
    }

    private function handleWordGet(string $slug): void
    {
        $pdo = Db::pdo();
        $st = $pdo->prepare("SELECT keyword, slug, description FROM seo_keyword WHERE LOWER(slug) = LOWER(:s) LIMIT 1");
        $st->execute([":s" => $slug]);
        $row = $st->fetch();
        if (!$row) {
            http_response_code(404);
            $this->render("404.php", []);
            return;
        }
        $this->render("word.php", ["w" => $row]);
    }

    private function areFriends(int $userId, int $friendUserId): bool
    {
        $pdo = Db::pdo();
        $st = $pdo->prepare("SELECT id FROM friend WHERE user_id = :u AND friend_user_id = :f LIMIT 1");
        $st->execute([":u" => $userId, ":f" => $friendUserId]);
        return (bool) $st->fetch();
    }

    private function handleFriendsGet(): void
    {
        $user = $this->currentUser();
        if (!$user) {
            $this->redirect("/login");
            return;
        }
        $pdo = Db::pdo();
        $st = $pdo->prepare("
            SELECT u.id, u.username, u.full_name, u.role, u.badge_override, u.verified_badge, u.student_id
            FROM friend f
            INNER JOIN user u ON u.id = f.friend_user_id
            WHERE f.user_id = :uid
            ORDER BY u.username ASC
        ");
        $st->execute([":uid" => (int) $user["id"]]);
        $friends = $st->fetchAll();
        $statQ = $pdo->prepare("SELECT COALESCE(SUM(correct),0) AS c FROM student_word_stat WHERE student_id = :sid");
        foreach ($friends as &$f) {
            $f["special_badge_label"] = $this->specialBadgeLabel(
                (string) ($f["username"] ?? ""),
                (string) ($f["role"] ?? ""),
                isset($f["badge_override"]) ? (string) $f["badge_override"] : null
            );
            $totalCorrect = 0;
            if (!empty($f["student_id"])) {
                $statQ->execute([":sid" => (int) $f["student_id"]]);
                $row = $statQ->fetch();
                $totalCorrect = (int) ($row["c"] ?? 0);
            }
            $f["badge"] = $this->getBadgeLabel($f, $totalCorrect);
        }
        unset($f);

        $in = $pdo->prepare("
            SELECT fr.id, u.username, u.full_name
            FROM friend_request fr
            INNER JOIN user u ON u.id = fr.from_user_id
            WHERE fr.to_user_id = :uid
            ORDER BY fr.id DESC
        ");
        $in->execute([":uid" => (int) $user["id"]]);
        $incoming = $in->fetchAll();

        $out = $pdo->prepare("
            SELECT fr.id, u.username, u.full_name
            FROM friend_request fr
            INNER JOIN user u ON u.id = fr.to_user_id
            WHERE fr.from_user_id = :uid
            ORDER BY fr.id DESC
        ");
        $out->execute([":uid" => (int) $user["id"]]);
        $outgoing = $out->fetchAll();

        $this->render("friends.php", [
            "csrf_token" => Security::csrfToken(),
            "friends" => $friends,
            "incoming" => $incoming,
            "outgoing" => $outgoing,
            "can_grant" => (strtolower((string) $user["username"]) === "linustor"),
        ]);
    }

    private function handleFriendRequestPost(): void
    {
        $user = $this->currentUser();
        if (!$user) {
            $this->redirect("/login");
            return;
        }
        $targetName = trim((string) ($_POST["username"] ?? ""));
        $target = $this->findUserByUsername($targetName);
        if (!$target || (int) $target["id"] === (int) $user["id"]) {
            $this->redirect("/users");
            return;
        }
        if ($this->areFriends((int) $user["id"], (int) $target["id"])) {
            $this->redirect("/users");
            return;
        }
        $pdo = Db::pdo();
        $check = $pdo->prepare("SELECT id FROM friend_request WHERE from_user_id = :f AND to_user_id = :t LIMIT 1");
        $check->execute([":f" => (int) $user["id"], ":t" => (int) $target["id"]]);
        if (!$check->fetch()) {
            $ins = $pdo->prepare("INSERT INTO friend_request (from_user_id, to_user_id, created_at) VALUES (:f, :t, UTC_TIMESTAMP())");
            $ins->execute([":f" => (int) $user["id"], ":t" => (int) $target["id"]]);
        }
        $this->redirect("/users");
    }

    private function handleFriendAcceptPost(): void
    {
        $user = $this->currentUser();
        if (!$user) {
            $this->redirect("/login");
            return;
        }
        $reqId = (int) ($_POST["request_id"] ?? 0);
        $pdo = Db::pdo();
        $st = $pdo->prepare("SELECT id, from_user_id, to_user_id FROM friend_request WHERE id = :id LIMIT 1");
        $st->execute([":id" => $reqId]);
        $req = $st->fetch();
        if (!$req || (int) $req["to_user_id"] !== (int) $user["id"]) {
            $this->redirect("/friends");
            return;
        }
        if (!$this->areFriends((int) $user["id"], (int) $req["from_user_id"])) {
            $ins1 = $pdo->prepare("INSERT INTO friend (user_id, friend_user_id, created_at) VALUES (:u, :f, UTC_TIMESTAMP())");
            $ins1->execute([":u" => (int) $user["id"], ":f" => (int) $req["from_user_id"]]);
        }
        if (!$this->areFriends((int) $req["from_user_id"], (int) $user["id"])) {
            $ins2 = $pdo->prepare("INSERT INTO friend (user_id, friend_user_id, created_at) VALUES (:u, :f, UTC_TIMESTAMP())");
            $ins2->execute([":u" => (int) $req["from_user_id"], ":f" => (int) $user["id"]]);
        }
        $del = $pdo->prepare("DELETE FROM friend_request WHERE id = :id");
        $del->execute([":id" => $reqId]);
        $this->redirect("/friends");
    }

    private function handleFriendDeclinePost(): void
    {
        $user = $this->currentUser();
        if (!$user) {
            $this->redirect("/login");
            return;
        }
        $reqId = (int) ($_POST["request_id"] ?? 0);
        $pdo = Db::pdo();
        $del = $pdo->prepare("DELETE FROM friend_request WHERE id = :id AND to_user_id = :uid");
        $del->execute([":id" => $reqId, ":uid" => (int) $user["id"]]);
        $this->redirect("/friends");
    }

    private function handleFriendRemovePost(): void
    {
        $user = $this->currentUser();
        if (!$user) {
            $this->redirect("/login");
            return;
        }
        $friend = $this->findUserByUsername((string) ($_POST["username"] ?? ""));
        if (!$friend) {
            $this->redirect("/friends");
            return;
        }
        $pdo = Db::pdo();
        $d1 = $pdo->prepare("DELETE FROM friend WHERE user_id = :u AND friend_user_id = :f");
        $d2 = $pdo->prepare("DELETE FROM friend WHERE user_id = :f AND friend_user_id = :u");
        $d1->execute([":u" => (int) $user["id"], ":f" => (int) $friend["id"]]);
        $d2->execute([":u" => (int) $user["id"], ":f" => (int) $friend["id"]]);
        $this->redirect("/friends");
    }

    private function handleFriendGrantBadgePost(): void
    {
        $user = $this->currentUser();
        if (!$user || strtolower((string) $user["username"]) !== "linustor") {
            $this->redirect("/friends");
            return;
        }
        $target = $this->findUserByUsername((string) ($_POST["username"] ?? ""));
        if (!$target) {
            $this->redirect("/friends");
            return;
        }
        $badge = trim((string) ($_POST["badge"] ?? ""));
        $pdo = Db::pdo();
        $up = $pdo->prepare("UPDATE user SET badge_override = :b WHERE id = :id");
        $up->execute([":b" => (strtolower($badge) === "none" ? null : $badge), ":id" => (int) $target["id"]]);
        $this->createNotification((int) $target["id"], "New badge granted: " . $badge, "info");
        $this->logAudit($user, "grant_friend_badge", "user", (int) $target["id"], [
            "username" => $target["username"] ?? null,
            "badge" => (strtolower($badge) === "none" ? null : $badge),
        ]);
        $this->redirect("/friends");
    }

    private function handleMessagesGet(string $username): void
    {
        $user = $this->currentUser();
        if (!$user) {
            $this->redirect("/login");
            return;
        }
        $peer = $this->findUserByUsername($username);
        if (!$peer) {
            http_response_code(404);
            $this->render("404.php", []);
            return;
        }
        $messages = [];
        $chatBubbleStyle = "default";
        $pageError = null;
        $isFounder = strtolower((string) $user["username"]) === "linustor";
        try {
            $isFriend = $this->areFriends((int) $user["id"], (int) $peer["id"]);
        } catch (\Throwable $e) {
            $isFriend = false;
        }
        if (!$isFounder && (string) $peer["username"] !== "RoadToWord" && !$isFriend) {
            http_response_code(403);
            echo "Not friends";
            return;
        }
        try {
            $pdo = Db::pdo();
            $messageTable = $this->resolveMessageTableName();
            if ($messageTable === null) {
                throw new \RuntimeException("message table not found");
            }
            try {
                $st = $pdo->prepare("
                    SELECT from_user_id, to_user_id, content, attachment_name, attachment_expires_at, created_at, id
                    FROM " . $this->quoteIdent($messageTable) . "
                    WHERE (from_user_id = :u1 AND to_user_id = :p1) OR (from_user_id = :p2 AND to_user_id = :u2)
                    ORDER BY created_at ASC
                ");
                $st->execute([
                    ":u1" => (int) $user["id"],
                    ":p1" => (int) $peer["id"],
                    ":p2" => (int) $peer["id"],
                    ":u2" => (int) $user["id"],
                ]);
                $rows = $st->fetchAll();
            } catch (\Throwable $e1) {
                try {
                    $cols = [];
                    $cols = $this->getTableColumns($messageTable);
                    $idCol = $this->firstMatchingColumn($cols, ["id", "message_id"]);
                    $fromCol = $this->firstMatchingColumn($cols, ["from_user_id", "from_id", "sender_id", "user_id"]);
                    $toCol = $this->firstMatchingColumn($cols, ["to_user_id", "to_id", "receiver_id", "recipient_id", "target_user_id"]);
                    $contentCol = $this->firstMatchingColumn($cols, ["content", "message", "body", "text"]);
                    $createdCol = $this->firstMatchingColumn($cols, ["created_at", "sent_at", "created", "timestamp", "time"]);
                    $attachmentNameCol = $this->firstMatchingColumn($cols, ["attachment_name", "file_name"]);
                    $attachmentExpiresCol = $this->firstMatchingColumn($cols, ["attachment_expires_at", "expires_at", "attachment_expiry"]);

                    if ($idCol === null || $fromCol === null || $toCol === null || $contentCol === null || $createdCol === null) {
                        throw new \RuntimeException("Required message columns not found");
                    }

                    $selectParts = [
                        $this->quoteIdent($idCol) . " AS id",
                        $this->quoteIdent($fromCol) . " AS from_user_id",
                        $this->quoteIdent($toCol) . " AS to_user_id",
                        $this->quoteIdent($contentCol) . " AS content",
                        $this->quoteIdent($createdCol) . " AS created_at",
                    ];
                    $selectParts[] = $attachmentNameCol !== null
                        ? ($this->quoteIdent($attachmentNameCol) . " AS attachment_name")
                        : "NULL AS attachment_name";
                    $selectParts[] = $attachmentExpiresCol !== null
                        ? ($this->quoteIdent($attachmentExpiresCol) . " AS attachment_expires_at")
                        : "NULL AS attachment_expires_at";

                    $sql = "SELECT " . implode(", ", $selectParts)
                        . " FROM " . $this->quoteIdent($messageTable)
                        . " WHERE (" . $this->quoteIdent($fromCol) . " = :u1 AND " . $this->quoteIdent($toCol) . " = :p1)"
                        . " OR (" . $this->quoteIdent($fromCol) . " = :p2 AND " . $this->quoteIdent($toCol) . " = :u2)"
                        . " ORDER BY " . $this->quoteIdent($createdCol) . " ASC";
                    $st = $pdo->prepare($sql);
                    $st->execute([
                        ":u1" => (int) $user["id"],
                        ":p1" => (int) $peer["id"],
                        ":p2" => (int) $peer["id"],
                        ":u2" => (int) $user["id"],
                    ]);
                    $rows = $st->fetchAll();
                } catch (\Throwable $e2) {
                    $rows = [];
                    $pageError = "Chat is temporarily unavailable on this server schema. (message table schema mismatch)";
                }
            }
            $now = time();
            foreach (($rows ?? []) as $r) {
                $expRaw = (string) ($r["attachment_expires_at"] ?? "");
                $expTs = $expRaw !== "" ? strtotime($expRaw . " UTC") : 0;
                $messages[] = [
                    "from_me" => ((int) ($r["from_user_id"] ?? 0) === (int) $user["id"]),
                    "content" => (string) ($r["content"] ?? ""),
                    "created_at" => (string) ($r["created_at"] ?? ""),
                    "attachment" => !empty($r["attachment_name"]) ? [
                        "name" => (string) $r["attachment_name"],
                        "url" => "/messages/attachment/" . (int) ($r["id"] ?? 0),
                        "expired" => ($expTs > 0 && $expTs < $now),
                    ] : null,
                ];
            }
        } catch (\Throwable $e) {
            $pageError = $pageError ?: "Chat is temporarily unavailable on this server schema.";
        }
        try {
            $meta = $this->studentMetaByUser($user);
            $chatBubbleStyle = $meta ? (string) ($meta["chat_bubble_style"] ?? "default") : "default";
        } catch (\Throwable $e) {
            // Optional personalization should never break chat.
            $chatBubbleStyle = "default";
        }
        $callUrl = null;
        try {
            $callUrl = "/call/" . rawurlencode((string) ($peer["username"] ?? ""));
        } catch (\Throwable $e) {
            $callUrl = null;
        }
        $this->render("messages.php", [
            "csrf_token" => Security::csrfToken(),
            "peer" => $peer,
            "messages" => $messages,
            "error" => $pageError,
            "chat_bubble_style" => $chatBubbleStyle,
            "call_url" => $callUrl,
        ]);
    }

    private function handleCallGet(string $username): void
    {
        $user = $this->currentUser();
        if (!$user) {
            $this->redirect("/login");
            return;
        }
        $peer = $this->findUserByUsername($username);
        if (!$peer) {
            http_response_code(404);
            $this->render("404.php", []);
            return;
        }
        $isFounder = strtolower((string) ($user["username"] ?? "")) === "linustor";
        if (
            !$isFounder
            && (string) ($peer["username"] ?? "") !== "RoadToWord"
            && !$this->areFriends((int) $user["id"], (int) $peer["id"])
        ) {
            http_response_code(403);
            $this->render("403.php", []);
            return;
        }
        try {
            $jitsiUrl = $this->buildCallRoomUrl((string) ($user["username"] ?? ""), (string) ($peer["username"] ?? ""));
        } catch (\Throwable $e) {
            http_response_code(500);
            $this->render("blocked.php", []);
            return;
        }
        $this->render("call.php", [
            "peer" => $peer,
            "jitsi_url" => $jitsiUrl,
        ]);
    }

    private function handleMessagesPost(string $username): void
    {
        $user = $this->currentUser();
        if (!$user) {
            $this->redirect("/login");
            return;
        }
        $peer = $this->findUserByUsername($username);
        if (!$peer) {
            http_response_code(404);
            $this->render("404.php", []);
            return;
        }
        $isFounder = strtolower((string) $user["username"]) === "linustor";
        if (!$isFounder && (string) $peer["username"] !== "RoadToWord" && !$this->areFriends((int) $user["id"], (int) $peer["id"])) {
            http_response_code(403);
            echo "Not friends";
            return;
        }
        $content = trim((string) ($_POST["content"] ?? ""));
        if ($content !== "") {
            $pdo = Db::pdo();
            $ins = $pdo->prepare("INSERT INTO `message` (from_user_id, to_user_id, content, created_at) VALUES (:f, :t, :c, UTC_TIMESTAMP())");
            $ins->execute([":f" => (int) $user["id"], ":t" => (int) $peer["id"], ":c" => $content]);
            $note = $pdo->prepare("INSERT INTO notification (user_id, message, kind, is_read, created_at) VALUES (:u, :m, 'info', 0, UTC_TIMESTAMP())");
            $sender = (string) ($user["full_name"] ?? "") !== "" ? (string) $user["full_name"] : (string) $user["username"];
            $note->execute([":u" => (int) $peer["id"], ":m" => $sender . ": " . $content]);
            $this->maybeHandleBotCommand($user, $peer, $content);
        }
        $this->redirect("/messages/" . rawurlencode((string) $peer["username"]));
    }

    private function handleMessageAttachmentGet(int $messageId): void
    {
        $user = $this->currentUser();
        if (!$user) {
            $this->redirect("/login");
            return;
        }
        $pdo = Db::pdo();
        $st = $pdo->prepare("SELECT * FROM `message` WHERE id = :id LIMIT 1");
        $st->execute([":id" => $messageId]);
        $m = $st->fetch();
        if (!$m || empty($m["attachment_path"])) {
            http_response_code(404);
            echo "Not found";
            return;
        }
        $isFounder = strtolower((string) $user["username"]) === "linustor";
        if (!$isFounder && (int) $user["id"] !== (int) $m["from_user_id"] && (int) $user["id"] !== (int) $m["to_user_id"]) {
            http_response_code(403);
            echo "Forbidden";
            return;
        }
        $expRaw = (string) ($m["attachment_expires_at"] ?? "");
        $expTs = $expRaw !== "" ? strtotime($expRaw . " UTC") : 0;
        if ($expTs > 0 && $expTs < time()) {
            http_response_code(410);
            echo "Attachment expired";
            return;
        }
        $path = (string) $m["attachment_path"];
        if (!is_file($path)) {
            http_response_code(404);
            echo "Not found";
            return;
        }
        $name = (string) ($m["attachment_name"] ?? basename($path));
        header("Content-Type: " . ((string) ($m["attachment_mime"] ?? "application/octet-stream")));
        header("Content-Disposition: inline; filename=\"" . str_replace('"', "", $name) . "\"");
        readfile($path);
        exit;
    }

    private function handleAdminUsersGet(): void
    {
        $user = $this->currentUser();
        if (!$user) {
            $this->redirect("/login");
            return;
        }
        $pdo = Db::pdo();
        $rows = $pdo->query("
            SELECT u.*, s.id AS student_row_id, COALESCE(sm.road_tokens, 0) AS road_tokens
            FROM user u
            LEFT JOIN student s ON s.id = u.student_id
            LEFT JOIN student_meta sm ON sm.student_id = s.id
            ORDER BY u.username ASC
            LIMIT 500
        ")->fetchAll();
        $this->render("admin_users.php", [
            "csrf_token" => Security::csrfToken(),
            "rows" => $rows,
            "is_founder" => $this->isFounder($user),
        ]);
    }

    private function handleAdminBroadcastGet(): void
    {
        $this->render("admin_broadcast.php", [
            "csrf_token" => Security::csrfToken(),
            "error" => null,
            "message_value" => "",
        ]);
    }

    private function handleAdminBroadcastPost(): void
    {
        $actor = $this->currentUser();
        if (!$actor) {
            $this->redirect("/login");
            return;
        }
        $message = trim((string) ($_POST["message"] ?? ""));
        if ($message === "") {
            $this->render("admin_broadcast.php", [
                "csrf_token" => Security::csrfToken(),
                "error" => "Message is required.",
                "message_value" => "",
            ]);
            return;
        }
        $pdo = Db::pdo();
        $users = $pdo->query("SELECT id FROM user")->fetchAll();
        $count = 0;
        foreach ($users as $u) {
            $this->createNotification((int) $u["id"], $message, "info");
            $count++;
        }
        $this->logAudit($actor, "broadcast_message", "notification", null, ["message" => $message, "count" => $count]);
        $this->redirect("/admin");
    }

    private function handleAdminAuditGet(): void
    {
        $pdo = Db::pdo();
        $rows = $pdo->query("
            SELECT a.created_at, a.action, a.entity_type, a.entity_id, a.details, u.username AS actor
            FROM audit_log a
            LEFT JOIN user u ON u.id = a.actor_user_id
            ORDER BY a.created_at DESC
            LIMIT 300
        ")->fetchAll();
        foreach ($rows as &$r) {
            $r["actor"] = (string) ($r["actor"] ?? "") !== "" ? (string) $r["actor"] : "System";
            $r["entity_id"] = (string) ($r["entity_id"] ?? "");
        }
        unset($r);
        $this->render("admin_audit.php", ["rows" => $rows]);
    }

    private function handleAdminLogsGet(): void
    {
        $pdo = Db::pdo();
        $limit = (int) ($_GET["limit"] ?? 50);
        if ($limit < 10) {
            $limit = 10;
        }
        if ($limit > 200) {
            $limit = 200;
        }
        $beforeId = null;
        if (isset($_GET["before_id"]) && trim((string) $_GET["before_id"]) !== "") {
            $tmp = filter_var((string) $_GET["before_id"], FILTER_VALIDATE_INT);
            if ($tmp !== false) {
                $beforeId = (int) $tmp;
            }
        }
        $total = null;
        if (((string) ($_GET["count"] ?? "")) === "1") {
            try {
                $row = $pdo->query("SELECT COUNT(*) AS c FROM log_entry")->fetch();
                $total = (int) ($row["c"] ?? 0);
            } catch (\Throwable $e) {
                $total = null;
            }
        }

        $lookupIp = trim((string) ($_GET["lookup_ip"] ?? ""));
        $lookupError = null;
        $lookupResult = null;
        $lookupRows = [];
        $lookupTotal = null;
        if ($lookupIp !== "") {
            if (!filter_var($lookupIp, FILTER_VALIDATE_IP)) {
                $lookupError = "Please enter a valid IP address.";
            } else {
                $geo = $this->lookupIpInfo($lookupIp);
                $hostname = @gethostbyaddr($lookupIp);
                if (!is_string($hostname) || $hostname === "" || $hostname === $lookupIp) {
                    $hostname = null;
                }
                $lookupResult = [
                    "ip" => $lookupIp,
                    "country" => $geo["country"] ?? null,
                    "city" => $geo["city"] ?? null,
                    "asn_org" => $geo["asn_org"] ?? null,
                    "country_code" => $geo["country_code"] ?? null,
                    "flag_url" => $this->countryCodeToFlagUrl((string) ($geo["country_code"] ?? "")),
                    "hostname" => $hostname,
                ];
                try {
                    $cst = $pdo->prepare("SELECT COUNT(*) AS c FROM log_entry WHERE ip_address = :ip");
                    $cst->execute([":ip" => $lookupIp]);
                    $lookupTotal = (int) (($cst->fetch()["c"] ?? 0));
                } catch (\Throwable $e) {
                    $lookupTotal = null;
                }
                $lst = $pdo->prepare("SELECT * FROM log_entry WHERE ip_address = :ip ORDER BY id DESC LIMIT 100");
                $lst->execute([":ip" => $lookupIp]);
                $lookupRows = $lst->fetchAll();
            }
        }

        if ($beforeId !== null) {
            $st = $pdo->prepare("SELECT * FROM log_entry WHERE id < :id ORDER BY id DESC LIMIT :lim");
            $st->bindValue(":id", $beforeId, \PDO::PARAM_INT);
            $st->bindValue(":lim", $limit, \PDO::PARAM_INT);
            $st->execute();
            $rows = $st->fetchAll();
        } else {
            $st = $pdo->prepare("SELECT * FROM log_entry ORDER BY id DESC LIMIT :lim");
            $st->bindValue(":lim", $limit, \PDO::PARAM_INT);
            $st->execute();
            $rows = $st->fetchAll();
        }

        $nextBeforeId = null;
        if (!empty($rows)) {
            $last = end($rows);
            $nextBeforeId = (int) ($last["id"] ?? 0);
            reset($rows);
        }
        $this->render("admin_logs.php", [
            "rows" => $rows,
            "limit" => $limit,
            "total_logs" => $total,
            "before_id" => $beforeId,
            "next_before_id" => $nextBeforeId,
            "lookup_ip" => $lookupIp,
            "lookup_error" => $lookupError,
            "lookup_result" => $lookupResult,
            "lookup_rows" => $lookupRows,
            "lookup_total" => $lookupTotal,
        ]);
    }

    private function handleAdminKeywordsGet(): void
    {
        $pdo = Db::pdo();
        $rows = $pdo->query("SELECT id, keyword, slug, description FROM seo_keyword ORDER BY keyword ASC")->fetchAll();
        $this->render("admin_keywords.php", ["rows" => $rows, "csrf_token" => Security::csrfToken()]);
    }

    private function handleAdminKeywordFormGet(string $mode, ?int $id): void
    {
        $item = ["keyword" => "", "description" => ""];
        if ($mode === "edit") {
            $pdo = Db::pdo();
            $st = $pdo->prepare("SELECT * FROM seo_keyword WHERE id = :id LIMIT 1");
            $st->execute([":id" => (int) $id]);
            $row = $st->fetch();
            if (!$row) {
                http_response_code(404);
                $this->render("404.php", []);
                return;
            }
            $item = [
                "keyword" => (string) ($row["keyword"] ?? ""),
                "description" => (string) ($row["description"] ?? ""),
                "id" => (int) $row["id"],
            ];
        }
        $this->render("admin_keyword_form.php", [
            "mode" => $mode,
            "item" => $item,
            "error" => null,
            "csrf_token" => Security::csrfToken(),
        ]);
    }

    private function handleAdminKeywordNewPost(): void
    {
        $pdo = Db::pdo();
        $keyword = $this->sanitizeText((string) ($_POST["keyword"] ?? ""));
        $description = trim((string) ($_POST["description"] ?? ""));
        $item = ["keyword" => $keyword, "description" => $description];
        if ($keyword === "" || $description === "") {
            $this->render("admin_keyword_form.php", ["mode" => "new", "item" => $item, "error" => "Keyword and description are required.", "csrf_token" => Security::csrfToken()]);
            return;
        }
        $chk = $pdo->prepare("SELECT id FROM seo_keyword WHERE LOWER(keyword) = LOWER(:k) LIMIT 1");
        $chk->execute([":k" => $keyword]);
        if ($chk->fetch()) {
            $this->render("admin_keyword_form.php", ["mode" => "new", "item" => $item, "error" => "This keyword already exists.", "csrf_token" => Security::csrfToken()]);
            return;
        }
        $slug = $this->ensureUniqueKeywordSlug($this->slugify($keyword), null);
        $st = $pdo->prepare("INSERT INTO seo_keyword (keyword, slug, description) VALUES (:k, :s, :d)");
        $st->execute([":k" => $keyword, ":s" => $slug, ":d" => $description]);
        $actor = $this->currentUser();
        $this->logAudit($actor, "create_keyword", "seo_keyword", (int) $pdo->lastInsertId(), ["keyword" => $keyword]);
        $this->redirect("/admin/keywords");
    }

    private function handleAdminKeywordEditPost(int $id): void
    {
        $pdo = Db::pdo();
        $row = $this->fetchSeoKeyword($id);
        if (!$row) {
            http_response_code(404);
            $this->render("404.php", []);
            return;
        }
        $keyword = $this->sanitizeText((string) ($_POST["keyword"] ?? ""));
        $description = trim((string) ($_POST["description"] ?? ""));
        $item = ["id" => $id, "keyword" => $keyword, "description" => $description];
        if ($keyword === "" || $description === "") {
            $this->render("admin_keyword_form.php", ["mode" => "edit", "item" => $item, "error" => "Keyword and description are required.", "csrf_token" => Security::csrfToken()]);
            return;
        }
        $chk = $pdo->prepare("SELECT id FROM seo_keyword WHERE LOWER(keyword) = LOWER(:k) AND id <> :id LIMIT 1");
        $chk->execute([":k" => $keyword, ":id" => $id]);
        if ($chk->fetch()) {
            $this->render("admin_keyword_form.php", ["mode" => "edit", "item" => $item, "error" => "This keyword already exists.", "csrf_token" => Security::csrfToken()]);
            return;
        }
        $slug = $this->ensureUniqueKeywordSlug($this->slugify($keyword), $id);
        $st = $pdo->prepare("UPDATE seo_keyword SET keyword = :k, slug = :s, description = :d WHERE id = :id");
        $st->execute([":k" => $keyword, ":s" => $slug, ":d" => $description, ":id" => $id]);
        $this->logAudit($this->currentUser(), "update_keyword", "seo_keyword", $id, ["keyword" => $keyword]);
        $this->redirect("/admin/keywords");
    }

    private function handleAdminKeywordDeletePost(int $id): void
    {
        $row = $this->fetchSeoKeyword($id);
        if (!$row) {
            http_response_code(404);
            $this->render("404.php", []);
            return;
        }
        $pdo = Db::pdo();
        $st = $pdo->prepare("DELETE FROM seo_keyword WHERE id = :id");
        $st->execute([":id" => $id]);
        $this->logAudit($this->currentUser(), "delete_keyword", "seo_keyword", $id, ["keyword" => (string) ($row["keyword"] ?? "")]);
        $this->redirect("/admin/keywords");
    }

    private function handleAdminKeywordImportGet(?string $error, int $added): void
    {
        $this->render("admin_keyword_import.php", [
            "error" => $error,
            "added" => $added,
            "csrf_token" => Security::csrfToken(),
        ]);
    }

    private function handleAdminKeywordImportPost(): void
    {
        if (empty($_FILES["file"]["tmp_name"])) {
            $this->handleAdminKeywordImportGet("Please choose a CSV file.", 0);
            return;
        }
        $csv = @file_get_contents((string) $_FILES["file"]["tmp_name"]);
        if ($csv === false) {
            $this->handleAdminKeywordImportGet("Could not read uploaded file.", 0);
            return;
        }
        $lines = preg_split("/\r\n|\n|\r/", (string) $csv) ?: [];
        $pdo = Db::pdo();
        $existingRows = $pdo->query("SELECT keyword, slug FROM seo_keyword")->fetchAll();
        $existingKeywords = [];
        foreach ($existingRows as $r) {
            $existingKeywords[strtolower((string) ($r["keyword"] ?? ""))] = true;
        }
        $added = 0;
        foreach ($lines as $idx => $line) {
            if (trim($line) === "") {
                continue;
            }
            $cols = str_getcsv($line);
            $cell = trim((string) ($cols[0] ?? ""));
            if ($idx === 0 && in_array(strtolower($cell), ["keyword", "keywords"], true)) {
                continue;
            }
            $keyword = $this->sanitizeText($cell);
            if ($keyword === "") {
                continue;
            }
            $lower = strtolower($keyword);
            if (isset($existingKeywords[$lower])) {
                continue;
            }
            $description = trim((string) ($cols[1] ?? ""));
            if ($description === "") {
                $description = $keyword . " is a vocabulary topic on RoadToWord. Practice it to build stronger English skills.";
            }
            $slug = $this->ensureUniqueKeywordSlug($this->slugify($keyword), null);
            $st = $pdo->prepare("INSERT INTO seo_keyword (keyword, slug, description) VALUES (:k, :s, :d)");
            $st->execute([":k" => $keyword, ":s" => $slug, ":d" => $description]);
            $existingKeywords[$lower] = true;
            $added++;
        }
        $this->logAudit($this->currentUser(), "import_keywords", "seo_keyword", null, ["added" => $added]);
        $this->redirect("/admin/keywords");
    }

    private function handleAdminVocabListGet(): void
    {
        $pdo = Db::pdo();
        $q = strtolower(trim((string) ($_GET["q"] ?? "")));
        $level = trim((string) ($_GET["level"] ?? ""));
        $rows = $pdo->query("SELECT id, level, word, turkish, definition, example_en, example_tr, synonyms, antonyms FROM vocab ORDER BY id ASC")->fetchAll();
        if ($level !== "") {
            $rows = array_values(array_filter($rows, fn($r) => (string) ($r["level"] ?? "") === $level));
        }
        if ($q !== "") {
            $rows = array_values(array_filter($rows, function ($r) use ($q): bool {
                $blob = strtolower(
                    (string) ($r["word"] ?? "") . " " .
                    (string) ($r["turkish"] ?? "") . " " .
                    (string) ($r["definition"] ?? "") . " " .
                    (string) ($r["example_en"] ?? "") . " " .
                    (string) ($r["example_tr"] ?? "") . " " .
                    (string) ($r["synonyms"] ?? "") . " " .
                    (string) ($r["antonyms"] ?? "")
                );
                return str_contains($blob, $q);
            }));
        }
        $levels = $pdo->query("SELECT DISTINCT level FROM vocab WHERE level IS NOT NULL AND level <> '' ORDER BY level ASC")->fetchAll();
        $levelList = array_values(array_map(fn($r) => (string) ($r["level"] ?? ""), $levels));
        $this->render("admin_vocab_list.php", [
            "rows" => $rows,
            "q" => $q,
            "level" => $level,
            "levels" => $levelList,
            "csrf_token" => Security::csrfToken(),
        ]);
    }

    private function handleAdminVocabFormGet(string $mode, ?int $id): void
    {
        $item = ["level" => "A1", "word" => "", "turkish" => "", "definition" => "", "example_en" => "", "example_tr" => "", "synonyms" => [], "antonyms" => []];
        if ($mode === "edit") {
            $row = $this->fetchVocabById((int) $id);
            if (!$row) {
                http_response_code(404);
                $this->render("404.php", []);
                return;
            }
            $item = $this->vocabRowToFormItem($row);
            $item["id"] = (int) $row["id"];
        }
        $this->render("admin_vocab_form.php", ["mode" => $mode, "item" => $item, "error" => null, "csrf_token" => Security::csrfToken()]);
    }

    private function handleAdminVocabNewPost(): void
    {
        $item = $this->buildVocabItemFromPost();
        if ($item["word"] === "" || $item["turkish"] === "") {
            $this->render("admin_vocab_form.php", ["mode" => "new", "item" => $item, "error" => "Word and Turkish are required.", "csrf_token" => Security::csrfToken()]);
            return;
        }
        $pdo = Db::pdo();
        $chk = $pdo->prepare("SELECT id FROM vocab WHERE LOWER(word) = LOWER(:w) LIMIT 1");
        $chk->execute([":w" => $item["word"]]);
        if ($chk->fetch()) {
            $this->render("admin_vocab_form.php", ["mode" => "new", "item" => $item, "error" => "This word already exists.", "csrf_token" => Security::csrfToken()]);
            return;
        }
        $this->insertVocabRow($item);
        $this->logAudit($this->currentUser(), "create_vocab", "vocab", null, ["word" => $item["word"]]);
        $this->redirect("/admin/vocab");
    }

    private function handleAdminVocabEditPost(int $id): void
    {
        if (!$this->fetchVocabById($id)) {
            http_response_code(404);
            $this->render("404.php", []);
            return;
        }
        $item = $this->buildVocabItemFromPost();
        $item["id"] = $id;
        if ($item["word"] === "" || $item["turkish"] === "") {
            $this->render("admin_vocab_form.php", ["mode" => "edit", "item" => $item, "error" => "Word and Turkish are required.", "csrf_token" => Security::csrfToken()]);
            return;
        }
        $pdo = Db::pdo();
        $chk = $pdo->prepare("SELECT id FROM vocab WHERE LOWER(word) = LOWER(:w) AND id <> :id LIMIT 1");
        $chk->execute([":w" => $item["word"], ":id" => $id]);
        if ($chk->fetch()) {
            $this->render("admin_vocab_form.php", ["mode" => "edit", "item" => $item, "error" => "Another entry with this word exists.", "csrf_token" => Security::csrfToken()]);
            return;
        }
        $this->updateVocabRow($id, $item);
        $this->logAudit($this->currentUser(), "update_vocab", "vocab", $id, ["word" => $item["word"]]);
        $this->redirect("/admin/vocab");
    }

    private function handleAdminVocabDeletePost(int $id): void
    {
        $row = $this->fetchVocabById($id);
        if (!$row) {
            http_response_code(404);
            $this->render("404.php", []);
            return;
        }
        $pdo = Db::pdo();
        $st = $pdo->prepare("DELETE FROM vocab WHERE id = :id");
        $st->execute([":id" => $id]);
        $this->logAudit($this->currentUser(), "delete_vocab", "vocab", $id, ["word" => (string) ($row["word"] ?? "")]);
        $this->redirect("/admin/vocab");
    }

    private function handleAdminVocabExportGet(): void
    {
        $pdo = Db::pdo();
        $rows = $pdo->query("SELECT level, word, turkish, definition, example_en, example_tr, synonyms, antonyms FROM vocab ORDER BY id ASC")->fetchAll();
        $payload = ["vocab" => []];
        foreach ($rows as $r) {
            $payload["vocab"][] = [
                "level" => (string) ($r["level"] ?? "A1"),
                "word" => (string) ($r["word"] ?? ""),
                "turkish" => (string) ($r["turkish"] ?? ""),
                "definition" => (string) ($r["definition"] ?? ""),
                "example_en" => (string) ($r["example_en"] ?? ""),
                "example_tr" => (string) ($r["example_tr"] ?? ""),
                "synonyms" => $this->decodeJsonList($r["synonyms"] ?? null),
                "antonyms" => $this->decodeJsonList($r["antonyms"] ?? null),
            ];
        }
        header("Content-Type: application/json; charset=utf-8");
        header("Content-Disposition: attachment; filename=\"vocab_export.json\"");
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }

    private function handleAdminVocabImportGet(?string $error): void
    {
        $this->render("admin_vocab_import.php", ["error" => $error, "csrf_token" => Security::csrfToken()]);
    }

    private function handleAdminVocabImportPost(): void
    {
        if (empty($_FILES["file"]["tmp_name"])) {
            $this->handleAdminVocabImportGet("Please choose a JSON file.");
            return;
        }
        $raw = @file_get_contents((string) $_FILES["file"]["tmp_name"]);
        if ($raw === false) {
            $this->handleAdminVocabImportGet("Could not read uploaded file.");
            return;
        }
        $parsed = json_decode((string) $raw, true);
        if (!is_array($parsed) || !isset($parsed["vocab"]) || !is_array($parsed["vocab"])) {
            $this->handleAdminVocabImportGet("Invalid JSON: missing/empty 'vocab' list.");
            return;
        }
        $cleaned = [];
        foreach ($parsed["vocab"] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $item = $this->buildVocabItemFromArray($row);
            if ($item["word"] === "" || $item["turkish"] === "") {
                continue;
            }
            $cleaned[strtolower($item["word"])] = $item;
        }
        if (empty($cleaned)) {
            $this->handleAdminVocabImportGet("No valid entries found in file.");
            return;
        }
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            $pdo->exec("DELETE FROM vocab");
            foreach (array_values($cleaned) as $item) {
                $this->insertVocabRow($item, false);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $this->handleAdminVocabImportGet("Import failed: " . $e->getMessage());
            return;
        }
        $this->logAudit($this->currentUser(), "import_vocab", "vocab", null, ["count" => count($cleaned)]);
        $this->redirect("/admin/vocab");
    }

    private function handleAdminPacksGet(): void
    {
        $levels = ["A1", "A2", "B1", "B2", "C1", "C2"];
        if (((string) ($_GET["action"] ?? "")) === "export") {
            $level = strtoupper(trim((string) ($_GET["level"] ?? "ALL")));
            if ($level === "") {
                $level = "ALL";
            }
            $pdo = Db::pdo();
            if ($level === "ALL") {
                $rows = $pdo->query("SELECT level, word, turkish, definition, example_en, example_tr, synonyms, antonyms FROM vocab ORDER BY id ASC")->fetchAll();
            } else {
                $st = $pdo->prepare("SELECT level, word, turkish, definition, example_en, example_tr, synonyms, antonyms FROM vocab WHERE UPPER(level) = :lv ORDER BY id ASC");
                $st->execute([":lv" => $level]);
                $rows = $st->fetchAll();
            }
            $payload = ["vocab" => []];
            foreach ($rows as $r) {
                $payload["vocab"][] = [
                    "level" => (string) ($r["level"] ?? "A1"),
                    "word" => (string) ($r["word"] ?? ""),
                    "turkish" => (string) ($r["turkish"] ?? ""),
                    "definition" => (string) ($r["definition"] ?? ""),
                    "example_en" => (string) ($r["example_en"] ?? ""),
                    "example_tr" => (string) ($r["example_tr"] ?? ""),
                    "synonyms" => $this->decodeJsonList($r["synonyms"] ?? null),
                    "antonyms" => $this->decodeJsonList($r["antonyms"] ?? null),
                ];
            }
            header("Content-Type: application/json; charset=utf-8");
            header("Content-Disposition: attachment; filename=\"content_pack_" . strtolower($level) . ".json\"");
            echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            exit;
        }
        $this->render("admin_packs.php", [
            "levels" => $levels,
            "error" => null,
            "message" => null,
            "csrf_token" => Security::csrfToken(),
        ]);
    }

    private function handleAdminPacksPost(): void
    {
        $levels = ["A1", "A2", "B1", "B2", "C1", "C2"];
        if (((string) ($_POST["action"] ?? "")) !== "import") {
            $this->redirect("/admin/packs");
            return;
        }
        if (empty($_FILES["file"]["tmp_name"])) {
            $this->render("admin_packs.php", ["levels" => $levels, "error" => "Please choose a JSON file.", "message" => null, "csrf_token" => Security::csrfToken()]);
            return;
        }
        $raw = @file_get_contents((string) $_FILES["file"]["tmp_name"]);
        if ($raw === false) {
            $this->render("admin_packs.php", ["levels" => $levels, "error" => "Could not read uploaded file.", "message" => null, "csrf_token" => Security::csrfToken()]);
            return;
        }
        $parsed = json_decode((string) $raw, true);
        if (!is_array($parsed) || !isset($parsed["vocab"]) || !is_array($parsed["vocab"]) || count($parsed["vocab"]) === 0) {
            $this->render("admin_packs.php", ["levels" => $levels, "error" => "Invalid JSON: missing/empty 'vocab' list.", "message" => null, "csrf_token" => Security::csrfToken()]);
            return;
        }
        $pdo = Db::pdo();
        $existingRows = $pdo->query("SELECT word FROM vocab")->fetchAll();
        $existing = [];
        foreach ($existingRows as $r) {
            $existing[strtolower((string) ($r["word"] ?? ""))] = true;
        }
        $added = 0;
        foreach ($parsed["vocab"] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $item = $this->buildVocabItemFromArray($row);
            if ($item["word"] === "" || $item["turkish"] === "") {
                continue;
            }
            $key = strtolower($item["word"]);
            if (isset($existing[$key])) {
                continue;
            }
            $this->insertVocabRow($item);
            $existing[$key] = true;
            $added++;
        }
        $this->logAudit($this->currentUser(), "import_pack", "vocab", null, ["added" => $added]);
        $this->render("admin_packs.php", [
            "levels" => $levels,
            "error" => null,
            "message" => "Imported {$added} new words.",
            "csrf_token" => Security::csrfToken(),
        ]);
    }

    private function handleAdminExportAllGet(): void
    {
        $pdo = Db::pdo();
        $tables = [
            "user", "student", "student_meta", "vocab", "notification", "message", "friend_request", "friend",
            "log_entry", "audit_log", "seo_keyword", "student_word_stat", "student_mode_stat", "student_daily_stat",
            "classroom", "classroom_member", "assignment"
        ];
        $payload = [];
        foreach ($tables as $t) {
            try {
                $payload[$t] = $pdo->query("SELECT * FROM `{$t}`")->fetchAll();
            } catch (\Throwable $e) {
                $payload[$t] = ["_error" => $e->getMessage()];
            }
        }
        header("Content-Type: application/json; charset=utf-8");
        header("Content-Disposition: attachment; filename=\"roadtoword_export_all.json\"");
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }

    private function handleShareProgressGet(string $token): void
    {
        $studentName = $this->decodeShareToken($token);
        if ($studentName === null) {
            http_response_code(404);
            $this->render("404.php", []);
            return;
        }
        $report = $this->buildStudentReport($studentName);
        if ($report === null) {
            http_response_code(404);
            $this->render("404.php", []);
            return;
        }
        $this->render("share.php", ["report" => $report]);
    }

    private function handleShareProgressCsvGet(string $token): void
    {
        $studentName = $this->decodeShareToken($token);
        if ($studentName === null) {
            http_response_code(404);
            echo "Not found";
            return;
        }
        $report = $this->buildStudentReport($studentName);
        if ($report === null) {
            http_response_code(404);
            echo "Not found";
            return;
        }
        header("Content-Type: text/csv; charset=utf-8");
        header("Content-Disposition: attachment; filename=\"" . preg_replace('/[^A-Za-z0-9_-]+/', '_', $studentName) . "_progress.csv\"");
        $out = fopen("php://output", "w");
        if ($out === false) {
            http_response_code(500);
            return;
        }
        fputcsv($out, ["Word", "Correct", "Wrong", "Accuracy", "Interval", "Level"]);
        foreach ($report["per_word"] as $row) {
            fputcsv($out, [
                $row["word"],
                $row["correct"],
                $row["wrong"],
                $row["accuracy"],
                $row["interval"],
                $row["level"],
            ]);
        }
        fclose($out);
        exit;
    }

    private function decodeShareToken(string $token): ?string
    {
        $raw = trim($token);
        if ($raw === "") {
            return null;
        }
        $b64 = strtr($raw, '-_', '+/');
        $pad = strlen($b64) % 4;
        if ($pad !== 0) {
            $b64 .= str_repeat('=', 4 - $pad);
        }
        $decoded = base64_decode($b64, true);
        $candidate = $decoded !== false ? trim($decoded) : urldecode($raw);
        if ($candidate === "") {
            return null;
        }
        // allow only a safe subset for student names
        if (preg_match('/^[\pL\pN _.\-@]+$/u', $candidate) !== 1) {
            return null;
        }
        return $candidate;
    }

    private function buildStudentReport(string $studentName): ?array
    {
        $pdo = Db::pdo();
        $st = $pdo->prepare("SELECT id, name FROM student WHERE name = :n LIMIT 1");
        $st->execute([":n" => $studentName]);
        $student = $st->fetch();
        if (!$student) {
            return null;
        }
        $sid = (int) $student["id"];
        $metaSt = $pdo->prepare("SELECT * FROM student_meta WHERE student_id = :sid LIMIT 1");
        $metaSt->execute([":sid" => $sid]);
        $meta = $metaSt->fetch();
        if (is_array($meta)) {
            $meta = $this->ensureDailyRollover($meta);
        }
        $userSt = $pdo->prepare("SELECT * FROM user WHERE student_id = :sid OR LOWER(username)=LOWER(:u) LIMIT 1");
        $userSt->execute([":sid" => $sid, ":u" => (string) $student["name"]]);
        $userRow = $userSt->fetch();
        $rows = $pdo->prepare("
            SELECT v.word, sws.correct, sws.wrong, sws.interval, sws.level
            FROM student_word_stat sws
            INNER JOIN vocab v ON v.id = sws.vocab_id
            WHERE sws.student_id = :sid
            ORDER BY v.word ASC
        ");
        $rows->execute([":sid" => $sid]);
        $perWordRows = $rows->fetchAll();
        $perWord = [];
        $totalCorrect = 0;
        $totalWrong = 0;
        foreach ($perWordRows as $r) {
            $c = (int) ($r["correct"] ?? 0);
            $w = (int) ($r["wrong"] ?? 0);
            $t = $c + $w;
            $acc = $t > 0 ? round(($c * 100.0) / $t, 1) : 0.0;
            $perWord[] = [
                "word" => (string) ($r["word"] ?? ""),
                "correct" => $c,
                "wrong" => $w,
                "accuracy" => $acc,
                "interval" => (float) ($r["interval"] ?? 0),
                "level" => (string) ($r["level"] ?? ""),
            ];
            $totalCorrect += $c;
            $totalWrong += $w;
        }
        $total = $totalCorrect + $totalWrong;
        $accuracy = $total > 0 ? round(($totalCorrect * 100.0) / $total, 1) : 0.0;
        usort($perWord, function ($a, $b) {
            if ($a["accuracy"] === $b["accuracy"]) {
                return $b["correct"] <=> $a["correct"];
            }
            return $b["accuracy"] <=> $a["accuracy"];
        });
        $dailyCtx = is_array($meta) ? $this->getDailyStreakContext($meta) : ["message" => "", "broke" => false];
        $badge = $this->getBadgeLabel(is_array($userRow) ? $userRow : null, $totalCorrect);
        return [
            "student" => (string) $student["name"],
            "summary" => [
                "correct" => $totalCorrect,
                "wrong" => $totalWrong,
                "accuracy" => $accuracy,
            ],
            "name" => (string) $student["name"],
            "total_correct" => $totalCorrect,
            "total_wrong" => $totalWrong,
            "accuracy" => $accuracy,
            "badge" => $badge,
            "streak" => (int) ($meta["streak"] ?? 0),
            "best_streak" => (int) ($meta["best_streak"] ?? 0),
            "daily_done" => (int) ($meta["daily_done"] ?? 0),
            "daily_target" => (int) ($meta["daily_target"] ?? 20),
            "daily_streak" => (int) ($meta["daily_streak"] ?? 0),
            "best_daily_streak" => (int) ($meta["best_daily_streak"] ?? 0),
            "daily_freezes" => (int) ($meta["streak_freezes"] ?? 0),
            "road_tokens" => (int) ($meta["road_tokens"] ?? 0),
            "daily_message" => (string) ($dailyCtx["message"] ?? ""),
            "daily_broke" => (bool) ($dailyCtx["broke"] ?? false),
            "per_word" => $perWord,
        ];
    }

    private function handleDashboardGet(): void
    {
        $user = $this->currentUser();
        if (!$user) {
            $this->redirect("/login");
            return;
        }
        if ((string) ($user["role"] ?? "") === "admin") {
            $this->redirect("/admin");
            return;
        }
        if ((string) ($user["role"] ?? "") === "student") {
            $this->redirect("/quiz/" . rawurlencode((string) ($user["username"] ?? "")));
            return;
        }
        $this->redirect("/classrooms");
    }

    private function flagPath(string $name): string
    {
        return BASE_DIR . "/" . $name . ".flag";
    }

    private function flagEnabled(string $name): bool
    {
        return is_file($this->flagPath($name));
    }

    private function setFlag(string $name, bool $enabled): void
    {
        $path = $this->flagPath($name);
        if ($enabled) {
            @file_put_contents($path, "1");
        } else {
            @unlink($path);
        }
    }

    private function ensureProfilePrivacyColumn(): void
    {
        if ($this->privacyColumnReady) {
            return;
        }
        $this->privacyColumnReady = true;
        try {
            Db::pdo()->exec("ALTER TABLE user ADD COLUMN profile_public INTEGER DEFAULT 1");
        } catch (\Throwable $e) {
        }
        try {
            Db::pdo()->exec("UPDATE user SET profile_public = 1 WHERE profile_public IS NULL");
        } catch (\Throwable $e) {
        }
    }

    private function handleAdminHome(): void
    {
        $this->render("admin_home.php", [
            "maintenance" => $this->flagEnabled("maintenance"),
            "suspended" => $this->flagEnabled("suspend"),
            "destroyed" => $this->flagEnabled("destroyed"),
        ]);
    }

    private function handleAdminMaintenanceGet(): void
    {
        $this->render("admin_maintenance.php", [
            "csrf_token" => Security::csrfToken(),
            "maintenance" => $this->flagEnabled("maintenance"),
        ]);
    }

    private function handleAdminMaintenancePost(): void
    {
        $enabled = ((string) ($_POST["enabled"] ?? "0")) === "1";
        $this->setFlag("maintenance", $enabled);
        $this->logAudit($this->currentUser(), "maintenance_toggle", "system", null, ["enabled" => $enabled]);
        $this->redirect("/admin/maintenance");
    }

    private function handleAdminManageServerGet(): void
    {
        $sysLines = [];
        $sysLines[] = "PHP: " . PHP_VERSION;
        $sysLines[] = "OS: " . (defined("PHP_OS_FAMILY") ? PHP_OS_FAMILY : PHP_OS);
        $sysLines[] = "Server: " . ((string) ($_SERVER["SERVER_SOFTWARE"] ?? "unknown"));

        try {
            $pid = @getmypid();
            $sysLines[] = "PID: " . (($pid === false || $pid === null) ? "unavailable" : (string) $pid);
        } catch (\Throwable $e) {
            $sysLines[] = "PID: unavailable";
        }

        try {
            $diskFree = @disk_free_space(BASE_DIR);
            $diskTotal = @disk_total_space(BASE_DIR);
            $sysLines[] = ($diskFree !== false && $diskTotal !== false)
                ? ("Disk free: " . round($diskFree / 1024 / 1024 / 1024, 2) . " GB / " . round($diskTotal / 1024 / 1024 / 1024, 2) . " GB")
                : "Disk info unavailable";
        } catch (\Throwable $e) {
            $sysLines[] = "Disk info unavailable";
        }

        $sysLines[] = "Base dir: " . (defined("BASE_DIR") ? BASE_DIR : __DIR__);
        $sysinfo = implode("\n", $sysLines);
        $this->render("admin_manage_server.php", [
            "csrf_token" => Security::csrfToken(),
            "suspended" => $this->flagEnabled("suspend"),
            "destroyed" => $this->flagEnabled("destroyed"),
            "sysinfo" => $sysinfo,
        ]);
    }

    private function handleAdminManageServerPost(): void
    {
        $suspendEnabled = ((string) ($_POST["suspended"] ?? "0")) === "1";
        $destroyedEnabled = ((string) ($_POST["destroyed"] ?? "0")) === "1";
        $this->setFlag("suspend", $suspendEnabled);
        $this->setFlag("destroyed", $destroyedEnabled);
        $this->logAudit($this->currentUser(), "manage_server_toggle", "system", null, ["suspended" => $suspendEnabled, "destroyed" => $destroyedEnabled]);
        $this->redirect("/admin/manage-server");
    }

    private function handleAdminUserFormGet(string $mode, ?int $id): void
    {
        $item = ["username" => "", "email" => "", "full_name" => "", "role" => "teacher"];
        if ($mode === "edit") {
            $u = $this->findUserById((int) $id);
            if (!$u) {
                http_response_code(404);
                $this->render("404.php", []);
                return;
            }
            $item = [
                "id" => (int) $u["id"],
                "username" => (string) ($u["username"] ?? ""),
                "email" => (string) ($u["email"] ?? ""),
                "full_name" => (string) ($u["full_name"] ?? ""),
                "role" => (string) ($u["role"] ?? "teacher"),
            ];
        }
        $this->render("admin_user_form.php", ["mode" => $mode, "item" => $item, "error" => null, "csrf_token" => Security::csrfToken()]);
    }

    private function handleAdminUserNewPost(): void
    {
        $pdo = Db::pdo();
        $item = [
            "username" => $this->sanitizeText((string) ($_POST["username"] ?? "")),
            "email" => strtolower(trim((string) ($_POST["email"] ?? ""))),
            "full_name" => trim((string) ($_POST["full_name"] ?? "")),
            "role" => strtolower(trim((string) ($_POST["role"] ?? "teacher"))),
        ];
        $password = (string) ($_POST["password"] ?? "");
        if ($item["username"] === "" || $item["email"] === "" || $item["full_name"] === "" || $password === "") {
            $this->render("admin_user_form.php", ["mode" => "new", "item" => $item, "error" => "Username, full name, email, and password are required.", "csrf_token" => Security::csrfToken()]);
            return;
        }
        if (!in_array($item["role"], ["admin", "teacher"], true)) {
            $this->render("admin_user_form.php", ["mode" => "new", "item" => $item, "error" => "Role must be admin or teacher.", "csrf_token" => Security::csrfToken()]);
            return;
        }
        $chkU = $pdo->prepare("SELECT id FROM user WHERE LOWER(username)=LOWER(:u) LIMIT 1");
        $chkU->execute([":u" => $item["username"]]);
        if ($chkU->fetch()) {
            $this->render("admin_user_form.php", ["mode" => "new", "item" => $item, "error" => "Username already exists.", "csrf_token" => Security::csrfToken()]);
            return;
        }
        $chkE = $pdo->prepare("SELECT id FROM user WHERE LOWER(email)=LOWER(:e) LIMIT 1");
        $chkE->execute([":e" => $item["email"]]);
        if ($chkE->fetch()) {
            $this->render("admin_user_form.php", ["mode" => "new", "item" => $item, "error" => "Email already exists.", "csrf_token" => Security::csrfToken()]);
            return;
        }
        $st = $pdo->prepare("INSERT INTO user (username,email,full_name,role,password) VALUES (:u,:e,:f,:r,:p)");
        $st->execute([":u"=>$item["username"],":e"=>$item["email"],":f"=>$item["full_name"],":r"=>$item["role"],":p"=>$password]);
        $this->logAudit($this->currentUser(), "create_user", "user", (int) $pdo->lastInsertId(), ["username"=>$item["username"],"role"=>$item["role"]]);
        $this->redirect("/admin/users");
    }

    private function handleAdminUserEditPost(int $id): void
    {
        $u = $this->findUserById($id);
        if (!$u) {
            http_response_code(404);
            $this->render("404.php", []);
            return;
        }
        $pdo = Db::pdo();
        $item = [
            "id" => $id,
            "username" => (string) ($u["username"] ?? ""),
            "email" => strtolower(trim((string) ($_POST["email"] ?? ""))),
            "full_name" => trim((string) ($_POST["full_name"] ?? "")),
            "role" => strtolower(trim((string) ($_POST["role"] ?? "teacher"))),
        ];
        $password = (string) ($_POST["password"] ?? "");
        if ($item["email"] === "" || $item["full_name"] === "") {
            $this->render("admin_user_form.php", ["mode" => "edit", "item" => $item, "error" => "Email and full name are required.", "csrf_token" => Security::csrfToken()]);
            return;
        }
        if (!in_array($item["role"], ["admin", "teacher"], true)) {
            $this->render("admin_user_form.php", ["mode" => "edit", "item" => $item, "error" => "Role must be admin or teacher.", "csrf_token" => Security::csrfToken()]);
            return;
        }
        $chkE = $pdo->prepare("SELECT id FROM user WHERE LOWER(email)=LOWER(:e) AND id <> :id LIMIT 1");
        $chkE->execute([":e"=>$item["email"], ":id"=>$id]);
        if ($chkE->fetch()) {
            $this->render("admin_user_form.php", ["mode" => "edit", "item" => $item, "error" => "Another user already uses this email.", "csrf_token" => Security::csrfToken()]);
            return;
        }
        $sql = "UPDATE user SET email=:e, full_name=:f, role=:r" . ($password !== "" ? ", password=:p" : "") . " WHERE id=:id";
        $st = $pdo->prepare($sql);
        $params = [":e"=>$item["email"], ":f"=>$item["full_name"], ":r"=>$item["role"], ":id"=>$id];
        if ($password !== "") { $params[":p"] = $password; }
        $st->execute($params);
        $this->logAudit($this->currentUser(), "update_user", "user", $id, ["email"=>$item["email"],"role"=>$item["role"],"password_changed"=>($password !== "")]);
        $this->redirect("/admin/users");
    }

    private function handleAdminUserDeletePost(int $id): void
    {
        $u = $this->findUserById($id);
        if (!$u) {
            http_response_code(404);
            $this->render("404.php", []);
            return;
        }
        if (strtolower((string) ($u["username"] ?? "")) === strtolower((string) ($this->sessionUsername() ?? ""))) {
            http_response_code(400);
            echo "Cannot delete your own account.";
            return;
        }
        $pdo = Db::pdo();
        $st = $pdo->prepare("DELETE FROM user WHERE id = :id");
        $st->execute([":id" => $id]);
        $this->logAudit($this->currentUser(), "delete_user", "user", $id, ["username" => (string) ($u["username"] ?? "")]);
        $this->redirect("/admin/users");
    }

    private function quizModesMap(): array
    {
        return [
            "turkish" => "Turkish meaning",
            "synonym" => "English synonym",
            "antonym" => "English antonym",
            "definition" => "English definition",
            "typing" => "Type the answer",
            "timed" => "Timed drill",
            "review" => "Smart review",
            "mixed" => "Mixed (all modes)",
        ];
    }

    private function handleAdminStudentsGet(): void
    {
        $pdo = Db::pdo();
        $rows = $pdo->query("
            SELECT s.id, s.name,
                   COALESCE(SUM(sws.correct),0) AS total_correct,
                   COALESCE(SUM(sws.wrong),0) AS total_wrong
            FROM student s
            LEFT JOIN student_word_stat sws ON sws.student_id = s.id
            GROUP BY s.id, s.name
            ORDER BY s.name ASC
        ")->fetchAll();
        foreach ($rows as &$r) {
            $c = (int) ($r["total_correct"] ?? 0);
            $w = (int) ($r["total_wrong"] ?? 0);
            $t = $c + $w;
            $r["accuracy"] = $t > 0 ? round($c * 100 / $t, 1) : 0;
        }
        unset($r);
        $this->render("admin_students.php", ["rows" => $rows, "csrf_token" => Security::csrfToken()]);
    }

    private function handleAdminStudentEditGet(string $studentName, ?string $error): void
    {
        $student = $this->findStudentByName(urldecode($studentName));
        if (!$student) {
            http_response_code(404);
            $this->render("404.php", []);
            return;
        }
        $meta = $this->ensureStudentMetaByStudentId((int) $student["id"]);
        $allowed = $this->decodeJsonList($meta["allowed_modes"] ?? null);
        if (empty($allowed)) { $allowed = array_keys($this->quizModesMap()); }
        $item = [
            "name" => (string) $student["name"],
            "allowed_modes" => $allowed,
            "streak_mode" => (string) (($meta["streak_mode"] ?? "") ?: "strict"),
            "daily_target" => (int) (($meta["daily_target"] ?? 20)),
        ];
        $this->render("admin_student_edit.php", ["item"=>$item, "modes"=>$this->quizModesMap(), "error"=>$error, "csrf_token"=>Security::csrfToken()]);
    }

    private function handleAdminStudentEditPost(string $studentName): void
    {
        $student = $this->findStudentByName(urldecode($studentName));
        if (!$student) {
            http_response_code(404);
            $this->render("404.php", []);
            return;
        }
        $meta = $this->ensureStudentMetaByStudentId((int) $student["id"]);
        $allowedModes = $_POST["allowed_modes"] ?? [];
        if (!is_array($allowedModes)) { $allowedModes = []; }
        $allowedModes = array_values(array_intersect(array_keys($this->quizModesMap()), array_map('strval', $allowedModes)));
        $streakMode = (string) ($_POST["streak_mode"] ?? "strict");
        $dailyTarget = filter_var((string) ($_POST["daily_target"] ?? "20"), FILTER_VALIDATE_INT);
        if (empty($allowedModes)) {
            $this->handleAdminStudentEditGet($student["name"], "Select at least one mode.");
            return;
        }
        if (!in_array($streakMode, ["strict","lenient"], true)) {
            $this->handleAdminStudentEditGet($student["name"], "Invalid streak mode.");
            return;
        }
        if ($dailyTarget === false || $dailyTarget < 1 || $dailyTarget > 200) {
            $this->handleAdminStudentEditGet($student["name"], "Daily target must be between 1 and 200.");
            return;
        }
        $pdo = Db::pdo();
        $st = $pdo->prepare("UPDATE student_meta SET allowed_modes=:m, streak_mode=:s, daily_target=:d WHERE id=:id");
        $st->execute([
            ":m" => json_encode($allowedModes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ":s" => $streakMode,
            ":d" => (int) $dailyTarget,
            ":id" => (int) $meta["id"],
        ]);
        $this->logAudit($this->currentUser(), "update_student_settings", "student", (int) $student["id"], ["allowed_modes"=>$allowedModes,"streak_mode"=>$streakMode,"daily_target"=>(int)$dailyTarget]);
        $this->redirect("/admin/students");
    }

    private function handleAdminStudentResetPost(string $studentName): void
    {
        $student = $this->findStudentByName(urldecode($studentName));
        if ($student) {
            $sid = (int) $student["id"];
            $pdo = Db::pdo();
            try {
                $pdo->beginTransaction();
                $pdo->prepare("DELETE FROM student_mode_stat WHERE stat_id IN (SELECT id FROM student_word_stat WHERE student_id = :sid)")->execute([":sid"=>$sid]);
                $pdo->prepare("DELETE FROM student_word_stat WHERE student_id = :sid")->execute([":sid"=>$sid]);
                $pdo->prepare("DELETE FROM student_meta WHERE student_id = :sid")->execute([":sid"=>$sid]);
                $pdo->commit();
            } catch (\Throwable $e) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
            }
            $this->ensureStudentMetaByStudentId($sid);
        }
        $this->redirect("/admin/students");
    }

    private function handleAdminStudentsResetAllPost(): void
    {
        $pdo = Db::pdo();
        $students = $pdo->query("SELECT id FROM student")->fetchAll();
        foreach ($students as $s) {
            $sid = (int) ($s["id"] ?? 0);
            if ($sid <= 0) { continue; }
            try {
                $pdo->beginTransaction();
                $pdo->prepare("DELETE FROM student_mode_stat WHERE stat_id IN (SELECT id FROM student_word_stat WHERE student_id = :sid)")->execute([":sid"=>$sid]);
                $pdo->prepare("DELETE FROM student_word_stat WHERE student_id = :sid")->execute([":sid"=>$sid]);
                $pdo->prepare("DELETE FROM student_meta WHERE student_id = :sid")->execute([":sid"=>$sid]);
                $pdo->commit();
            } catch (\Throwable $e) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
            }
            $this->ensureStudentMetaByStudentId($sid);
        }
        $this->redirect("/admin/students");
    }

    private function handleAdminStudentsPurgeAllPost(): void
    {
        $pdo = Db::pdo();
        try {
            $pdo->beginTransaction();
            $pdo->exec("DELETE FROM student_mode_stat");
            $pdo->exec("DELETE FROM student_word_stat");
            $pdo->exec("DELETE FROM student_meta");
            $pdo->exec("DELETE FROM classroom_member");
            $pdo->exec("DELETE FROM user WHERE role = 'student'");
            $pdo->exec("DELETE FROM student");
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
        }
        $this->logAudit($this->currentUser(), "purge_students", "student", null, ["scope" => "all"]);
        $this->redirect("/admin/students");
    }

    private function findStudentByName(string $name): ?array
    {
        $pdo = Db::pdo();
        $st = $pdo->prepare("SELECT * FROM student WHERE name = :n LIMIT 1");
        $st->execute([":n" => $name]);
        $row = $st->fetch();
        return is_array($row) ? $row : null;
    }

    private function ensureStudentMetaByStudentId(int $studentId): array
    {
        $pdo = Db::pdo();
        $st = $pdo->prepare("SELECT * FROM student_meta WHERE student_id = :sid LIMIT 1");
        $st->execute([":sid" => $studentId]);
        $meta = $st->fetch();
        if ($meta) { return $meta; }
        $ins = $pdo->prepare("
            INSERT INTO student_meta (
                student_id, daily_target, streak_freezes, lives_remaining, lives_reset_at,
                road_tokens, theme_pack_owned, preferred_theme, name_glow_enabled,
                owned_profile_frames, profile_frame_style, owned_chat_bubbles, chat_bubble_style, mission_claimed
            )
            VALUES (
                :sid, 20, 2, :l, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 15 MINUTE),
                0, 0, 'default', 0, :opf, 'default', :ocb, 'default', :mclaimed
            )
        ");
        $ins->execute([
            ":sid" => $studentId,
            ":l" => self::QUIZ_LIVES_MAX,
            ":opf" => json_encode(["default"], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ":ocb" => json_encode(["default"], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ":mclaimed" => json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
        $st->execute([":sid" => $studentId]);
        return (array) $st->fetch();
    }

    private function handleClassroomsGet(): void
    {
        $pdo = Db::pdo();
        $rows = $pdo->query("
            SELECT c.*, u.username AS owner_username, COUNT(cm.id) AS member_count
            FROM classroom c
            LEFT JOIN user u ON u.id = c.owner_user_id
            LEFT JOIN classroom_member cm ON cm.classroom_id = c.id
            GROUP BY c.id
            ORDER BY c.created_at DESC, c.id DESC
        ")->fetchAll();
        $this->render("classrooms.php", ["rows"=>$rows, "csrf_token"=>Security::csrfToken(), "error"=>null, "message"=>null]);
    }

    private function handleClassroomsPost(): void
    {
        $pdo = Db::pdo();
        $action = (string) ($_POST["action"] ?? "create");
        if ($action === "delete") {
            $classroomId = (int) ($_POST["classroom_id"] ?? 0);
            if ($classroomId > 0) {
                $existing = $this->fetchClassroomById($classroomId);
                if (!$existing) {
                    $this->redirect("/classrooms");
                    return;
                }
                try {
                    $pdo->beginTransaction();
                    $pdo->prepare("DELETE FROM assignment WHERE classroom_id = :id")->execute([":id"=>$classroomId]);
                    $pdo->prepare("DELETE FROM classroom_member WHERE classroom_id = :id")->execute([":id"=>$classroomId]);
                    $pdo->prepare("DELETE FROM classroom WHERE id = :id")->execute([":id"=>$classroomId]);
                    $pdo->commit();
                } catch (\Throwable $e) {
                    if ($pdo->inTransaction()) { $pdo->rollBack(); }
                }
                $this->logAudit($this->currentUser(), "delete_classroom", "classroom", $classroomId, ["name" => $existing["name"] ?? null]);
            }
            $this->redirect("/classrooms");
            return;
        }
        $name = trim((string) ($_POST["name"] ?? ""));
        if (mb_strlen($name) > 120) {
            $name = mb_substr($name, 0, 120);
        }
        if ($name === "") {
            $rows = $pdo->query("SELECT c.*, u.username AS owner_username, COUNT(cm.id) AS member_count FROM classroom c LEFT JOIN user u ON u.id = c.owner_user_id LEFT JOIN classroom_member cm ON cm.classroom_id = c.id GROUP BY c.id ORDER BY c.id DESC")->fetchAll();
            $this->render("classrooms.php", ["rows"=>$rows, "csrf_token"=>Security::csrfToken(), "error"=>"Classroom name is required.", "message"=>null]);
            return;
        }
        $owner = $this->currentUser();
        $st = $pdo->prepare("INSERT INTO classroom (name, owner_user_id, created_at) VALUES (:n, :o, UTC_TIMESTAMP())");
        $st->execute([":n"=>$name, ":o"=>$owner ? (int) $owner["id"] : null]);
        $id = (int) $pdo->lastInsertId();
        $this->logAudit($owner, "create_classroom", "classroom", $id, ["name"=>$name]);
        $this->redirect("/classrooms");
    }

    private function handleClassroomDetailGet(int $classroomId): void
    {
        $pdo = Db::pdo();
        $classroom = $this->fetchClassroomById($classroomId);
        if (!$classroom) { http_response_code(404); $this->render("404.php", []); return; }
        $members = $pdo->prepare("
            SELECT cm.id AS membership_id, s.id AS student_id, s.name
            FROM classroom_member cm
            INNER JOIN student s ON s.id = cm.student_id
            WHERE cm.classroom_id = :cid
            ORDER BY s.name ASC
        ");
        $members->execute([":cid"=>$classroomId]);
        $memberRows = $members->fetchAll();
        $assignments = $pdo->prepare("SELECT * FROM assignment WHERE classroom_id = :cid ORDER BY created_at DESC, id DESC");
        $assignments->execute([":cid"=>$classroomId]);
        $assignmentRows = $assignments->fetchAll();
        $this->render("classroom_detail.php", [
            "csrf_token"=>Security::csrfToken(),
            "classroom"=>$classroom,
            "members"=>$memberRows,
            "assignments"=>$assignmentRows,
            "error"=>null,
            "message"=>null,
        ]);
    }

    private function handleClassroomDetailPost(int $classroomId): void
    {
        $pdo = Db::pdo();
        $classroom = $this->fetchClassroomById($classroomId);
        if (!$classroom) { http_response_code(404); $this->render("404.php", []); return; }
        $action = (string) ($_POST["action"] ?? "");
        if ($action === "add_student") {
            $studentName = trim((string) ($_POST["student_name"] ?? ""));
            if ($studentName !== "") {
                $studentName = preg_replace('/\s+/', '', $studentName) ?? $studentName;
                $student = $this->findStudentByName($studentName);
                if (!$student) {
                    $ins = $pdo->prepare("INSERT INTO student (name, created_at) VALUES (:n, UTC_TIMESTAMP())");
                    $ins->execute([":n"=>$studentName]);
                    $student = $this->findStudentById((int) $pdo->lastInsertId());
                    if ($student) { $this->ensureStudentMetaByStudentId((int)$student["id"]); }
                }
                if ($student) {
                    $chk = $pdo->prepare("SELECT id FROM classroom_member WHERE classroom_id = :cid AND student_id = :sid LIMIT 1");
                    $chk->execute([":cid"=>$classroomId, ":sid"=>(int)$student["id"]]);
                    if (!$chk->fetch()) {
                        $pdo->prepare("INSERT INTO classroom_member (classroom_id, student_id, added_at) VALUES (:cid, :sid, UTC_TIMESTAMP())")->execute([":cid"=>$classroomId, ":sid"=>(int)$student["id"]]);
                        $this->logAudit($this->currentUser(), "classroom_add_student", "classroom", $classroomId, ["student"=>$student["name"] ?? null]);
                    }
                }
            }
        } elseif ($action === "remove_student") {
            $membershipId = (int) ($_POST["membership_id"] ?? 0);
            if ($membershipId > 0) {
                $pdo->prepare("DELETE FROM classroom_member WHERE id = :id AND classroom_id = :cid")->execute([":id"=>$membershipId, ":cid"=>$classroomId]);
                $this->logAudit($this->currentUser(), "classroom_remove_student", "classroom", $classroomId, ["membership_id"=>$membershipId]);
            }
        } elseif ($action === "create_assignment") {
            $title = trim((string) ($_POST["title"] ?? ""));
            if (mb_strlen($title) > 180) { $title = mb_substr($title, 0, 180); }
            if ($title !== "") {
                $stmt = $pdo->prepare("
                    INSERT INTO assignment (classroom_id, title, description, due_date, level, allowed_modes, created_by_user_id, created_at)
                    VALUES (:cid, :t, :d, :due, :lv, :m, :uid, UTC_TIMESTAMP())
                ");
                $stmt->execute([
                    ":cid"=>$classroomId,
                    ":t"=>$title,
                    ":d"=>(($d = trim((string)($_POST["description"] ?? ""))) !== "" ? $d : null),
                    ":due"=>(($due = trim((string)($_POST["due_date"] ?? ""))) !== "" ? $due : null),
                    ":lv"=>(($lv = trim((string)($_POST["level"] ?? ""))) !== "" ? strtoupper($lv) : null),
                    ":m"=>null,
                    ":uid"=>(int) (($this->currentUser()["id"] ?? 0)),
                ]);
                $this->logAudit($this->currentUser(), "classroom_create_assignment", "classroom", $classroomId, ["title"=>$title]);
            }
        } elseif ($action === "delete_assignment") {
            $assignmentId = (int) ($_POST["assignment_id"] ?? 0);
            if ($assignmentId > 0) {
                $pdo->prepare("DELETE FROM assignment WHERE id = :id AND classroom_id = :cid")->execute([":id"=>$assignmentId, ":cid"=>$classroomId]);
                $this->logAudit($this->currentUser(), "classroom_delete_assignment", "classroom", $classroomId, ["assignment_id"=>$assignmentId]);
            }
        }
        $this->redirect("/classrooms/" . $classroomId);
    }

    private function handleClassroomDashboardGet(int $classroomId): void
    {
        $pdo = Db::pdo();
        $classroom = $this->fetchClassroomById($classroomId);
        if (!$classroom) { http_response_code(404); $this->render("404.php", []); return; }
        $st = $pdo->prepare("
            SELECT s.name,
                   COALESCE(SUM(sws.correct),0) AS total_correct,
                   COALESCE(SUM(sws.wrong),0) AS total_wrong
            FROM classroom_member cm
            INNER JOIN student s ON s.id = cm.student_id
            LEFT JOIN student_word_stat sws ON sws.student_id = s.id
            WHERE cm.classroom_id = :cid
            GROUP BY s.id, s.name
            ORDER BY s.name ASC
        ");
        $st->execute([":cid"=>$classroomId]);
        $rows = $st->fetchAll();
        foreach ($rows as &$r) {
            $c=(int)($r["total_correct"]??0); $w=(int)($r["total_wrong"]??0); $t=$c+$w;
            $r["accuracy"] = $t>0 ? round($c*100/$t,1) : 0;
        }
        unset($r);
        $this->render("classroom_dashboard.php", ["classroom"=>$classroom, "rows"=>$rows]);
    }

    private function fetchClassroomById(int $id): ?array
    {
        $pdo = Db::pdo();
        $st = $pdo->prepare("SELECT c.*, u.username AS owner_username FROM classroom c LEFT JOIN user u ON u.id = c.owner_user_id WHERE c.id = :id LIMIT 1");
        $st->execute([":id"=>$id]);
        $row = $st->fetch();
        return is_array($row) ? $row : null;
    }

    private function findStudentById(int $id): ?array
    {
        $pdo = Db::pdo();
        $st = $pdo->prepare("SELECT * FROM student WHERE id = :id LIMIT 1");
        $st->execute([":id"=>$id]);
        $row = $st->fetch();
        return is_array($row) ? $row : null;
    }

    private function fetchSeoKeyword(int $id): ?array
    {
        $pdo = Db::pdo();
        $st = $pdo->prepare("SELECT * FROM seo_keyword WHERE id = :id LIMIT 1");
        $st->execute([":id" => $id]);
        $row = $st->fetch();
        return is_array($row) ? $row : null;
    }

    private function slugify(string $text): string
    {
        $s = strtolower(trim($text));
        $s = iconv("UTF-8", "ASCII//TRANSLIT//IGNORE", $s) ?: $s;
        $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?: "";
        $s = trim($s, '-');
        return $s !== "" ? $s : "keyword";
    }

    private function ensureUniqueKeywordSlug(string $baseSlug, ?int $excludeId): string
    {
        $pdo = Db::pdo();
        $slug = $baseSlug !== "" ? $baseSlug : "keyword";
        $i = 1;
        while (true) {
            if ($excludeId === null) {
                $st = $pdo->prepare("SELECT id FROM seo_keyword WHERE slug = :s LIMIT 1");
                $st->execute([":s" => $slug]);
            } else {
                $st = $pdo->prepare("SELECT id FROM seo_keyword WHERE slug = :s AND id <> :id LIMIT 1");
                $st->execute([":s" => $slug, ":id" => $excludeId]);
            }
            if (!$st->fetch()) {
                return $slug;
            }
            $i++;
            $slug = $baseSlug . "-" . $i;
        }
    }

    private function sanitizeText(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;
        return $value;
    }

    private function fetchVocabById(int $id): ?array
    {
        $pdo = Db::pdo();
        $st = $pdo->prepare("SELECT * FROM vocab WHERE id = :id LIMIT 1");
        $st->execute([":id" => $id]);
        $row = $st->fetch();
        return is_array($row) ? $row : null;
    }

    private function buildVocabItemFromPost(): array
    {
        return $this->buildVocabItemFromArray($_POST);
    }

    private function buildVocabItemFromArray(array $src): array
    {
        return [
            "level" => strtoupper($this->sanitizeText((string) ($src["level"] ?? "A1"))) ?: "A1",
            "word" => $this->sanitizeText((string) ($src["word"] ?? "")),
            "turkish" => $this->sanitizeText((string) ($src["turkish"] ?? "")),
            "definition" => $this->sanitizeText((string) ($src["definition"] ?? "")),
            "example_en" => $this->sanitizeText((string) ($src["example_en"] ?? "")),
            "example_tr" => $this->sanitizeText((string) ($src["example_tr"] ?? "")),
            "synonyms" => $this->splitToList($src["synonyms"] ?? []),
            "antonyms" => $this->splitToList($src["antonyms"] ?? []),
        ];
    }

    private function splitToList($value): array
    {
        if (is_array($value)) {
            $parts = $value;
        } else {
            $raw = (string) $value;
            $parts = preg_split('/[,;\n\r]+/', $raw) ?: [];
        }
        $out = [];
        foreach ($parts as $p) {
            $t = $this->sanitizeText((string) $p);
            if ($t !== "" && !in_array($t, $out, true)) {
                $out[] = $t;
            }
        }
        return $out;
    }

    private function decodeJsonList($raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if ($raw === null) {
            return [];
        }
        $s = trim((string) $raw);
        if ($s === "") {
            return [];
        }
        $parsed = json_decode($s, true);
        if (is_array($parsed)) {
            return array_values(array_map(fn($v) => (string) $v, $parsed));
        }
        return $this->splitToList($s);
    }

    private function vocabRowToFormItem(array $row): array
    {
        return [
            "level" => (string) ($row["level"] ?? "A1"),
            "word" => (string) ($row["word"] ?? ""),
            "turkish" => (string) ($row["turkish"] ?? ""),
            "definition" => (string) ($row["definition"] ?? ""),
            "example_en" => (string) ($row["example_en"] ?? ""),
            "example_tr" => (string) ($row["example_tr"] ?? ""),
            "synonyms" => $this->decodeJsonList($row["synonyms"] ?? null),
            "antonyms" => $this->decodeJsonList($row["antonyms"] ?? null),
        ];
    }

    private function insertVocabRow(array $item, bool $ownConnection = true): void
    {
        $pdo = Db::pdo();
        $st = $pdo->prepare("
            INSERT INTO vocab (level, word, turkish, definition, example_en, example_tr, synonyms, antonyms)
            VALUES (:level, :word, :turkish, :definition, :example_en, :example_tr, :synonyms, :antonyms)
        ");
        $st->execute([
            ":level" => $item["level"],
            ":word" => $item["word"],
            ":turkish" => $item["turkish"],
            ":definition" => ($item["definition"] !== "" ? $item["definition"] : null),
            ":example_en" => ($item["example_en"] !== "" ? $item["example_en"] : null),
            ":example_tr" => ($item["example_tr"] !== "" ? $item["example_tr"] : null),
            ":synonyms" => !empty($item["synonyms"]) ? json_encode(array_values($item["synonyms"]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            ":antonyms" => !empty($item["antonyms"]) ? json_encode(array_values($item["antonyms"]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        ]);
    }

    private function updateVocabRow(int $id, array $item): void
    {
        $pdo = Db::pdo();
        $st = $pdo->prepare("
            UPDATE vocab
            SET level = :level, word = :word, turkish = :turkish, definition = :definition,
                example_en = :example_en, example_tr = :example_tr, synonyms = :synonyms, antonyms = :antonyms
            WHERE id = :id
        ");
        $st->execute([
            ":id" => $id,
            ":level" => $item["level"],
            ":word" => $item["word"],
            ":turkish" => $item["turkish"],
            ":definition" => ($item["definition"] !== "" ? $item["definition"] : null),
            ":example_en" => ($item["example_en"] !== "" ? $item["example_en"] : null),
            ":example_tr" => ($item["example_tr"] !== "" ? $item["example_tr"] : null),
            ":synonyms" => !empty($item["synonyms"]) ? json_encode(array_values($item["synonyms"]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            ":antonyms" => !empty($item["antonyms"]) ? json_encode(array_values($item["antonyms"]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        ]);
    }

    private function handleAdminUserVerifyPost(int $userId): void
    {
        $actor = $this->currentUser();
        if (!$actor || !$this->isFounder($actor)) {
            http_response_code(403);
            echo "Forbidden";
            return;
        }
        $pdo = Db::pdo();
        $badge = strtolower(trim((string) ($_POST["verified_badge"] ?? "")));
        if (!in_array($badge, ["", "none", "blue", "gold"], true)) {
            http_response_code(400);
            echo "Invalid badge";
            return;
        }
        $st = $pdo->prepare("UPDATE user SET verified_badge = :b WHERE id = :id");
        $st->execute([
            ":b" => ($badge === "" || $badge === "none") ? null : $badge,
            ":id" => $userId,
        ]);
        $target = $this->findUserById($userId);
        $this->logAudit($actor, "verify_user", "user", $userId, [
            "username" => $target["username"] ?? null,
            "verified_badge" => (($badge === "" || $badge === "none") ? null : $badge),
        ]);
        $this->redirect("/admin/users");
    }

    private function handleAdminUserGrantRoadPost(int $userId): void
    {
        $actor = $this->currentUser();
        if (!$actor || !$this->isFounder($actor)) {
            http_response_code(403);
            echo "Forbidden";
            return;
        }
        $pdo = Db::pdo();
        $st = $pdo->prepare("SELECT * FROM user WHERE id = :id LIMIT 1");
        $st->execute([":id" => $userId]);
        $target = $st->fetch();
        if (!$target) {
            http_response_code(404);
            echo "Not found";
            return;
        }
        if ((string) ($target["role"] ?? "") !== "student") {
            http_response_code(400);
            echo "Only learner accounts can receive Road.";
            return;
        }
        $rawAmount = trim((string) ($_POST["road_amount"] ?? ""));
        $amount = filter_var($rawAmount, FILTER_VALIDATE_INT);
        if ($amount === false || $amount <= 0) {
            http_response_code(400);
            echo "Invalid Road amount.";
            return;
        }
        if ($amount > 1000000) {
            http_response_code(400);
            echo "Road amount is too large.";
            return;
        }

        $meta = $this->ensureStudentMetaForUser($target);
        if (!$meta) {
            http_response_code(500);
            echo "Could not prepare learner meta.";
            return;
        }
        $before = (int) ($meta["road_tokens"] ?? 0);
        $up = $pdo->prepare("UPDATE student_meta SET road_tokens = :r WHERE id = :id");
        $up->execute([":r" => $before + (int) $amount, ":id" => (int) $meta["id"]]);
        $this->logAudit($actor, "grant_road", "user", $userId, [
            "username" => $target["username"] ?? null,
            "amount" => (int) $amount,
            "before" => $before,
            "after" => $before + (int) $amount,
        ]);
        $this->redirect("/admin/users");
    }

    private function ensureStudentMetaForUser(array $user): ?array
    {
        if ((string) ($user["role"] ?? "") !== "student") {
            return null;
        }
        $pdo = Db::pdo();
        $studentId = (int) ($user["student_id"] ?? 0);

        if ($studentId <= 0) {
            $s = $pdo->prepare("SELECT id FROM student WHERE name = :n LIMIT 1");
            $s->execute([":n" => (string) $user["username"]]);
            $row = $s->fetch();
            if ($row) {
                $studentId = (int) $row["id"];
            } else {
                $insS = $pdo->prepare("INSERT INTO student (name, created_at) VALUES (:n, UTC_TIMESTAMP())");
                $insS->execute([":n" => (string) $user["username"]]);
                $studentId = (int) $pdo->lastInsertId();
            }
            $updU = $pdo->prepare("UPDATE user SET student_id = :sid WHERE id = :uid");
            $updU->execute([":sid" => $studentId, ":uid" => (int) $user["id"]]);
        }

        $m = $pdo->prepare("SELECT * FROM student_meta WHERE student_id = :sid LIMIT 1");
        $m->execute([":sid" => $studentId]);
        $meta = $m->fetch();
        if ($meta) {
            $updates = [];
            if (!array_key_exists("theme_pack_owned", $meta) || $meta["theme_pack_owned"] === null) {
                $updates["theme_pack_owned"] = 0;
                $meta["theme_pack_owned"] = 0;
            }
            if (!array_key_exists("preferred_theme", $meta) || (string) $meta["preferred_theme"] === "") {
                $updates["preferred_theme"] = "default";
                $meta["preferred_theme"] = "default";
            }
            if (!array_key_exists("name_glow_enabled", $meta) || $meta["name_glow_enabled"] === null) {
                $updates["name_glow_enabled"] = 0;
                $meta["name_glow_enabled"] = 0;
            }
            if (!array_key_exists("owned_profile_frames", $meta) || $meta["owned_profile_frames"] === null) {
                $updates["owned_profile_frames"] = json_encode(["default"], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $meta["owned_profile_frames"] = $updates["owned_profile_frames"];
            }
            if (!array_key_exists("profile_frame_style", $meta) || (string) $meta["profile_frame_style"] === "") {
                $updates["profile_frame_style"] = "default";
                $meta["profile_frame_style"] = "default";
            }
            if (!array_key_exists("owned_chat_bubbles", $meta) || $meta["owned_chat_bubbles"] === null) {
                $updates["owned_chat_bubbles"] = json_encode(["default"], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $meta["owned_chat_bubbles"] = $updates["owned_chat_bubbles"];
            }
            if (!array_key_exists("chat_bubble_style", $meta) || (string) $meta["chat_bubble_style"] === "") {
                $updates["chat_bubble_style"] = "default";
                $meta["chat_bubble_style"] = "default";
            }
            if (!array_key_exists("mission_claimed", $meta) || $meta["mission_claimed"] === null) {
                $updates["mission_claimed"] = json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $meta["mission_claimed"] = $updates["mission_claimed"];
            }
            if (!array_key_exists("weekly_bonus_week", $meta) || $meta["weekly_bonus_week"] === null) {
                $updates["weekly_bonus_week"] = null;
            }
            if ($updates) {
                $setParts = [];
                $params = [":id" => (int) $meta["id"]];
                foreach ($updates as $key => $val) {
                    $setParts[] = "{$key} = :{$key}";
                    $params[":{$key}"] = $val;
                }
                $pdo->prepare("UPDATE student_meta SET " . implode(", ", $setParts) . " WHERE id = :id")->execute($params);
            }
            return $meta;
        }
        $insM = $pdo->prepare("
            INSERT INTO student_meta (
                student_id, streak_freezes, road_tokens, daily_target, lives_remaining, lives_reset_at,
                theme_pack_owned, preferred_theme, name_glow_enabled, owned_profile_frames, profile_frame_style,
                owned_chat_bubbles, chat_bubble_style, mission_claimed, weekly_bonus_week
            )
            VALUES (
                :sid, 2, 0, 20, :l, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 15 MINUTE),
                0, 'default', 0, :opf, 'default', :ocb, 'default', :mclaimed, NULL
            )
        ");
        $insM->execute([
            ":sid" => $studentId,
            ":l" => self::QUIZ_LIVES_MAX,
            ":opf" => json_encode(["default"], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ":ocb" => json_encode(["default"], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ":mclaimed" => json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
        $m->execute([":sid" => $studentId]);
        $meta = $m->fetch();
        return is_array($meta) ? $meta : null;
    }

    private function requireAdmin(): void
    {
        $user = $this->currentUser();
        if (!$user) {
            $this->redirect("/login");
            return;
        }
        if (!in_array((string) ($user["role"] ?? ""), ["admin"], true)) {
            http_response_code(403);
            echo "Forbidden";
            exit;
        }
    }

    private function isFounder(?array $user): bool
    {
        return $user !== null && strtolower((string) ($user["username"] ?? "")) === "linustor";
    }

    private function createNotification(int $userId, string $message, string $kind = "info"): void
    {
        try {
            $pdo = Db::pdo();
            $st = $pdo->prepare("INSERT INTO notification (user_id, message, kind, is_read, created_at) VALUES (:u, :m, :k, 0, UTC_TIMESTAMP())");
            $st->execute([":u" => $userId, ":m" => $message, ":k" => $kind]);
            $this->maybeSendWebPushToUser($userId, "RoadToWord", $message, [
                "tag" => "rtw-notification-" . $kind,
                "url" => "/notifications",
            ]);
        } catch (\Throwable $e) {
            // ignore notification failures
        }
    }

    private function logAudit(?array $actor, string $action, string $entityType, $entityId = null, $details = null): void
    {
        try {
            $pdo = Db::pdo();
            $payload = is_array($details) ? json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (is_string($details) ? $details : null);
            $st = $pdo->prepare("
                INSERT INTO audit_log (created_at, actor_user_id, action, entity_type, entity_id, details)
                VALUES (UTC_TIMESTAMP(), :actor, :action, :etype, :eid, :details)
            ");
            $st->execute([
                ":actor" => $actor ? (int) ($actor["id"] ?? 0) : null,
                ":action" => $action,
                ":etype" => $entityType,
                ":eid" => ($entityId === null ? null : (string) $entityId),
                ":details" => $payload,
            ]);
        } catch (\Throwable $e) {
        }
    }

    private function ensureBotUser(): ?array
    {
        $pdo = Db::pdo();
        $st = $pdo->prepare("SELECT * FROM user WHERE username = 'RoadToWord' LIMIT 1");
        $st->execute();
        $bot = $st->fetch();
        if ($bot) {
            return $bot;
        }
        try {
            $ins = $pdo->prepare("INSERT INTO user (username, role, password, full_name) VALUES ('RoadToWord', 'admin', NULL, 'RoadToWord')");
            $ins->execute();
            $st->execute();
            $bot = $st->fetch();
            return is_array($bot) ? $bot : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function sendBotMessageToUser(array $toUser, string $content): void
    {
        $bot = $this->ensureBotUser();
        if (!$bot) {
            return;
        }
        try {
            $pdo = Db::pdo();
            $ins = $pdo->prepare("INSERT INTO `message` (from_user_id, to_user_id, content, created_at) VALUES (:f, :t, :c, UTC_TIMESTAMP())");
            $ins->execute([":f" => (int) $bot["id"], ":t" => (int) $toUser["id"], ":c" => $content]);
            $up = $pdo->prepare("UPDATE user SET last_bot_message_at = UTC_TIMESTAMP() WHERE id = :id");
            $up->execute([":id" => (int) $toUser["id"]]);
            $sender = (string) ($bot["full_name"] ?? "") !== "" ? (string) $bot["full_name"] : "RoadToWord";
            $this->createNotification((int) $toUser["id"], $sender . ": " . $content, "info");
        } catch (\Throwable $e) {
            // ignore bot failures
        }
    }

    private function sendBotIntroIfNeeded(array $user): void
    {
        if (strtolower((string) ($user["username"] ?? "")) === "roadtoword") {
            return;
        }
        if (!empty($user["bot_opt_out"])) {
            return;
        }
        if (!empty($user["bot_intro_sent_at"])) {
            return;
        }
        $intro = "Hi! I am RoadToWord Bot. I will often message you about your day and your streak. "
            . "If you don't want to get messages from this bot, simply type 'stop messaging'. "
            . "You can resume anytime by typing 'start messaging'.";
        $this->sendBotMessageToUser($user, $intro);
        try {
            $pdo = Db::pdo();
            $up = $pdo->prepare("UPDATE user SET bot_intro_sent_at = UTC_TIMESTAMP() WHERE id = :id");
            $up->execute([":id" => (int) $user["id"]]);
        } catch (\Throwable $e) {
        }
    }

    private function maybeHandleBotCommand(array $user, array $peer, string $content): void
    {
        if (strtolower((string) ($peer["username"] ?? "")) !== "roadtoword") {
            return;
        }
        $lowered = strtolower($content);
        $pdo = Db::pdo();
        if (str_contains($lowered, "stop messaging")) {
            try {
                $up = $pdo->prepare("UPDATE user SET bot_opt_out = 1 WHERE id = :id");
                $up->execute([":id" => (int) $user["id"]]);
            } catch (\Throwable $e) {
            }
            $fresh = $this->findUserByUsername((string) $user["username"]);
            if ($fresh) {
                $this->sendBotMessageToUser($fresh, "Stopping messages from @RoadToWord....");
            }
            return;
        }
        if (str_contains($lowered, "start messaging")) {
            try {
                $up = $pdo->prepare("UPDATE user SET bot_opt_out = 0 WHERE id = :id");
                $up->execute([":id" => (int) $user["id"]]);
            } catch (\Throwable $e) {
            }
            $fresh = $this->findUserByUsername((string) $user["username"]);
            if ($fresh) {
                $this->sendBotMessageToUser($fresh, "Starting messages from @RoadToWord....");
            }
        }
    }

    private function maybeSendBotMessage(): void
    {
        try {
            if (strtoupper((string) ($_SERVER["REQUEST_METHOD"] ?? "GET")) !== "GET") {
                return;
            }
            $uri = $this->requestPath();
            if (str_starts_with($uri, "/static/") || str_starts_with($uri, "/api/") || in_array($uri, ["/sw.js", "/favicon.ico"], true)) {
                return;
            }
            $r = mt_rand(1, 10000) / 10000;
            if ($r >= 0.02) {
                return;
            }
            $bot = $this->ensureBotUser();
            if (!$bot) {
                return;
            }
            $pdo = Db::pdo();
            if ($r < 0.01) {
                $rows = $pdo->query("SELECT * FROM user WHERE username <> 'RoadToWord' AND COALESCE(bot_opt_out,0)=0 ORDER BY RAND() LIMIT 20")->fetchAll();
                $pool = ["How's it going?", "Howdy?", "Hope you're having a great day!", "Keep up the awesome work!", "Checking in - how's your practice going?"];
            } else {
                $rows = $pdo->query("SELECT * FROM user WHERE role = 'student' AND COALESCE(bot_opt_out,0)=0 ORDER BY RAND() LIMIT 20")->fetchAll();
                $pool = ["How's your streak going?", "Keeping the streak alive today?", "Want to hit your daily goal together?", "Streak check: how's it going?", "Need a quick practice to keep your streak?"];
            }
            $now = time();
            foreach ($rows as $target) {
                $last = (string) ($target["last_bot_message_at"] ?? "");
                $lastTs = $last !== "" ? strtotime($last . " UTC") : 0;
                if ($lastTs > 0 && ($now - $lastTs) < (3 * 60 * 60)) {
                    continue;
                }
                $this->sendBotMessageToUser($target, $pool[array_rand($pool)]);
                break;
            }
        } catch (\Throwable $e) {
        }
    }

    private function logRequestAndMaybeBot(): void
    {
        try {
            $uri = $this->requestPath();
            if (!str_starts_with($uri, "/static/") && !in_array($uri, ["/favicon.ico", "/sw.js", "/manifest.json"], true)) {
                $pdo = Db::pdo();
                $ua = (string) ($_SERVER["HTTP_USER_AGENT"] ?? "");
                $ref = (string) ($_SERVER["HTTP_REFERER"] ?? "no referrer");
                $geo = $this->lookupIpInfo($this->clientIp());
                $uaInfo = UserAgent::parse($ua);
                $host = null;
                try {
                    $resolved = @gethostbyaddr($this->clientIp());
                    if (is_string($resolved) && $resolved !== "" && $resolved !== $this->clientIp()) {
                        $host = $resolved;
                    }
                } catch (\Throwable $e) {
                }
                $st = $pdo->prepare("
                    INSERT INTO log_entry (created_at, ip_address, country, city, browser, operating_system, user_agent, referrer, host_name, isp, path, method, status_code)
                    VALUES (UTC_TIMESTAMP(), :ip, :country, :city, :browser, :os, :ua, :ref, :host, :isp, :path, :method, :status)
                ");
                $st->execute([
                    ":ip" => $this->clientIp(),
                    ":country" => $geo["country"] ?? null,
                    ":city" => $geo["city"] ?? null,
                    ":browser" => $uaInfo["browser"] ?? null,
                    ":os" => $uaInfo["os"] ?? null,
                    ":ua" => ($ua !== "" ? $ua : null),
                    ":ref" => ($ref !== "" ? $ref : "no referrer"),
                    ":host" => $host,
                    ":isp" => $geo["asn_org"] ?? null,
                    ":path" => $uri,
                    ":method" => (string) ($_SERVER["REQUEST_METHOD"] ?? "GET"),
                    ":status" => http_response_code(),
                ]);
            }
        } catch (\Throwable $e) {
        }
        $this->maybeSendBotMessage();
    }

    private function lookupIpInfo(string $ip): array
    {
        $token = Config::ipInfoToken();
        if ($token === "") {
            return ["country" => null, "city" => null, "asn_org" => null, "country_code" => null];
        }
        $url = "https://api.ipinfo.io/lite/" . rawurlencode($ip) . "?token=" . rawurlencode($token);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $raw = curl_exec($ch);
        curl_close($ch);
        if (!is_string($raw) || $raw === "") {
            return ["country" => null, "city" => null, "asn_org" => null, "country_code" => null];
        }
        $json = json_decode($raw, true);
        if (!is_array($json)) {
            return ["country" => null, "city" => null, "asn_org" => null, "country_code" => null];
        }
        $country = null;
        $city = null;
        $org = null;
        $countryCode = null;
        if (!empty($json["country_code"])) {
            $countryCode = strtoupper((string) $json["country_code"]);
        } elseif (!empty($json["country"])) {
            $countryRaw = (string) $json["country"];
            if (strlen($countryRaw) === 2 && ctype_alpha($countryRaw)) {
                $countryCode = strtoupper($countryRaw);
            }
        }
        if (!empty($json["country"])) {
            $country = (string) $json["country"];
        }
        if (!empty($json["city"])) {
            $city = (string) $json["city"];
        }
        if (!empty($json["org"])) {
            $org = (string) $json["org"];
        }
        return ["country" => $country, "city" => $city, "asn_org" => $org, "country_code" => $countryCode];
    }

    private function countryCodeToFlagUrl(?string $code): string
    {
        $clean = strtolower(trim((string) $code));
        if (strlen($clean) !== 2 || !ctype_alpha($clean)) {
            return "";
        }
        return "https://flagcdn.com/32x24/" . $clean . ".png";
    }

    private function countryCodeToFlag(?string $code): string
    {
        $clean = strtoupper(trim((string) $code));
        if (strlen($clean) !== 2 || !ctype_alpha($clean)) {
            return "";
        }
        $a = 0x1F1E6 + (ord($clean[0]) - 65);
        $b = 0x1F1E6 + (ord($clean[1]) - 65);
        return mb_chr($a, "UTF-8") . mb_chr($b, "UTF-8");
    }

    private function getWelcomePayload(): array
    {
        $ip = $this->clientIp();
        $geo = $this->lookupIpInfo($ip);
        $country = trim((string) ($geo["country"] ?? ""));
        $city = trim((string) ($geo["city"] ?? ""));
        $countryCode = trim((string) ($geo["country_code"] ?? ""));

        if ($city !== "" && $country !== "") {
            $location = $city . ", " . $country;
        } elseif ($country !== "") {
            $location = $country;
        } elseif ($city !== "") {
            $location = $city;
        } else {
            $location = "Unknown location";
        }

        return [
            "ip" => $ip !== "" ? $ip : "Unknown",
            "location" => $location,
            "country_code" => $countryCode !== "" ? $countryCode : null,
            "flag" => $this->countryCodeToFlag($countryCode),
            "flag_url" => $this->countryCodeToFlagUrl($countryCode),
        ];
    }

    private function roadBalanceText(int $road): string
    {
        return $road === 1 ? "1 Road" : ($road . " Roads");
    }

    private function normalizeOwnedList(?string $raw, array $defaults): array
    {
        $items = $this->decodeJsonList($raw);
        $cleaned = [];
        foreach ($items as $v) {
            $t = strtolower(trim((string) $v));
            if ($t !== "") {
                $cleaned[] = $t;
            }
        }
        $out = [];
        foreach (array_merge($defaults, $cleaned) as $v) {
            if (!in_array($v, $out, true)) {
                $out[] = $v;
            }
        }
        return $out;
    }

    private function weekKey(\DateTimeImmutable $day): string
    {
        return $day->format("o-\\WW");
    }

    private function historyMap(?string $raw): array
    {
        if ($raw === null || trim($raw) === "") {
            return [];
        }
        $parsed = json_decode((string) $raw, true);
        if (!is_array($parsed)) {
            return [];
        }
        $map = [];
        foreach ($parsed as $item) {
            if (!is_array($item)) {
                continue;
            }
            $date = (string) ($item["date"] ?? "");
            $status = (string) ($item["status"] ?? "");
            if ($date !== "" && $status !== "") {
                $map[$date] = $status;
            }
        }
        return $map;
    }

    private function recordStreakDay(array $history, \DateTimeImmutable $day, string $status): array
    {
        $key = $day->format("Y-m-d");
        $out = [];
        foreach ($history as $item) {
            if (!is_array($item)) {
                continue;
            }
            if ((string) ($item["date"] ?? "") === $key) {
                continue;
            }
            $out[] = $item;
        }
        $out[] = ["date" => $key, "status" => $status];
        usort($out, fn($a, $b) => strcmp((string) ($a["date"] ?? ""), (string) ($b["date"] ?? "")));
        if (count($out) > 120) {
            $out = array_slice($out, -120);
        }
        return $out;
    }

    private function ensureDailyRollover(array $meta): array
    {
        $today = new \DateTimeImmutable("now", new \DateTimeZone("UTC"));
        $todayStr = $today->format("Y-m-d");
        $lastDay = (string) ($meta["last_day"] ?? "");
        $changed = false;
        $dailyDone = (int) ($meta["daily_done"] ?? 0);
        $dailyTarget = max(1, (int) ($meta["daily_target"] ?? 20));
        $dailyStreak = (int) ($meta["daily_streak"] ?? 0);
        $bestDailyStreak = (int) ($meta["best_daily_streak"] ?? 0);
        $streakFreezes = (int) ($meta["streak_freezes"] ?? 0);
        $historyRaw = (string) ($meta["streak_history"] ?? "");
        $history = json_decode($historyRaw, true);
        if (!is_array($history)) {
            $history = [];
        }

        if ($lastDay === "") {
            $lastDay = $todayStr;
            $changed = true;
        }

        if ($lastDay !== $todayStr) {
            $prevDay = \DateTimeImmutable::createFromFormat("Y-m-d", $lastDay, new \DateTimeZone("UTC")) ?: $today;
            if ($dailyDone >= $dailyTarget) {
                $history = $this->recordStreakDay($history, $prevDay, "done");
                if ($dailyStreak > 0 && $dailyStreak % 7 === 0) {
                    $streakFreezes = min($streakFreezes + 1, 2);
                }
            } else {
                if ($dailyStreak > 0 && $streakFreezes > 0) {
                    $streakFreezes -= 1;
                    $history = $this->recordStreakDay($history, $prevDay, "freeze");
                } else {
                    $history = $this->recordStreakDay($history, $prevDay, "missed");
                    $dailyStreak = 0;
                }
            }
            $dailyDone = 0;
            $lastDay = $todayStr;
            $changed = true;
        }

        if ($changed) {
            $pdo = Db::pdo();
            $st = $pdo->prepare("
                UPDATE student_meta
                SET daily_done = :dd,
                    last_day = :ld,
                    daily_streak = :ds,
                    best_daily_streak = :bds,
                    streak_freezes = :sf,
                    streak_history = :sh
                WHERE id = :id
            ");
            $st->execute([
                ":dd" => $dailyDone,
                ":ld" => $lastDay,
                ":ds" => $dailyStreak,
                ":bds" => $bestDailyStreak,
                ":sf" => $streakFreezes,
                ":sh" => json_encode(array_values($history), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ":id" => (int) ($meta["id"] ?? 0),
            ]);
            $meta["daily_done"] = $dailyDone;
            $meta["last_day"] = $lastDay;
            $meta["daily_streak"] = $dailyStreak;
            $meta["best_daily_streak"] = $bestDailyStreak;
            $meta["streak_freezes"] = $streakFreezes;
            $meta["streak_history"] = json_encode(array_values($history), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return $meta;
    }

    private function getDailyStreakContext(array $meta): array
    {
        $today = new \DateTimeImmutable("now", new \DateTimeZone("UTC"));
        $history = $this->historyMap((string) ($meta["streak_history"] ?? ""));
        $yesterdayKey = $today->modify("-1 day")->format("Y-m-d");
        $yesterdayStatus = $history[$yesterdayKey] ?? null;
        $broke = false;
        $lastGoalDay = (string) ($meta["last_goal_day"] ?? "");
        if ($yesterdayStatus === "missed") {
            $broke = true;
        } elseif ($lastGoalDay !== "" && $lastGoalDay < $today->format("Y-m-d")) {
            $yesterday = $today->format("Y-m-d");
            $prev = $today->modify("-1 day")->format("Y-m-d");
            $broke = $lastGoalDay !== $prev;
        }

        $dailyDone = (int) ($meta["daily_done"] ?? 0);
        $dailyTarget = max(1, (int) ($meta["daily_target"] ?? 20));
        $message = "";
        if ($broke) {
            $pool = [
                "Ahhh, streak broken! Restart your daily goal today.",
                "Ouch — the streak slipped. Let's rebuild it now!",
                "Streak missed, but not gone forever. Start again today!",
                "Rough day! Jump back in and reignite your streak.",
            ];
            $message = $pool[array_rand($pool)];
        } elseif ($dailyDone >= $dailyTarget) {
            $pool = [
                "Daily goal complete! Keep the streak alive!",
                "You did it today — streak protected!",
                "Goal finished! Come back tomorrow to extend it.",
                "Nice work! Your streak is safe for today.",
                "Amazing! Daily goal achieved — see you tomorrow.",
                "You crushed it! Today's streak is locked in.",
            ];
            $message = $pool[array_rand($pool)];
        } elseif ($dailyDone > 0) {
            $pool = [
                "Great start! You're on your way to today's goal.",
                "Nice progress — keep going to protect the streak.",
                "Good momentum! Finish today's 20 to keep it alive.",
                "You're rolling — stay the course and hit the goal.",
                "Solid pace! A few more and you're there.",
                "Keep the rhythm — you're close to the goal.",
                "You're building a habit — keep it up!",
            ];
            $message = $pool[array_rand($pool)];
        } else {
            $pool = [
                "Start your daily streak today — {$dailyTarget} questions to go!",
                "New day, new streak — finish {$dailyTarget} to keep it alive!",
                "Time to begin — {$dailyTarget} questions and the streak is safe.",
                "Let's go! {$dailyTarget} questions to secure today's streak.",
                "Small start, big streak — {$dailyTarget} to go!",
                "Your streak awaits — {$dailyTarget} questions today.",
                "Ready to roll? {$dailyTarget} questions keeps the streak going.",
            ];
            $message = $pool[array_rand($pool)];
        }
        return ["message" => $message, "broke" => $broke];
    }

    private function getStreakCalendar(array $meta): array
    {
        $today = new \DateTimeImmutable("now", new \DateTimeZone("UTC"));
        $history = $this->historyMap((string) ($meta["streak_history"] ?? ""));
        $year = (int) $today->format("Y");
        $month = (int) $today->format("m");
        $daysInMonth = (int) $today->format("t");
        $items = [];
        for ($dayNum = 1; $dayNum <= $daysInMonth; $dayNum++) {
            $day = new \DateTimeImmutable(sprintf("%04d-%02d-%02d", $year, $month, $dayNum), new \DateTimeZone("UTC"));
            $key = $day->format("Y-m-d");
            $status = $history[$key] ?? null;
            $label = $status ?? "missed";
            if ($day > $today) {
                $status = "future";
                $label = "future";
            }
            if ($day->format("Y-m-d") === $today->format("Y-m-d")) {
                $dailyDone = (int) ($meta["daily_done"] ?? 0);
                $dailyTarget = max(1, (int) ($meta["daily_target"] ?? 20));
                if ($dailyDone >= $dailyTarget) {
                    $status = "done";
                    $label = "done";
                } elseif ($dailyDone > 0) {
                    $status = "inprogress";
                    $label = "in progress";
                } else {
                    $status = "today";
                    $label = "today";
                }
            }
            if ($status === null) {
                $status = "missed";
            }
            $items[] = [
                "date" => $key,
                "status" => $status,
                "label" => $label,
                "is_today" => $day->format("Y-m-d") === $today->format("Y-m-d"),
            ];
        }
        return $items;
    }

    private function buildDailyMissions(array $meta): array
    {
        $missionDay = (string) ($meta["mission_day"] ?? "");
        $today = (new \DateTimeImmutable("now", new \DateTimeZone("UTC")))->format("Y-m-d");
        $questionsDone = (int) ($meta["mission_questions_done"] ?? 0);
        $bestStreak = (int) ($meta["mission_best_correct_streak"] ?? 0);
        if ($missionDay !== $today) {
            $questionsDone = 0;
            $bestStreak = 0;
        }
        $claimed = $this->decodeJsonList($meta["mission_claimed"] ?? null);
        $claimedMap = array_flip($claimed);
        return [
            [
                "id" => "questions30",
                "label" => "Answer " . self::DAILY_MISSION_QUESTIONS_TARGET . " questions today",
                "progress" => min($questionsDone, self::DAILY_MISSION_QUESTIONS_TARGET),
                "target" => self::DAILY_MISSION_QUESTIONS_TARGET,
                "reward" => self::DAILY_MISSION_QUESTIONS_REWARD,
                "completed" => isset($claimedMap["questions30"]),
            ],
            [
                "id" => "correct5",
                "label" => "Get " . self::DAILY_MISSION_STREAK_TARGET . " correct answers in a row",
                "progress" => min($bestStreak, self::DAILY_MISSION_STREAK_TARGET),
                "target" => self::DAILY_MISSION_STREAK_TARGET,
                "reward" => self::DAILY_MISSION_STREAK_REWARD,
                "completed" => isset($claimedMap["correct5"]),
            ],
        ];
    }

    private function buildAchievements(array $meta, array $report = []): array
    {
        $totalCorrect = (int) ($report["total_correct"] ?? 0);
        $road = (int) ($meta["road_tokens"] ?? 0);
        $achievements = [];
        if ($road >= 100) {
            $achievements[] = "First 100 Roads";
        }
        if ($road >= 500) {
            $achievements[] = "Road Collector (500)";
        }
        if (!empty($meta["daily_streak"]) && (int) $meta["daily_streak"] >= 30) {
            $achievements[] = "30-Day Daily Streak";
        }
        if (!empty($meta["best_streak"]) && (int) $meta["best_streak"] >= 100) {
            $achievements[] = "Quiz Combo 100";
        }
        if ($totalCorrect >= 1000) {
            $achievements[] = "Word Worker (1000 correct)";
        }
        return $achievements;
    }

    private function maybeApplyReferralBonus(array $newUser, ?string $referrerUsername): void
    {
        if (!$referrerUsername) {
            return;
        }
        $ref = trim($referrerUsername);
        if ($ref === "") {
            return;
        }
        if (!empty($newUser["referred_by_id"])) {
            return;
        }
        if (strtolower($ref) === strtolower((string) ($newUser["username"] ?? ""))) {
            return;
        }
        $pdo = Db::pdo();
        $st = $pdo->prepare("SELECT * FROM user WHERE LOWER(username)=LOWER(:u) LIMIT 1");
        $st->execute([":u" => $ref]);
        $refUser = $st->fetch();
        if (!$refUser || (string) ($refUser["role"] ?? "") !== "student") {
            return;
        }
        $meta = $this->ensureStudentMetaForUser($refUser);
        if (!$meta) {
            return;
        }
        $road = (int) ($meta["road_tokens"] ?? 0);
        $pdo->prepare("UPDATE student_meta SET road_tokens = :r WHERE id = :id")
            ->execute([":r" => $road + self::ROAD_REFERRAL_BONUS, ":id" => (int) $meta["id"]]);
        $pdo->prepare("UPDATE user SET referred_by_id = :rid WHERE id = :id")
            ->execute([":rid" => (int) ($refUser["id"] ?? 0), ":id" => (int) ($newUser["id"] ?? 0)]);
        $this->createNotification((int) ($refUser["id"] ?? 0), "Referral bonus: +" . self::ROAD_REFERRAL_BONUS . " Road for inviting @" . (string) ($newUser["username"] ?? "") . ".", "info");
    }

    private function buildWeeklyProgress(int $studentId, array $meta): ?array
    {
        if ($studentId <= 0) {
            return null;
        }
        $today = new \DateTimeImmutable("now", new \DateTimeZone("UTC"));
        $start = $today->modify("-" . ($today->format("N") - 1) . " days");
        $pdo = Db::pdo();
        $st = $pdo->prepare("
            SELECT COALESCE(SUM(correct),0) AS c, COALESCE(SUM(wrong),0) AS w
            FROM student_daily_stat
            WHERE student_id = :sid AND day >= :start AND day <= :end
        ");
        $st->execute([
            ":sid" => $studentId,
            ":start" => $start->format("Y-m-d"),
            ":end" => $today->format("Y-m-d"),
        ]);
        $row = $st->fetch();
        $count = (int) ($row["c"] ?? 0) + (int) ($row["w"] ?? 0);
        return [
            "count" => $count,
            "target" => self::WEEKLY_CHALLENGE_TARGET,
            "done" => $count >= self::WEEKLY_CHALLENGE_TARGET,
            "reward" => self::WEEKLY_CHALLENGE_REWARD,
        ];
    }

    private function getBadgeLabel(?array $user, int $totalCorrect): ?string
    {
        $username = $user ? (string) ($user["username"] ?? "") : "";
        $role = $user ? (string) ($user["role"] ?? "") : null;
        $badgeOverride = $user ? (string) ($user["badge_override"] ?? "") : null;
        $special = $this->specialBadgeLabel($username, $role, $badgeOverride);
        if ($special) {
            return $special;
        }
        $levels = [
            [200, "🏆 Master"],
            [100, "Gold"],
            [50, "Silver"],
            [20, "Bronze"],
        ];
        foreach ($levels as [$limit, $name]) {
            if ($totalCorrect >= $limit) {
                return $name;
            }
        }
        return null;
    }

    private function getCefrProgress(int $studentId): array
    {
        if ($studentId <= 0) {
            return [];
        }
        $pdo = Db::pdo();
        $rows = $pdo->prepare("SELECT level, correct FROM student_word_stat WHERE student_id = :sid");
        $rows->execute([":sid" => $studentId]);
        $stats = $rows->fetchAll();
        if (!$stats) {
            return [];
        }
        $levels = ["A1", "A2", "B1", "B2", "C1", "C2"];
        $byLevel = [];
        foreach ($levels as $lvl) {
            $byLevel[$lvl] = [];
        }
        foreach ($stats as $s) {
            $lvl = strtoupper((string) ($s["level"] ?? "A1"));
            if (!isset($byLevel[$lvl])) {
                continue;
            }
            $byLevel[$lvl][] = $s;
        }
        $currentLevel = "A1";
        foreach ($levels as $lvl) {
            if (empty($byLevel[$lvl])) {
                continue;
            }
            $hasZero = false;
            foreach ($byLevel[$lvl] as $s) {
                if ((int) ($s["correct"] ?? 0) <= 0) {
                    $hasZero = true;
                    break;
                }
            }
            if ($hasZero) {
                $currentLevel = $lvl;
                break;
            }
            $currentLevel = $lvl;
        }
        $progress = [];
        $unlocked = true;
        foreach ($levels as $lvl) {
            $levelStats = $byLevel[$lvl] ?? [];
            $total = count($levelStats);
            $mastered = 0;
            foreach ($levelStats as $s) {
                if ((int) ($s["correct"] ?? 0) > 0) {
                    $mastered++;
                }
            }
            $pct = $total > 0 ? round(($mastered * 100.0) / $total, 1) : 0.0;
            $status = "locked";
            if ($unlocked) {
                if ($lvl === $currentLevel) {
                    $status = "current";
                } else {
                    $status = ($total > 0 && $mastered === $total) ? "complete" : "active";
                }
            }
            $progress[] = [
                "level" => $lvl,
                "total" => $total,
                "mastered" => $mastered,
                "pct" => $pct,
                "status" => $status,
            ];
            if ($lvl === $currentLevel) {
                $unlocked = false;
            }
        }
        return $progress;
    }

    private function specialBadgeLabel(string $username, ?string $role = null, ?string $badgeOverride = null): ?string
    {
        $u = strtolower(trim($username));
        if ($u === "linustor") {
            return "< /\\/ > Founder & Developer";
        }
        if ($u === "roadtoword") {
            return "Bot";
        }
        if ($badgeOverride !== null && trim($badgeOverride) !== "") {
            return $badgeOverride;
        }
        if ($role === "admin") {
            return "🛡️ Administrator";
        }
        if ($role === "teacher") {
            return "🎓 Teacher";
        }
        return null;
    }

    private function findUserByUsername(string $username): ?array
    {
        $u = trim($username);
        if ($u === "") {
            return null;
        }
        $pdo = Db::pdo();
        $st = $pdo->prepare("SELECT * FROM user WHERE LOWER(username) = LOWER(:u) LIMIT 1");
        $st->execute([":u" => $u]);
        $row = $st->fetch();
        return is_array($row) ? $row : null;
    }

    private function findUserById(int $userId): ?array
    {
        $pdo = Db::pdo();
        $st = $pdo->prepare("SELECT * FROM user WHERE id = :id LIMIT 1");
        $st->execute([":id" => $userId]);
        $row = $st->fetch();
        return is_array($row) ? $row : null;
    }

    private function cspNonce(): string
    {
        if ($this->cspNonce === null) {
            $this->cspNonce = bin2hex(random_bytes(16));
        }
        return $this->cspNonce;
    }

    private function applySecurityHeaders(): void
    {
        $nonce = $this->cspNonce();
        header("X-Content-Type-Options: nosniff");
        header("X-Frame-Options: DENY");
        header("Referrer-Policy: same-origin");
        header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
        header("Cross-Origin-Resource-Policy: same-origin");
        header("Cross-Origin-Opener-Policy: same-origin");
        header("Cross-Origin-Embedder-Policy: unsafe-none");
        $https = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off")
            || ((string) ($_SERVER["HTTP_X_FORWARDED_PROTO"] ?? "") === "https");
        if ($https) {
            header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
        }
        $scriptSrc = "script-src 'self' 'nonce-{$nonce}' https://cdn.jsdelivr.net https://challenges.cloudflare.com; ";
        header(
            "Content-Security-Policy: "
            . "default-src 'self'; "
            . "img-src 'self' data: https:; "
            . "style-src 'self' 'unsafe-inline'; "
            . $scriptSrc
            . "font-src 'self' data:; "
            . "connect-src 'self' https://challenges.cloudflare.com https://api.ipinfo.io; "
            . "frame-src 'self' https://challenges.cloudflare.com; "
            . "child-src 'self' https://challenges.cloudflare.com; "
            . "frame-ancestors 'none'"
        );
    }

    private function currentMeta(): ?array
    {
        if ($this->cachedMeta !== null) {
            return $this->cachedMeta;
        }
        $user = $this->currentUser();
        if (!$user) {
            return null;
        }
        $meta = $this->studentMetaByUser($user);
        if (!$meta) {
            return null;
        }
        $this->cachedMeta = $meta;
        return $meta;
    }

    private function getUnreadNotificationCount(): int
    {
        $user = $this->currentUser();
        if (!$user) {
            return 0;
        }
        $pdo = Db::pdo();
        $st = $pdo->prepare("SELECT COUNT(*) AS c FROM notification WHERE user_id = :u AND is_read = 0");
        $st->execute([":u" => (int) $user["id"]]);
        $row = $st->fetch();
        return (int) ($row["c"] ?? 0);
    }

    private function getSeoKeywords(): string
    {
        try {
            $pdo = Db::pdo();
            $rows = $pdo->query("SELECT keyword FROM seo_keyword ORDER BY keyword ASC")->fetchAll();
            if (!$rows) {
                return "";
            }
            $list = [];
            foreach ($rows as $r) {
                $val = trim((string) ($r["keyword"] ?? ""));
                if ($val !== "") {
                    $list[] = $val;
                }
            }
            return implode(", ", $list);
        } catch (\Throwable $e) {
            return "";
        }
    }

    private function getNavNameGlow(): bool
    {
        $meta = $this->currentMeta();
        return $meta ? !empty($meta["name_glow_enabled"]) : false;
    }

    private function getChatBubbleStyle(): string
    {
        $meta = $this->currentMeta();
        $style = $meta ? (string) ($meta["chat_bubble_style"] ?? "default") : "default";
        return $style !== "" ? $style : "default";
    }

    private function getProfileFrameStyle(): string
    {
        $meta = $this->currentMeta();
        $style = $meta ? (string) ($meta["profile_frame_style"] ?? "default") : "default";
        return $style !== "" ? $style : "default";
    }

    private function turnstileEnabled(): bool
    {
        return Config::turnstileSiteKey() !== "" && Config::turnstileSecret() !== "";
    }

    private function render(string $template, array $data): void
    {
        if (!array_key_exists("csrf_token", $data)) {
            $data["csrf_token"] = Security::csrfToken();
        }
        if (!array_key_exists("csp_nonce", $data)) {
            $data["csp_nonce"] = $this->cspNonce();
        }
        if (!array_key_exists("theme_class", $data)) {
            $data["theme_class"] = $this->resolveThemeClass();
        }
        if (!array_key_exists("notification_count", $data)) {
            $data["notification_count"] = $this->getUnreadNotificationCount();
        }
        if (!array_key_exists("seo_keywords", $data)) {
            $data["seo_keywords"] = $this->getSeoKeywords();
        }
        if (!array_key_exists("online_count", $data)) {
            $data["online_count"] = $this->getOnlineCount();
        }
        if (!array_key_exists("nav_name_glow", $data)) {
            $data["nav_name_glow"] = $this->getNavNameGlow();
        }
        if (!array_key_exists("google_enabled", $data)) {
            $data["google_enabled"] = $this->googleOauthEnabled();
        }
        if (!array_key_exists("turnstile_enabled", $data)) {
            $data["turnstile_enabled"] = $this->turnstileEnabled();
        }
        if (!array_key_exists("turnstile_sitekey", $data)) {
            $data["turnstile_sitekey"] = Config::turnstileSiteKey();
        }
        if (!array_key_exists("vapid_public_key", $data)) {
            $data["vapid_public_key"] = Config::vapidPublicKey();
        }
        if (!array_key_exists("chat_bubble_style", $data)) {
            $data["chat_bubble_style"] = $this->getChatBubbleStyle();
        }
        if (!array_key_exists("profile_frame_style", $data)) {
            $data["profile_frame_style"] = $this->getProfileFrameStyle();
        }
        extract($data, EXTR_SKIP);
        $tpl = BASE_DIR . "/templates_php/" . $template;
        if (!is_file($tpl)) {
            http_response_code(500);
            echo "Template not found: " . htmlspecialchars($template, ENT_QUOTES);
            return;
        }
        include BASE_DIR . "/templates_php/layout.php";
    }

    private function sessionUsername(): ?string
    {
        $u = $_SESSION["username"] ?? null;
        return is_string($u) && $u !== "" ? $u : null;
    }

    private function resolveThemeClass(): string
    {
        $user = $this->currentUser();
        if (!$user) {
            return "";
        }
        $meta = $this->studentMetaByUser($user);
        if (!$meta || empty($meta["theme_pack_owned"])) {
            return "";
        }
        $preferred = strtolower((string) ($meta["preferred_theme"] ?? ""));
        if (in_array($preferred, ["aurora", "ocean"], true)) {
            return "theme-" . $preferred;
        }
        return "";
    }

    private function getOnlineCount(): ?int
    {
        try {
            $pdo = Db::pdo();
            $row = $pdo->query("SELECT COUNT(DISTINCT ip_address) AS c FROM log_entry WHERE created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 MINUTE)")->fetch();
            return isset($row["c"]) ? (int) $row["c"] : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function buildCallRoomUrl(string $userA, string $userB): string
    {
        $a = strtolower(trim($userA));
        $b = strtolower(trim($userB));
        $pair = [$a, $b];
        sort($pair, SORT_STRING);
        $hash = substr(md5(implode("|", $pair)), 0, 24);
        $room = "RoadToWord-" . $hash;
        return "https://meet.jit.si/" . rawurlencode($room)
            . "#config.prejoinPageEnabled=false&config.startWithVideoMuted=true";
    }

    private function getTableColumns(string $table): array
    {
        try {
            $pdo = Db::pdo();
            $safeTable = str_replace("`", "``", $table);
            $rows = $pdo->query("SHOW COLUMNS FROM `" . $safeTable . "`")->fetchAll();
            $cols = [];
            foreach ($rows as $r) {
                $f = isset($r["Field"]) ? (string) $r["Field"] : "";
                if ($f !== "") {
                    $cols[] = $f;
                }
            }
            return $cols;
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function getTableNames(): array
    {
        try {
            $pdo = Db::pdo();
            $rows = $pdo->query("SHOW TABLES")->fetchAll(\PDO::FETCH_NUM);
            $out = [];
            foreach ($rows as $r) {
                $name = isset($r[0]) ? (string) $r[0] : "";
                if ($name !== "") {
                    $out[] = $name;
                }
            }
            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function resolveMessageTableName(): ?string
    {
        $tables = $this->getTableNames();
        if (!$tables) {
            return "message";
        }
        $candidates = ["message", "messages", "chat_message", "direct_message", "dm_message"];
        foreach ($candidates as $candidate) {
            foreach ($tables as $actual) {
                if (strcasecmp($actual, $candidate) === 0) {
                    return $actual;
                }
            }
        }
        foreach ($tables as $actual) {
            $t = strtolower($actual);
            if (str_contains($t, "message")) {
                return $actual;
            }
        }
        return null;
    }

    private function firstMatchingColumn(array $available, array $candidates): ?string
    {
        $map = [];
        foreach ($available as $col) {
            $map[strtolower((string) $col)] = (string) $col;
        }
        foreach ($candidates as $candidate) {
            $key = strtolower((string) $candidate);
            if (isset($map[$key])) {
                return $map[$key];
            }
        }
        return null;
    }

    private function quoteIdent(string $identifier): string
    {
        return "`" . str_replace("`", "``", $identifier) . "`";
    }

    private function redirect(string $url): void
    {
        header("Location: " . $url, true, 302);
        exit;
    }

    private function requestPath(): string
    {
        $candidates = [
            (string) ($_SERVER["PATH_INFO"] ?? ""),
            (string) ($_SERVER["ORIG_PATH_INFO"] ?? ""),
            (string) ($_SERVER["REDIRECT_URL"] ?? ""),
            (string) parse_url((string) ($_SERVER["REQUEST_URI"] ?? "/"), PHP_URL_PATH),
        ];

        $scriptName = (string) ($_SERVER["SCRIPT_NAME"] ?? "");
        $scriptDir = rtrim(str_replace("\\", "/", dirname($scriptName)), "/");
        foreach ($candidates as $raw) {
            $path = trim($raw);
            if ($path === "") {
                continue;
            }
            $path = str_replace("\\", "/", $path);
            if ($path !== "/" && str_starts_with($path, "/index.php")) {
                $path = substr($path, strlen("/index.php")) ?: "/";
            }
            if ($scriptDir !== "" && $scriptDir !== "/" && str_starts_with($path, $scriptDir . "/")) {
                $path = substr($path, strlen($scriptDir)) ?: "/";
            } elseif ($path === $scriptDir) {
                $path = "/";
            }
            if ($path === "") {
                $path = "/";
            }
            return $path;
        }
        return "/";
    }

    private function clientIp(): string
    {
        $keys = [
            "HTTP_CF_PSEUDO_IPV4",    // Cloudflare pseudo-IPv4 (if enabled)
            "HTTP_TRUE_CLIENT_IP",    // Some reverse proxies / CDNs
            "HTTP_CF_CONNECTING_IP",  // Cloudflare visitor IP
            "HTTP_X_FORWARDED_FOR",
            "HTTP_X_REAL_IP",
            "REMOTE_ADDR",
        ];
        foreach ($keys as $k) {
            $v = trim((string) ($_SERVER[$k] ?? ""));
            if ($v !== "") {
                $parts = array_map("trim", explode(",", $v));
                $fallbackIp = null;
                foreach ($parts as $part) {
                    if ($part === "") {
                        continue;
                    }
                    $clean = $part;
                    if (str_starts_with(strtolower($clean), "for=")) {
                        $clean = trim(substr($clean, 4), "\" ");
                    }
                    if (preg_match('/^\[(.+)\]:(\d+)$/', $clean, $m) === 1) {
                        $clean = $m[1];
                    } elseif (substr_count($clean, ':') === 1 && str_contains($clean, '.')) {
                        [$hostPart, $portPart] = explode(':', $clean, 2);
                        if (ctype_digit($portPart)) {
                            $clean = $hostPart;
                        }
                    }
                    if (str_starts_with((string) $clean, "::ffff:")) {
                        $mapped = substr((string) $clean, 7);
                        if (filter_var($mapped, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                            return $mapped;
                        }
                    }
                    if (filter_var($clean, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                        return $clean;
                    }
                    if ($fallbackIp === null && filter_var($clean, FILTER_VALIDATE_IP)) {
                        $fallbackIp = $clean;
                    }
                }
                if ($fallbackIp !== null) {
                    return $fallbackIp;
                }
            }
        }
        return "127.0.0.1";
    }

    private function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header("Content-Type: application/json; charset=utf-8");
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function handlePushSubscribePost(): void
    {
        $publicKey = trim(Config::vapidPublicKey());
        if ($publicKey === "") {
            $this->json(["ok" => false, "error" => "VAPID public key is not configured."], 503);
            return;
        }

        $raw = file_get_contents("php://input");
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($decoded)) {
            $this->json(["ok" => false, "error" => "Invalid JSON body."], 400);
            return;
        }

        $subscription = $decoded["subscription"] ?? $decoded;
        if (!is_array($subscription)) {
            $this->json(["ok" => false, "error" => "Missing subscription payload."], 400);
            return;
        }

        $endpoint = trim((string) ($subscription["endpoint"] ?? ""));
        $keys = $subscription["keys"] ?? [];
        $p256dh = is_array($keys) ? trim((string) ($keys["p256dh"] ?? "")) : "";
        $auth = is_array($keys) ? trim((string) ($keys["auth"] ?? "")) : "";
        $encoding = trim((string) ($decoded["encoding"] ?? $subscription["contentEncoding"] ?? ""));
        if ($encoding === "") {
            $encoding = "aesgcm";
        }

        if ($endpoint === "" || $p256dh === "" || $auth === "") {
            $this->json(["ok" => false, "error" => "Incomplete Push subscription."], 400);
            return;
        }

        try {
            $this->ensurePushSubscriptionTable();
            $pdo = Db::pdo();
            $user = $this->currentUser();
            $userId = $user ? (int) ($user["id"] ?? 0) : null;
            $endpointHash = sha1($endpoint);
            $ua = substr((string) ($_SERVER["HTTP_USER_AGENT"] ?? ""), 0, 255);
            $ip = substr($this->clientIp(), 0, 64);
            $st = $pdo->prepare(
                "INSERT INTO push_subscription
                (user_id, endpoint, endpoint_hash, p256dh_key, auth_key, content_encoding, user_agent, ip_address, created_at, last_seen_at)
                VALUES (:uid, :endpoint, :ehash, :p256dh, :auth, :enc, :ua, :ip, UTC_TIMESTAMP(), UTC_TIMESTAMP())
                ON DUPLICATE KEY UPDATE
                  user_id = VALUES(user_id),
                  p256dh_key = VALUES(p256dh_key),
                  auth_key = VALUES(auth_key),
                  content_encoding = VALUES(content_encoding),
                  user_agent = VALUES(user_agent),
                  ip_address = VALUES(ip_address),
                  last_seen_at = UTC_TIMESTAMP(),
                  revoked_at = NULL"
            );
            $st->bindValue(":uid", $userId, $userId === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
            $st->bindValue(":endpoint", $endpoint, \PDO::PARAM_STR);
            $st->bindValue(":ehash", $endpointHash, \PDO::PARAM_STR);
            $st->bindValue(":p256dh", $p256dh, \PDO::PARAM_STR);
            $st->bindValue(":auth", $auth, \PDO::PARAM_STR);
            $st->bindValue(":enc", $encoding, \PDO::PARAM_STR);
            $st->bindValue(":ua", $ua, \PDO::PARAM_STR);
            $st->bindValue(":ip", $ip, \PDO::PARAM_STR);
            $st->execute();
            $this->json(["ok" => true]);
        } catch (\Throwable $e) {
            $this->json(["ok" => false, "error" => "Push registration failed."], 500);
        }
    }

    private function handleAdminTestPushPost(): void
    {
        $actor = $this->currentUser();
        if (!$actor) {
            $this->json(["ok" => false, "error" => "Unauthorized"], 401);
            return;
        }
        $raw = file_get_contents("php://input");
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($decoded)) {
            $decoded = $_POST;
        }
        $title = trim((string) ($decoded["title"] ?? "RoadToWord"));
        $body = trim((string) ($decoded["body"] ?? "Test push notification"));
        $url = trim((string) ($decoded["url"] ?? "/"));
        $targetUsername = trim((string) ($decoded["username"] ?? ""));
        $targetUserId = (int) ($actor["id"] ?? 0);
        if ($targetUsername !== "") {
            $target = $this->findUserByUsername($targetUsername);
            if (!$target) {
                $this->json(["ok" => false, "error" => "Target user not found"], 404);
                return;
            }
            $targetUserId = (int) ($target["id"] ?? 0);
        }
        $result = $this->sendWebPushToUser($targetUserId, $title !== "" ? $title : "RoadToWord", $body !== "" ? $body : "Test push notification", [
            "url" => $url !== "" ? $url : "/",
            "tag" => "rtw-admin-test-push",
        ]);
        $this->json(["ok" => true] + $result);
    }

    private function ensurePushSubscriptionTable(): void
    {
        static $ready = false;
        if ($ready) {
            return;
        }
        $pdo = Db::pdo();
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS push_subscription (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NULL,
                endpoint VARCHAR(1024) NOT NULL,
                endpoint_hash CHAR(40) NOT NULL,
                p256dh_key VARCHAR(255) NOT NULL,
                auth_key VARCHAR(255) NOT NULL,
                content_encoding VARCHAR(32) NOT NULL DEFAULT 'aesgcm',
                user_agent VARCHAR(255) NULL,
                ip_address VARCHAR(64) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                revoked_at DATETIME NULL DEFAULT NULL,
                UNIQUE KEY uq_push_subscription_endpoint_hash (endpoint_hash),
                KEY idx_push_subscription_user_id (user_id),
                KEY idx_push_subscription_last_seen_at (last_seen_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        $ready = true;
    }

    private function webPushConfigured(): bool
    {
        return trim(Config::vapidPublicKey()) !== ""
            && trim(Config::vapidPrivateKey()) !== ""
            && class_exists("\\Minishlink\\WebPush\\WebPush")
            && class_exists("\\Minishlink\\WebPush\\Subscription");
    }

    private function maybeSendWebPushToUser(int $userId, string $title, string $body, array $extra = []): void
    {
        try {
            $this->sendWebPushToUser($userId, $title, $body, $extra);
        } catch (\Throwable $e) {
            // ignore push failures
        }
    }

    private function sendWebPushToUser(int $userId, string $title, string $body, array $extra = []): array
    {
        if ($userId <= 0) {
            return ["sent" => 0, "failed" => 0, "skipped" => "invalid_user"];
        }
        if (!$this->webPushConfigured()) {
            return ["sent" => 0, "failed" => 0, "skipped" => "webpush_not_configured_or_vendor_missing"];
        }

        $this->ensurePushSubscriptionTable();
        $pdo = Db::pdo();
        $st = $pdo->prepare("
            SELECT id, endpoint, p256dh_key, auth_key, content_encoding
            FROM push_subscription
            WHERE user_id = :uid
              AND revoked_at IS NULL
            ORDER BY last_seen_at DESC
            LIMIT 20
        ");
        $st->execute([":uid" => $userId]);
        $rows = $st->fetchAll();
        if (!$rows) {
            return ["sent" => 0, "failed" => 0, "subscriptions" => 0];
        }

        $payload = [
            "title" => $title,
            "body" => $body,
            "url" => (string) ($extra["url"] ?? "/"),
            "tag" => (string) ($extra["tag"] ?? "rtw-push"),
            "icon" => (string) ($extra["icon"] ?? "/favicon.ico"),
            "badge" => (string) ($extra["badge"] ?? "/favicon.ico"),
        ];

        $auth = [
            "VAPID" => [
                "subject" => Config::vapidSubject(),
                "publicKey" => Config::vapidPublicKey(),
                "privateKey" => Config::vapidPrivateKey(),
            ],
        ];

        $webPushClass = "\\Minishlink\\WebPush\\WebPush";
        $subscriptionClass = "\\Minishlink\\WebPush\\Subscription";
        $webPush = new $webPushClass($auth);
        if (method_exists($webPush, "setReuseVAPIDHeaders")) {
            $webPush->setReuseVAPIDHeaders(true);
        }

        $mapped = [];
        foreach ($rows as $row) {
            $subData = [
                "endpoint" => (string) ($row["endpoint"] ?? ""),
                "publicKey" => (string) ($row["p256dh_key"] ?? ""),
                "authToken" => (string) ($row["auth_key"] ?? ""),
                "contentEncoding" => (string) ($row["content_encoding"] ?? "aesgcm"),
            ];
            if ($subData["endpoint"] === "" || $subData["publicKey"] === "" || $subData["authToken"] === "") {
                continue;
            }
            $subscription = $subscriptionClass::create($subData);
            $mapped[(string) $row["endpoint"]] = (int) ($row["id"] ?? 0);
            $webPush->queueNotification($subscription, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), [
                "TTL" => 60,
                "urgency" => "normal",
            ]);
        }

        $sent = 0;
        $failed = 0;
        $revokedIds = [];
        foreach ($webPush->flush() as $report) {
            $endpoint = method_exists($report, "getEndpoint") ? (string) $report->getEndpoint() : "";
            $ok = method_exists($report, "isSuccess") ? (bool) $report->isSuccess() : false;
            if ($ok) {
                $sent++;
                continue;
            }
            $failed++;
            $response = method_exists($report, "getResponse") ? $report->getResponse() : null;
            $statusCode = null;
            if ($response && method_exists($response, "getStatusCode")) {
                $statusCode = (int) $response->getStatusCode();
            }
            if (($statusCode === 404 || $statusCode === 410) && $endpoint !== "" && isset($mapped[$endpoint])) {
                $revokedIds[] = (int) $mapped[$endpoint];
            }
        }

        if ($revokedIds) {
            $revokedIds = array_values(array_unique(array_filter($revokedIds, fn($v) => $v > 0)));
            if ($revokedIds) {
                $placeholders = implode(",", array_fill(0, count($revokedIds), "?"));
                $upd = $pdo->prepare("UPDATE push_subscription SET revoked_at = UTC_TIMESTAMP() WHERE id IN ($placeholders)");
                foreach ($revokedIds as $i => $id) {
                    $upd->bindValue($i + 1, $id, \PDO::PARAM_INT);
                }
                $upd->execute();
            }
        }

        return [
            "sent" => $sent,
            "failed" => $failed,
            "subscriptions" => count($rows),
            "revoked" => count($revokedIds),
        ];
    }

    private function baseUrl(): string
    {
        $https = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off");
        $scheme = $https ? "https" : "http";
        $host = (string) ($_SERVER["HTTP_HOST"] ?? "localhost");
        return $scheme . "://" . $host;
    }
}
