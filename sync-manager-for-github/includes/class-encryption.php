<?php
/**
 * Encryption Helper for GitHub Sync Manager
 *
 * @package GitHubSyncManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class GSM_Encryption
 *
 * Handles secure encryption and decryption of sensitive tokens using AES-256-GCM.
 */
class GSM_Encryption {

	/**
	 * Default WordPress placeholder phrase for security keys.
	 */
	const WP_DEFAULT_PHRASE = 'put your unique phrase here';

	/**
	 * Checks if the WordPress security configuration is complete and secure.
	 *
	 * @return bool|WP_Error True if configuration is secure, WP_Error otherwise.
	 */
	public static function check_security_keys() {
		if ( ! defined( 'AUTH_KEY' ) || ! defined( 'SECURE_AUTH_KEY' ) ) {
			return new WP_Error(
				'gsm_security_incomplete',
				__( 'Configuração de segurança do WordPress incompleta. Defina AUTH_KEY e SECURE_AUTH_KEY no wp-config.php antes de conectar uma conta GitHub.', 'sync-manager-for-github' )
			);
		}

		$auth_key        = trim( AUTH_KEY );
		$secure_auth_key = trim( SECURE_AUTH_KEY );

		if ( empty( $auth_key ) || empty( $secure_auth_key ) ||
			self::WP_DEFAULT_PHRASE === $auth_key || self::WP_DEFAULT_PHRASE === $secure_auth_key ) {
			return new WP_Error(
				'gsm_security_default_keys',
				__( 'Configuração de segurança do WordPress incompleta. Defina AUTH_KEY e SECURE_AUTH_KEY no wp-config.php antes de conectar uma conta GitHub.', 'sync-manager-for-github' )
			);
		}

		return true;
	}

	/**
	 * Derives a strong 32-byte encryption key from WordPress constants.
	 *
	 * @return string The derived key.
	 */
	private static function get_derived_key() {
		$salt = defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : 'gsm-fallback-salt';
		$key  = defined( 'AUTH_KEY' ) ? AUTH_KEY : 'gsm-fallback-key';
		return hash_hmac( 'sha256', $key, $salt, true );
	}

	/**
	 * Encrypts a plain text string using AES-256-GCM.
	 *
	 * @param string $plain_text Text to encrypt.
	 * @return string|WP_Error Encrypted string as JSON base64-encoded, or WP_Error on failure.
	 */
	public static function encrypt( $plain_text ) {
		$security_check = self::check_security_keys();
		if ( is_wp_error( $security_check ) ) {
			return $security_check;
		}

		if ( ! function_exists( 'openssl_encrypt' ) ) {
			return new WP_Error(
				'gsm_missing_openssl',
				__( 'A extensão PHP openssl não está disponível neste servidor.', 'sync-manager-for-github' )
			);
		}

		$method = 'aes-256-gcm';
		$key    = self::get_derived_key();
		$iv_len = openssl_cipher_iv_length( $method );
		$iv     = openssl_random_pseudo_bytes( $iv_len );

		$tag = '';
		// Encrypt the plain text.
		$ciphertext = openssl_encrypt( $plain_text, $method, $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16 );

		if ( false === $ciphertext ) {
			return new WP_Error(
				'gsm_encryption_failed',
				__( 'Falha na criptografia do token.', 'sync-manager-for-github' )
			);
		}

		$payload = array(
			'iv'         => base64_encode( $iv ),
			'tag'        => base64_encode( $tag ),
			'ciphertext' => base64_encode( $ciphertext ),
		);

		return base64_encode( wp_json_encode( $payload ) );
	}

	/**
	 * Decrypts an AES-256-GCM encrypted string.
	 *
	 * @param string $encrypted_str The base64-encoded JSON payload.
	 * @return string|WP_Error The decrypted plain text, or WP_Error on failure.
	 */
	public static function decrypt( $encrypted_str ) {
		$security_check = self::check_security_keys();
		if ( is_wp_error( $security_check ) ) {
			return $security_check;
		}

		if ( ! function_exists( 'openssl_decrypt' ) ) {
			return new WP_Error(
				'gsm_missing_openssl',
				__( 'A extensão PHP openssl não está disponível neste servidor.', 'sync-manager-for-github' )
			);
		}

		$decoded_payload = base64_decode( $encrypted_str, true );
		if ( false === $decoded_payload ) {
			return new WP_Error( 'gsm_decryption_invalid_base64', __( 'Dados criptografados inválidos (formato incorreto).', 'sync-manager-for-github' ) );
		}

		$payload = json_decode( $decoded_payload, true );
		if ( ! is_array( $payload ) || empty( $payload['iv'] ) || empty( $payload['tag'] ) || empty( $payload['ciphertext'] ) ) {
			return new WP_Error( 'gsm_decryption_invalid_format', __( 'Formato de carga criptografada inválido.', 'sync-manager-for-github' ) );
		}

		$method = 'aes-256-gcm';
		$key    = self::get_derived_key();
		$iv     = base64_decode( $payload['iv'], true );
		$tag    = base64_decode( $payload['tag'], true );
		$cipher = base64_decode( $payload['ciphertext'], true );

		if ( false === $iv || false === $tag || false === $cipher ) {
			return new WP_Error( 'gsm_decryption_decode_failed', __( 'Erro ao decodificar componentes da criptografia.', 'sync-manager-for-github' ) );
		}

		$plain_text = openssl_decrypt( $cipher, $method, $key, OPENSSL_RAW_DATA, $iv, $tag );

		if ( false === $plain_text ) {
			return new WP_Error( 'gsm_decryption_failed', __( 'Falha na descriptografia do token (chave incorreta ou integridade violada).', 'sync-manager-for-github' ) );
		}

		return $plain_text;
	}

	/**
	 * Masks the token showing only the last 4 characters for security in UI.
	 *
	 * @param string $token Cleartext token.
	 * @return string Masked token.
	 */
	public static function mask_token( $token ) {
		if ( empty( $token ) ) {
			return '';
		}

		$len = strlen( $token );
		if ( $len <= 4 ) {
			return str_repeat( '•', 16 ) . substr( $token, -1 );
		}

		$visible = substr( $token, -4 );
		return str_repeat( '•', 24 ) . $visible;
	}
}
