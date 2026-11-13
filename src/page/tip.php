<?php

/**
 * Ícono de ayuda con tooltip (se abre con hover o foco de teclado).
 *
 * @param string $html Texto de ayuda; admite <a>, <code>, <strong>, <br>, <ol>/<li>.
 */
function MLS_PAGE_TIP($html)
{
    $allowed = [
        'a' => ['href' => [], 'target' => [], 'rel' => []],
        'code' => [],
        'strong' => [],
        'br' => [],
        'ol' => [],
        'li' => [],
    ];
    echo '<span class="mls-tip" tabindex="0" aria-label="Ayuda">'
        . '<span class="dashicons dashicons-editor-help" aria-hidden="true"></span>'
        . '<span class="mls-tip__text" role="tooltip">' . wp_kses($html, $allowed) . '</span>'
        . '</span>';
}
