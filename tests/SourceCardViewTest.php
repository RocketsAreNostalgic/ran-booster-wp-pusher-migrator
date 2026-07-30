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
use RAN\BoosterWpPusherMigrator\WpPusherPackage;
use RuntimeException;

final class SourceCardViewTest extends TestCase {

	public function testRendersEscapedAccessibleNoJavascriptReview(): void {
		$source           = WpPusherPackage::fromRow(
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
		$candidate        = new PortabilityCandidate(
			'plugin',
			$source->package,
			'Fixture',
			'github',
			$source->repository,
			'main'
		);
		$review           = new PortabilityReviewResult(
			$candidate,
			'blocked',
			'credential_required',
			'Use <existing> Booster credentials.',
			'v1:' . str_repeat( 'a', 64 )
		);
		$rows             = array( $this->row( $source, $candidate, $review ) );
		$optionPresence   = array( 'gh_token' => true );
		$error            = '';
		$formAction       = 'bridge-review';
		$applyFormAction  = 'bridge-apply';
		$apply            = null;
		$cleanup          = null;
		$tablePresent     = true;
		$optionsAction    = 'bridge-options';
		$tableAction      = 'bridge-table';
		$migrationUrl     = 'https://example.test/wp-admin/admin.php?page=ran-booster&tab=portability#ran-booster-portability-wp-pusher';
		$adminPostAction  = 'bridge-package';
		$adminInteraction = null;

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
		self::assertStringContainsString( 'Saved WP Pusher settings were found.', $output );
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

	public function testRendersModeAndEvidencePromptAsSeparateControls(): void {
		ob_start();
		require dirname( __DIR__ ) . '/views/migration-mode.php';
		$mode = (string) ob_get_clean();

		$migrationUrl = 'https://example.test/wp-admin/admin.php?page=ran-booster&amp;tab=portability#ran-booster-portability-wp-pusher';
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

	public function testPublicPackageDoesNotAskForCredentials(): void {
		$output = $this->renderPublicPackage();

		self::assertStringContainsString( '>Check</button>', $output );
		self::assertStringNotContainsString( 'name="credential_id"', $output );
	}

	public function testActionableCheckedPackageReplacesCheckWithAdopt(): void {
		$output = $this->renderPublicPackage( 'adopt' );

		self::assertStringContainsString( '<strong> Ready to adopt </strong>', preg_replace( '/\\s+/', ' ', $output ) ?? '' );
		self::assertStringContainsString( 'value="apply"', $output );
		self::assertStringContainsString( '>Adopt</button>', $output );
		self::assertStringNotContainsString( '>Check</button>', $output );
		self::assertStringNotContainsString( 'Move to Booster (deployments off)', $output );
	}

	public function testManagedReviewExplainsTheRemainingCleanupAction(): void {
		$output = $this->renderPublicPackage( 'managed' );

		self::assertStringContainsString( '<strong> Ready to finish </strong>', preg_replace( '/\\s+/', ' ', $output ) ?? '' );
		self::assertStringNotContainsString( '<strong>Checked</strong>', $output );
		self::assertStringContainsString( '>Finish</button>', $output );
		self::assertStringNotContainsString( '>Adopt</button>', $output );
	}

	public function testBlockedReviewKeepsItsReasonBelowAConciseHeading(): void {
		$output = $this->renderPublicPackage( 'blocked' );

		self::assertStringContainsString( '<strong>Cannot adopt</strong>', $output );
		self::assertStringContainsString( '<span>Repository access could not be verified.</span>', $output );
		self::assertStringContainsString( '>Check</button>', $output );
	}

	public function testEnhancedCheckAndAdoptUseCoreRowFacade(): void {
		$interaction = new SourceCardInteractionSpy();
		$check       = $this->renderPublicPackage( null, $interaction );

		self::assertStringContainsString( 'id="ran-booster-transporter-migration-source-', $check );
		self::assertStringContainsString( 'data-test-operation="wp-pusher:check-package"', $check );
		self::assertStringContainsString( 'name="action" value="bridge-package"', $check );
		self::assertStringContainsString(
			'action="https://example.test/wp-admin/admin.php?page=ran-booster&amp;tab=portability#ran-booster-portability-wp-pusher"',
			$check
		);

		$import = $this->renderPublicPackage( 'adopt', $interaction );

		self::assertStringContainsString( 'data-test-operation="wp-pusher:import-package"', $import );
		self::assertStringContainsString( '>Adopt</button>', $import );
		self::assertSame(
			array( 'wp-pusher:check-package', 'wp-pusher:import-package' ),
			$interaction->renderedOperations
		);
	}

	public function testImportedPackageReplacesActionsWithManageSettingsLink(): void {
		$output = $this->renderPublicPackage( null, null, true );

		self::assertStringContainsString( '<strong>Adopted by Booster</strong>', $output );
		self::assertStringContainsString( '<td class="ran-booster-wp-pusher-migrator__action-cell">', $output );
		self::assertStringContainsString( '>Settings</a>', $output );
		self::assertStringContainsString(
			'href="https://example.test/wp-admin/admin.php?page=ran-booster-plugins&amp;package=fixture%2Ffixture.php"',
			$output
		);
		self::assertStringNotContainsString( '>Check</button>', $output );
		self::assertStringNotContainsString( '>Adopt</button>', $output );
	}

	private function renderPublicPackage(
		?string $reviewAction = null,
		?TransporterRowAdminInteractionFacade $interaction = null,
		bool $imported = false
	): string {
		$source    = WpPusherPackage::fromRow(
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
		$review    = null === $reviewAction
			? null
			: new PortabilityReviewResult(
				$candidate,
				$reviewAction,
				'ready',
				'blocked' === $reviewAction ? 'Repository access could not be verified.' : 'Ready to adopt.',
				'v1:' . str_repeat( 'a', 64 )
			);
		$row       = $this->row( $source, $candidate, $review, $interaction );
		if ( $imported ) {
			$row['imported']     = true;
			$row['status_label'] = 'Adopted by Booster';
			$row['manage_url']   = 'https://example.test/wp-admin/admin.php?page=ran-booster-plugins&package=fixture%2Ffixture.php';
			$row['manage_label'] = 'Settings';
		}
		$rows             = array( $row );
		$error            = '';
		$optionPresence   = array();
		$formAction       = 'bridge-review';
		$applyFormAction  = 'bridge-apply';
		$apply            = null;
		$cleanup          = null;
		$tablePresent     = true;
		$optionsAction    = 'bridge-options';
		$tableAction      = 'bridge-table';
		$migrationUrl     = 'https://example.test/wp-admin/admin.php?page=ran-booster&tab=portability#ran-booster-portability-wp-pusher';
		$adminPostAction  = 'bridge-package';
		$adminInteraction = $interaction;

		ob_start();
		require dirname( __DIR__ ) . '/views/source-card.php';

		return (string) ob_get_clean();
	}

	/**
	 * @return array<string, mixed>
	 */
	private function row(
		WpPusherPackage $source,
		PortabilityCandidate $candidate,
		?PortabilityReviewResult $review,
		?TransporterRowAdminInteractionFacade $interaction = null
	): array {
		$migrationUrl = 'https://example.test/wp-admin/admin.php?page=ran-booster&tab=portability#ran-booster-portability-wp-pusher';
		$rowNamespace = 'wp-pusher:package-' . substr( hash( 'sha256', $source->type . ':' . $source->package ), 0, 40 );
		$errorRegion  = 'ran-booster-wp-pusher-row-error-' . substr( hash( 'sha256', $source->type . ':' . $source->package ), 0, 32 );

		return array(
			'source'          => $source,
			'candidate'       => $candidate,
			'error'           => '',
			'review'          => $review,
			'imported'        => false,
			'status_label'    => '',
			'manage_url'      => '',
			'manage_label'    => '',
			'check_request'   => null === $interaction
				? null
				: AdminInteractionRequest::transporterMigrationSourceRow(
					'wp-pusher:check-package',
					$rowNamespace,
					$migrationUrl,
					$errorRegion
				),
			'import_request'  => null === $interaction
				? null
				: AdminInteractionRequest::transporterMigrationSourceRow(
					'wp-pusher:import-package',
					$rowNamespace,
					$migrationUrl,
					$errorRegion
				),
			'error_region_id' => $errorRegion,
		);
	}
}

final class SourceCardInteractionSpy implements AdminInteractionFacade, TransporterRowAdminInteractionFacade {

	/** @var list<string> */
	public array $renderedOperations = array();

	public function renderFormAttributes( AdminInteractionRequest $request ): void {
		$this->renderedOperations[] = $request->operation();
		echo ' data-test-operation="' . esc_attr( $request->operation() ) . '"';
	}

	public function isEnhancedRequest( AdminInteractionRequest $request ): bool {
		unset( $request );

		return true;
	}

	public function respond( AdminInteractionOutcome $outcome ): never {
		unset( $outcome );
		throw new RuntimeException( 'Response terminated.' );
	}

	public function respondWithTransporterRowFragment(
		AdminInteractionOutcome $outcome,
		callable $renderFragment
	): never {
		unset( $outcome, $renderFragment );
		throw new RuntimeException( 'Response terminated.' );
	}
}
