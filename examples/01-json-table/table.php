<?php

declare(strict_types=1);

namespace StrObj\Examples\JsonTable;

use StrObj\StringObjects;

use function StrObj\Examples\escapeHtml;

/**
 * Selects table cells by path without inspecting the nested JSON structure.
 *
 * @param StringObjects $source  Complete API response.
 * @param array         $columns Header => path/default/optional formatter.
 * @param callable|null $accept  Optional predicate receiving the customer index.
 *
 * @return array
 */
function buildRows(StringObjects $source, array $columns, ?callable $accept = null): array
{
    $indexes = array_keys($source->get('payload/customers', []));
    $indexes = array_filter($indexes, $accept ?? static function (): bool {
        return true;
    });

    return array_values(array_map(static function ($index) use ($source, $columns): array {
        $row = [];

        foreach ($columns as $title => $column) {
            $value = $source->get('payload/customers/' . $index . '/' . $column['path'], $column['default']);
            $format = $column['format'] ?? static function ($value): string {
                return (string) $value;
            };
            $row[$title] = $format($value);
        }

        return $row;
    }, $indexes));
}

/** Renders only the selected columns, preserving their configured order. */
function renderTable(array $columns, array $rows): void
{
    echo '<div class="table-scroll"><table><thead><tr>';

    foreach (array_keys($columns) as $title) {
        echo '<th scope="col">', escapeHtml($title), '</th>';
    }

    echo '</tr></thead><tbody>';

    foreach ($rows as $row) {
        echo '<tr>';

        foreach ($row as $cell) {
            echo '<td>', escapeHtml($cell), '</td>';
        }

        echo '</tr>';
    }

    echo '</tbody></table></div>';
}

// One JSON object: casts and value filters apply to complete paths.
