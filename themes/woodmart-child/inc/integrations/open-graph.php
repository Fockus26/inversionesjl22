<?php
/**
 * Añade meta etiquetas Open Graph para previsualización social
 */

function agregar_meta_og_tags() {
    if (is_front_page() || is_home()) {
        ?>
        <meta property="og:title" content="<?php bloginfo('name'); ?>">
        <meta property="og:description" content="<?php bloginfo('description'); ?>">
        <meta property="og:image" content="<?php echo get_stylesheet_directory_uri(); ?>/assets/images/Preview.png">
        <meta property="og:url" content="<?php echo esc_url(home_url()); ?>">
        <meta property="og:type" content="website">
        <?php
    }
}
add_action('wp_head', 'agregar_meta_og_tags');
