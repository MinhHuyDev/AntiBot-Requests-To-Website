<?php
error_reporting(0);
date_default_timezone_set('Asia/Ho_Chi_Minh');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function containsAny(string $haystack, array $needles): bool
{
    foreach ($needles as $needle) {
        if ($needle !== '' && strpos($haystack, $needle) !== false) {
            return true;
        }
    }

    return false;
}

function issueJsVerificationToken(string $sessionKey): string
{
    try {
        $token = bin2hex(random_bytes(24));
    } catch (Throwable $exception) {
        $token = sha1(uniqid('', true) . microtime(true));
    }

    $_SESSION[$sessionKey] = $token;

    return $token;
}

function renderJsChallengePage(
    string $cookieName,
    string $token,
    int $ttlSeconds,
    string $returnUrl,
    string $denyReason = ''
): void {
    $cookieNameJs = json_encode($cookieName, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $tokenJs = json_encode($token, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $returnUrlJs = json_encode($returnUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $safeTtl = $ttlSeconds > 0 ? $ttlSeconds : 1200;
    $hasDenyReason = $denyReason !== '';
    $hasDenyReasonJs = $hasDenyReason ? 'true' : 'false';
    $initialBodyClass = $hasDenyReason ? ' class="verify-error"' : '';
    $initialStatusHtml = $hasDenyReason ? $denyReason : 'Verifying your browser';

    if ($cookieNameJs === false) {
        $cookieNameJs = '""';
    }

    if ($tokenJs === false) {
        $tokenJs = '""';
    }

    if ($returnUrlJs === false) {
        $returnUrlJs = '"./AntiBot-Requests.php"';
    }

    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    echo <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>AntiBot Requests Challenge</title>
    <style>
        :root {
            --bg-1: #f7fafc;
            --bg-2: #e2ebf5;
            --ink: #0f172a;
            --muted: #4b5563;
            --panel: #ffffff;
            --line: rgba(15, 23, 42, 0.1);
            --alert: #dc2626;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            width: 100%;
            min-height: 100%;
        }

        body {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 20px;
            color: var(--ink);
            font-family: "Space Grotesk", "Poppins", "Segoe UI", sans-serif;
            background:
                radial-gradient(900px 420px at 50% -120px, rgba(151, 180, 220, 0.55), rgba(151, 180, 220, 0) 66%),
                linear-gradient(180deg, var(--bg-1), var(--bg-2));
        }

        .verify-panel {
            width: min(450px, 100%);
            border: 1px solid var(--line);
            border-radius: 20px;
            background: var(--panel);
            box-shadow: 0 16px 38px rgba(15, 23, 42, 0.1);
            text-align: center;
            padding: 30px 24px;
        }

        .verify-dot {
            display: block;
            width: 18px;
            height: 18px;
            border-radius: 999px;
            margin: 0 auto 14px;
            background: var(--alert);
            box-shadow: 0 0 0 rgba(220, 38, 38, 0.5);
            animation: dotPulse 1.2s ease-in-out infinite;
            position: relative;
        }

        .verify-dot::before,
        .verify-dot::after {
            content: "";
            position: absolute;
            left: 50%;
            top: 50%;
            width: 14px;
            height: 2.5px;
            border-radius: 99px;
            background: var(--alert);
            opacity: 0;
            transform: translate(-50%, -50%) rotate(0deg);
        }

        .verify-text {
            margin: 0;
            font-size: 1.04rem;
            line-height: 1.45;
            font-weight: 600;
            color: var(--muted);
        }

        .verify-text a,
        .verify-noscript a {
            color: inherit;
            text-decoration: underline;
            text-underline-offset: 2px;
            font-weight: 700;
        }

        .verify-noscript {
            display: none;
            margin: 0;
            color: #7f1d1d;
            font-size: 1.02rem;
            line-height: 1.45;
            font-weight: 600;
        }

        body.verify-error .verify-dot {
            animation: none;
            box-shadow: none;
            background: transparent;
        }

        body.verify-error .verify-dot::before,
        body.verify-error .verify-dot::after {
            opacity: 1;
        }

        body.verify-error .verify-dot::before {
            transform: translate(-50%, -50%) rotate(45deg);
        }

        body.verify-error .verify-dot::after {
            transform: translate(-50%, -50%) rotate(-45deg);
        }

        body.verify-error .verify-text {
            color: #7f1d1d;
        }

        body.security-locked {
            overflow: hidden;
        }

        body.security-locked .verify-panel {
            visibility: hidden;
        }

        .security-warning-overlay {
            position: fixed;
            inset: 0;
            z-index: 999999;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: rgba(2, 6, 23, 0.8);
        }

        .security-warning-overlay.is-visible {
            display: flex;
        }

        .security-warning-modal {
            width: min(430px, 100%);
            border-radius: 18px;
            border: 1px solid rgba(248, 113, 113, 0.45);
            background: #fff7f7;
            color: #7f1d1d;
            text-align: center;
            padding: 24px 20px;
            box-shadow: 0 18px 40px rgba(2, 6, 23, 0.45);
        }

        .security-warning-modal h2 {
            margin: 0 0 10px;
            font-size: 1.2rem;
            line-height: 1.2;
        }

        .security-warning-modal p {
            margin: 0;
            font-size: 0.98rem;
            line-height: 1.5;
            font-weight: 600;
        }

        @keyframes dotPulse {
            0%,
            100% {
                transform: scale(0.88);
                opacity: 0.4;
                box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.32);
            }

            50% {
                transform: scale(1);
                opacity: 1;
                box-shadow: 0 0 0 12px rgba(220, 38, 38, 0);
            }
        }
    </style>
    <script>
        (function () {
            var cName = {$cookieNameJs};
            var cValue = {$tokenJs};
            var returnUrl = {$returnUrlJs};
            var hasDenyReason = {$hasDenyReasonJs};
            var isLocked = false;
            var baseGapWidth = null;
            var baseGapHeight = null;
            var gapHits = 0;
            var debuggerHits = 0;
            var lastDebuggerProbeAt = 0;

            function lockPage(message) {
                if (isLocked) {
                    return;
                }

                isLocked = true;

                if (document.body) {
                    document.body.classList.add("security-locked");
                }

                var overlay = document.getElementById("security-warning-overlay");
                var warningText = document.getElementById("security-warning-text");

                if (warningText) {
                    warningText.textContent = message;
                }

                if (overlay) {
                    overlay.removeAttribute("hidden");
                    overlay.classList.add("is-visible");
                }
            }

            function getWindowGaps() {
                return {
                    width: Math.abs(window.outerWidth - window.innerWidth),
                    height: Math.abs(window.outerHeight - window.innerHeight)
                };
            }

            function refreshGapBaseline(gaps) {
                if (baseGapWidth === null || baseGapHeight === null) {
                    baseGapWidth = gaps.width;
                    baseGapHeight = gaps.height;
                    return;
                }

                if (gaps.width < baseGapWidth) {
                    baseGapWidth = gaps.width;
                }

                if (gaps.height < baseGapHeight) {
                    baseGapHeight = gaps.height;
                }
            }

            function hasGapDevtoolsSignal(gaps) {
                if (baseGapWidth === null || baseGapHeight === null) {
                    return false;
                }

                var widthDelta = gaps.width - baseGapWidth;
                var heightDelta = gaps.height - baseGapHeight;
                var verticalDock = widthDelta > 220 && gaps.width > 280 && window.innerHeight > 500;
                var horizontalDock = heightDelta > 180 && gaps.height > 240 && window.innerWidth > 680;

                return verticalDock || horizontalDock;
            }

            function debuggerProbeSignalsOpen() {
                var now = Date.now();
                if (now - lastDebuggerProbeAt < 1800) {
                    return false;
                }

                lastDebuggerProbeAt = now;
                var start = performance.now();
                Function("debugger")();
                var elapsed = performance.now() - start;
                return elapsed > 120;
            }

            function monitorSecuritySignals() {
                if (isLocked) {
                    return;
                }

                if (document.visibilityState !== "visible" || !document.hasFocus()) {
                    return;
                }

                var gaps = getWindowGaps();
                refreshGapBaseline(gaps);
                var gapSignal = hasGapDevtoolsSignal(gaps);

                if (gapSignal) {
                    gapHits = Math.min(gapHits + 1, 8);
                } else if (gapHits > 0) {
                    gapHits--;
                }

                if (debuggerProbeSignalsOpen()) {
                    debuggerHits = Math.min(debuggerHits + 1, 4);
                } else if (debuggerHits > 0) {
                    debuggerHits--;
                }

                if ((gapHits >= 2 && debuggerHits >= 1) || debuggerHits >= 2) {
                    lockPage("Access denied: Developer tools detected. Close DevTools and reload this page.");
                }
            }

            function shouldBlockShortcut(event) {
                var key = (event.key || "").toLowerCase();

                if (key === "f12") {
                    return true;
                }

                if (event.ctrlKey && event.shiftKey && (key === "i" || key === "j" || key === "c")) {
                    return true;
                }

                if (event.ctrlKey && key === "u") {
                    return true;
                }

                return false;
            }

            function hasCookie(name) {
                var prefix = name + "=";
                var pieces = document.cookie ? document.cookie.split(";") : [];

                for (var i = 0; i < pieces.length; i++) {
                    var item = pieces[i].trim();
                    if (item.indexOf(prefix) === 0) {
                        return true;
                    }
                }

                return false;
            }

            function setState(message, isError) {
                var textNode = document.getElementById("verify-text");
                if (textNode) {
                    textNode.textContent = message;
                }

                if (isError) {
                    document.body.classList.add("verify-error");
                }
            }

            document.addEventListener("keydown", function (event) {
                if (!shouldBlockShortcut(event)) {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();
                lockPage("Access denied: Developer tools are blocked on this page.");
            }, true);

            window.addEventListener("DOMContentLoaded", function () {
                monitorSecuritySignals();
                window.addEventListener("resize", monitorSecuritySignals);
                window.setInterval(monitorSecuritySignals, 1200);

                if (hasDenyReason) {
                    document.body.classList.add("verify-error");
                    return;
                }

                if (isLocked) {
                    return;
                }

                document.cookie = cName + "=" + encodeURIComponent(cValue) + "; Max-Age={$safeTtl}; Path=/; SameSite=Lax";

                window.setTimeout(function () {
                    if (isLocked) {
                        return;
                    }

                    if (!hasCookie(cName)) {
                        setState("Access denied: browser cannot store verification cookie.", true);
                        return;
                    }

                    window.location.replace(returnUrl);
                }, 650);
            });
        })();
    </script>
</head>
<body{$initialBodyClass}>
    <main class="verify-panel" role="status" aria-live="polite" aria-label="Verification status">
        <span class="verify-dot" aria-hidden="true"></span>
        <p class="verify-text" id="verify-text">{$initialStatusHtml}</p>
        <noscript>
            <style>
                .verify-panel {
                    display: none;
                }

                .security-warning-overlay,
                .security-warning-overlay[hidden] {
                    display: flex !important;
                }
            </style>
        </noscript>
    </main>
    <div class="security-warning-overlay" id="security-warning-overlay" hidden>
        <section class="security-warning-modal" role="alertdialog" aria-modal="true" aria-live="assertive">
            <h2>Security Warning</h2>
            <p id="security-warning-text">Access denied: JavaScript is disabled.</p>
        </section>
    </div>
</body>
</html>
HTML;
    exit;
}

$hostUrl = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
$remoteAddr = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
$remotePort = (string) ($_SERVER['REMOTE_PORT'] ?? 'unknown');
$userAgent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
$acceptHeader = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
$acceptLanguage = trim((string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''));
$secFetchMode = trim((string) ($_SERVER['HTTP_SEC_FETCH_MODE'] ?? ''));
$secFetchDest = trim((string) ($_SERVER['HTTP_SEC_FETCH_DEST'] ?? ''));

$jsVerifyParam = '__js_verify';
$jsVerifyCookie = '__mh_js_verified';
$jsVerifySessionKey = 'antibot_js_verified_token';
$jsVerifyCookieTtl = 1200;
$selfPath = (string) ($_SERVER['PHP_SELF'] ?? './AntiBot-Requests.php');
$verifyUrl = $selfPath . '?' . $jsVerifyParam . '=1';

$normalizedUa = strtolower($userAgent);
$blockedSignatures = [
    'python-requests',
    'python-urllib',
    'httpx',
    'aiohttp',
    'curl/',
    'scrapy',
    'fetch/',
    'go-http-client',
    'insomnia',
    'okhttp',
    'libwww-perl',
    'java/',
    'wget/',
    'postmanruntime',
];

$browserSignals = 0;
if ($acceptHeader !== '' && strpos($acceptHeader, 'text/html') !== false) {
    $browserSignals++;
}
if ($acceptLanguage !== '') {
    $browserSignals++;
}
if ($secFetchMode !== '') {
    $browserSignals++;
}
if ($secFetchDest !== '') {
    $browserSignals++;
}

$isAutomationUa = $normalizedUa === '' || containsAny($normalizedUa, $blockedSignatures);
$isLikelyScriptClient = $browserSignals < 2;

if ($isAutomationUa || $isLikelyScriptClient) {
    http_response_code(403);
    renderJsChallengePage(
        $jsVerifyCookie,
        '',
        $jsVerifyCookieTtl,
        $verifyUrl,
        'Access denied: suspicious automated request detected. Please <a href="' . e($verifyUrl) . '">click here</a> to reload.'
    );
}

if ((string) ($_GET[$jsVerifyParam] ?? '') === '1') {
    $issuedToken = issueJsVerificationToken($jsVerifySessionKey);
    renderJsChallengePage($jsVerifyCookie, $issuedToken, $jsVerifyCookieTtl, $selfPath);
}

$cookieToken = (string) ($_COOKIE[$jsVerifyCookie] ?? '');
$sessionToken = (string) ($_SESSION[$jsVerifySessionKey] ?? '');
$isJsVerified = $cookieToken !== '' && $sessionToken !== '' && hash_equals($sessionToken, $cookieToken);

if (!$isJsVerified) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Location: ' . $verifyUrl, true, 302);
    exit;
}

$nextJsToken = issueJsVerificationToken($jsVerifySessionKey);
$nextJsCookieNameJs = json_encode($jsVerifyCookie, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$nextJsTokenJs = json_encode($nextJsToken, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($nextJsCookieNameJs === false) {
    $nextJsCookieNameJs = '"__mh_js_verified"';
}
if ($nextJsTokenJs === false) {
    $nextJsTokenJs = '""';
}

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AntiBot Requests</title>
    <style>
        :root {
            --bg: #eff4f9;
            --ink: #111827;
            --muted: #4b5563;
            --card: #ffffff;
            --line: rgba(17, 24, 39, 0.12);
            --accent: #0f766e;
            --accent-soft: rgba(15, 118, 110, 0.15);
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            min-height: 100%;
        }

        body {
            min-height: 100vh;
            font-family: "Space Grotesk", "Poppins", "Segoe UI", sans-serif;
            color: var(--ink);
            background:
                radial-gradient(1000px 420px at 20% -160px, rgba(129, 185, 173, 0.28), rgba(129, 185, 173, 0) 70%),
                radial-gradient(900px 360px at 85% -110px, rgba(138, 167, 215, 0.2), rgba(138, 167, 215, 0) 72%),
                var(--bg);
            padding: clamp(20px, 5vw, 52px);
        }

        .layout {
            width: min(980px, 100%);
            margin: 0 auto;
            display: grid;
            gap: 18px;
        }

        .hero,
        .card {
            border: 1px solid var(--line);
            border-radius: 20px;
            background: var(--card);
            box-shadow: 0 14px 36px rgba(15, 23, 42, 0.08);
            padding: clamp(20px, 4vw, 28px);
        }

        .chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border-radius: 999px;
            padding: 6px 12px;
            background: var(--accent-soft);
            color: #0f4f4b;
            font-size: 0.84rem;
            font-weight: 600;
            letter-spacing: 0.02em;
        }

        .chip::before {
            content: "";
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: var(--accent);
            box-shadow: 0 0 0 rgba(15, 118, 110, 0.45);
            animation: pulse 1.2s ease-in-out infinite;
        }

        h1 {
            margin: 12px 0 10px;
            font-size: clamp(1.8rem, 4.8vw, 3rem);
            line-height: 1.1;
            letter-spacing: -0.02em;
        }

        p {
            margin: 0;
            color: var(--muted);
            line-height: 1.6;
        }

        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        body.security-locked {
            overflow: hidden;
        }

        body.security-locked .layout {
            visibility: hidden;
        }

        .security-warning-overlay {
            position: fixed;
            inset: 0;
            z-index: 999999;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: rgba(2, 6, 23, 0.82);
        }

        .security-warning-overlay.is-visible {
            display: flex;
        }

        .security-warning-modal {
            width: min(430px, 100%);
            border-radius: 18px;
            border: 1px solid rgba(248, 113, 113, 0.5);
            background: #fff7f7;
            color: #7f1d1d;
            text-align: center;
            padding: 24px 20px;
            box-shadow: 0 18px 40px rgba(2, 6, 23, 0.45);
        }

        .security-warning-modal h2 {
            margin: 0 0 10px;
            font-size: 1.2rem;
            line-height: 1.2;
        }

        .security-warning-modal p {
            margin: 0;
            font-size: 0.98rem;
            line-height: 1.5;
            font-weight: 600;
        }

        .kv {
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 12px 14px;
            background: #fbfdff;
        }

        .kv strong {
            display: block;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #64748b;
            margin-bottom: 6px;
        }

        .kv span {
            color: #0f172a;
            font-size: 0.96rem;
            word-break: break-word;
        }

        .list {
            margin: 0;
            padding-left: 18px;
            color: var(--muted);
            display: grid;
            gap: 8px;
        }

        code {
            font-family: "JetBrains Mono", "Consolas", monospace;
            font-size: 0.9em;
            color: #0f172a;
            background: #eef2ff;
            border: 1px solid #dbe4ff;
            border-radius: 6px;
            padding: 1px 6px;
        }

        @keyframes pulse {
            0%,
            100% {
                box-shadow: 0 0 0 0 rgba(15, 118, 110, 0.3);
            }

            50% {
                box-shadow: 0 0 0 8px rgba(15, 118, 110, 0);
            }
        }

        @media (max-width: 760px) {
            .grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <script>
        (function () {
            var cName = <?= $nextJsCookieNameJs; ?>;
            var cValue = <?= $nextJsTokenJs; ?>;
            document.cookie = cName + "=" + encodeURIComponent(cValue) + "; Max-Age=<?= (int) $jsVerifyCookieTtl; ?>; Path=/; SameSite=Lax";

            var isLocked = false;
            var baseGapWidth = null;
            var baseGapHeight = null;
            var gapHits = 0;
            var debuggerHits = 0;
            var lastDebuggerProbeAt = 0;

            function lockPage(message) {
                if (isLocked) {
                    return;
                }

                isLocked = true;

                if (document.body) {
                    document.body.classList.add("security-locked");
                }

                var overlay = document.getElementById("security-warning-overlay");
                var warningText = document.getElementById("security-warning-text");

                if (warningText) {
                    warningText.textContent = message;
                }

                if (overlay) {
                    overlay.removeAttribute("hidden");
                    overlay.classList.add("is-visible");
                }
            }

            function getWindowGaps() {
                return {
                    width: Math.abs(window.outerWidth - window.innerWidth),
                    height: Math.abs(window.outerHeight - window.innerHeight)
                };
            }

            function refreshGapBaseline(gaps) {
                if (baseGapWidth === null || baseGapHeight === null) {
                    baseGapWidth = gaps.width;
                    baseGapHeight = gaps.height;
                    return;
                }

                if (gaps.width < baseGapWidth) {
                    baseGapWidth = gaps.width;
                }

                if (gaps.height < baseGapHeight) {
                    baseGapHeight = gaps.height;
                }
            }

            function hasGapDevtoolsSignal(gaps) {
                if (baseGapWidth === null || baseGapHeight === null) {
                    return false;
                }

                var widthDelta = gaps.width - baseGapWidth;
                var heightDelta = gaps.height - baseGapHeight;
                var verticalDock = widthDelta > 220 && gaps.width > 280 && window.innerHeight > 500;
                var horizontalDock = heightDelta > 180 && gaps.height > 240 && window.innerWidth > 680;

                return verticalDock || horizontalDock;
            }

            function debuggerProbeSignalsOpen() {
                var now = Date.now();
                if (now - lastDebuggerProbeAt < 1800) {
                    return false;
                }

                lastDebuggerProbeAt = now;
                var start = performance.now();
                Function("debugger")();
                var elapsed = performance.now() - start;
                return elapsed > 120;
            }

            function monitorSecuritySignals() {
                if (isLocked) {
                    return;
                }

                if (document.visibilityState !== "visible" || !document.hasFocus()) {
                    return;
                }

                var gaps = getWindowGaps();
                refreshGapBaseline(gaps);
                var gapSignal = hasGapDevtoolsSignal(gaps);

                if (gapSignal) {
                    gapHits = Math.min(gapHits + 1, 8);
                } else if (gapHits > 0) {
                    gapHits--;
                }

                if (debuggerProbeSignalsOpen()) {
                    debuggerHits = Math.min(debuggerHits + 1, 4);
                } else if (debuggerHits > 0) {
                    debuggerHits--;
                }

                if ((gapHits >= 2 && debuggerHits >= 1) || debuggerHits >= 2) {
                    lockPage("Access denied: Developer tools detected. Close DevTools and reload this page.");
                }
            }

            function shouldBlockShortcut(event) {
                var key = (event.key || "").toLowerCase();

                if (key === "f12") {
                    return true;
                }

                if (event.ctrlKey && event.shiftKey && (key === "i" || key === "j" || key === "c")) {
                    return true;
                }

                if (event.ctrlKey && key === "u") {
                    return true;
                }

                return false;
            }

            document.addEventListener("keydown", function (event) {
                if (!shouldBlockShortcut(event)) {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();
                lockPage("Access denied: Developer tools are blocked on this page.");
            }, true);

            window.addEventListener("DOMContentLoaded", function () {
                monitorSecuritySignals();
                window.addEventListener("resize", monitorSecuritySignals);
                window.setInterval(monitorSecuritySignals, 1200);
            });
        })();
    </script>
</head>
<body>
    <noscript>
        <style>
            .layout {
                display: none !important;
            }

            .security-warning-overlay,
            .security-warning-overlay[hidden] {
                display: flex !important;
            }
        </style>
    </noscript>
    <main class="layout">
        <section class="hero">
            <span class="chip">Browser verified</span>
            <h1>Anti-Bot Requests Gate</h1>
            <p>
                This page is a standalone extraction from <code>index.php</code> for blocking script-based request libraries
                and forcing JavaScript cookie verification before serving protected content.
            </p>
        </section>

        <section class="card">
            <h2>Extracted anti-bot functions</h2>
            <ul class="list">
                <li><code>containsAny()</code> detects blocked user-agent signatures such as <code>python-requests</code>, <code>curl/</code>, and <code>postmanruntime</code>.</li>
                <li><code>issueJsVerificationToken()</code> generates a cryptographically secure token and stores it in session.</li>
                <li><code>renderJsChallengePage()</code> serves a challenge screen that sets a verification cookie via JavaScript.</li>
                <li>Header signal checks (<code>Accept</code>, <code>Accept-Language</code>, <code>Sec-Fetch-Mode</code>, <code>Sec-Fetch-Dest</code>) identify likely non-browser clients.</li>
                <li>Only requests with matching session token and cookie token can access this final HTML content.</li>
            </ul>
        </section>

        <section class="card">
            <h2>Current request snapshot</h2>
            <div class="grid">
                <div class="kv">
                    <strong>Host</strong>
                    <span><?= e($hostUrl); ?></span>
                </div>
                <div class="kv">
                    <strong>Remote address</strong>
                    <span><?= e($remoteAddr); ?></span>
                </div>
                <div class="kv">
                    <strong>Remote port</strong>
                    <span><?= e($remotePort); ?></span>
                </div>
                <div class="kv">
                    <strong>User agent</strong>
                    <span><?= e($userAgent !== '' ? $userAgent : 'empty'); ?></span>
                </div>
            </div>
        </section>
    </main>
    <div class="security-warning-overlay" id="security-warning-overlay" hidden>
        <section class="security-warning-modal" role="alertdialog" aria-modal="true" aria-live="assertive">
            <h2>Security Warning</h2>
            <p id="security-warning-text">Access denied: JavaScript is disabled.</p>
        </section>
    </div>
</body>
</html>
