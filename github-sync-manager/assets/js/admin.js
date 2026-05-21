/**
 * GitHub Sync Manager Administration Logic
 */
jQuery(document).ready(function($) {

	// Cached DOM elements
	var $connectForm        = $('#gsm-connect-form');
	var $connectSpinner     = $connectForm.find('.gsm-spinner');
	var $connectError       = $connectForm.find('.gsm-error-message');
	
	var $scanBtn            = $('#gsm-btn-scan-now');
	var $scanSpinner        = $('#gsm-scan-spinner');
	var $pluginsTableBody   = $('#gsm-plugins-table tbody');
	
	var $reposContainer     = $('#gsm-repos-container');
	var $reposSpinner       = $('#gsm-repos-spinner');
	var $searchField        = $('#gsm-repo-search');
	var $reloadReposBtn     = $('#gsm-btn-reload-repos');
	
	var $disconnectBtn      = $('#gsm-btn-disconnect');
	var $disconnectSpinner  = $('#gsm-disconnect-spinner');

	var cachedRepos         = []; // Store repositories lists for live filtering

	/* ---------------------------------------------------- */
	/* 1. Admin Tab Navigation                              */
	/* ---------------------------------------------------- */
	$('.gsm-tabs-nav a').on('click', function(e) {
		e.preventDefault();
		
		var targetTab = $(this).attr('href');
		
		// Nav active state
		$('.gsm-tabs-nav a').removeClass('nav-tab-active');
		$(this).addClass('nav-tab-active');
		
		// Tab content active state
		$('.gsm-tab-content').removeClass('gsm-tab-active');
		$(targetTab).addClass('gsm-tab-active');

		// If clicking the Add Tab, pull fresh list of repos
		if (targetTab === '#gsm-tab-add' && cachedRepos.length === 0) {
			loadGitHubRepositories();
		}
	});

	/* ---------------------------------------------------- */
	/* 2. Connect Account via PAT                           */
	/* ---------------------------------------------------- */
	$connectForm.on('submit', function(e) {
		e.preventDefault();
		
		var token = $('#gsm_pat_token').val().trim();
		if (!token) return;

		$connectSpinner.addClass('is-active');
		$connectError.hide().text('');
		$connectForm.find('button[type="submit"]').prop('disabled', true);

		$.ajax({
			url: gsm_ajax.url,
			type: 'POST',
			data: {
				action: 'gsm_connect_account',
				nonce: gsm_ajax.nonce,
				token: token
			},
			success: function(response) {
				$connectSpinner.removeClass('is-active');
				if (response.success) {
					// Connection success: refresh page to render dashboard
					window.location.reload();
				} else {
					$connectError.text(response.data.message).fadeIn();
					$connectForm.find('button[type="submit"]').prop('disabled', false);
				}
			},
			error: function() {
				$connectSpinner.removeClass('is-active');
				$connectError.text('Falha na requisição. Verifique sua conexão de rede.').fadeIn();
				$connectForm.find('button[type="submit"]').prop('disabled', false);
			}
		});
	});

	/* ---------------------------------------------------- */
	/* 3. Disconnect Account                                */
	/* ---------------------------------------------------- */
	$disconnectBtn.on('click', function(e) {
		e.preventDefault();

		if (!confirm('Tem certeza de que deseja desconectar sua conta GitHub? Os plugins continuarão instalados, mas não receberão notificações de atualização.')) {
			return;
		}

		$disconnectSpinner.addClass('is-active');
		$disconnectBtn.prop('disabled', true);

		$.ajax({
			url: gsm_ajax.url,
			type: 'POST',
			data: {
				action: 'gsm_disconnect_account',
				nonce: gsm_ajax.nonce
			},
			success: function(response) {
				$disconnectSpinner.removeClass('is-active');
				if (response.success) {
					window.location.reload();
				} else {
					alert(response.data.message);
					$disconnectBtn.prop('disabled', false);
				}
			},
			error: function() {
				$disconnectSpinner.removeClass('is-active');
				alert('Falha na comunicação.');
				$disconnectBtn.prop('disabled', false);
			}
		});
	});

	/* ---------------------------------------------------- */
	/* 4. Load GitHub Repositories                          */
	/* ---------------------------------------------------- */
	function loadGitHubRepositories() {
		$reposSpinner.addClass('is-active');
		$reposContainer.html('<p class="description">Carregando seus repositórios do GitHub...</p>');
		$searchField.val('').prop('disabled', true);

		$.ajax({
			url: gsm_ajax.url,
			type: 'POST',
			data: {
				action: 'gsm_add_plugin',
				action_type: 'list',
				nonce: gsm_ajax.nonce
			},
			success: function(response) {
				$reposSpinner.removeClass('is-active');
				$searchField.prop('disabled', false);

				if (response.success) {
					cachedRepos = response.data.repos;
					renderRepositories(cachedRepos);
				} else {
					$reposContainer.html('<p class="gsm-error-message">' + response.data.message + '</p>');
				}
			},
			error: function() {
				$reposSpinner.removeClass('is-active');
				$searchField.prop('disabled', false);
				$reposContainer.html('<p class="gsm-error-message">Erro de conexão ao buscar repositórios.</p>');
			}
		});
	}

	$reloadReposBtn.on('click', function(e) {
		e.preventDefault();
		loadGitHubRepositories();
	});

	// Render the list/grid of repositories
	function renderRepositories(repos) {
		if (repos.length === 0) {
			$reposContainer.html('<p class="description">Nenhum repositório encontrado na sua conta do GitHub.</p>');
			return;
		}

		var html = '';
		$.each(repos, function(idx, repo) {
			var visibilityClass = repo.private ? 'gsm-private' : 'gsm-public';
			var visibilityLabel = repo.private ? 'Privado' : 'Público';
			var lastUpdated = new Date(repo.updated_at).toLocaleDateString();

			var btnLabel = repo.is_managed ? 'Já Gerenciado' : 'Instalar Plugin';
			var btnClass = repo.is_managed ? 'button-disabled' : 'button-primary gsm-btn-install';
			var btnAttr  = repo.is_managed ? 'disabled' : 'data-repo="' + repo.full_name + '"';

			var desc = repo.description ? repo.description : 'Sem descrição no repositório.';

			html += '<div class="gsm-repo-card" data-name="' + repo.name.toLowerCase() + '" data-fullname="' + repo.full_name.toLowerCase() + '">';
			html += '  <div class="gsm-repo-card-header">';
			html += '    <h3 class="gsm-repo-card-title">' + repo.name + '</h3>';
			html += '    <span class="gsm-visibility-badge ' + visibilityClass + '">' + visibilityLabel + '</span>';
			html += '  </div>';
			html += '  <p class="gsm-repo-desc" title="' + desc + '">' + desc + '</p>';
			html += '  <div class="gsm-repo-footer">';
			html += '    <span class="gsm-repo-date">Atualizado: ' + lastUpdated + '</span>';
			html += '    <button type="button" class="button button-small ' + btnClass + '" ' + btnAttr + '>' + btnLabel + '</button>';
			html += '  </div>';
			html += '</div>';
		});

		$reposContainer.html(html);
	}

	/* ---------------------------------------------------- */
	/* 5. Live Search Filter                                */
	/* ---------------------------------------------------- */
	$searchField.on('input', function() {
		var query = $(this).val().toLowerCase().trim();
		if (!query) {
			$('.gsm-repo-card').show();
			return;
		}

		$('.gsm-repo-card').each(function() {
			var name     = $(this).data('name');
			var fullname = $(this).data('fullname');
			var desc     = $(this).find('.gsm-repo-desc').text().toLowerCase();

			if (name.indexOf(query) !== -1 || fullname.indexOf(query) !== -1 || desc.indexOf(query) !== -1) {
				$(this).show();
			} else {
				$(this).hide();
			}
		});
	});

	/* ---------------------------------------------------- */
	/* 6. Programmatic Installation                         */
	/* ---------------------------------------------------- */
	$reposContainer.on('click', '.gsm-btn-install', function(e) {
		e.preventDefault();
		
		var $btn = $(this);
		var repo = $btn.data('repo');
		if (!repo) return;

		// Confirm
		if (!confirm('Deseja baixar e instalar o plugin do repositório ' + repo + '?')) {
			return;
		}

		$btn.prop('disabled', true).text(gsm_ajax.texts.installing);
		$reposSpinner.addClass('is-active');

		$.ajax({
			url: gsm_ajax.url,
			type: 'POST',
			data: {
				action: 'gsm_add_plugin',
				action_type: 'install',
				repo: repo,
				nonce: gsm_ajax.nonce
			},
			success: function(response) {
				$reposSpinner.removeClass('is-active');
				if (response.success) {
					// Show beautiful confirmation with link to activate
					var confirmHtml = '<div class="gsm-card gsm-notice-success">';
					confirmHtml += '  <h3>✅ Plugin Instalado com Sucesso!</h3>';
					confirmHtml += '  <p>O plugin <strong>' + response.data.plugin_name + '</strong> (Versão ' + response.data.version + ') foi baixado e gravado localmente.</p>';
					confirmHtml += '  <p>👉 <a href="' + response.data.activate_url + '" class="button button-primary">Ativar Plugin Agora</a></p>';
					confirmHtml += '</div>';

					$reposContainer.html(confirmHtml);

					// Clear cache so it reloads table
					cachedRepos = [];
				} else {
					alert('Erro na Instalação: ' + response.data.message);
					$btn.prop('disabled', false).text('Instalar Plugin');
				}
			},
			error: function() {
				$reposSpinner.removeClass('is-active');
				alert('Falha na comunicação de rede ao tentar instalar o plugin.');
				$btn.prop('disabled', false).text('Instalar Plugin');
			}
		});
	});

	/* ---------------------------------------------------- */
	/* 7. Stop Managing Plugin                             */
	/* ---------------------------------------------------- */
	$pluginsTableBody.on('click', '.gsm-btn-remove', function(e) {
		e.preventDefault();
		
		var $btn = $(this);
		var repo = $btn.data('repo');
		if (!repo) return;

		if (!confirm(gsm_ajax.texts.confirm_stop)) {
			return;
		}

		$btn.prop('disabled', true);
		var $row = $btn.closest('tr');

		$.ajax({
			url: gsm_ajax.url,
			type: 'POST',
			data: {
				action: 'gsm_remove_plugin',
				repo: repo,
				nonce: gsm_ajax.nonce
			},
			success: function(response) {
				if (response.success) {
					$row.fadeOut(300, function() {
						$(this).remove();
						if ($pluginsTableBody.find('tr').length === 0) {
							$pluginsTableBody.html('<tr class="gsm-no-data-row"><td colspan="7">Nenhum plugin gerenciado ainda. Acesse a aba "Adicionar Plugin" para começar.</td></tr>');
						}
					});
					
					// Clear repo cache list
					cachedRepos = [];
				} else {
					alert(response.data.message);
					$btn.prop('disabled', false);
				}
			},
			error: function() {
				alert('Erro ao excluir gerenciamento.');
				$btn.prop('disabled', false);
			}
		});
	});

	/* ---------------------------------------------------- */
	/* 8. Manual Scan for Updates                           */
	/* ---------------------------------------------------- */
	$scanBtn.on('click', function(e) {
		e.preventDefault();

		$scanBtn.prop('disabled', true).addClass('updating');
		$scanSpinner.addClass('is-active');

		$.ajax({
			url: gsm_ajax.url,
			type: 'POST',
			data: {
				action: 'gsm_check_updates',
				nonce: gsm_ajax.nonce
			},
			success: function(response) {
				$scanBtn.prop('disabled', false).removeClass('updating');
				$scanSpinner.removeClass('is-active');

				if (response.success) {
					// Update plugins table body HTML
					if (response.data.table_html) {
						$pluginsTableBody.html(response.data.table_html);
					}
					// Update logs tab content
					if (response.data.logs_html) {
						$('#gsm-logs-table-wrapper').html(response.data.logs_html);
					}
					
					alert(response.data.message);
				} else {
					alert('Erro ao verificar: ' + response.data.message);
				}
			},
			error: function() {
				$scanBtn.prop('disabled', false).removeClass('updating');
				$scanSpinner.removeClass('is-active');
				alert('Falha na comunicação de rede durante o escaneamento.');
			}
		});
	});
});
