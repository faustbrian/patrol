#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

$cookbookDir = __DIR__;
$outputFile = $cookbookDir.'/COMPLETE-COOKBOOK.md';

// Parse command line arguments
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--output=')) {
        $outputFile = mb_substr($arg, 9);
    }
}

// File order for logical flow
$files = [
    'README.md',
    'ACL.md',
    'ACL-Superuser.md',
    'ACL-Without-Users.md',
    'ACL-Without-Resources.md',
    'RBAC.md',
    'RBAC-Resource-Roles.md',
    'RBAC-Domains.md',
    'ABAC.md',
    'RESTful.md',
    'Deny-Override.md',
    'Priority-Based.md',
];

$output = '';
$output .= "# Patrol Authorization Cookbook - Complete Guide\n\n";
$output .= '> Generated on '.date('Y-m-d H:i:s')."\n\n";
$output .= "This is a concatenated version of all cookbook entries for easy reference, searching, and offline use.\n\n";
$output .= "---\n\n";

foreach ($files as $file) {
    $filePath = $cookbookDir.'/'.$file;

    if (!file_exists($filePath)) {
        echo "Warning: {$file} not found, skipping...\n";

        continue;
    }

    echo "Processing: {$file}\n";

    $content = file_get_contents($filePath);

    // For non-README files, add page break marker
    if ($file !== 'README.md') {
        $output .= "\n\n---\n\n<div style=\"page-break-before: always;\"></div>\n\n";
    }

    $output .= $content."\n\n";
}

file_put_contents($outputFile, $output);

echo "\n✓ Complete cookbook written to: {$outputFile}\n";
echo '  Size: '.number_format(mb_strlen($output) / 1_024, 2)." KB\n";
echo '  Files: '.count($files)."\n";

// Generate table of contents
$tocFile = $cookbookDir.'/TABLE-OF-CONTENTS.md';
$toc = "# Patrol Cookbook - Table of Contents\n\n";
$toc .= "Quick reference index for all cookbook entries.\n\n";

foreach ($files as $file) {
    $filePath = $cookbookDir.'/'.$file;

    if (!file_exists($filePath)) {
        continue;
    }

    $content = file_get_contents($filePath);

    // Extract title (first H1)
    if (preg_match('/^#\s+(.+)$/m', $content, $matches)) {
        $title = $matches[1];
        $link = './'.$file;
        $toc .= "- [{$title}]({$link})\n";
    }
}

file_put_contents($tocFile, $toc);
echo "✓ Table of contents written to: {$tocFile}\n";
