<?php

declare(strict_types=1);

namespace StrObj\Examples;

/**
 * Escapes text and attribute values in the example pages.
 * Escapes a cell or heading for safe HTML output.
 */
function escapeHtml(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Opens the shared layout and navigation; paths also work in a subdirectory. */
function renderHeader(string $title, string $active, string $base = '../'): void
{
    $pages = [
        'home' => ['', 'Overview'],
        'table' => ['01-json-table/', 'JSON table'],
        'crud' => ['02-crud/', 'Product CRUD'],
    ];
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= escapeHtml($title) ?> · StrObj examples</title>
        <link rel="stylesheet" href="<?= escapeHtml($base) ?>assets/examples.css">
    </head>
    <body>
    <header class="site-header">
        <a class="brand" href="<?= escapeHtml($base) ?>">
            <span class="brand-mark">S/</span> StrObj <span>examples</span>
        </a>
        <nav aria-label="Examples">
            <?php foreach ($pages as $page => [$path, $label]) : ?>
                <a href="<?= escapeHtml($base . $path) ?>"<?= $page === $active ? ' aria-current="page"' : '' ?>><?=
                    escapeHtml($label) ?></a>
            <?php endforeach; ?>
        </nav>
    </header>
    <main>
    <?php
}

/** Closes the layout after an example's own content. */
function renderFooter(): void
{
    ?>
    </main>
    <footer class="site-footer">StrObj · Practical examples of paths, defaults, filters and validation.</footer>
    </body>
    </html>
    <?php
}
