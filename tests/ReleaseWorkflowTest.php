<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

final class ReleaseWorkflowTest extends TestCase {
	public function test_documentation_projects_the_canonical_core_tag_without_duplicating_its_commit(): void {
		$composer = json_decode( (string) file_get_contents( dirname( __DIR__ ) . '/composer.json' ), true, 512, JSON_THROW_ON_ERROR ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local release contract.
		$tuple    = $composer['extra']['ran-booster-core-certification'] ?? null;
		self::assertIsArray( $tuple );
		foreach ( array( 'README.md', 'RELEASE.md' ) as $document_name ) {
			$document = file_get_contents( dirname( __DIR__ ) . '/' . $document_name ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local release-contract document.
			self::assertIsString( $document );
			self::assertStringContainsString( (string) $tuple['tag'], $document );
			self::assertStringNotContainsString( (string) $tuple['commit'], $document );
			self::assertStringContainsString( 'README.md' === $document_name ? 'Portability API 3' : 'Portability API 2', $document );
			self::assertStringContainsString( 'README.md' === $document_name ? 'Admin Interaction API 3' : 'Admin Interaction API 2', $document );
			self::assertStringContainsString( 'installed', strtolower( $document ) );
		}
	}

	public function test_archive_commands_bind_metadata_and_runtime_bytes_to_one_explicit_commit(): void {
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
		self::assertStringContainsString( '$source_commit = $argv[2] ??', $verifier );
		self::assertStringContainsString( "'commit'             => \$source_commit", $verifier );
		self::assertStringContainsString( 'bash scripts/build-release.sh "$source_commit"', $quality );
	}

	public function test_quality_uses_exact_event_revision_and_inputless_dispatch(): void {
		$quality = file_get_contents( dirname( __DIR__ ) . '/.github/workflows/quality.yml' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local workflow contract.
		self::assertIsString( $quality );

		self::assertStringContainsString( "  workflow_dispatch:\n  pull_request:", $quality );
		self::assertStringNotContainsString( 'release_pr:', $quality );
		self::assertStringNotContainsString( 'release_sha:', $quality );
		self::assertStringContainsString( 'base_commit="$(bash scripts/release-candidate-base.sh)"', $quality );
		self::assertStringContainsString( 'pull-requests: read', $quality );
		self::assertStringNotContainsString( '${source_commit}^1', $quality );
		self::assertStringNotContainsString( 'autorelease: pending', $quality );
		self::assertStringNotContainsString( 'fetch-release-candidate-ref.sh', $quality );
		self::assertStringContainsString( 'bash scripts/validate-release-candidate.sh "$base_commit" "$source_commit"', $quality );
		self::assertStringNotContainsString( 'has-trusted-release-candidate-run.sh', $quality );

		$exact_revision = '${{ github.event_name == \'pull_request\' && github.event.pull_request.head.sha || github.sha }}';
		self::assertStringContainsString( 'ref: ' . $exact_revision, $quality );
		self::assertStringContainsString( 'RAN_SOURCE_SHA: ' . $exact_revision, $quality );
		self::assertStringContainsString( 'test "$(git rev-parse HEAD)" = "$source_commit"', $quality );
		self::assertStringContainsString( 'quality_commit: $quality_commit', $quality );
		self::assertStringContainsString( 'source_commit: $source_commit', $quality );
		self::assertSame( 1, substr_count( $quality, 'bash scripts/build-release.sh' ) );
	}

	public function test_quality_emits_the_standard_profile_b_promotion_manifest(): void {
		$quality = file_get_contents( dirname( __DIR__ ) . '/.github/workflows/quality.yml' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local workflow contract.
		self::assertIsString( $quality );

		self::assertStringContainsString( 'schema: "ran-profile-b-promotion"', $quality );
		self::assertStringContainsString( 'schema_version: 1', $quality );
		self::assertStringContainsString( 'repository: $repository', $quality );
		self::assertStringContainsString( 'tag: $tag', $quality );
		self::assertStringContainsString( '{name: $archive_name, sha256: $archive_sha256}', $quality );
		self::assertStringContainsString( '{name: $checksum_name, sha256: $checksum_sha256}', $quality );
		self::assertStringContainsString( 'dist/ran-profile-b-promotion.json', $quality );
		self::assertStringContainsString( 'actions/upload-artifact@', $quality );
		self::assertStringContainsString( 'ran-booster-wp-pusher-migrator-runtime-${GITHUB_RUN_ID}-${GITHUB_RUN_ATTEMPT}', $quality );
	}

	public function test_pull_request_and_dispatched_quality_require_the_local_product_lanes(): void {
		$quality = file_get_contents( dirname( __DIR__ ) . '/.github/workflows/quality.yml' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local workflow contract.
		self::assertIsString( $quality );

		$terminal_start = strpos( $quality, "  terminal-quality:\n" );
		self::assertIsInt( $terminal_start );
		$terminal = substr( $quality, $terminal_start );
		self::assertStringContainsString( "    name: quality\n", $terminal );
		self::assertStringContainsString( "github.event_name == 'pull_request' || github.event_name == 'workflow_dispatch'", $terminal );
		self::assertStringContainsString( "      - baseline\n", $terminal );
		self::assertStringContainsString( "      - runtime-archive\n", $terminal );
		self::assertStringContainsString( "      - core-contract\n", $terminal );
		self::assertStringContainsString( "      - quality\n", $terminal );
		self::assertStringContainsString( 'test "$RAN_BASELINE_RESULT" = success', $terminal );
		self::assertStringContainsString( 'test "$RAN_RUNTIME_ARCHIVE_RESULT" = success', $terminal );
		self::assertStringContainsString( 'test "$RAN_CORE_CONTRACT_RESULT" = success', $terminal );
		self::assertStringContainsString( 'test "$RAN_REPOSITORY_QUALITY_RESULT" = success', $terminal );
	}

	public function test_certified_core_checkout_uses_the_exact_local_certification(): void {
		$quality = file_get_contents( dirname( __DIR__ ) . '/.github/workflows/quality.yml' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local workflow contract.
		self::assertIsString( $quality );

		self::assertStringContainsString(
			"repository: RocketsAreNostalgic/ran-booster\n"
			. '          ref: ${{ needs.runtime-archive.outputs.core-commit }}' . "\n"
			. "          fetch-depth: 0\n"
			. "          path: core\n"
			. '          persist-credentials: false',
			$quality
		);
		self::assertStringContainsString( 'php migrator/scripts/core-certification.php verify migrator/composer.json core', $quality );
	}

	public function test_source_quality_cannot_publish_without_released_core_analysis(): void {
		$root    = dirname( __DIR__ );
		$release = file_get_contents( $root . '/.github/workflows/release-please.yml' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local release guard contract.
		$quality = file_get_contents( $root . '/.github/workflows/quality.yml' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local release guard contract.
		self::assertIsString( $release );
		self::assertIsString( $quality );
		self::assertStringContainsString( 'needs: released-core-proof', $release );
		self::assertStringContainsString( 'run: composer analyze:certified', $release );
		self::assertStringContainsString( 'ref: ${{ github.event.workflow_run.head_sha }}', $release );
		self::assertStringContainsString( 'test "$(git rev-parse HEAD)" = "$RAN_SOURCE_SHA"', $release );
		foreach ( array( "conclusion == 'success'", "event == 'push'", "head_branch == 'main'", 'head_repository.id == github.repository_id', 'head_repository.full_name == github.repository', "path == '.github/workflows/quality.yml'" ) as $predicate ) {
			self::assertStringContainsString( $predicate, $release );
		}
		self::assertStringContainsString( "needs.runtime-archive.outputs.release-candidate == 'true'", $quality );
		self::assertStringContainsString( 'run: composer analyze:certified', $quality );
	}

	public function test_release_workflow_is_a_thin_pinned_profile_b_caller(): void {
		$release = file_get_contents( dirname( __DIR__ ) . '/.github/workflows/release-please.yml' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local workflow contract.
		self::assertIsString( $release );

		self::assertStringContainsString(
			'uses: RocketsAreNostalgic/.github/.github/workflows/release-profile-b.yml@e2fb19244a301a62f8fae2a80536898adf21fe22',
			$release
		);
		self::assertStringContainsString( 'expected-workflow-path: .github/workflows/quality.yml', $release );
		self::assertStringContainsString( 'release-pr-head: release-please--branches--main--components--ran-booster-wp-pusher-migrator', $release );
		self::assertStringContainsString( 'artifact-prefix: ran-booster-wp-pusher-migrator-runtime', $release );
		self::assertStringNotContainsString( 'googleapis/release-please-action@', $release );
		self::assertStringNotContainsString( 'gh release ', $release );
		self::assertStringNotContainsString( 'autorelease: pending', $release );
		self::assertStringNotContainsString( 'merge_commit_sha', $release );
	}

	public function test_release_pull_request_gate_requires_fresh_installed_site_evidence(): void {
		$release = file_get_contents( dirname( __DIR__ ) . '/RELEASE.md' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local release contract.
		self::assertIsString( $release );

		self::assertStringContainsString( 'Do not merge a Release Please pull request until this gate is complete', $release );
		self::assertStringContainsString( 'Portability API 3 and Admin Interaction API 3', $release );
		self::assertStringContainsString( 'disposable single-site WordPress installation', $release );
		self::assertStringContainsString( 'Record the exact candidate SHA', $release );
		self::assertStringContainsString( 'does not by itself satisfy items 3–5 for a new candidate', $release );
	}

	public function test_release_please_configuration_provides_profile_b_draft_semantics(): void {
		$config = json_decode( (string) file_get_contents( dirname( __DIR__ ) . '/release-please-config.json' ), true, 512, JSON_THROW_ON_ERROR ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local release contract.
		self::assertTrue( $config['draft'] ?? false );
		self::assertTrue( $config['force-tag-creation'] ?? false );
		self::assertNotSame( true, $config['skip-github-release'] ?? false );
	}

	public function test_repository_workflows_pin_every_external_action_or_reusable_workflow_to_an_exact_commit(): void {
		foreach ( array( 'quality.yml', 'release-please.yml' ) as $workflow_name ) {
			$workflow = file_get_contents( dirname( __DIR__ ) . '/.github/workflows/' . $workflow_name ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local workflow contract.
			self::assertIsString( $workflow );

			$match_count = preg_match_all( '/^[[:space:]]+(?:- )?uses: ([^[:space:]#]+)/m', $workflow, $matches );
			self::assertIsInt( $match_count );
			self::assertGreaterThan( 0, $match_count );
			foreach ( $matches[1] as $action ) {
				self::assertMatchesRegularExpression(
					'/\A[^@]+@[0-9a-f]{40}\z/',
					$action,
					$workflow_name . ' must not use mutable action reference ' . $action
				);
			}
		}
	}

	public function test_public_documentation_keeps_acquisition_support_and_security_truthful(): void {
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
