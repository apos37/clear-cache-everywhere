<?php
namespace Apos37\ClearCache;

if ( ! defined( 'ABSPATH' ) ) exit;

$cceverywhere_logo_url = CCEVERYWHERE_IMG_PATH . 'logo.png';
$cceverywhere_version = __( 'Version', 'clear-cache-everywhere' ) . ' ' . CCEVERYWHERE_VERSION;
$cceverywhere_tabs = Settings::get_tabs();
$cceverywhere_active_tab = Settings::get_current_tab();
?>
<div id="cceverywhere-header">
    <img src="<?php echo esc_url( $cceverywhere_logo_url ); ?>" alt="<?php echo esc_attr( CCEVERYWHERE_NAME ); ?> Logo" title="<?php echo esc_attr( $cceverywhere_version ); ?>" class="logo">
    <div class="title-cont">
        <h1 title="<?php echo esc_attr( $cceverywhere_version ); ?>"><?php echo esc_html( CCEVERYWHERE_NAME ); ?></h1>
    </div>
    <div class="tabs-wrapper">
        <?php foreach ( $cceverywhere_tabs as $cceverywhere_key => $cceverywhere_label ) : ?>
            <a href="<?php echo esc_url( Settings::tab_url( $cceverywhere_key ) ); ?>" class="cceverywhere-tab<?php echo $cceverywhere_active_tab === $cceverywhere_key ? ' cceverywhere-tab-active' : ''; ?>"<?php echo $cceverywhere_active_tab === $cceverywhere_key ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $cceverywhere_label ); ?></a>
        <?php endforeach; ?>
    </div>
</div>
<div id="cceverywhere-subheader">
    <div class="subheader-left">
        <h2 class="tab-title"><?php echo esc_html( $cceverywhere_tabs[ $cceverywhere_active_tab ] ); ?></h2>
        <?php do_action( 'cceverywhere_subheader_left', $cceverywhere_active_tab ); ?>
    </div>
    <div class="subheader-right">
        <?php do_action( 'cceverywhere_subheader_right', $cceverywhere_active_tab ); ?>
    </div>
</div>
