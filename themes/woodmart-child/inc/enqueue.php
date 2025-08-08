<?php
/**
 * =====================
 * ESTILOS DEL CHILD THEME
 * =====================
 *
 * Se carga el archivo style.css del tema hijo, asegurando que el estilo del tema padre Woodmart
 * se cargue antes para evitar conflictos y mantener la herencia de estilos.
 */

// Hook para cargar los estilos en el frontend con prioridad alta para asegurar orden correcto
add_action('wp_enqueue_scripts', 'woodmart_child_enqueue_styles', 10010);

function woodmart_child_enqueue_styles() {
    wp_enqueue_style(
        'child-style', // Handle único para el stylesheet del child theme
        get_stylesheet_directory_uri() . '/style.css', // Ruta al style.css del tema hijo
        ['woodmart-style'], // Dependencia del estilo principal del tema padre
        woodmart_get_theme_info('Version') // Versión del tema para cache busting
    );
}
