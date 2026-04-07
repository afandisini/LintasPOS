<?php

declare(strict_types=1);

return [
    'latest' => 'v0.3.0',
    'tracks' => [
        [
            'from' => 'v0.2.0',
            'to' => 'v0.3.0',
            'guide' => 'upgrade-guides/v0.2.0-to-v0.3.0.md',
            'breaking' => [
                'None in this scaffolded track. Keep as empty unless a real breaking change exists.',
            ],
            'risks' => [
                'Always run `php aiti upgrade:check` before applying.',
                'User-owned paths (`app/`, `routes/`, `database/`) are always skipped.',
            ],
            'deprecations' => [
                'Deprecation warnings must remain active for at least one major cycle.',
            ],
            'patches' => [
                // Example patch shape:
                // [
                //   'target' => 'system/Some/CoreFile.php',
                //   'template' => 'upgrade-guides/stubs/v0.2.0-to-v0.3.0/system/Some/CoreFile.php',
                //   'strategy' => 'replace',
                //   'expected_checksum' => 'sha256-hex-of-current-file',
                //   'backup' => true,
                // ],
                // Marker merge example:
                // [
                //   'target' => 'public/index.php',
                //   'template' => 'upgrade-guides/stubs/v0.2.0-to-v0.3.0/public/index.debug-snippet.php',
                //   'strategy' => 'marker_merge',
                //   'marker_start' => '/* AITI:DEBUG-START */',
                //   'marker_end' => '/* AITI:DEBUG-END */',
                //   'expected_checksum' => 'sha256-hex-of-current-file',
                //   'backup' => true,
                // ],
            ],
        ],
    ],
];

