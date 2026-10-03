<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use RAN\AddOn\Portability\PortabilityCandidate;
use RAN\AddOn\Portability\PortabilityReviewResult;
use RAN\Admin\Interaction\AdminInteractionFacade;
use RAN\Admin\Interaction\AdminInteractionOutcome;
use RAN\Admin\Interaction\AdminInteractionRequest;
use RAN\Admin\Interaction\TransporterRowAdminInteractionFacade;
use RAN\BoosterWpPusherMigrator\CandidateFactory;
use RAN\BoosterWpPusherMigrator\MigrationPresenter;
use RAN\BoosterWpPusherMigrator\WpPusherPackage;
use RuntimeException;

final class SourceCardViewTest extends TestCase {
	private ?SourceCardInteractionSpy $interaction = null;

	public function test_renders_escaped_accessible_no_javascript_review(): void {
		$source              = WpPusherPackage::from_row(
			array(
				'id'           => '1',
				'package'      => 'fixture/fixture.php',
				'repository'   => 'RocketsAreNostalgic/booster-fixture-plugin',
				'branch'       => 'main',
				'type'         => '1',
				'status'       => '1',
				'ptd'          => '0',
				'host'         => 'gh',
				'private'      => '1',
				'subdirectory' => null,
			)
		);
		$candidate           = new PortabilityCandidate(
			'plugin',
			$source->package,
			'Fixture',
			'github',
			$source->repository,
			'main'
		);
		$review              = new PortabilityReviewResult(
			$candidate,
			'blocked',
			'credential_required',
			'Use <existing> Booster credentials.',
			'v1:' . str_repeat( 'a', 64 )
		);
		$rows                = array( $this->row( $source, $candidate, $review ) );
		$error               = '';
		$has_error           = false;
		$legacy_data_present = true;
		$apply               = null;
		$apply_visible       = false;
		$apply_class         = 'notice-error';
		$apply_message       = '';
		$cleanup_pending     = false;
		$completion_visible  = false;
		$plugins_url         = 'https://example.test/wp-admin/plugins.php';
		$admin_interaction   = $this->interaction;

		ob_start();
		require dirname( __DIR__ ) . '/views/source-card.php';
		$output = (string) ob_get_clean();

		self::assertStringContainsString( '<th scope="col">', $output );
		self::assertStringContainsString( '<th scope="row">', $output );
		self::assertStringContainsString( 'class="ran-booster-wp-pusher-migrator__status-column"', $output );
		self::assertStringContainsString( 'id="ran-booster-portability-wp-pusher"', $output );
		self::assertStringContainsString( 'Move each package into Booster', $output );
		self::assertStringContainsString( 'Use &lt;existing&gt; Booster credentials.', $output );
		self::assertStringContainsString( 'name="credential_id"', $output );
		self::assertStringContainsString( ' required', $output );
		self::assertStringContainsString( 'WP Pusher settings found: Not all Pusher settings can be migrated.', $output );
		self::assertStringContainsString( '>Check</button>', $output );
		self::assertStringNotContainsString( '>Adopt</button>', $output );
		self::assertStringNotContainsString( 'temporary bridge reads retained', $output );
		self::assertStringContainsString( 'name="_wpnonce"', $output );
		self::assertStringContainsString(
			'action="https://example.test/wp-admin/admin.php?page=ran-booster&amp;tab=portability#ran-booster-portability-wp-pusher"',
			$output
		);
		self::assertStringNotContainsString( '<script', $output );
		self::assertStringNotContainsString( 'SECRET-CANARY', $output );
	}

	public function test_renders_mode_and_evidence_prompt_as_separate_controls(): void {
		ob_start();
		require dirname( __DIR__ ) . '/views/migration-mode.php';
		$mode = (string) ob_get_clean();

		$migration_url = 'https://example.test/wp-admin/admin.php?page=ran-booster&amp;tab=portability#ran-booster-portability-wp-pusher';
		ob_start();
		require dirname( __DIR__ ) . '/views/overview-prompt.php';
		$prompt = (string) ob_get_clean();

		self::assertStringContainsString( 'data-portability-mode="wp-pusher"', $mode );
		self::assertStringContainsString( 'aria-controls="ran-booster-portability-wp-pusher"', $mode );
		self::assertStringContainsString( 'Migrate from WP Pusher', $mode );
		self::assertStringContainsString( 'Booster found package records from WP Pusher.', $prompt );
		self::assertStringContainsString( 'Migrate to Booster', $prompt );
		self::assertStringContainsString( '#ran-booster-portability-wp-pusher', $prompt );
	}

	public function test_public_package_does_not_ask_for_credentials(): void {
		$output = $this->render_public_package();

		self::assertStringContainsString( '>Check</button>', $output );
		self::assertStringNotContainsString( 'name="credential_id"', $output );
	}

	public function test_actionable_checked_package_replaces_check_with_adopt(): void {
		$output = $this->render_public_package( 'adopt' );

		self::assertStringContainsString( '<strong>Ready to adopt</strong>', $output );
		self::assertStringContainsString( 'value="apply"', $output );
		self::assertStringContainsString( '>Adopt</button>', $output );
		self::assertStringNotContainsString( '>Check</button>', $output );
		self::assertStringNotContainsString( 'Move to Booster (deployments off)', $output );
	}

	public function test_managed_review_explains_the_remaining_cleanup_action(): void {
		$output = $this->render_public_package( 'managed' );

		self::assertStringContainsString( '<strong>Adoption incomplete</strong>', $output );
		self::assertStringContainsString( '<span>Booster manages this package; a WP Pusher record remains.</span>', $output );
		self::assertStringNotContainsString( '<strong>Checked</strong>', $output );
		self::assertStringContainsString( '>Finish</button>', $output );
		self::assertStringNotContainsString( '>Adopt</button>', $output );
	}

	public function test_blocked_review_keeps_its_reason_below_a_concise_heading(): void {
		$output = $this->render_public_package( 'blocked' );

		self::assertStringContainsString( '<strong>Cannot adopt</strong>', $output );
		self::assertStringContainsString( '<span>Repository access could not be verified.</span>', $output );
		self::assertStringContainsString( '>Check</button>', $output );
	}

	public function test_git_lab_package_renders_user_visible_unsupported_row_without_actions(): void {
		$source = WpPusherPackage::from_row(
			array(
				'id'           => '1',
				'package'      => 'fixture/fixture.php',
				'repository'   => 'fixture-group/gitlab-plugin',
				'branch'       => 'main',
				'type'         => '1',
				'status'       => '1',
				'ptd'          => '0',
				'host'         => 'gl',
				'private'      => '0',
				'subdirectory' => null,
			)
		);
		$row    = $this->row( $source, null, null );
		$output = $this->render_rows( array( $row ) );

		self::assertStringContainsString( '<strong>Cannot adopt</strong>', $output );
		self::assertStringContainsString( 'GitLab WP Pusher packages are not supported.', $output );
		self::assertStringNotContainsString( '>Check</button>', $output );
		self::assertStringNotContainsString( '>Adopt</button>', $output );
		self::assertStringNotContainsString( 'name="credential_id"', $output );
		self::assertSame( array(), $this->interaction?->rendered_operations );
	}

	public function test_enhanced_check_and_adopt_use_core_row_facade(): void {
		$interaction = new SourceCardInteractionSpy();
		$check       = $this->render_public_package( null, $interaction );

		self::assertStringContainsString( 'id="ran-booster-transporter-migration-source-', $check );
		self::assertStringContainsString( 'data-test-operation="wp-pusher:check-package"', $check );
		self::assertStringContainsString( 'name="action" value="ran_booster_wp_pusher_migrator_package"', $check );
		self::assertStringContainsString(
			'action="https://example.test/wp-admin/admin.php?page=ran-booster&amp;tab=portability#ran-booster-portability-wp-pusher"',
			$check
		);

		$import = $this->render_public_package( 'adopt', $interaction );

		self::assertStringContainsString( 'data-test-operation="wp-pusher:import-package"', $import );
		self::assertStringContainsString( '>Adopt</button>', $import );
		self::assertSame(
			array( 'wp-pusher:check-package', 'wp-pusher:import-package' ),
			$interaction->rendered_operations
		);
	}

	public function test_imported_package_replaces_actions_with_manage_settings_link(): void {
		$output = $this->render_public_package( null, null, true );

		self::assertStringContainsString( '<strong>Adopted</strong>', $output );
		self::assertStringContainsString( '<td class="ran-booster-wp-pusher-migrator__action-cell">', $output );
		self::assertStringContainsString( '>Settings</a>', $output );
		self::assertStringContainsString(
			'href="https://example.test/wp-admin/admin.php?page=ran-booster-plugins&amp;package=fixture%2Ffixture.php"',
			$output
		);
		self::assertStringNotContainsString( '>Check</button>', $output );
		self::assertStringNotContainsString( '>Adopt</button>', $output );
		self::assertSame( array(), $this->interaction?->rendered_operations );
	}

	public function test_final_imported_row_reveals_the_pending_completion_panel(): void {
		$output = $this->render_public_package( null, null, true, true );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local test fixture, not a remote request.
		$styles = (string) file_get_contents( dirname( __DIR__ ) . '/assets/wp-pusher-migrator.css' );

		self::assertStringContainsString( 'data-ran-booster-wp-pusher-migration-complete="true"', $output );
		self::assertStringContainsString( 'role="status" aria-live="polite">Package migration complete.</span>', $output );
		self::assertStringContainsString( 'id="ran-booster-wp-pusher-migration-complete"', $output );
		self::assertStringNotContainsString( 'completion-panel--visible', $output );
		self::assertLessThan(
			strpos( $output, 'id="ran-booster-wp-pusher-migration-complete"' ),
			strpos( $output, 'ran-booster-wp-pusher-migrator__table-scroll' )
		);
		self::assertStringContainsString(
			'tr[data-ran-booster-wp-pusher-migration-complete="true"]',
			$styles
		);
	}

	public function test_empty_inventory_renders_the_completion_panel_visible_without_a_table(): void {
		$output = $this->render_empty_inventory();

		self::assertStringContainsString(
			'class="ran-booster-wp-pusher-migrator__completion-panel ran-booster-wp-pusher-migrator__completion-panel--visible"',
			$output
		);
		self::assertStringContainsString( 'Migration complete!', $output );
		self::assertStringContainsString( 'No WP Pusher packages remain. You can now uninstall WP Pusher.', $output );
		self::assertStringContainsString(
			'class="notice notice-success inline ran-booster-wp-pusher-migrator__completion-advisory"',
			$output
		);
		self::assertStringContainsString( 'WP Pusher will remove its own settings and package table', $output );
		self::assertStringContainsString( 'href="https://example.test/wp-admin/plugins.php"', $output );
		self::assertStringContainsString( 'href="https://dashboard.wppusher.com/login"', $output );
		self::assertStringContainsString( 'check your repository provider separately', $output );
		self::assertStringNotContainsString( 'delete_options', $output );
		self::assertStringNotContainsString( 'drop_table', $output );
		self::assertStringNotContainsString( 'Remove unused settings', $output );
		self::assertStringNotContainsString( 'Remove empty package table', $output );
		self::assertStringNotContainsString( 'ran-booster-wp-pusher-migrator__packages', $output );
		self::assertStringNotContainsString(
			'WP Pusher settings found: Not all Pusher settings can be migrated.',
			$output
		);
	}

	private function render_public_package(
		?string $review_action = null,
		?SourceCardInteractionSpy $interaction = null,
		bool $imported = false,
		bool $migration_complete = false
	): string {
		$source    = WpPusherPackage::from_row(
			array(
				'id'           => '1',
				'package'      => 'fixture/fixture.php',
				'repository'   => 'RocketsAreNostalgic/booster-fixture-plugin',
				'branch'       => 'main',
				'type'         => '1',
				'status'       => '1',
				'ptd'          => '0',
				'host'         => 'gh',
				'private'      => '0',
				'subdirectory' => null,
			)
		);
		$candidate = new PortabilityCandidate(
			'plugin',
			$source->package,
			'Fixture',
			'github',
			$source->repository,
			'main'
		);
		$review    = null === $review_action
			? null
			: new PortabilityReviewResult(
				$candidate,
				$review_action,
				'ready',
				'blocked' === $review_action ? 'Repository access could not be verified.' : 'Ready to adopt.',
				'v1:' . str_repeat( 'a', 64 )
			);
		$row       = $this->row( $source, $candidate, $review, $interaction );
		if ( $imported ) {
			$row['migration_complete'] = $migration_complete;
			$row['status_heading']     = 'Adopted';
			$row['status_strong']      = true;
			$row['action']             = 'manage';
			$row['manage_url']         = 'https://example.test/wp-admin/admin.php?page=ran-booster-plugins&package=fixture%2Ffixture.php';
			$row['manage_label']       = 'Settings';
		}

		return $this->render_rows( array( $row ) );
	}

	private function render_empty_inventory(): string {
		return $this->render_rows( array(), array( 'gh_token' => true ) );
	}

	/**
	 * @param list<array<string, mixed>> $rows
	 * @param array<string, bool>        $option_presence
	 */
	private function render_rows( array $rows, array $option_presence = array() ): string {
		$error               = '';
		$has_error           = false;
		$legacy_data_present = in_array( true, $option_presence, true );
		$apply               = null;
		$apply_visible       = false;
		$apply_class         = 'notice-error';
		$apply_message       = '';
		$cleanup_pending     = false;
		$completion_visible  = array() === $rows;
		$plugins_url         = 'https://example.test/wp-admin/plugins.php';
		$admin_interaction   = $this->interaction ?? new SourceCardInteractionSpy();

		ob_start();
		require dirname( __DIR__ ) . '/views/source-card.php';

		return (string) ob_get_clean();
	}

	/**
	 * @return array<string, mixed>
	 */
	private function row(
		WpPusherPackage $source,
		?PortabilityCandidate $candidate,
		?PortabilityReviewResult $review,
		?SourceCardInteractionSpy $interaction = null
	): array {
		unset( $candidate );
		$interaction     ??= new SourceCardInteractionSpy();
		$this->interaction = $interaction;
		$presenter         = new MigrationPresenter(
			new CandidateFactory(
				static fn (): array => array( 'fixture/fixture.php' => array( 'Name' => 'Fixture Plugin' ) )
			),
			$interaction
		);

		return $presenter->row( $source, $review );
	}
}

final class SourceCardInteractionSpy implements AdminInteractionFacade, TransporterRowAdminInteractionFacade {

	/** @var list<string> */
	public array $rendered_operations = array();

	public function render_form_attributes( AdminInteractionRequest $request ): void {
		$this->rendered_operations[] = $request->operation();
		echo ' data-test-operation="' . esc_attr( $request->operation() ) . '"';
	}

	public function is_enhanced_request( AdminInteractionRequest $request ): bool {
		unset( $request );

		return true;
	}

	public function respond( AdminInteractionOutcome $outcome ): never {
		unset( $outcome );
		throw new RuntimeException( 'Response terminated.' );
	}

	public function respond_with_transporter_row_fragment(
		AdminInteractionOutcome $outcome,
		callable $render_fragment
	): never {
		unset( $outcome, $render_fragment );
		throw new RuntimeException( 'Response terminated.' );
	}
}
