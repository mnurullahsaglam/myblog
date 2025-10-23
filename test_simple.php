<?php

echo "Testing basic functionality...\n";

try {
    $url = "https://www.kariyer.net/pozisyonlar/kidemli+web+arayuz+gelistirici/maas";

    echo "Making request to: $url\n";

    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36'
        ]
    ]);

    $html = file_get_contents($url, false, $context);

    if ($html === false) {
        echo "❌ Failed to fetch URL\n";
        exit(1);
    }

    echo "✅ Successfully fetched HTML (" . strlen($html) . " bytes)\n";

    // Look for salary pattern
    if (preg_match('/"(\d{2,3}\.\d{3})","(\d{2,3}\.\d{3})","(\d{2,3}\.\d{3})"/', $html, $matches)) {
        echo "✅ Found salary pattern!\n";
        echo "Values: {$matches[1]}, {$matches[2]}, {$matches[3]}\n";
    } else {
        echo "❌ No salary pattern found\n";
    }

    // Look for user count
    if (preg_match('/(\d+)\s*kullanıcının\s*verileriyle/', $html, $matches)) {
        echo "✅ Found user count: {$matches[1]}\n";
    } else {
        echo "❌ No user count found\n";
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "✅ Test completed successfully!\n";