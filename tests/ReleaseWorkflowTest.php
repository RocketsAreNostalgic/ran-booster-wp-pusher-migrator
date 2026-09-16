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
		self::assertStringContainsString( 'RAN_DISPATCH_RELEASE_SHA: ${{ inputs.release_sha }}', $quality );
		self::assertStringContainsString( 'test "$head_sha" = "$GITHUB_SHA"', $quality );
		self::assertStringContainsString( 'source_commit="$head_sha"', $quality );
		self::assertStringContainsString( 'git rev-parse "${source_commit}^{commit}"', $quality );
		self::assertStringContainsString( 'bash scripts/validate-release-candidate.sh "$base_sha" "$head_sha"', $quality );
		self::assertStringContainsString( 'bash scripts/fetch-release-candidate-ref.sh origin "refs/pull/${RAN_DISPATCH_RELEASE_PR}/head"', $quality );
		self::assertStringContainsString( 'bash scripts/build-release.sh "$source_commit"', $quality );
	}

	public function testQualityRequiresExactTrustedDispatchAndKeepsDirectPullRequestChecks(): void {
		$quality = file_get_contents( dirname( __DIR__ ) . '/.github/workflows/quality.yml' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local workflow contract.
		self::assertIsString( $quality );

		self::assertStringContainsString( 'workflow_dispatch:', $quality );
		self::assertStringContainsString( 'release_pr:', $quality );
		self::assertStringContainsString( 'release_sha:', $quality );
		self::assertSame( 2, substr_count( $quality, 'required: true' ) );
		self::assertStringNotContainsString( "name: Runtime archive\n    if:", $quality );
		self::assertStringContainsString( "GH_TOKEN: \${{ github.event_name == 'workflow_dispatch' && secrets.GITHUB_TOKEN || '' }}", $quality );
		self::assertStringContainsString( '"$GITHUB_ACTOR" == \'github-actions[bot]\'', $quality );
		self::assertStringContainsString( '"$GITHUB_TRIGGERING_ACTOR" == \'github-actions[bot]\'', $quality );
		self::assertStringContainsString( '.state == "open"', $quality );
		self::assertStringContainsString( '.head.repo.full_name == $repository', $quality );
		self::assertStringContainsString( 'any(.labels[]?; .name == "autorelease: pending")', $quality );
		self::assertStringNotContainsString( 'elif [[ "$GITHUB_EVENT_NAME" == workflow_dispatch ]]', $quality );
	}

	public function testPullRequestQualityFanInBindsEveryLaneToExactHead(): void {
		$quality = file_get_contents( dirname( __DIR__ ) . '/.github/workflows/quality.yml' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local workflow contract.
		self::assertIsString( $quality );

		$pullRequestHead = '${{ github.event_name == \'pull_request\' && github.event.pull_request.head.sha || github.sha }}';
		self::assertStringContainsString( 'ref: ' . $pullRequestHead, $quality );
		self::assertStringContainsString( 'RAN_SOURCE_SHA: ' . $pullRequestHead, $quality );
		self::assertStringContainsString( 'source_commit="$RAN_SOURCE_SHA"', $quality );
		self::assertStringContainsString( 'test "$(git rev-parse HEAD)" = "$source_commit"', $quality );

		$terminalStart = strpos( $quality, "  terminal-quality:\n" );
		self::assertIsInt( $terminalStart );
		$terminal = substr( $quality, $terminalStart );
		self::assertStringContainsString( "    name: quality\n", $terminal );
		self::assertStringContainsString( "      - baseline\n", $terminal );
		self::assertStringContainsString( "      - runtime-archive\n", $terminal );
		self::assertStringContainsString( "      - core-contract\n", $terminal );
		self::assertStringContainsString( "      - quality\n", $terminal );
		self::assertStringContainsString( 'test "$RAN_BASELINE_RESULT" = success', $terminal );
		self::assertStringContainsString( 'test "$RAN_RUNTIME_ARCHIVE_RESULT" = success', $terminal );
		self::assertStringContainsString( 'test "$RAN_CORE_CONTRACT_RESULT" = success', $terminal );
		self::assertStringContainsString( 'test "$RAN_REPOSITORY_QUALITY_RESULT" = success', $terminal );
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

	public function testCertifiedCoreCheckoutUsesPinnedPublicSourceWithoutThePrivateReadKey(): void {
		$quality = file_get_contents( dirname( __DIR__ ) . '/.github/workflows/quality.yml' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local workflow contract.
		self::assertIsString( $quality );
		self::assertStringNotContainsString( 'RAN_BOOSTER_CORE_READ_SSH_KEY', $quality );
		self::assertStringNotContainsString( 'ssh-key:', $quality );
		self::assertStringContainsString(
			"repository: RocketsAreNostalgic/ran-booster\n"
			. "          ref: \${{ needs.runtime-archive.outputs.core-commit }}\n"
			. "          fetch-depth: 0\n"
			. "          path: core\n"
			. '          persist-credentials: false',
			$quality
		);
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

	public function testReleaseDispatchesOnlyAnExactVerifiedCandidateAndSuppressesDuplicates(): void {
		$workflow = file_get_contents( dirname( __DIR__ ) . '/.github/workflows/release-please.yml' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local workflow contract.
		self::assertIsString( $workflow );

		self::assertStringContainsString( 'actions: write', $workflow );
		self::assertStringNotContainsString( 'id: release-please', $workflow );
		self::assertStringContainsString( 'current_main="$(gh api "repos/${GITHUB_REPOSITORY}/git/ref/heads/main"', $workflow );
		self::assertStringContainsString( 'skipping stale reconciliation', $workflow );
		self::assertStringContainsString( "if: steps.release-state.outputs.release-please-required == 'true'", $workflow );
		self::assertStringContainsString( 'test "$base_sha" = "$RAN_QUALITY_COMMIT"', $workflow );
		self::assertStringContainsString( 'bash scripts/validate-release-candidate.sh "$base_sha" "$head_sha"', $workflow );
		self::assertStringContainsString( 'bash scripts/fetch-release-candidate-ref.sh origin "refs/pull/${pr_number}/head"', $workflow );
		self::assertStringNotContainsString( 'git config', $workflow );
		self::assertStringContainsString( 'bash scripts/validate-release-candidate-identity.sh "$base_sha" "$head_sha"', $workflow );
		self::assertStringContainsString( 'commits(last: 1)', $workflow );
		self::assertStringContainsString( 'signature {', $workflow );
		self::assertStringContainsString( 'bash scripts/has-trusted-release-candidate-run.sh', $workflow );
		self::assertStringContainsString( 'gh workflow run quality.yml --ref "$head_ref"', $workflow );
		self::assertStringContainsString( '-f "release_pr=${pr_number}"', $workflow );
		self::assertStringContainsString( '-f "release_sha=${head_sha}"', $workflow );
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

	public function testPublicDocumentationKeepsAcquisitionSupportAndSecurityTruthful(): void {
		$root         = dirname( __DIR__ );
		$composer     = json_decode( (string) file_get_contents( $root . '/composer.json' ), true, 512, JSON_THROW_ON_ERROR ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local public-metadata contract.
		$entrypoint   = file_get_contents( $root . '/ran-booster-wp-pusher-migrator.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local public-metadata contract.
		$readme       = file_get_contents( $root . '/README.md' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local documentation contract.
		$security     = file_get_contents( $root . '/SECURITY.md' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local documentation contract.
		$support      = file_get_contents( $root . '/SUPPORT.md' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local documentation contract.
		$contributing = file_get_contents( $root . '/CONTRIBUTING.md' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local documentation contract.
		$suitability  = file_get_contents( $root . '/docs/wordpress-org-suitability.md' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local documentation contract.

		self::assertIsString( $entrypoint );
		self::assertStringContainsString( 'Author URI: https://github.com/RocketsAreNostalgic', $entrypoint );
		self::assertStringContainsString( 'License URI: https://www.gnu.org/licenses/gpl-2.0.html', $entrypoint );
		self::assertEquals(
			array(
				'docs'   => 'https://github.com/RocketsAreNostalgic/ran-booster-wp-pusher-migrator#readme',
				'issues' => 'https://github.com/RocketsAreNostalgic/ran-booster-wp-pusher-migrator/issues',
				'source' => 'https://github.com/RocketsAreNostalgic/ran-booster-wp-pusher-migrator',
			),
			$composer['support'] ?? null
		);

		foreach ( array( $readme, $security, $support, $contributing, $suitability ) as $document ) {
			self::assertIsString( $document );
			self::assertStringNotContainsString( '.ran-booster-workbench', $document );
			self::assertStringNotContainsString( '/private/tmp', $document );
		}

		self::assertStringContainsString( '/releases', $readme );
		self::assertStringContainsString( '.zip.sha256', $readme );
		self::assertStringContainsString( 'does not register an automatic update provider', $readme );
		self::assertStringContainsString( 'Beta release is supported', $security );
		self::assertStringContainsString( '/security/advisories/new', $security );
		self::assertStringContainsString( '/issues', $support );
		self::assertStringContainsString( 'no response or resolution time is guaranteed', $support );
		self::assertStringContainsString( 'Conventional Commit', $contributing );
		self::assertStringContainsString( 'Requires Plugins: ran-booster', $suitability );
		self::assertStringContainsString( 'do not submit', $suitability );
	}
}
