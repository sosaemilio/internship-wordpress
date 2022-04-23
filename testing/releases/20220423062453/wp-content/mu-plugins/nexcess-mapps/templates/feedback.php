<?php
/**
 * Collect feedback from customers via Canny.
 *
 * @global \Nexcess\MAPPS\Settings $settings The current settings object.
 */

?>

<p>
	<?php esc_html_e( 'As a member of the Nexcess MAPPS Beta program, your sites get early-access to some of the new and exciting features we\'ve been working on.', 'nexcess-mapps' ); ?> );
</p>
<p><?php esc_html_e( 'When you get a moment, please leave your feedback on the latest features:', 'nexcess-mapps' ); ?></p>
<div style="margin-top: 1rem;" data-canny></div>

<script>!function(w,d,i,s){function l(){if(!d.getElementById(i)){var f=d.getElementsByTagName(s)[0],e=d.createElement(s);e.type="text/javascript",e.async=!0,e.src="https://canny.io/sdk.js",f.parentNode.insertBefore(e,f)}}if("function"!=typeof w.Canny){var c=function(){c.q.push(arguments)};c.q=[],w.Canny=c,"complete"===d.readyState?l():w.attachEvent?w.attachEvent("onload",l):w.addEventListener("load",l,!1)}}(window,document,"canny-jssdk","script");</script>
<script>
	Canny('render', {
		boardToken: '<?php echo esc_js( $settings->canny_board_token ); ?>',
	});
</script>
