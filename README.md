# AntiBot-Requests.php

`AntiBot-Requests.php` is a standalone PHP request gate that filters common automation clients and forces JavaScript cookie verification before protected content is served.

## Purpose

- Reduce traffic from bot/script clients that do not execute JavaScript.
- Bind each verified browser session using a session token + cookie token pair.
- Add a page-lock warning layer when DevTools behavior is detected.

## How it works (3 layers)

### 1) User-Agent + browser header signal filtering

- The script checks User-Agent against blocked signatures, for example:
  - `python-requests`, `python-urllib`, `httpx`, `aiohttp`
  - `curl/`, `wget/`, `postmanruntime`, `go-http-client`, etc.
- It also calculates `browserSignals` from common browser headers:
  - `Accept` containing `text/html`
  - `Accept-Language`
  - `Sec-Fetch-Mode`
  - `Sec-Fetch-Dest`
- If User-Agent is suspicious or signal score is lower than 2, the request is moved to a challenge page (HTTP 403).

### 2) JavaScript challenge + session/cookie token binding

- When `?__js_verify=1` is requested, `issueJsVerificationToken()` creates a new token using `random_bytes()` (with fallback).
- The token is stored in `$_SESSION['antibot_js_verified_token']`.
- The challenge page uses JavaScript to set cookie `__mh_js_verified` (default TTL: 1200 seconds).
- Browser is redirected back to the main page.
- Server compares cookie token and session token with `hash_equals()`:
  - Match -> access is granted.
  - Mismatch -> redirected back to challenge flow.

### 3) DevTools detection and page lock

On both the challenge page and verified page, frontend script:

- Blocks common DevTools/view-source shortcuts:
  - `F12`
  - `Ctrl + Shift + I/J/C`
  - `Ctrl + U`
- Monitors DevTools dock-like window gap signals:
  - Compares `outerWidth/innerWidth`, `outerHeight/innerHeight`
  - Measures delay from `Function("debugger")()`
- If suspicious thresholds are exceeded, page enters `security-locked` mode and shows warning overlay.

## Request flow

1. Client requests `AntiBot-Requests.php`.
2. Server evaluates User-Agent + browser headers.
3. If suspicious -> challenge response (403) with verify link.
4. Browser (or redirected request) loads `?__js_verify=1` to receive token.
5. JavaScript sets verification cookie and redirects to main page.
6. Server validates session token against cookie token.
7. If valid -> protected content is served and token rotates for the next request.

## Important configuration values

- `__js_verify` (`$jsVerifyParam`): challenge trigger query parameter.
- `__mh_js_verified` (`$jsVerifyCookie`): browser verification cookie.
- `antibot_js_verified_token` (`$jsVerifySessionKey`): session key storing token.
- `1200` (`$jsVerifyCookieTtl`): cookie lifetime in seconds.

## Deployment notes

- Requires stable PHP sessions (`session_start()`).
- HTTPS is recommended in production for better cookie security.
- False positives may occur in specific browser/extension environments.
- This is an application-layer protection and should complement (not replace) WAF, rate-limit, CAPTCHA, and firewall controls.

## Quick test

- Browser test: should pass challenge and access protected content.
- Script client test:

```bash
curl -i http://localhost/AntiBot-Requests.php
```

Expected: blocked/challenged when browser signals are missing or User-Agent matches suspicious patterns.

---
MinhHuyDev / raintee.dev
