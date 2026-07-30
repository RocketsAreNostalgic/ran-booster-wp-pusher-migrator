<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use RAN\AddOn\Portability\PortabilityCandidate;
use RAN\AddOn\Portability\PortabilityReviewResult;
use RAN\BoosterWpPusherMigrator\WpPusherPackage;

final class SourceCardViewTest extends TestCase {

	public function testRendersEscapedAccessibleNoJavascriptReview(): void {
		$source          = WpPusherPackage::fromRow(
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
		$candidate       = new PortabilityCandidate(
			'plugin',
			$source->package,
			'Fixture',
			'github',
			$source->repository,
			'main'
		);
		$review          = new PortabilityReviewResult(
			$candidate,
			'blocked',
			'credential_required',
			'Use <existing> Booster credentials.',
			'v1:' . str_repeat( 'a', 64 )
		);
		$rows            = array(
			array(
				'source'    => $source,
				'candidate' => $candidate,
				'error'     => '',
				'review'    => $review,
			),
		);
		$optionPresence  = array( 'gh_token' => true );
		$error           = '';
		$formAction      = 'bridge-review';
		$applyFormAction = 'bridge-apply';
		$apply           = null;
		$cleanup         = null;
		$tablePresent    = true;
		$optionsAction   = 'bridge-options';
		$tableAction     = 'bridge-table';

		ob_start();
		require dirname( __DIR__ ) . '/views/source-card.php';
		$output = (string) ob_get_clean();

		self::assertStringContainsString( '<th scope="col">', $output );
		self::assertStringContainsString( '<th scope="row">', $output );
		self::assertStringContainsString( 'id="ran-booster-portability-wp-pusher"', $output );
		self::assertStringContainsString( 'Move each package into Booster', $output );
		self::assertStringContainsString( 'Use &lt;existing&gt; Booster credentials.', $output );
		self::assertStringContainsString( 'name="credential_id"', $output );
		self::assertStringContainsString( ' required', $output );
		self::assertStringContainsString( 'Saved WP Pusher settings were found.', $output );
		self::assertStringNotContainsString( 'temporary bridge reads retained', $output );
		self::assertStringContainsString( 'name="_wpnonce"', $output );
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
		$source          = WpPusherPackage::fromRow(
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
		$candidate       = new PortabilityCandidate(
			'plugin',
			$source->package,
			'Fixture',
			'github',
			$source->repository,
			'main'
		);
		$rows            = array(
			array(
				'source'    => $source,
				'candidate' => $candidate,
				'error'     => '',
				'review'    => null,
			),
		);
		$error           = '';
		$optionPresence  = array();
		$formAction      = 'bridge-review';
		$applyFormAction = 'bridge-apply';
		$apply           = null;
		$cleanup         = null;
		$tablePresent    = true;
		$optionsAction   = 'bridge-options';
		$tableAction     = 'bridge-table';

		ob_start();
		require dirname( __DIR__ ) . '/views/source-card.php';
		$output = (string) ob_get_clean();

		self::assertStringContainsString( 'Check package', $output );
		self::assertStringNotContainsString( 'name="credential_id"', $output );
	}
}
