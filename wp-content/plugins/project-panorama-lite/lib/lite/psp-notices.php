<?php
add_action( 'admin_notices', 'panorama_admin_notice' );
function panorama_admin_notice() {

    global $pagenow, $typenow, $current_user;

    $views = array(
        'post.php',
        'post-new.php'
    );

    if ( ( in_array( $pagenow, $views ) ) && ( $typenow == 'psp_projects' ) ) {

        $user_id = $current_user->ID;
        if( !get_user_meta( $user_id, 'panorama_ignore_notice_new' ) ) {

            ?>

            <div class="updated">

                <p><img src="<?php echo PROJECT_PANORAMA_URI; ?>/assets/images/panorama-logo.png" alt="Project Panorama" height="50"></p>
                <p><strong>Like Project Panorama?</strong> Did you know we have a <a href="https://www.projectpanorama.com?utm=lite-admin-notice" target="_new">full featured premium version?</a>. Features include:

                <ul style="list-style:disc;padding-left: 15px;">
                    <li>Front end task completion</li>
                    <li>Assign tasks to users</li>
                    <li>More sophisticated design</li>
                    <li>Teams</li>
                    <li>Automatic notifications</li>
                    <li>And more!</li>
                </ul>

                <?php
                $close_url = add_query_arg( 'panorama_nag_ignore', '0', psp_full_url($_SERVER) ); ?>

                <p><strong>Use coupon code <code>litemeup</code> to save 20%.</strong> | <a href="<?php echo esc_attr($close_url); ?>">Hide Notice</a></p>

            </div>
        <?php }
    }
}

add_action( 'admin_init', 'panorama_nag_ignore' );
function panorama_nag_ignore() {


    global $current_user;
    $user_id = $current_user->ID;

    /* If user clicks to ignore the notice, add that to their user meta */
    if ( isset( $_GET['panorama_nag_ignore'] ) && '0' == $_GET['panorama_nag_ignore'] ) {

        add_user_meta( $user_id, 'panorama_ignore_notice_new', 'true', true );

    }

}

add_action( 'add_meta_boxes', 'psp_add_promotional_metabox' );
function psp_add_promotional_metabox() {

    global $current_user;
    $user_id = $current_user->ID;

    if( !get_user_meta( $user_id, 'panorama_ignore_notice_new' ) ) {
        add_meta_box( 'psp_lite_promotional_sidebar', __( 'Project Panorama', 'psp_projects' ), 'psp_promotional_metabox', 'psp_projects', 'side' );
    }

}

function psp_promotional_metabox() { ?>

    <p><img src="<?php echo PROJECT_PANORAMA_URI; ?>/assets/images/panorama-logo.png" alt="Project Panorama" height="50"></p>
    <p>Like Project Panorama? <a href="https://www.projectpanorama.com/?discount=litemeup" target="_new">Did you know we have a full featured premium version?</a>. <strong>Use coupon code <code>litemeup</code> to save 20%.</strong></p>

    <p><strong>Features include:</strong></p>

    <ul style="list-style:disc;padding-left: 15px;">
        <li>Front end task completion</li>
        <li>Assign tasks to users</li>
        <li>More sophisticated design</li>
        <li>Teams</li>
        <li>Automatic notifications</li>
        <li>And more!</li>
    </ul>

    <?php
    $close_url = add_query_arg( 'panorama_nag_ignore', '0', psp_full_url($_SERVER) ); ?>

    <p><a href="<?php echo esc_url( $close_url ); ?>">No thanks!</a></p>

<?php }

function psp_url_origin( $s, $use_forwarded_host = false ) {
    $ssl      = ( ! empty( $s['HTTPS'] ) && $s['HTTPS'] == 'on' );
    $sp       = strtolower( $s['SERVER_PROTOCOL'] );
    $protocol = substr( $sp, 0, strpos( $sp, '/' ) ) . ( ( $ssl ) ? 's' : '' );
    $port     = $s['SERVER_PORT'];
    $port     = ( ( ! $ssl && $port=='80' ) || ( $ssl && $port=='443' ) ) ? '' : ':'.$port;
    $host     = ( $use_forwarded_host && isset( $s['HTTP_X_FORWARDED_HOST'] ) ) ? $s['HTTP_X_FORWARDED_HOST'] : ( isset( $s['HTTP_HOST'] ) ? $s['HTTP_HOST'] : null );
    $host     = isset( $host ) ? $host : $s['SERVER_NAME'] . $port;
    return $protocol . '://' . $host;
}

function psp_full_url( $s, $use_forwarded_host = false ) {
    return psp_url_origin( $s, $use_forwarded_host ) . $s['REQUEST_URI'];
}
