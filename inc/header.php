<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$cceverywhere_logo_url = CCEVERYWHERE_IMG_PATH . 'logo.png';
?>
<div id="cceverywhere-header">
    <img src="<?php echo esc_url( $cceverywhere_logo_url ); ?>" alt="<?php echo esc_attr( CCEVERYWHERE_NAME ); ?> Logo" class="logo">
    <div class="title-cont">
        <h1><?php echo esc_html( CCEVERYWHERE_NAME ); ?></h1>
    </div>
</div>
<div id="cceverywhere-subheader">
    <div class="subheader-left">
        <h2 class="tab-title"><?php esc_html_e( 'Settings', 'clear-cache-everywhere' ); ?></h2>
        <button type="button" id="cceverywhere-save-settings" class="cceverywhere-button"><?php esc_html_e( 'Save', 'clear-cache-everywhere' ); ?></button>
        <span id="cceverywhere-save-reminder"><?php esc_html_e( 'Remember to click "Save" after making changes to your settings.', 'clear-cache-everywhere' ); ?></span>
    </div>
    <div class="subheader-right">
        <button type="button" id="cce-clear-cache-btn" class="cceverywhere-button"><?php esc_html_e( 'Clear Cache Now', 'clear-cache-everywhere' ); ?></button>
    </div>
</div>