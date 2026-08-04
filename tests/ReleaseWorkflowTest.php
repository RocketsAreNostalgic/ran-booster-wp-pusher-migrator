<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

final class ReleaseWorkflowTest extends TestCase {
	private const TESTED_CORE_TAG = 'v1.0.0-beta.5';

	private const TESTED_CORE_COMMIT = 'c992d612a827bef2bc6dea6993e25045087b6d52';

	public function testDocumentationRecordsTheExactTestedCoreReleaseWithoutClaimingARuntimePin(): void {
		foreach ( array( 'README.md', 'RELEASE.md' ) as $documentName ) {
			$document = file_get_contents( dirname( __DIR__ ) . '/' . $documentName ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local release-contract document.
			self::assertIsString( $document );
			self::assertStringContainsString( self::TESTED_CORE_TAG, $document );
			self::assertStringContainsString( self::TESTED_CORE_COMMIT, $document );
			self::assertStringContainsString( 'Portability API 2', $document );
			self::assertStringContainsString( 'Admin Interaction API 2', $document );
		}

		$readme = file_get_contents( dirname( __DIR__ ) . '/README.md' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local release-contract document.
		self::assertIsString( $readme );
		self::assertStringContainsString( 'the bridge remains coupled to those public API generations', $readme );
	}

	public function testRepositoryWorkflowsPinEveryActionToAnExactCommit(): void {
		foreach ( array( 'quality.yml', 'release-please.yml' ) as $workflowName ) {
			$workflow = file_get_contents( dirname( __DIR__ ) . '/.github/workflows/' . $workflowName ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local workflow contract.
			self::assertIsString( $workflow );

			$matchCount = preg_match_all( '/^[[:space:]]+(?:- )?uses: ([^[:space:]#]+)/m', $workflow, $matches );
			self::assertIsInt( $matchCount );
			self::assertGreaterThan( 0, $matchCount );
			foreach ( $matches[1] as $action ) {
				self::assertMatchesRegularExpression(
					'/\A[^@]+@[0-9a-f]{40}\z/',
					$action,
					$workflowName . ' must not use mutable action reference ' . $action
				);
			}
		}

		$releaseWorkflow = file_get_contents( dirname( __DIR__ ) . '/.github/workflows/release-please.yml' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local workflow contract.
		self::assertIsString( $releaseWorkflow );
		self::assertStringContainsString(
			'googleapis/release-please-action@45996ed1f6d02564a971a2fa1b5860e934307cf7',
			$releaseWorkflow
		);
	}

	public function testPackageReleaseIsBoundToTheManifestChangingCommit(): void {
		$workflow = file_get_contents( dirname( __DIR__ ) . '/.github/workflows/release-please.yml' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local workflow contract.
		self::assertIsString( $workflow );

		self::assertStringContainsString( 'skip-github-release: true', $workflow );
		self::assertStringContainsString( 'git diff --quiet HEAD^ HEAD -- .release-please-manifest.json && manifest_changed=false', $workflow );
		self::assertStringContainsString( 'gh api --paginate --slurp "repos/${GITHUB_REPOSITORY}/releases?per_page=100"', $workflow );
		self::assertStringContainsString( 'select(.tag_name == $tag)', $workflow );
		self::assertStringContainsString( '"$manifest_changed" == false', $workflow );
		self::assertStringContainsString( 'git log -1 --format=%H -- .release-please-manifest.json', $workflow );
		self::assertStringContainsString( "'.target_commitish'", $workflow );
		self::assertStringContainsString( 'The published release is not immutable', $workflow );
		self::assertStringContainsString( 'git checkout --detach "${RAN_RELEASE_COMMIT}"', $workflow );
		self::assertStringContainsString( '--target "${RAN_RELEASE_COMMIT}"', $workflow );
		self::assertStringNotContainsString( "needs.release-please.outputs.release_created == 'true'", $workflow );
	}

	public function testVerifiedDraftAssetsPrecedeImmutablePublication(): void {
		$workflow = file_get_contents( dirname( __DIR__ ) . '/.github/workflows/release-please.yml' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local workflow contract.
		self::assertIsString( $workflow );

		self::assertStringContainsString( 'gh release create "${RAN_RELEASE_TAG}" --draft', $workflow );
		self::assertStringContainsString( 'gh release upload "${RAN_RELEASE_TAG}"', $workflow );
		self::assertStringContainsString( 'gh release download "${RAN_RELEASE_TAG}"', $workflow );
		self::assertStringContainsString( 'ran-booster-wp-pusher-migrator-${RAN_RELEASE_VERSION}.zip', $workflow );
		self::assertStringContainsString( 'ran-booster-wp-pusher-migrator-${RAN_RELEASE_VERSION}.zip.sha256', $workflow );
		self::assertStringContainsString( 'ran-booster-wp-pusher-migrator-${RAN_RELEASE_VERSION}.json', $workflow );
		self::assertSame( 3, substr_count( $workflow, 'cmp -s "dist/ran-booster-wp-pusher-migrator-' ) );
		self::assertStringContainsString( 'RAN_IMMUTABLE_RELEASES_ENABLED', $workflow );
		self::assertStringContainsString( 'The verified release remains a draft.', $workflow );
		self::assertStringContainsString( 'gh release edit "${RAN_RELEASE_TAG}" --draft=false', $workflow );
		self::assertStringContainsString( "--jq '.immutable'", $workflow );
		self::assertStringContainsString( 'for delay in 0 2 2 2 2', $workflow );
	}
}
