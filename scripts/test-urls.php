<?php
declare(strict_types=1);

/**
 * CLI URL tester.
 *
 * Usage:
 *   php scripts/test-urls.php
 *
 * Runs a fixed battery of URLs through the same validators the
 * app uses. Prints pass/fail for each. Exits non-zero if any
 * expected result is wrong.
 *
 * Not reachable from the browser.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../src/bootstrap.php';

$pass = 0;
$fail = 0;

function ok(bool $cond, string $label, string $detail = ''): void
{
    global $pass, $fail;
    if ($cond) {
        $pass++;
        echo "  \033[32m✓\033[0m {$label}\n";
    } else {
        $fail++;
        echo "  \033[31m✗\033[0m {$label}";
        if ($detail !== '') echo "  ({$detail})";
        echo "\n";
    }
}

echo "\n── YouTube URL → ID ──────────────────────────────\n";

$ytCases = [
    // [input, expected_id_or_null]
    ['https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
    ['https://youtube.com/watch?v=dQw4w9WgXcQ',     'dQw4w9WgXcQ'],
    ['https://m.youtube.com/watch?v=dQw4w9WgXcQ',   'dQw4w9WgXcQ'],
    ['https://youtu.be/dQw4w9WgXcQ',                'dQw4w9WgXcQ'],
    ['https://www.youtube.com/shorts/dQw4w9WgXcQ',  'dQw4w9WgXcQ'],
    ['https://www.youtube.com/embed/dQw4w9WgXcQ',   'dQw4w9WgXcQ'],
    ['https://www.youtube.com/live/dQw4w9WgXcQ',    'dQw4w9WgXcQ'],
    ['https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
    ['dQw4w9WgXcQ',                                 'dQw4w9WgXcQ'],
    ['  dQw4w9WgXcQ  ',                             'dQw4w9WgXcQ'], // trim
    ['https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=30s', 'dQw4w9WgXcQ'], // with params

    // Rejections
    ['',                                            null],
    [null,                                          null],
    ['not a url',                                   null],
    ['https://example.com/watch?v=dQw4w9WgXcQ',     null], // wrong host
    ['http://www.youtube.com/watch?v=dQw4w9WgXcQ',  null], // not https
    ['javascript:alert(1)',                         null],
    ['https://www.youtube.com/watch?v=short',       null], // id too short
    ['https://youtu.be/',                           null],
    ['https://www.youtube.com/channel/UCabc',       null], // channel, not video
];

foreach ($ytCases as [$input, $expected]) {
    $got = youtube_extract_id($input);
    $label = var_export($input, true) . ' → ' . var_export($got, true);
    ok($got === $expected, $label, $expected === null
        ? "expected null"
        : "expected {$expected}");
}

echo "\n── External URL acceptance ──────────────────────\n";

$extCases = [
    // [url, expected_ok]
    ['https://open.spotify.com/track/xxx', true],
    ['https://www.youtube.com/watch?v=abc', true],
    ['https://example.com/', true],
    ['https://example.com/path?query=1', true],

    ['http://example.com/', false],       // not https
    ['ftp://example.com/', false],        // wrong scheme
    ['javascript:alert(1)', false],
    ['data:text/html,<script>alert(1)</script>', false],
    ['file:///etc/passwd', false],
    ['', false],
];

foreach ($extCases as [$url, $expectedOk]) {
    $isHttps = false;
    if ($url !== '') {
        $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
        $isHttps = $scheme === 'https' && (bool)filter_var($url, FILTER_VALIDATE_URL);
    }
    ok($isHttps === $expectedOk, var_export($url, true) . ' → ' . ($isHttps ? 'accept' : 'reject'));
}

echo "\n── ImageKit URL acceptance ──────────────────────\n";

$ikEndpoint = (string)(config()['imagekit']['url_endpoint'] ?? '');
echo "  Endpoint configured: " . ($ikEndpoint ?: '(none)') . "\n";

if ($ikEndpoint === '') {
    echo "  \033[33m!\033[0m Skipping ImageKit tests — set IMAGEKIT_URL_ENDPOINT in .env\n";
} else {
    $ikCases = [
        // [url, expected_ok]
        [$ikEndpoint . '/folder/photo.jpg',                  true],
        [$ikEndpoint . '/photo.png',                          true],

        ['https://ik.imagekit.io/someone-else/photo.jpg',     false],
        ['http://' . substr($ikEndpoint, 8) . '/photo.jpg',   false], // http
        ['https://example.com/photo.jpg',                     false],
        ['',                                                  false],
    ];

    foreach ($ikCases as [$url, $expectedOk]) {
        $got = $url !== '' && imagekit_url_ok($url);
        ok($got === $expectedOk, var_export($url, true) . ' → ' . ($got ? 'accept' : 'reject'));
    }
}

echo "\n";
echo "Passed: {$pass}\n";
echo "Failed: {$fail}\n\n";

exit($fail === 0 ? 0 : 1);