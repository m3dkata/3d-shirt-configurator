<?php
/**
 * Template Name: Shirt Configurator
 */

get_header();
?>

<div class="shirt-configurator-container">
    <iframe src="<?php echo esc_url(home_url('/configurator/')); ?>" width="100%" height="800" frameborder="0"></iframe>
</div>

<?php
get_footer();
