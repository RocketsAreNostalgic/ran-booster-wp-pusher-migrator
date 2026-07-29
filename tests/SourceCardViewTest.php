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
		$formAction      = 'bridge-review';
		$applyFormAction = 'bridge-apply';
		$apply           = null;

		ob_start();
		require dirname( __DIR__ ) . '/views/source-card.php';
		$output = (string) ob_get_clean();

		self::assertStringContainsString( '<th scope="col">', $output );
		self::assertStringContainsString( '<th scope="row">', $output );
		self::assertStringContainsString( 'Use &lt;existing&gt; Booster credentials.', $output );
		self::assertStringContainsString( 'name="credential_id"', $output );
		self::assertStringContainsString( ' required', $output );
		self::assertStringContainsString( 'name="_wpnonce"', $output );
		self::assertStringNotContainsString( '<script', $output );
		self::assertStringNotContainsString( 'SECRET-CANARY', $output );
	}
}
