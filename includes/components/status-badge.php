<?php
/** Coloured labels for item statuses, claim statuses and report types. */
declare(strict_types=1);

/** $kind is "item" or "claim". The CSS class decides the colour (see style.css). */
function status_badge(string $status, string $kind = 'item'): string
{
    $labels = $kind === 'claim' ? claim_statuses() : item_statuses();
    $label  = $labels[$status] ?? ucfirst(str_replace('_', ' ', $status));
    $class  = preg_replace('/[^a-z_]/', '', $status);
    return '<span class="badge badge-' . e($kind) . '-' . e($class) . '">' . e($label) . '</span>';
}

function type_badge(string $type): string
{
    $label = report_types()[$type] ?? ucfirst($type);
    return '<span class="badge badge-type-' . e($type === 'found' ? 'found' : 'lost') . '">' . e($label) . '</span>';
}
