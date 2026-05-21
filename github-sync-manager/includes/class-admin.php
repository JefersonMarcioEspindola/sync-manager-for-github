<?php
/**
 * Core Admin Area and AJAX Handlers
 *
 * @package GitHubSyncManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class GSM_Admin
 *
 * Handles administrative interface rendering, styling enqueues, and secure AJAX endpoints.
 */
class GSM_Admin {

	/**
	 * Init admin hooks.
	 */
	public static function init() {
		// Add menu page
		add_action( 'admin_menu', array( __CLASS__, 'add_admin_menu' ) );

		// Enqueue scripts and styles
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );

		// AJAX handlers
		add_action( 'wp_ajax_gsm_connect_account', array( __CLASS__, 'ajax_connect_account' ) );
		add_action( 'wp_ajax_gsm_disconnect_account', array( __CLASS__, 'ajax_disconnect_account' ) );
		add_action( 'wp_ajax_gsm_add_plugin', array( __CLASS__, 'ajax_add_plugin' ) );
		add_action( 'wp_ajax_gsm_remove_plugin', array( __CLASS__, 'ajax_remove_plugin' ) );
		add_action( 'wp_ajax_gsm_check_updates', array( __CLASS__, 'ajax_check_updates' ) );
	}

	/**
	 * Register the WordPress admin menu item.
	 */
	public static function add_admin_menu() {
		add_menu_page(
			__( 'GitHub Sync', 'github-sync-manager' ),
			__( 'GitHub Sync', 'github-sync-manager' ),
			'manage_options',
			'github-sync-manager',
			array( __CLASS__, 'render_admin_page' ),
			'dashicons-update-alt',
			100
		);
	}

	/**
	 * Enqueue stylesheet and script files.
	 *
	 * @param string $hook The current admin page hook.
	 */
	public static function enqueue_assets( $hook ) {
		if ( 'toplevel_page_github-sync-manager' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'gsm-admin-style',
			plugins_url( 'assets/css/admin.css', dirname( __FILE__ ) ),
			array(),
			defined( 'GSM_VERSION' ) ? GSM_VERSION : '1.0.0'
		);

		wp_enqueue_script(
			'gsm-admin-script',
			plugins_url( 'assets/js/admin.js', dirname( __FILE__ ) ),
			array( 'jquery' ),
			defined( 'GSM_VERSION' ) ? GSM_VERSION : '1.0.0',
			true
		);

		// Localize parameters for use in JS file
		wp_localize_script( 'gsm-admin-script', 'gsm_ajax', array(
			'url'    => admin_url( 'admin-ajax.php' ),
			'nonce'  => wp_create_nonce( 'gsm_admin_nonce' ),
			'texts'  => array(
				'confirm_stop' => __( 'O plugin continuará instalado, mas deixará de receber atualizações automáticas. Deseja continuar?', 'github-sync-manager' ),
				'installing'   => __( 'Baixando e instalando...', 'github-sync-manager' ),
				'searching'    => __( 'Pesquisando repositórios...', 'github-sync-manager' ),
			),
		) );
	}

	/**
	 * Render the HTML dashboard admin page.
	 */
	public static function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Você não tem permissão para acessar esta página.', 'github-sync-manager' ) );
		}

		$connected_user  = get_option( GSM_Manager::OPTION_USER );
		$token_exists    = ! empty( get_option( GSM_Manager::OPTION_TOKEN ) );
		$security_status = GSM_Encryption::check_security_keys();
		$security_error  = is_wp_error( $security_status ) ? $security_status->get_error_message() : '';

		?>
		<div class="wrap gsm-wrap">
			<div class="gsm-header-panel">
				<h1 class="gsm-title">
					<span class="dashicons dashicons-update-alt"></span>
					<?php esc_html_e( 'GitHub Sync Manager', 'github-sync-manager' ); ?>
				</h1>
				
				<?php if ( $token_exists && is_array( $connected_user ) ) : ?>
					<div class="gsm-user-badge">
						<img src="<?php echo esc_url( $connected_user['avatar_url'] ); ?>" alt="Avatar" class="gsm-user-avatar" />
						<div class="gsm-user-info">
							<span class="gsm-username">@<?php echo esc_html( $connected_user['username'] ); ?></span>
							<span class="gsm-pulse-badge">
								<span class="gsm-pulse"></span>
								<?php echo esc_html( 'Conectado' ); ?>
							</span>
						</div>
					</div>
				<?php endif; ?>
			</div>

			<?php if ( ! empty( $security_error ) ) : ?>
				<div class="notice notice-error gsm-notice-blocking">
					<p><strong><?php esc_html_e( 'Erro de Segurança:', 'github-sync-manager' ); ?></strong> <?php echo esc_html( $security_error ); ?></p>
				</div>
			<?php else : ?>

				<?php if ( ! $token_exists ) : ?>
					<!-- Activation screen -->
					<div class="gsm-card gsm-login-card">
						<h2><?php esc_html_e( 'Conectar Conta GitHub', 'github-sync-manager' ); ?></h2>
						<p><?php esc_html_e( 'Para começar a gerenciar seus plugins hospedados no GitHub, conecte uma conta utilizando um Personal Access Token (PAT) com as devidas permissões.', 'github-sync-manager' ); ?></p>
						
						<div class="gsm-help-box">
							<p><strong><?php esc_html_e( 'Qual tipo de token criar?', 'github-sync-manager' ); ?></strong></p>
							<ul>
								<li><strong>Classic PAT:</strong> <?php esc_html_e( 'Crie um token com o escopo ', 'github-sync-manager' ); ?><code>repo</code> (<?php esc_html_e( 'para repositórios privados e públicos', 'github-sync-manager' ); ?>) <?php esc_html_e( 'ou ', 'github-sync-manager' ); ?><code>public_repo</code> (<?php esc_html_e( 'somente para públicos', 'github-sync-manager' ); ?>).</li>
								<li><strong>Fine-Grained PAT (Novo):</strong> <?php esc_html_e( 'Selecione permissão de leitura e gravação para "Contents" e "Metadata" nos repositórios que deseja gerenciar.', 'github-sync-manager' ); ?></li>
							</ul>
							<p>👉 <a href="https://github.com/settings/tokens" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Clique aqui para criar seu Token no GitHub', 'github-sync-manager' ); ?></a></p>
						</div>

						<form id="gsm-connect-form">
							<div class="gsm-form-group">
								<label for="gsm_pat_token"><strong><?php esc_html_e( 'GitHub Personal Access Token (PAT)', 'github-sync-manager' ); ?></strong></label>
								<input type="password" id="gsm_pat_token" name="gsm_pat_token" class="regular-text" required placeholder="github_pat_..." autocomplete="off" />
							</div>
							<div class="gsm-submit-btn-row">
								<button type="submit" class="button button-primary button-large"><?php esc_html_e( 'Conectar Conta', 'github-sync-manager' ); ?></button>
								<span class="spinner gsm-spinner"></span>
							</div>
							<div class="gsm-error-message" style="display:none;"></div>
						</form>
					</div>
				<?php else : ?>
					<!-- Admin core view -->
					<h2 class="nav-tab-wrapper gsm-tabs-nav">
						<a href="#gsm-tab-plugins" class="nav-tab nav-tab-active"><?php esc_html_e( 'Plugins Gerenciados', 'github-sync-manager' ); ?></a>
						<a href="#gsm-tab-add" class="nav-tab" id="gsm-trigger-add-tab"><?php esc_html_e( 'Adicionar Plugin', 'github-sync-manager' ); ?></a>
						<a href="#gsm-tab-logs" class="nav-tab"><?php esc_html_e( 'Histórico de Logs', 'github-sync-manager' ); ?></a>
						<a href="#gsm-tab-config" class="nav-tab"><?php esc_html_e( 'Configurações', 'github-sync-manager' ); ?></a>
					</h2>

					<!-- Tab content: Plugins -->
					<div id="gsm-tab-plugins" class="gsm-tab-content gsm-tab-active">
						<div class="gsm-action-bar">
							<button type="button" class="button button-primary" id="gsm-btn-scan-now">
								<span class="dashicons dashicons-update"></span>
								<?php esc_html_e( 'Verificar atualizações agora', 'github-sync-manager' ); ?>
							</button>
							<span class="spinner gsm-spinner" id="gsm-scan-spinner"></span>
						</div>

						<div class="gsm-card gsm-table-card">
							<table class="wp-list-table widefat fixed striped table-view-list" id="gsm-plugins-table">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Plugin', 'github-sync-manager' ); ?></th>
										<th><?php esc_html_e( 'Repositório GitHub', 'github-sync-manager' ); ?></th>
										<th><?php esc_html_e( 'Versão Instalada', 'github-sync-manager' ); ?></th>
										<th><?php esc_html_e( 'Versão mais Recente', 'github-sync-manager' ); ?></th>
										<th><?php esc_html_e( 'Última Verificação', 'github-sync-manager' ); ?></th>
										<th><?php esc_html_e( 'Status', 'github-sync-manager' ); ?></th>
										<th><?php esc_html_e( 'Ações', 'github-sync-manager' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php self::render_plugins_table_body(); ?>
								</tbody>
							</table>
						</div>
					</div>

					<!-- Tab content: Add Plugin -->
					<div id="gsm-tab-add" class="gsm-tab-content">
						<div class="gsm-filter-bar">
							<input type="text" id="gsm-repo-search" placeholder="<?php esc_attr_e( 'Buscar repositório por nome...', 'github-sync-manager' ); ?>" autocomplete="off" />
							<button type="button" class="button" id="gsm-btn-reload-repos">
								<span class="dashicons dashicons-image-rotate"></span>
								<?php esc_html_e( 'Recarregar Repositórios', 'github-sync-manager' ); ?>
							</button>
							<span class="spinner gsm-spinner" id="gsm-repos-spinner"></span>
						</div>

						<div class="gsm-repos-grid" id="gsm-repos-container">
							<!-- Populated via JS/AJAX -->
						</div>
					</div>

					<!-- Tab content: Logs -->
					<div id="gsm-tab-logs" class="gsm-tab-content">
						<div class="gsm-card gsm-logs-card">
							<div class="gsm-logs-container" id="gsm-logs-table-wrapper">
								<?php self::render_logs_table(); ?>
							</div>
						</div>
					</div>

					<!-- Tab content: Settings/Config -->
					<div id="gsm-tab-config" class="gsm-tab-content">
						<div class="gsm-card gsm-settings-card">
							<h2><?php esc_html_e( 'Configurações do GitHub Sync', 'github-sync-manager' ); ?></h2>
							<table class="form-table" role="presentation">
								<tbody>
									<tr>
										<th scope="row"><?php esc_html_e( 'Conta Conectada', 'github-sync-manager' ); ?></th>
										<td>
											<div class="gsm-profile-detail">
												<img src="<?php echo esc_url( $connected_user['avatar_url'] ); ?>" class="gsm-profile-avatar" alt="Avatar" />
												<div>
													<strong>@<?php echo esc_html( $connected_user['username'] ); ?></strong>
													<p class="description"><?php printf( esc_html__( 'Tipo de Token: %s', 'github-sync-manager' ), esc_html( ucfirst( $connected_user['token_type'] ) ) ); ?></p>
												</div>
											</div>
										</td>
									</tr>
									<tr>
										<th scope="row"><?php esc_html_e( 'Token Armazenado', 'github-sync-manager' ); ?></th>
										<td>
											<code><?php echo esc_html( GSM_Encryption::mask_token( GSM_Encryption::decrypt( get_option( GSM_Manager::OPTION_TOKEN ) ) ) ); ?></code>
										</td>
									</tr>
									<tr>
										<th scope="row"><?php esc_html_e( 'Agendamento Automático', 'github-sync-manager' ); ?></th>
										<td>
											<p><?php esc_html_e( 'O sistema verifica se há atualizações disponíveis para os plugins de forma automática duas vezes ao dia.', 'github-sync-manager' ); ?></p>
											
											<div class="gsm-info-notice">
												<span class="dashicons dashicons-info"></span>
												<p><em>“<?php esc_html_e( 'As verificações automáticas dependem de tráfego no site. Para sites de produção, recomenda-se desabilitar o WP-Cron no wp-config.php e agendar uma tarefa cron real no servidor chamando wp-cron.php.', 'github-sync-manager' ); ?>”</em></p>
											</div>
										</td>
									</tr>
								</tbody>
							</table>

							<div class="gsm-settings-actions">
								<button type="button" class="button button-link-delete" id="gsm-btn-disconnect">
									<?php esc_html_e( 'Desconectar conta GitHub', 'github-sync-manager' ); ?>
								</button>
								<span class="spinner gsm-spinner" id="gsm-disconnect-spinner"></span>
							</div>
						</div>
					</div>

				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Helper to render plugins table body rows.
	 */
	public static function render_plugins_table_body() {
		$managed = get_option( GSM_Manager::OPTION_PLUGINS, array() );
		if ( empty( $managed ) || ! is_array( $managed ) ) {
			?>
			<tr class="gsm-no-data-row">
				<td colspan="7"><?php esc_html_e( 'Nenhum plugin gerenciado ainda. Acesse a aba "Adicionar Plugin" para começar.', 'github-sync-manager' ); ?></td>
			</tr>
			<?php
			return;
		}

		foreach ( $managed as $repo => $data ) {
			$plugin_file = isset( $data['plugin_file'] ) ? $data['plugin_file'] : '';
			$plugin_name = dirname( $plugin_file );

			$installed_version = '0.0.0';
			if ( ! empty( $plugin_file ) && file_exists( WP_PLUGIN_DIR . '/' . $plugin_file ) ) {
				$file_data = get_file_data( WP_PLUGIN_DIR . '/' . $plugin_file, array(
					'Name'    => 'Plugin Name',
					'Version' => 'Version',
				) );
				$installed_version = $file_data['Version'];
				$plugin_name       = $file_data['Name'];
			}

			$status         = isset( $data['status'] ) ? $data['status'] : 'atualizado';
			$latest_version = isset( $data['latest_version'] ) ? $data['latest_version'] : $installed_version;
			$last_checked   = isset( $data['last_checked'] ) ? $data['last_checked'] : '';
			$error_message  = isset( $data['error_message'] ) ? $data['error_message'] : '';

			$status_label = '';
			$status_class = '';

			switch ( $status ) {
				case 'atualizado':
					$status_label = __( 'Atualizado', 'github-sync-manager' );
					$status_class = 'gsm-status-updated';
					break;
				case 'atualizacao_disponivel':
					$status_label = __( 'Atualização disponível', 'github-sync-manager' );
					$status_class = 'gsm-status-update';
					break;
				case 'indisponivel':
					$status_label = __( 'Indisponível', 'github-sync-manager' );
					$status_class = 'gsm-status-unavailable';
					break;
				case 'erro':
				default:
					$status_label = __( 'Erro', 'github-sync-manager' );
					$status_class = 'gsm-status-error';
					break;
			}

			?>
			<tr data-repo="<?php echo esc_attr( $repo ); ?>">
				<td><strong><?php echo esc_html( $plugin_name ); ?></strong></td>
				<td>
					<a href="<?php echo esc_url( 'https://github.com/' . $repo ); ?>" target="_blank" rel="noopener noreferrer">
						<span class="dashicons dashicons-external"></span>
						<?php echo esc_html( $repo ); ?>
					</a>
				</td>
				<td><code><?php echo esc_html( $installed_version ); ?></code></td>
				<td><code><?php echo esc_html( $latest_version ); ?></code></td>
				<td><?php echo esc_html( ! empty( $last_checked ) ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $last_checked ) ) : '-' ); ?></td>
				<td>
					<span class="gsm-status-badge <?php echo esc_attr( $status_class ); ?>">
						<?php echo esc_html( $status_label ); ?>
					</span>
					<?php if ( 'erro' === $status && ! empty( $error_message ) ) : ?>
						<span class="dashicons dashicons-editor-help gsm-tooltip-trigger" title="<?php echo esc_attr( $error_message ); ?>"></span>
					<?php endif; ?>
				</td>
				<td>
					<button type="button" class="button button-link-delete gsm-btn-remove" data-repo="<?php echo esc_attr( $repo ); ?>">
						<?php esc_html_e( 'Parar de gerenciar', 'github-sync-manager' ); ?>
					</button>
				</td>
			</tr>
			<?php
		}
	}

	/**
	 * Helper to render the activity logs table.
	 */
	public static function render_logs_table() {
		$logs = get_option( GSM_Manager::OPTION_LOGS, array() );
		if ( empty( $logs ) || ! is_array( $logs ) ) {
			?>
			<p class="gsm-no-logs-msg"><?php esc_html_e( 'Nenhuma atividade registrada ainda.', 'github-sync-manager' ); ?></p>
			<?php
			return;
		}

		?>
		<table class="wp-list-table widefat fixed striped table-view-list gsm-logs-table">
			<thead>
				<tr>
					<th style="width: 160px;"><?php esc_html_e( 'Data/Hora', 'github-sync-manager' ); ?></th>
					<th style="width: 200px;"><?php esc_html_e( 'Repositório', 'github-sync-manager' ); ?></th>
					<th style="width: 140px;"><?php esc_html_e( 'Ação', 'github-sync-manager' ); ?></th>
					<th style="width: 110px;"><?php esc_html_e( 'Resultado', 'github-sync-manager' ); ?></th>
					<th><?php esc_html_e( 'Mensagem', 'github-sync-manager' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $logs as $log ) :
					$res_class = ( 'sucesso' === $log['result'] ) ? 'gsm-log-success' : 'gsm-log-error';
					?>
					<tr>
						<td><code><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $log['timestamp'] ) ) ); ?></code></td>
						<td><strong><?php echo esc_html( $log['repo'] ); ?></strong></td>
						<td><span class="gsm-log-action-tag"><?php echo esc_html( strtoupper( $log['action'] ) ); ?></span></td>
						<td><span class="gsm-status-badge <?php echo esc_attr( $res_class ); ?>"><?php echo esc_html( ucfirst( $log['result'] ) ); ?></span></td>
						<td class="gsm-log-msg-cell"><?php echo esc_html( $log['message'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * AJAX endpoint: Connect GitHub PAT.
	 */
	public static function ajax_connect_account() {
		check_ajax_referer( 'gsm_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sem permissões adequadas.', 'github-sync-manager' ) ) );
		}

		$raw_token = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
		if ( empty( $raw_token ) ) {
			wp_send_json_error( array( 'message' => __( 'O token está vazio.', 'github-sync-manager' ) ) );
		}

		// Encrypt and test connection
		$encrypted = GSM_Encryption::encrypt( $raw_token );
		if ( is_wp_error( $encrypted ) ) {
			wp_send_json_error( array( 'message' => $encrypted->get_error_message() ) );
		}

		$api = new GSM_GitHub_API( $raw_token );
		$validation = $api->validate_token();

		if ( is_wp_error( $validation ) ) {
			wp_send_json_error( array( 'message' => $validation->get_error_message() ) );
		}

		// Success: Save details to options
		update_option( GSM_Manager::OPTION_TOKEN, $encrypted );
		update_option( GSM_Manager::OPTION_USER, $validation ); // Autoload = yes for general connected status is fine since it's small

		GSM_Manager::log( 'sistema', 'conexao', 'sucesso', sprintf( __( 'Conta conectada com sucesso (@%s).', 'github-sync-manager' ), $validation['username'] ) );

		wp_send_json_success( array(
			'message'    => __( 'Conectado com sucesso!', 'github-sync-manager' ),
			'username'   => $validation['username'],
			'avatar_url' => $validation['avatar_url'],
		) );
	}

	/**
	 * AJAX endpoint: Disconnect account.
	 */
	public static function ajax_disconnect_account() {
		check_ajax_referer( 'gsm_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sem permissões adequadas.', 'github-sync-manager' ) ) );
		}

		// Delete authentication options
		delete_option( GSM_Manager::OPTION_TOKEN );
		delete_option( GSM_Manager::OPTION_USER );

		GSM_Manager::log( 'sistema', 'desconexao', 'sucesso', __( 'Conta GitHub desconectada. Os plugins não receberão atualizações até que uma nova conta seja reconectada.', 'github-sync-manager' ) );

		wp_send_json_success( array( 'message' => __( 'Conta desconectada com sucesso!', 'github-sync-manager' ) ) );
	}

	/**
	 * AJAX endpoint: Retrieve repos list.
	 */
	public static function ajax_add_plugin() {
		check_ajax_referer( 'gsm_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sem permissões adequadas.', 'github-sync-manager' ) ) );
		}

		// Connect API
		$token = get_option( GSM_Manager::OPTION_TOKEN );
		if ( empty( $token ) ) {
			wp_send_json_error( array( 'message' => __( 'Token ausente.', 'github-sync-manager' ) ) );
		}

		$decrypted = GSM_Encryption::decrypt( $token );
		if ( is_wp_error( $decrypted ) ) {
			wp_send_json_error( array( 'message' => $decrypted->get_error_message() ) );
		}

		// Fetch action: Get list of user repositories OR install repository
		$action_type = isset( $_POST['action_type'] ) ? sanitize_text_field( wp_unslash( $_POST['action_type'] ) ) : '';

		if ( 'list' === $action_type ) {
			$api   = new GSM_GitHub_API( $decrypted );
			$repos = $api->get_repositories();

			if ( is_wp_error( $repos ) ) {
				wp_send_json_error( array( 'message' => $repos->get_error_message() ) );
			}

			$managed_plugins = get_option( GSM_Manager::OPTION_PLUGINS, array() );
			if ( ! is_array( $managed_plugins ) ) {
				$managed_plugins = array();
			}

			// Add is_managed flag
			foreach ( $repos as &$r ) {
				$r['is_managed'] = isset( $managed_plugins[ $r['full_name'] ] );
			}

			wp_send_json_success( array( 'repos' => $repos ) );
		} elseif ( 'install' === $action_type ) {
			$repo_slug = isset( $_POST['repo'] ) ? sanitize_text_field( wp_unslash( $_POST['repo'] ) ) : '';
			if ( empty( $repo_slug ) ) {
				wp_send_json_error( array( 'message' => __( 'Repositório não especificado.', 'github-sync-manager' ) ) );
			}

			// Self block validation
			$valid_repo = GSM_Manager::validate_repository_before_add( $repo_slug );
			if ( is_wp_error( $valid_repo ) ) {
				wp_send_json_error( array( 'message' => $valid_repo->get_error_message() ) );
			}

			$managed_plugins = get_option( GSM_Manager::OPTION_PLUGINS, array() );
			if ( ! is_array( $managed_plugins ) ) {
				$managed_plugins = array();
			}

			if ( isset( $managed_plugins[ $repo_slug ] ) ) {
				wp_send_json_error( array( 'message' => __( 'Este repositório já está sendo gerenciado.', 'github-sync-manager' ) ) );
			}

			// Explode owner and repo
			$parts = explode( '/', $repo_slug );
			if ( count( $parts ) !== 2 ) {
				wp_send_json_error( array( 'message' => __( 'Slug de repositório inválido.', 'github-sync-manager' ) ) );
			}
			$owner = $parts[0];
			$repo  = $parts[1];

			$api = new GSM_GitHub_API( $decrypted );

			// Check access directly for the specific repository (solves Fine-Grained access verification)
			$access = $api->test_repo_access( $owner, $repo );
			if ( is_wp_error( $access ) ) {
				wp_send_json_error( array( 'message' => $access->get_error_message() ) );
			}

			// Fetch releases
			$releases = $api->get_releases( $owner, $repo, true ); // Force reload bypass cache
			if ( is_wp_error( $releases ) ) {
				wp_send_json_error( array( 'message' => $releases->get_error_message() ) );
			}

			if ( empty( $releases ) ) {
				wp_send_json_error( array( 'message' => __( 'Este repositório ainda não tem releases publicadas. Crie uma release no GitHub antes de adicionar.', 'github-sync-manager' ) ) );
			}

			$latest_release = $releases[0];
			$package_url    = '';
			if ( ! empty( $latest_release['assets'] ) ) {
				$package_url = $latest_release['assets'][0]['url'];
			} elseif ( ! empty( $latest_release['zipball_url'] ) ) {
				$package_url = $latest_release['zipball_url'];
			}

			if ( empty( $package_url ) ) {
				wp_send_json_error( array( 'message' => __( 'Nenhum ZIP de pacote de download encontrado na última release.', 'github-sync-manager' ) ) );
			}

			// Native programmatic installation via Plugin_Upgrader
			include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
			include_once ABSPATH . 'wp-admin/includes/file.php';
			include_once ABSPATH . 'wp-admin/includes/plugin.php';

			// Set the dynamic global variable so the upgrader filters recognize and resolve the canonical slug
			GSM_Updater::$currently_installing_repo = $repo_slug;

			// Use silent/automatic skin
			$skin     = new Automatic_Upgrader_Skin();
			$upgrader = new Plugin_Upgrader( $skin );
			$result   = $upgrader->install( $package_url );

			// Re-initialize files and flush cache
			wp_clean_plugins_cache();

			// Clear dynamic variable
			GSM_Updater::$currently_installing_repo = '';

			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array( 'message' => $result->get_error_message() ) );
			}

			if ( ! $result ) {
				wp_send_json_error( array( 'message' => __( 'A instalação do plugin falhou. Verifique as permissões de gravação de arquivos.', 'github-sync-manager' ) ) );
			}

			// Locate the newly installed plugin directory and file using slug resolution code
			// We scan wp-content/plugins to find the plugin relative path
			$plugins = get_plugins();
			$installed_plugin_file = '';
			$latest_version        = ltrim( $latest_release['tag_name'], 'vV' );

			// Search for matching metadata
			foreach ( $plugins as $file => $meta ) {
				// We look for a folder directory name matching the resolved slug in unzipping
				// Standard case: check if we can match
				$parts_file = explode( '/', $file );
				$folder     = $parts_file[0];

				// Check if the Text Domain matches
				$domain = isset( $meta['TextDomain'] ) ? trim( $meta['TextDomain'] ) : '';
				
				// Standard slug is text domain or directory basename
				if ( ! empty( $domain ) && ( sanitize_title( $domain ) === $folder ) ) {
					$installed_plugin_file = $file;
					break;
				}
			}

			// Fallback: search by name/version if text domain didn't match perfectly
			if ( empty( $installed_plugin_file ) ) {
				foreach ( $plugins as $file => $meta ) {
					if ( basename( dirname( $file ) ) === sanitize_title( $repo ) ) {
						$installed_plugin_file = $file;
						break;
					}
				}
			}

			// Ultimate fallback: get the last modified plugin folder inside wp-content/plugins
			if ( empty( $installed_plugin_file ) ) {
				$folders = glob( WP_PLUGIN_DIR . '/*', GLOB_ONLYDIR );
				$latest_time = 0;
				$latest_folder = '';
				foreach ( $folders as $f ) {
					$mtime = filemtime( $f );
					if ( $mtime > $latest_time && basename( $f ) !== 'github-sync-manager' ) {
						$latest_time = $mtime;
						$latest_folder = basename( $f );
					}
				}

				if ( ! empty( $latest_folder ) ) {
					$nested_files = glob( WP_PLUGIN_DIR . '/' . $latest_folder . '/*.php' );
					foreach ( $nested_files as $nf ) {
						$data = get_file_data( $nf, array( 'PluginName' => 'Plugin Name' ) );
						if ( ! empty( $data['PluginName'] ) ) {
							$installed_plugin_file = $latest_folder . '/' . basename( $nf );
							break;
						}
					}
				}
			}

			if ( empty( $installed_plugin_file ) ) {
				wp_send_json_error( array( 'message' => __( 'O plugin foi extraído, mas o WordPress não conseguiu indexar o arquivo principal. Ative-o manualmente no painel Plugins.', 'github-sync-manager' ) ) );
			}

			// Add to managed list
			$managed_plugins[ $repo_slug ] = array(
				'plugin_file'    => $installed_plugin_file,
				'latest_version' => $latest_version,
				'status'         => 'atualizado',
				'last_checked'   => current_time( 'mysql' ),
				'html_url'       => 'https://github.com/' . $repo_slug,
				'error_message'  => '',
			);

			GSM_Manager::update_option_no_autoload( GSM_Manager::OPTION_PLUGINS, $managed_plugins );
			GSM_Manager::log(
				$repo_slug,
				'instalacao',
				'sucesso',
				sprintf( __( 'Plugin instalado com sucesso (Versão %s).', 'github-sync-manager' ), $latest_version )
			);

			$activate_url = wp_nonce_url( 'plugins.php?action=activate&amp;plugin=' . urlencode( $installed_plugin_file ), 'activate-plugin_' . $installed_plugin_file );

			wp_send_json_success( array(
				'message'      => __( 'Plugin instalado com sucesso!', 'github-sync-manager' ),
				'plugin_name'  => get_plugin_data( WP_PLUGIN_DIR . '/' . $installed_plugin_file )['Name'],
				'version'      => $latest_version,
				'activate_url' => admin_url( $activate_url ),
			) );
		}

		wp_send_json_error( array( 'message' => __( 'Ação inválida.', 'github-sync-manager' ) ) );
	}

	/**
	 * AJAX endpoint: Remove plugin from managed list.
	 */
	public static function ajax_remove_plugin() {
		check_ajax_referer( 'gsm_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sem permissões adequadas.', 'github-sync-manager' ) ) );
		}

		$repo_slug = isset( $_POST['repo'] ) ? sanitize_text_field( wp_unslash( $_POST['repo'] ) ) : '';
		if ( empty( $repo_slug ) ) {
			wp_send_json_error( array( 'message' => __( 'Repositório não especificado.', 'github-sync-manager' ) ) );
		}

		$managed = get_option( GSM_Manager::OPTION_PLUGINS, array() );
		if ( ! is_array( $managed ) ) {
			$managed = array();
		}

		if ( isset( $managed[ $repo_slug ] ) ) {
			unset( $managed[ $repo_slug ] );
			GSM_Manager::update_option_no_autoload( GSM_Manager::OPTION_PLUGINS, $managed );

			// Clear release cache transient immediately to prevent database orphan records
			$parts = explode( '/', $repo_slug );
			if ( count( $parts ) === 2 ) {
				GSM_GitHub_API::delete_releases_cache( $parts[0], $parts[1] );
			}

			GSM_Manager::log( $repo_slug, 'parar_gerenciar', 'sucesso', __( 'Parou de gerenciar o repositório. O plugin continua instalado no WordPress.', 'github-sync-manager' ) );

			wp_send_json_success( array( 'message' => __( 'Gerenciamento removido com sucesso!', 'github-sync-manager' ) ) );
		}

		wp_send_json_error( array( 'message' => __( 'Repositório não encontrado.', 'github-sync-manager' ) ) );
	}

	/**
	 * AJAX endpoint: Check updates now manually.
	 */
	public static function ajax_check_updates() {
		check_ajax_referer( 'gsm_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sem permissões adequadas.', 'github-sync-manager' ) ) );
		}

		$token = get_option( GSM_Manager::OPTION_TOKEN );
		if ( empty( $token ) ) {
			wp_send_json_error( array( 'message' => __( 'Token ausente.', 'github-sync-manager' ) ) );
		}

		$decrypted = GSM_Encryption::decrypt( $token );
		if ( is_wp_error( $decrypted ) ) {
			wp_send_json_error( array( 'message' => $decrypted->get_error_message() ) );
		}

		$managed = get_option( GSM_Manager::OPTION_PLUGINS, array() );
		if ( empty( $managed ) || ! is_array( $managed ) ) {
			wp_send_json_success( array( 'message' => __( 'Nenhum plugin gerenciado para verificar.', 'github-sync-manager' ) ) );
		}

		$api = new GSM_GitHub_API( $decrypted );

		foreach ( $managed as $repo => $data ) {
			$plugin_file = isset( $data['plugin_file'] ) ? $data['plugin_file'] : '';
			if ( empty( $plugin_file ) ) {
				continue;
			}

			$plugin_path = WP_PLUGIN_DIR . '/' . $plugin_file;
			if ( ! file_exists( $plugin_path ) ) {
				$managed[ $repo ]['status']        = 'indisponivel';
				$managed[ $repo ]['error_message'] = __( 'Diretório ou arquivo principal do plugin não encontrado localmente.', 'github-sync-manager' );
				continue;
			}

			// Resolve version
			$file_data = get_file_data( $plugin_path, array( 'Version' => 'Version' ) );
			$installed_version = ! empty( $file_data['Version'] ) ? $file_data['Version'] : '0.0.0';

			$parts = explode( '/', $repo );
			if ( count( $parts ) !== 2 ) {
				continue;
			}
			$owner = $parts[0];
			$repo_name = $parts[1];

			// Query releases directly bypassing 1-hour cache
			$releases = $api->get_releases( $owner, $repo_name, true );

			$managed[ $repo ]['last_checked'] = current_time( 'mysql' );

			if ( is_wp_error( $releases ) ) {
				$managed[ $repo ]['status']        = 'erro';
				$managed[ $repo ]['error_message'] = $releases->get_error_message();
				continue;
			}

			if ( empty( $releases ) ) {
				$managed[ $repo ]['status']        = 'erro';
				$managed[ $repo ]['error_message'] = __( 'Repositório não possui releases publicadas.', 'github-sync-manager' );
				continue;
			}

			$latest_release = $releases[0];
			$latest_version = ltrim( $latest_release['tag_name'], 'vV' );

			$managed[ $repo ]['latest_version'] = $latest_version;

			if ( version_compare( $latest_version, $installed_version, '>' ) ) {
				$managed[ $repo ]['status']        = 'atualizacao_disponivel';
				$managed[ $repo ]['error_message'] = '';
			} else {
				$managed[ $repo ]['status']        = 'atualizado';
				$managed[ $repo ]['error_message'] = '';
			}
		}

		// Save updated states to DB
		GSM_Manager::update_option_no_autoload( GSM_Manager::OPTION_PLUGINS, $managed );

		// Clear core update cache transient to force WordPress to recognize updates immediately in standard screen
		delete_site_transient( 'update_plugins' );

		GSM_Manager::log( 'sistema', 'verificacao_manual', 'sucesso', __( 'Verificação manual executada para todos os plugins gerenciados.', 'github-sync-manager' ) );

		// Capture rendered HTML of table body and logs
		ob_start();
		self::render_plugins_table_body();
		$table_html = ob_get_clean();

		ob_start();
		self::render_logs_table();
		$logs_html = ob_get_clean();

		wp_send_json_success( array(
			'message'    => __( 'Verificação concluída!', 'github-sync-manager' ),
			'table_html' => $table_html,
			'logs_html'  => $logs_html,
		) );
	}
}
