<?php

/**
 * Smoke test for spam verdict parsing (no Flarum bootstrap).
 */
require __DIR__.'/../src/SpamVerdict.php';

use HardenedStacks\SpamProtection\SpamVerdict;

$cases = [
    [
        'in' => ['is_spam' => true, 'confidence' => 0.91, 'actions' => ['hide_post', 'hide_discussion'], 'reason' => 'seo spam'],
        'spam' => true,
        'confidence' => 91,
    ],
    [
        'in' => ['is_spam' => false, 'confidence' => 12, 'actions' => [], 'reason' => 'ok'],
        'spam' => false,
        'confidence' => 12,
    ],
    [
        'in' => ['is_spam' => true, 'confidence' => 150, 'actions' => ['nope', 'suspend_user'], 'reason' => 'bot'],
        'spam' => true,
        'confidence' => 100,
    ],
];

foreach ($cases as $i => $case) {
    $v = SpamVerdict::fromArray($case['in']);
    if ($v->isSpam !== $case['spam'] || $v->confidence !== $case['confidence']) {
        fwrite(STDERR, "Case $i failed\n");
        exit(1);
    }
}

echo "SpamVerdict OK\n";
