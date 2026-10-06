<?php
/**
 * Plugin Name: Brute Force Telegram Bildirim
 * Description: Hatalı giriş denemelerinde (kullanıcı adı, e-posta veya şifre yanlış) yöneticilere Telegram üzerinden anında bildirim gönderir. Her yönetici kendi bot token ve chat ID bilgisini girmelidir.
 * Version: 1.0.0
 * Text Domain: bftb
 * License: GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const BFTB_META_TOKEN = 'bftb_bot_token';
const BFTB_META_CHAT  = 'bftb_chat_id';

/**
 * Profil sayfasında yalnızca yöneticiler için alanları gösterir.
 */
function bftb_profile_fields( $user ) {
	if ( ! user_can( $user, 'manage_options' ) || ! current_user_can( 'edit_user', $user->ID ) ) {
		return;
	}
	wp_nonce_field( 'bftb_save_' . $user->ID, 'bftb_nonce' );
	?>
	<h2 id="bftb-heading"><?php esc_html_e( 'Telegram Brute Force Bildirimleri', 'bftb' ); ?></h2>
	<p id="bftb-desc"><?php esc_html_e( 'Hatalı giriş denemelerinde bildirim almak için bot token ve chat ID girmeniz zorunludur.', 'bftb' ); ?></p>
	<table class="form-table" role="presentation" aria-labelledby="bftb-heading">
		<tr>
			<th scope="row"><label for="bftb_bot_token"><?php esc_html_e( 'Telegram Bot Token', 'bftb' ); ?> <span aria-hidden="true">*</span></label></th>
			<td>
				<input type="password" name="bftb_bot_token" id="bftb_bot_token" class="regular-text" autocomplete="off" required aria-required="true" aria-describedby="bftb-desc" value="<?php echo esc_attr( get_user_meta( $user->ID, BFTB_META_TOKEN, true ) ); ?>" />
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="bftb_chat_id"><?php esc_html_e( 'Telegram Chat ID', 'bftb' ); ?> <span aria-hidden="true">*</span></label></th>
			<td>
				<input type="text" name="bftb_chat_id" id="bftb_chat_id" class="regular-text" autocomplete="off" required aria-required="true" aria-describedby="bftb-desc" value="<?php echo esc_attr( get_user_meta( $user->ID, BFTB_META_CHAT, true ) ); ?>" />
			</td>
		</tr>
	</table>
	<?php
}
add_action( 'show_user_profile', 'bftb_profile_fields' );
add_action( 'edit_user_profile', 'bftb_profile_fields' );

/**
 * Zorunlu alan doğrulaması (yöneticiler için).
 */
function bftb_validate_profile( $errors, $update, $user ) {
	if ( ! isset( $_POST['bftb_nonce'] ) ) {
		return;
	}
	$token = isset( $_POST['bftb_bot_token'] ) ? trim( wp_unslash( $_POST['bftb_bot_token'] ) ) : '';
	$chat  = isset( $_POST['bftb_chat_id'] ) ? trim( wp_unslash( $_POST['bftb_chat_id'] ) ) : '';
	if ( '' === $token || '' === $chat ) {
		$errors->add( 'bftb_required', __( 'Telegram bot token ve chat ID alanları zorunludur.', 'bftb' ) );
	} elseif ( ! preg_match( '/^\d+:[A-Za-z0-9_-]+$/', $token ) ) {
		$errors->add( 'bftb_token', __( 'Telegram bot token biçimi geçersiz.', 'bftb' ) );
	} elseif ( ! preg_match( '/^(-?\d+|@[A-Za-z0-9_]+)$/', $chat ) ) {
		$errors->add( 'bftb_chat', __( 'Telegram chat ID biçimi geçersiz.', 'bftb' ) );
	}
}
add_action( 'user_profile_update_errors', 'bftb_validate_profile', 10, 3 );

function bftb_save_profile( $user_id ) {
	if ( ! isset( $_POST['bftb_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bftb_nonce'] ) ), 'bftb_save_' . $user_id ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_user', $user_id ) || ! user_can( $user_id, 'manage_options' ) ) {
		return;
	}
	$token = isset( $_POST['bftb_bot_token'] ) ? sanitize_text_field( wp_unslash( $_POST['bftb_bot_token'] ) ) : '';
	$chat  = isset( $_POST['bftb_chat_id'] ) ? sanitize_text_field( wp_unslash( $_POST['bftb_chat_id'] ) ) : '';
	if ( preg_match( '/^\d+:[A-Za-z0-9_-]+$/', $token ) && preg_match( '/^(-?\d+|@[A-Za-z0-9_]+)$/', $chat ) ) {
		update_user_meta( $user_id, BFTB_META_TOKEN, $token );
		update_user_meta( $user_id, BFTB_META_CHAT, $chat );
	}
}
add_action( 'personal_options_update', 'bftb_save_profile' );
add_action( 'edit_user_profile_update', 'bftb_save_profile' );

/**
 * Yanlış kullanıcı adı, e-posta veya şifre ile giriş yapıldığında yöneticilere bildirir.
 * Güvenlik için denenen şifre asla gönderilmez.
 */
function bftb_notify_failed_login( $username, $error = null ) {
	$ip   = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '-';
	$code = ( $error instanceof WP_Error ) ? $error->get_error_code() : '';

	switch ( $code ) {
		case 'invalid_username':
		case 'invalid_email':
			$reason = 'Kullanıcı adı / e-posta hatalı';
			break;
		case 'incorrect_password':
			$reason = 'Şifre hatalı';
			break;
		default:
			$reason = 'Hatalı giriş';
	}

	$text = sprintf(
		"⚠️ %s\nSite: %s\nGirilen kullanıcı adı/e-posta: %s\nSebep: %s\nIP: %s\nZaman: %s",
		'Başarısız giriş denemesi',
		home_url(),
		mb_substr( wp_strip_all_tags( (string) $username ), 0, 100 ),
		$reason,
		$ip,
		wp_date( 'Y-m-d H:i:s' )
	);

	$admins = get_users( array( 'role' => 'administrator', 'fields' => 'ID' ) );
	foreach ( $admins as $admin_id ) {
		$token = get_user_meta( $admin_id, BFTB_META_TOKEN, true );
		$chat  = get_user_meta( $admin_id, BFTB_META_CHAT, true );
		if ( ! $token || ! $chat ) {
			continue;
		}
		wp_remote_post(
			'https://api.telegram.org/bot' . rawurlencode( $token ) . '/sendMessage',
			array(
				'timeout'  => 5,
				'blocking' => false,
				'body'     => array(
					'chat_id' => $chat,
					'text'    => $text,
				),
			)
		);
	}
}
add_action( 'wp_login_failed', 'bftb_notify_failed_login', 10, 2 );

/**
 * Yapılandırması eksik yöneticiye erişilebilir uyarı gösterir.
 */
function bftb_admin_notice() {
	$user_id = get_current_user_id();
	if ( ! current_user_can( 'manage_options' ) || ( get_user_meta( $user_id, BFTB_META_TOKEN, true ) && get_user_meta( $user_id, BFTB_META_CHAT, true ) ) ) {
		return;
	}
	printf(
		'<div class="notice notice-warning" role="alert"><p>%s <a href="%s">%s</a></p></div>',
		esc_html__( 'Brute force bildirimleri için Telegram bot token ve chat ID girmeniz gerekiyor.', 'bftb' ),
		esc_url( get_edit_profile_url( $user_id ) . '#bftb-heading' ),
		esc_html__( 'Profil sayfasına git', 'bftb' )
	);
}
add_action( 'admin_notices', 'bftb_admin_notice' );
