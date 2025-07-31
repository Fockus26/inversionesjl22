<?php
/**
 * =====================
 * ESTILOS DEL CHILD THEME
 * =====================
**/

// Carga los estilos del tema hijo, asegurando dependencia del tema padre Woodmart
add_action('wp_enqueue_scripts', 'woodmart_child_enqueue_styles', 10010);
function woodmart_child_enqueue_styles() {
    wp_enqueue_style(
        'child-style',
        get_stylesheet_directory_uri() . '/style.css',
        ['woodmart-style'],
        woodmart_get_theme_info('Version')
    );
}