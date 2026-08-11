<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

final class ReleaseWorkflowTest extends TestCase {
	public function testDocumentationProjectsTheCanonicalCoreTagWithoutDuplicatingItsCommit(): void {
		$composer = json_decode( (string) file_get_contents( dirname( __DIR__ ) . '/composer.json' ), true, 512, JSON_THROW_ON_ERROR ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local release contract.
		$tuple    = $composer['extra']['ran-booster-core-certification'] ?? null;
		self::assertIsArray( $tuple );
		foreach ( array( 'README.md', 'RELEASE.md' ) as $documentName ) {
			$document = file_get_contents( dirname( __DIR__ ) . '/' . $documentName ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local release-contract document.
			self::assertIsString( $document );
			self::assertStringContainsString( (string) $tuple['tag'], $document );
			self::assertStringNotContainsString( (string) $tuple['commit'], $document );
			self::assertStringContainsString( 'Portability API 2', $document );
			self::assertStringContainsString( 'Admin Interaction API 2', $document );
			self::assertStringContainsString( 'installed', strtolower( $document ) );
		}
	}

	public function testArchiveCommandsBindMetadataAndRuntimeBytesToOneExplicitCommit(): void {
		$builder  = file_get_contents( dirname( __DIR__ ) . '/scripts/build-release.sh' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local release contract.
		$wrapper  = file_get_contents( dirname( __DIR__ ) . '/scripts/verify-release.sh' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local release contract.
		$verifier = file_get_contents( dirname( __DIR__ ) . '/scripts/verify-release.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local release contract.
		$quality  = file_get_contents( dirname( __DIR__ ) . '/.github/workflows/quality.yml' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local release contract.
		self::assertIsString( $builder );
		self::assertIsString( $wrapper );
		self::assertIsString( $verifier );
		self::assertIsString( $quality );
		self::assertStringContainsString( 'source_commit=${1:?full source commit is required}', $builder );
		self::assertStringContainsString( 'git show "${source_commit}:release-contents.txt"', $builder );
		self::assertStringContainsString( 'git archive', $builder );
		self::assertStringContainsString( 'exec php "$root/scripts/verify-release.php" "$@"', $wrapper );
		self::assertStringContainsString( '$sourceCommit = $argv[2] ??', $verifier );
		self::assertStringContainsString( "'commit'             => \$sourceCommit", $verifier );
		self::assertStringContainsString( 'git_output( array( \'show\', $commit . \':\' . $file ) )', $verifier );
		self::assertStringContainsString( 'bash scripts/build-release.sh "$source_commit"', $quality );
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

	public function testQualityBuildsOneArchiveAndReleaseReusesItsExactArtifact(): void {
		$quality = file_get_contents( dirname( __DIR__ ) . '/.github/workflows/quality.yml' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local workflow contract.
		$release = file_get_contents( dirname( __DIR__ ) . '/.github/workflows/release-please.yml' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local workflow contract.
		self::assertIsString( $quality );
		self::assertIsString( $release );

		self::assertSame( 1, substr_count( $quality, 'bash scripts/build-release.sh' ) );
		self::assertStringContainsString( 'actions/upload-artifact@', $quality );
		self::assertStringNotContainsString( 'bash scripts/build-release.sh', $release );
		self::assertStringContainsString( 'actions/download-artifact@', $release );
		self::assertStringContainsString( 'run-id: ${{ github.event.workflow_run.id }}', $release );
		self::assertStringNotContainsString( 'package-release:', $release );
	}

	public function testCertifiedCoreCheckoutFailsClosedWithoutThePrivateReadKey(): void {
		$quality = file_get_contents( dirname( __DIR__ ) . '/.github/workflows/quality.yml' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local workflow contract.
		self::assertIsString( $quality );
		self::assertStringContainsString( 'RAN_BOOSTER_CORE_READ_SSH_KEY: ${{ secrets.RAN_BOOSTER_CORE_READ_SSH_KEY }}', $quality );
		self::assertStringContainsString( 'RAN_BOOSTER_CORE_READ_SSH_KEY is required to check out the private RAN Booster Core repository.', $quality );
		self::assertStringContainsString( 'ssh-key: ${{ secrets.RAN_BOOSTER_CORE_READ_SSH_KEY }}', $quality );
	}

	public function testPackageReleaseIsBoundToSuccessfulQualityAndTheExactMergedPullRequest(): void {
		$workflow = file_get_contents( dirname( __DIR__ ) . '/.github/workflows/release-please.yml' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local workflow contract.
		self::assertIsString( $workflow );

		self::assertStringContainsString( 'skip-github-release: true', $workflow );
		self::assertStringContainsString( 'workflow_run:', $workflow );
		self::assertStringContainsString( "github.event.workflow_run.conclusion == 'success'", $workflow );
		self::assertStringContainsString( "github.event.workflow_run.event == 'push'", $workflow );
		self::assertStringContainsString( 'ref: ${{ github.event.workflow_run.head_sha }}', $workflow );
		self::assertStringContainsString( 'github.event.workflow_run.head_repository.full_name == github.repository', $workflow );
		self::assertStringContainsString( 'git diff --quiet "${RAN_QUALITY_COMMIT}^1"', $workflow );
		self::assertStringContainsString( 'gh api --paginate --slurp "repos/${GITHUB_REPOSITORY}/releases?per_page=100"', $workflow );
		self::assertStringContainsString( 'select(.tag_name == $tag)', $workflow );
		self::assertStringContainsString( 'git log -1 --format=%H "$RAN_QUALITY_COMMIT" -- .release-please-manifest.json', $workflow );
		self::assertStringContainsString( '.merge_commit_sha == $quality', $workflow );
		self::assertStringContainsString( '.head.ref == $head', $workflow );
		self::assertStringContainsString( '.user.login == $bot', $workflow );
		self::assertStringContainsString( 'test "$pending" = true', $workflow );
		self::assertStringContainsString( 'The published release is not immutable', $workflow );
		self::assertStringContainsString( '--target "$RAN_RELEASE_COMMIT"', $workflow );
	}

	public function testVerifiedDraftAssetsPrecedeImmutablePublication(): void {
		$workflow = file_get_contents( dirname( __DIR__ ) . '/.github/workflows/release-please.yml' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local workflow contract.
		self::assertIsString( $workflow );

		self::assertStringContainsString( 'gh release create "$RAN_RELEASE_TAG" --draft', $workflow );
		self::assertStringContainsString( 'gh release upload "$RAN_RELEASE_TAG"', $workflow );
		self::assertStringContainsString( 'gh release download "$RAN_RELEASE_TAG"', $workflow );
		self::assertStringContainsString( 'ran-booster-wp-pusher-migrator-${RAN_RELEASE_VERSION}.zip', $workflow );
		self::assertStringContainsString( 'ran-booster-wp-pusher-migrator-${RAN_RELEASE_VERSION}.zip.sha256', $workflow );
		self::assertStringContainsString( 'ran-booster-wp-pusher-migrator-${RAN_RELEASE_VERSION}.json', $workflow );
		self::assertSame( 3, substr_count( $workflow, 'cmp -s "dist/ran-booster-wp-pusher-migrator-' ) );
		self::assertStringContainsString( 'RAN_IMMUTABLE_RELEASES_ENABLED', $workflow );
		self::assertStringContainsString( 'The verified release remains a draft.', $workflow );
		self::assertStringContainsString( 'gh release edit "$RAN_RELEASE_TAG" --draft=false', $workflow );
		self::assertStringContainsString( "--jq '.immutable'", $workflow );
		self::assertStringContainsString( 'for delay in 0 2 2 2 2', $workflow );
	}
}
