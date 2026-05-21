<?php
/**
 * GitHub API Client Wrapper
 *
 * @package GitHubSyncManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class GSM_GitHub_API
 *
 * Interacts with the GitHub API securely, managing authentication, repository fetching,
 * release validation, transient caching, and authenticated asset downloading.
 */
class GSM_GitHub_API {

	/**
	 * GitHub API base URL.
	 */
	const API_URL = 'https://api.github.com';

	/**
	 * Personal Access Token.
	 *
	 * @var string
	 */
	private $token;

	/**
	 * Constructor.
	 *
	 * @param string $token Decrypted Personal Access Token.
	 */
	public function __construct( $token ) {
		$this->token = trim( $token );
	}

	/**
	 * Helper to generate default HTTP request arguments.
	 *
	 * @param string $method HTTP method.
	 * @param string $accept Custom Accept header.
	 * @return array Request arguments.
	 */
	private function get_request_args( $method = 'GET', $accept = 'application/vnd.github+json' ) {
		return array(
			'method'      => $method,
			'timeout'     => 30,
			'redirection' => 5,
			'httpversion' => '1.1',
			'headers'     => array(
				'Authorization'   => 'Bearer ' . $this->token,
				'Accept'          => $accept,
				'X-GitHub-Api-Version' => '2022-11-28',
				'User-Agent'      => 'WordPress-GitHub-Sync-Manager/' . ( defined( 'GSM_VERSION' ) ? GSM_VERSION : '1.0.0' ),
			),
		);
	}

	/**
	 * Validates the GitHub Personal Access Token and retrieves user information.
	 *
	 * @return array|WP_Error Array with user info and token type on success, WP_Error on failure.
	 */
	public function validate_token() {
		$url  = self::API_URL . '/user';
		$args = $this->get_request_args();

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status_code ) {
			return new WP_Error(
				'gsm_api_auth_failed',
				__( 'Token inválido ou sem permissões necessárias. Verifique se o token foi criado com o escopo repo e tente novamente.', 'github-sync-manager' )
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) ) {
			return new WP_Error( 'gsm_api_invalid_json', __( 'Resposta inválida da API do GitHub.', 'github-sync-manager' ) );
		}

		// Read scopes from header (Classic PATs only)
		$scopes_header = wp_remote_retrieve_header( $response, 'x-oauth-scopes' );
		$scopes        = array();
		$token_type    = 'fine-grained';

		if ( ! empty( $scopes_header ) ) {
			$token_type = 'classic';
			$scopes     = array_map( 'trim', explode( ',', $scopes_header ) );

			// Check scopes
			$has_repo        = in_array( 'repo', $scopes, true );
			$has_public_repo = in_array( 'public_repo', $scopes, true );

			if ( ! $has_repo && ! $has_public_repo ) {
				return new WP_Error(
					'gsm_api_insufficient_scopes',
					__( 'Token inválido ou sem permissões necessárias. Verifique se o token foi criado com o escopo repo e tente novamente.', 'github-sync-manager' )
				);
			}
		}

		return array(
			'username'   => isset( $body['login'] ) ? $body['login'] : '',
			'avatar_url' => isset( $body['avatar_url'] ) ? $body['avatar_url'] : '',
			'token_type' => $token_type,
			'scopes'     => $scopes,
		);
	}

	/**
	 * Lists repositories of the connected GitHub account.
	 *
	 * @return array|WP_Error List of repositories or WP_Error on failure.
	 */
	public function get_repositories() {
		$url  = self::API_URL . '/user/repos?per_page=100&sort=updated';
		$args = $this->get_request_args();

		$response = wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status_code ) {
			return new WP_Error( 'gsm_api_repos_failed', __( 'Falha ao buscar a lista de repositórios do GitHub.', 'github-sync-manager' ) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) ) {
			return new WP_Error( 'gsm_api_invalid_response', __( 'Dados de repositórios inválidos.', 'github-sync-manager' ) );
		}

		$repos = array();
		foreach ( $body as $repo ) {
			$repos[] = array(
				'id'          => $repo['id'],
				'name'        => $repo['name'],
				'full_name'   => $repo['full_name'],
				'description' => $repo['description'],
				'private'     => (bool) $repo['private'],
				'updated_at'  => $repo['updated_at'],
				'html_url'    => $repo['html_url'],
				'owner'       => isset( $repo['owner']['login'] ) ? $repo['owner']['login'] : '',
			);
		}

		return $repos;
	}

	/**
	 * Tests access to a specific repository.
	 * Critical for Fine-Grained PATs validation.
	 *
	 * @param string $owner Repo owner.
	 * @param string $repo Repo name.
	 * @return bool|WP_Error True if accessible, WP_Error otherwise.
	 */
	public function test_repo_access( $owner, $repo ) {
		$url  = sprintf( '%s/repos/%s/%s', self::API_URL, urlencode( $owner ), urlencode( $repo ) );
		$args = $this->get_request_args();

		$response = wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status_code ) {
			if ( 404 === $status_code ) {
				return new WP_Error(
					'gsm_repo_not_found',
					__( 'Repositório não encontrado ou sem permissão de acesso. Verifique se o token tem acesso a este repositório específico.', 'github-sync-manager' )
				);
			}
			return new WP_Error(
				'gsm_repo_access_error',
				sprintf( __( 'Erro ao acessar o repositório (%d).', 'github-sync-manager' ), $status_code )
			);
		}

		return true;
	}

	/**
	 * Fetches releases of a specific repository with 1-hour transient caching.
	 *
	 * @param string $owner Repo owner.
	 * @param string $repo Repo name.
	 * @param bool   $force_refresh Bypass the cache.
	 * @return array|WP_Error Array of releases or WP_Error on failure.
	 */
	public function get_releases( $owner, $repo, $force_refresh = false ) {
		$repo_slug = $owner . '/' . $repo;
		$cache_key = 'gsm_rel_' . md5( $repo_slug );

		if ( ! $force_refresh ) {
			$cached = get_transient( $cache_key );
			if ( false !== $cached ) {
				return $cached;
			}
		}

		// Perform specific access check first
		$access_check = $this->test_repo_access( $owner, $repo );
		if ( is_wp_error( $access_check ) ) {
			return $access_check;
		}

		$url  = sprintf( '%s/repos/%s/%s/releases?per_page=10', self::API_URL, urlencode( $owner ), urlencode( $repo ) );
		$args = $this->get_request_args();

		$response = wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status_code ) {
			return new WP_Error(
				'gsm_releases_failed',
				sprintf( __( 'Falha ao buscar as releases do repositório (%d).', 'github-sync-manager' ), $status_code )
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) ) {
			return new WP_Error( 'gsm_releases_invalid_json', __( 'Resposta inválida das releases.', 'github-sync-manager' ) );
		}

		$releases = array();
		foreach ( $body as $rel ) {
			// Skip drafts
			if ( ! empty( $rel['draft'] ) ) {
				continue;
			}

			$zipball_url = isset( $rel['zipball_url'] ) ? $rel['zipball_url'] : '';
			$assets      = array();

			if ( ! empty( $rel['assets'] ) && is_array( $rel['assets'] ) ) {
				foreach ( $rel['assets'] as $asset ) {
					if ( 'application/zip' === $asset['content_type'] || preg_match( '/\.zip$/i', $asset['name'] ) ) {
						$assets[] = array(
							'id'   => $asset['id'],
							'name' => $asset['name'],
							'url'  => $asset['url'], // GitHub API URL for the asset download
						);
					}
				}
			}

			$releases[] = array(
				'id'          => $rel['id'],
				'tag_name'    => $rel['tag_name'],
				'name'        => $rel['name'],
				'body'        => $rel['body'],
				'published_at'=> $rel['published_at'],
				'zipball_url' => $zipball_url,
				'assets'      => $assets,
			);
		}

		// Store in transient cache for 1 hour (3600 seconds)
		set_transient( $cache_key, $releases, HOUR_IN_SECONDS );

		return $releases;
	}

	/**
	 * Deletes the release transient cache for a repository.
	 *
	 * @param string $owner Repo owner.
	 * @param string $repo Repo name.
	 */
	public static function delete_releases_cache( $owner, $repo ) {
		$repo_slug = $owner . '/' . $repo;
		$cache_key = 'gsm_rel_' . md5( $repo_slug );
		delete_transient( $cache_key );
	}

	/**
	 * Downloads a package ZIP securely using the authorization header.
	 *
	 * @param string $url The ZIP URL (either API zipball or API asset URL).
	 * @return string|WP_Error Absolute path to the temporary downloaded ZIP, or WP_Error.
	 */
	public function download_package( $url ) {
		if ( empty( $url ) ) {
			return new WP_Error( 'gsm_download_empty_url', __( 'A URL de download está vazia.', 'github-sync-manager' ) );
		}

		// Define a local callback to inject the Authorization header securely
		$auth_callback = function( $args, $req_url ) use ( $url ) {
			// Make sure we only attach authorization to GitHub API or raw github download URLs.
			if ( strpos( $req_url, 'api.github.com' ) !== false || strpos( $req_url, 'codeload.github.com' ) !== false ) {
				$args['headers']['Authorization'] = 'Bearer ' . $this->token;
				$args['headers']['User-Agent']    = 'WordPress-GitHub-Sync-Manager';

				// If it's a release asset API URL, request it as application/octet-stream
				if ( strpos( $req_url, '/releases/assets/' ) !== false ) {
					$args['headers']['Accept'] = 'application/octet-stream';
				}
			}
			return $args;
		};

		// Attach the filter before starting download
		add_filter( 'http_request_args', $auth_callback, 10, 2 );

		// Use WordPress native download_url function. It streams the file to a secure local temp file.
		// We set a long timeout of 300 seconds for larger files.
		$tmp_file = download_url( $url, 300 );

		// Detach the filter immediately
		remove_filter( 'http_request_args', $auth_callback );

		if ( is_wp_error( $tmp_file ) ) {
			return $tmp_file;
		}

		return $tmp_file;
	}
}
