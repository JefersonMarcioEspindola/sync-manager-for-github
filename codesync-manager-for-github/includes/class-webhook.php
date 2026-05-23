<?php
/**
 * Webhook handler for GitHub Real-time updates.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CODESYNC_Webhook {

	/**
	 * Webhook Secret Option Key
	 */
	const OPTION_SECRET = 'codesync_webhook_secret';

	/**
	 * Init REST API.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register the REST API route.
	 */
	public static function register_routes() {
		register_rest_route( 'codesync/v1', '/webhook', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'handle_webhook' ),
			'permission_callback' => '__return_true', // Validation done inside via signature
		) );
	}

	/**
	 * Handle the incoming webhook.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	public static function handle_webhook( $request ) {
		$secret = get_option( self::OPTION_SECRET );
		if ( empty( $secret ) ) {
			return new WP_REST_Response( array( 'message' => 'Webhook não configurado no WordPress.' ), 400 );
		}

		$signature = $request->get_header( 'X-Hub-Signature-256' );
		if ( empty( $signature ) ) {
			return new WP_REST_Response( array( 'message' => 'Assinatura ausente.' ), 401 );
		}

		$payload = $request->get_body();
		$hash    = 'sha256=' . hash_hmac( 'sha256', $payload, $secret );

		if ( ! hash_equals( $hash, $signature ) ) {
			return new WP_REST_Response( array( 'message' => 'Assinatura inválida.' ), 401 );
		}

		$data  = json_decode( $payload, true );
		$event = $request->get_header( 'X-GitHub-Event' );

		if ( 'ping' === $event ) {
			$repo_slug = '';
			if ( ! empty( $data['repository']['full_name'] ) ) {
				$repo_slug = $data['repository']['full_name'];
				update_option( 'codesync_webhook_ping_' . $repo_slug, time() );
			}
			return new WP_REST_Response( array( 'message' => 'Pong! Conexão estabelecida com sucesso.' ), 200 );
		}

		if ( 'release' === $event || 'push' === $event ) {
			$repo_slug = '';
			if ( ! empty( $data['repository']['full_name'] ) ) {
				$repo_slug = $data['repository']['full_name'];
			}

			if ( empty( $repo_slug ) ) {
				return new WP_REST_Response( array( 'message' => 'Repositório não identificado no payload.' ), 400 );
			}

			$managed_plugins = get_option( CODESYNC_Manager::OPTION_PLUGINS, array() );
			
			// Is it managed by GSM?
			if ( isset( $managed_plugins[ $repo_slug ] ) ) {
				// Clear caches so the next WP check immediately sees the update
				delete_site_transient( 'update_plugins' );
				
				$parts = explode( '/', $repo_slug );
				if ( count( $parts ) === 2 ) {
					CODESYNC_GitHub_API::delete_releases_cache( $parts[0], $parts[1] );
				}

				// Mark locally that an update is available (just to trigger a refresh in the UI later)
				$managed_plugins[ $repo_slug ]['status'] = 'atualizacao_disponivel';
				CODESYNC_Manager::update_option_no_autoload( CODESYNC_Manager::OPTION_PLUGINS, $managed_plugins );

				CODESYNC_Manager::log(
					$repo_slug,
					'sistema',
					'sucesso',
					__( 'Webhook recebido do GitHub. Cache de atualizações limpo.', 'codesync-manager-for-github' )
				);

				return new WP_REST_Response( array( 'message' => 'Webhook processado com sucesso. Atualização sinalizada.' ), 200 );
			} else {
				return new WP_REST_Response( array( 'message' => 'Repositório ignorado pois não é gerenciado.' ), 200 );
			}
		}

		return new WP_REST_Response( array( 'message' => 'Evento ignorado.' ), 200 );
	}

	/**
	 * Generate a random secret if it doesn't exist.
	 *
	 * @return string
	 */
	public static function get_or_create_secret() {
		$secret = get_option( self::OPTION_SECRET );
		if ( empty( $secret ) ) {
			$secret = wp_generate_password( 32, false );
			update_option( self::OPTION_SECRET, $secret );
		}
		return $secret;
	}
}
