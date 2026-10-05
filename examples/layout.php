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
            <a href="<?= escapeHtml($base) ?>" <?= $active === 'home' ? 'aria-current="page"' : '' ?>>Overview</a>
            <a href="<?= escapeHtml($base) ?>01-json-table/"
    <?= $active === 'table' ? 'aria-current="page"' : '' ?>>JSON table</a>
            <a href="<?= escapeHtml($base) ?>02-crud/"
    <?= $active === 'crud' ? 'aria-current="page"' : '' ?>>Product CRUD</a>
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
