<?php
/**
 * Core Manager and Logger
 *
 * @package GitHubSyncManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class CODESYNC_Manager
 *
 * Manages database options, handles activity logs (max 100 entries, autoload=no),
 * creates and secures temporary/backup directories, and validates plugin rules.
 */
class CODESYNC_Manager {

	/**
	 * Options names.
	 */
	const OPTION_TOKEN      = 'codesync_encrypted_token';
	const OPTION_USER       = 'codesync_connected_user';
	const OPTION_PLUGINS    = 'codesync_managed_plugins';
	const OPTION_THEMES     = 'codesync_managed_themes';
	const OPTION_LOGS       = 'codesync_activity_logs';

	/**
	 * Logs a message to the database log system.
	 * Max 100 entries, prepended, saved with autoload = no.
	 *
	 * @param string $repo    Repository slug (e.g. owner/repo) or 'system'.
	 * @param string $action  Action name.
	 * @param string $result  Result: 'sucesso' or 'erro'.
	 * @param string $message Detailed description.
	 */
	public static function log( $repo, $action, $result, $message ) {
		// Verify capabilities or run contexts - logging is system wide
		$logs = get_option( self::OPTION_LOGS, array() );
		if ( ! is_array( $logs ) ) {
			$logs = array();
		}

		$new_entry = array(
			'timestamp' => current_time( 'mysql' ),
			'repo'      => sanitize_text_field( $repo ),
			'action'    => sanitize_text_field( $action ),
			'result'    => sanitize_text_field( $result ),
			'message'   => sanitize_text_field( $message ),
		);

		array_unshift( $logs, $new_entry );

		// Keep only the last 100 logs
		if ( count( $logs ) > 100 ) {
			$logs = array_slice( $logs, 0, 100 );
		}

		// Save option with autoload = no
		self::update_option_no_autoload( self::OPTION_LOGS, $logs );
	}

	/**
	 * Helper to update option with autoload = no.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  Option value.
	 * @return bool True on success, false on failure.
	 */
	public static function update_option_no_autoload( $option, $value ) {
		// In WordPress 6.4+, we can pass a third parameter to update_option to set autoload.
		// However, to be fully compatible with older/all WP versions, we first try to add the option
		// with autoload = 'no'. If it already exists, we call update_option which retains the original autoload flag.
		if ( false === get_option( $option ) ) {
			return add_option( $option, $value, '', 'no' );
		}
		
		// If WP version supports 3rd parameter in update_option (deprecated argument warning in some versions, but 6.4+ uses it):
		if ( function_exists( 'wp_updates_option_autoload_supported' ) || version_compare( get_bloginfo( 'version' ), '6.4', '>=' ) ) {
			return update_option( $option, $value, 'no' );
		}

		return update_option( $option, $value );
	}

	/**
	 * Creates and secures a directory in wp-content/uploads.
	 * Adds .htaccess (Deny from all) and index.php (Silence is golden) to prevent direct web access.
	 *
	 * @param string $subfolder Directory name inside uploads (e.g. 'codesync-temp').
	 * @return string|WP_Error Absolute path of the secure directory, or WP_Error on failure.
	 */
	public static function get_secure_directory( $subfolder ) {
		if ( 'codesync-backups' === $subfolder ) {
			$uploads = wp_upload_dir();
			if ( ! empty( $uploads['error'] ) ) {
				return new WP_Error( 'codesync_uploads_error', $uploads['error'] );
			}
			$dir_path = $uploads['basedir'] . '/' . sanitize_file_name( $subfolder );
		} else {
			$dir_path = rtrim( get_temp_dir(), '/' ) . '/' . sanitize_file_name( $subfolder );
		}

		if ( ! file_exists( $dir_path ) ) {
			if ( ! wp_mkdir_p( $dir_path ) ) {
				return new WP_Error(
					'codesync_dir_creation_failed',
					/* translators: %s: directory name */
					sprintf( __( 'Falha ao criar o diretório seguro: %s.', 'codesync-manager-for-github' ), $subfolder )
				);
			}
		}

		// Security: Create .htaccess file
		$htaccess_file = $dir_path . '/.htaccess';
		if ( ! file_exists( $htaccess_file ) ) {
			$htaccess_content = "Deny from all\n";
			@file_put_contents( $htaccess_file, $htaccess_content );
		}

		// Security: Create empty index.php file
		$index_file = $dir_path . '/index.php';
		if ( ! file_exists( $index_file ) ) {
			$index_content = "<?php\n// Silence is golden.\n";
			@file_put_contents( $index_file, $index_content );
		}

		return $dir_path;
	}

	/**
	 * Helper to empty and clean a secure directory.
	 *
	 * @param string $dir_path Absolute path to the directory.
	 */
	public static function clean_directory( $dir_path ) {
		if ( ! is_dir( $dir_path ) || strpos( $dir_path, 'uploads' ) === false ) {
			return;
		}

		global $wp_filesystem;
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if ( empty( $wp_filesystem ) ) {
			WP_Filesystem();
		}

		$files = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $dir_path, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $files as $fileinfo ) {
			$path = $fileinfo->getRealPath();
			if ( basename( $path ) === '.htaccess' || basename( $path ) === 'index.php' ) {
				continue; // Keep the security files
			}
			if ( $fileinfo->isDir() ) {
				$wp_filesystem->rmdir( $path );
			} else {
				wp_delete_file( $path );
			}
		}
	}

	/**
	 * Blocks self-management of this plugin to prevent update loop deadlocks.
	 *
	 * @param string $repo_slug Repository name (e.g. 'owner/repo').
	 * @return bool|WP_Error True if safe, WP_Error if blocked.
	 */
	public static function validate_repository_before_add( $repo_slug ) {
		// Prevent adding this plugin itself
		$repo_slug = strtolower( trim( $repo_slug ) );

		if ( false !== strpos( $repo_slug, 'codesync-manager-for-github' ) ) {
			return new WP_Error(
				'codesync_blocked_self_management',
				__( 'Não é permitido gerenciar este próprio plugin através do GitHub Sync Manager para evitar conflitos de atualização.', 'codesync-manager-for-github' )
			);
		}

		return true;
	}

	/**
	 * Deletes all plugin temporary files and backups older than 24 hours.
	 */
	public static function run_garbage_collector() {
		// Clean codesync-temp (anything older than 24 hours)
		$temp_dir = self::get_secure_directory( 'codesync-temp' );
		if ( ! is_wp_error( $temp_dir ) && is_dir( $temp_dir ) ) {
			$now = time();
			$files = new DirectoryIterator( $temp_dir );
			foreach ( $files as $file ) {
				if ( $file->isDot() || $file->getFilename() === '.htaccess' || $file->getFilename() === 'index.php' ) {
					continue;
				}
				if ( $now - $file->getMTime() > DAY_IN_SECONDS ) {
					$path = $file->getRealPath();
					if ( $file->isDir() ) {
						self::delete_directory_recursive( $path );
					} else {
						wp_delete_file( $path );
					}
				}
			}
		}

		// Clean codesync-backups (keep only the 2 most recent per plugin)
		$backups_dir = self::get_secure_directory( 'codesync-backups' );
		if ( ! is_wp_error( $backups_dir ) && is_dir( $backups_dir ) ) {
			$backups_by_plugin = array();
			$files = new DirectoryIterator( $backups_dir );
			foreach ( $files as $file ) {
				if ( $file->isDot() || ! $file->isDir() ) {
					continue;
				}
				$folder_name = $file->getFilename();
				// Extract the plugin name by removing the timestamp suffix
				if ( preg_match( '/^(.*?)-(\d{10})$/', $folder_name, $matches ) ) {
					$plugin_slug = $matches[1];
					$timestamp = (int) $matches[2];
					$backups_by_plugin[ $plugin_slug ][] = array(
						'path' => $file->getRealPath(),
						'time' => $timestamp
					);
				}
			}

			foreach ( $backups_by_plugin as $plugin_slug => $plugin_backups ) {
				// Sort by time descending (newest first)
				usort( $plugin_backups, function($a, $b) {
					return $b['time'] - $a['time'];
				});

				// Delete any backup after the second one
				if ( count( $plugin_backups ) > 2 ) {
					$to_delete = array_slice( $plugin_backups, 2 );
					foreach ( $to_delete as $backup ) {
						self::delete_directory_recursive( $backup['path'] );
					}
				}
			}
		}
	}

	/**
	 * Recursively delete a directory.
	 *
	 * @param string $dir Absolute directory path.
	 */
	public static function delete_directory_recursive( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		global $wp_filesystem;
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if ( empty( $wp_filesystem ) ) {
			WP_Filesystem();
		}

		$files = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $files as $fileinfo ) {
			$path = $fileinfo->getRealPath();
			if ( $fileinfo->isDir() ) {
				$wp_filesystem->rmdir( $path );
			} else {
				wp_delete_file( $path );
			}
		}
		$wp_filesystem->rmdir( $dir );
	}

	/**
	 * Map raw WordPress / CodeSync error codes to developer-friendly descriptions.
	 *
	 * @param WP_Error $error The WP_Error object.
	 * @return string Detailed developer-friendly error message.
	 */
	public static function get_developer_error_message( $error ) {
		if ( ! is_wp_error( $error ) ) {
			return is_string( $error ) ? $error : '';
		}

		$code    = $error->get_error_code();
		$message = $error->get_error_message();

		switch ( $code ) {
			case 'download_failed':
				return sprintf(
					__( 'Erro de Download: O WordPress não conseguiu baixar o arquivo ZIP da API do GitHub. Verifique se o seu servidor possui acesso à internet ou se o token tem permissões adequadas. (Erro: %s)', 'codesync-manager-for-github' ),
					$message
				);
			case 'incompatible_archive':
				return __( 'Arquivo Incompatível: O arquivo baixado não é um ZIP válido ou está corrompido.', 'codesync-manager-for-github' );
			case 'fs_unavailable':
				return __( 'Sistema de Arquivos Indisponível: O WordPress não conseguiu carregar as credenciais de escrita direta ou o método de acesso ao sistema de arquivos falhou.', 'codesync-manager-for-github' );
			case 'fs_mkdir_failed':
				return __( 'Falha de Diretório: Não foi possível criar a pasta final do plugin/tema. Verifique se a pasta principal (wp-content/plugins ou themes) tem permissões de escrita corretas.', 'codesync-manager-for-github' );
			case 'fs_copy_failed':
			case 'copy_failed':
				return __( 'Falha ao Copiar Arquivos: Falha ao mover os arquivos extraídos para a pasta definitiva de plugins/temas do WordPress. Verifique se há arquivos bloqueados.', 'codesync-manager-for-github' );
			case 'folder_exists':
				return __( 'Diretório Já Existe: A pasta de destino já existe e o instalador falhou ao tentar substituí-la.', 'codesync-manager-for-github' );
			case 'codesync_blocked_self_management':
				return __( 'Auto-gerenciamento Bloqueado: Não é permitido gerenciar este próprio plugin através do CodeSync para evitar loops de atualização e bloqueio de execução.', 'codesync-manager-for-github' );
			case 'codesync_zip_open_failed':
				return __( 'Erro de Descompactação: O ZIP temporário baixado não pôde ser aberto (classe ZipArchive falhou ou arquivo incompleto).', 'codesync-manager-for-github' );
			case 'codesync_invalid_package_zip':
				return __( 'Pacote Inválido: O ZIP do GitHub não possui a estrutura correta de um plugin ou tema WordPress (cabeçalho "Plugin Name" ou "Theme Name" ausente).', 'codesync-manager-for-github' );
			case 'codesync_slug_resolution_failed':
				return __( 'Resolução de Slug Falhou: Não foi possível identificar o arquivo PHP principal do plugin ou o style.css do tema nos arquivos extraídos.', 'codesync-manager-for-github' );
			case 'codesync_subfolder_not_found':
				return sprintf(
					__( 'Subpasta não encontrada: A subpasta configurada não existe no repositório. (Caminho: %s)', 'codesync-manager-for-github' ),
					$message
				);
			case 'codesync_php_version_mismatch':
			case 'codesync_php_version_mismatch_manual':
				return sprintf(
					__( 'Incompatibilidade de PHP: O pacote requer uma versão de PHP superior à executada pelo servidor. (Requisito: %s)', 'codesync-manager-for-github' ),
					$message
				);
			case 'codesync_backup_failed':
				return __( 'Erro de Cópia/Backup: Falha ao criar a pasta de backup de segurança em wp-content/uploads/codesync-backups. A atualização foi cancelada para proteger o plugin antigo.', 'codesync-manager-for-github' );
			case 'codesync_rename_failed':
			case 'codesync_rename_nested_failed':
				return __( 'Falha ao Renomear: Não foi possível renomear ou mover a pasta temporária extraída para a pasta definitiva com o slug correto.', 'codesync-manager-for-github' );
			default:
				return $message;
		}
	}
}
