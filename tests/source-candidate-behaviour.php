<?php
/** Paired CLI proof using real Core declarations and Migrator source. No host certification. */
declare(strict_types=1);

namespace RAN\BoosterWpPusherMigrator\SourceCandidate;

use RAN\AddOn\Portability\PortabilityApplyResult;
use RAN\AddOn\Portability\PortabilityCandidate;
use RAN\AddOn\Portability\PortabilityFacade;
use RAN\AddOn\Portability\PortabilityReviewResult;
use RAN\BoosterWpPusherMigrator\CandidateFactory;
use RAN\BoosterWpPusherMigrator\MigrationService;
use RAN\BoosterWpPusherMigrator\WpPusherSource;
use RuntimeException;

require_once __DIR__ . '/source-candidate-bootstrap.php';

function expect( bool $condition, string $message ): void {
	if ( ! $condition ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI assertion message, never rendered.
		throw new RuntimeException( $message );
	}
}

$ran_booster_wp_pusher_migrator_root = realpath( $argv[1] ?? dirname( __DIR__ ) );
$ran_booster_wp_pusher_migrator_mode = $argv[2] ?? 'candidate';
expect( in_array( $ran_booster_wp_pusher_migrator_mode, array( 'baseline', 'candidate' ), true ), 'Expected baseline or candidate mode.' );
expect( false !== $ran_booster_wp_pusher_migrator_root, 'Migrator source is missing.' );
verify_source( $ran_booster_wp_pusher_migrator_root, $argv[3] ?? '', array( 'src/', 'views/', 'tests/WpPusherSourceTest.php', 'composer.lock' ) );
define( 'ABSPATH', '/tmp/source-candidate-wordpress/' );
define( 'ARRAY_A', 'ARRAY_A' );
$ran_booster_wp_pusher_migrator_core_vendor = getenv( 'RAN_MIGRATOR_SOURCE_CORE_VENDOR' );
if ( false === $ran_booster_wp_pusher_migrator_core_vendor || '' === $ran_booster_wp_pusher_migrator_core_vendor ) {
	$ran_booster_wp_pusher_migrator_core_vendor = (string) getenv( 'RAN_MIGRATOR_SOURCE_CORE' ) . '/vendor';
}
require $ran_booster_wp_pusher_migrator_core_vendor . '/autoload.php';
require (string) getenv( 'RAN_MIGRATOR_SOURCE_CORE' ) . '/autoload.php';
require $ran_booster_wp_pusher_migrator_root . '/vendor/autoload.php';
require $ran_booster_wp_pusher_migrator_root . '/src/Autoloader.php';
\RAN\BoosterWpPusherMigrator\Autoloader::register();
// Reuse only the existing in-memory database fixture, never the fake Core APIs.
require $ran_booster_wp_pusher_migrator_root . '/tests/WpPusherSourceTest.php';

$ran_booster_wp_pusher_migrator_candidate = 'candidate' === $ran_booster_wp_pusher_migrator_mode;
expect( PortabilityFacade::API_VERSION === ( $ran_booster_wp_pusher_migrator_candidate ? 3 : 2 ), 'Unexpected Portability API generation.' );
$ran_booster_wp_pusher_migrator_array_method = $ran_booster_wp_pusher_migrator_candidate ? 'to_array' : 'toArray';
$ran_booster_wp_pusher_migrator_nonce_method = $ran_booster_wp_pusher_migrator_candidate ? 'nonce_action' : 'nonceAction';
$ran_booster_wp_pusher_migrator_resolved     = $ran_booster_wp_pusher_migrator_candidate ? 'from_resolved' : 'fromResolved';
$ran_booster_wp_pusher_migrator_verified     = $ran_booster_wp_pusher_migrator_candidate ? 'target_verified' : 'targetVerified';

$ran_booster_wp_pusher_migrator_facade                  = new class() extends PortabilityFacade {
	public string $resolved_method = '';
	public int $calls              = 0;

	public function review( PortabilityCandidate $candidate, string $nonce ): PortabilityReviewResult {
		unset( $nonce );
		++$this->calls;
		return PortabilityReviewResult::{$this->resolved_method}( $candidate, 'adopt', 'none', 'Ready to adopt.', 'fixture-identity', false );
	}

	public function apply( PortabilityCandidate $candidate, string $expected_fingerprint, string $nonce ): PortabilityApplyResult {
		unset( $candidate, $expected_fingerprint, $nonce );
		++$this->calls;
		return new PortabilityApplyResult( 'adopted', 'none', 'Adopted.', true );
	}
};
$ran_booster_wp_pusher_migrator_facade->resolved_method = $ran_booster_wp_pusher_migrator_resolved;
$ran_booster_wp_pusher_migrator_database                = new \Tests\FakeDatabase();
$ran_booster_wp_pusher_migrator_source                  = new WpPusherSource(
	$ran_booster_wp_pusher_migrator_database,
	static fn (): array => array( WpPusherSource::PLUGIN => array( 'Version' => '3.0.13' ) ),
	static fn (): array => array(),
	static fn (): array => array(),
	static fn (): bool => false
);
$ran_booster_wp_pusher_migrator_factory                 = new CandidateFactory( static fn (): array => array( 'fixture/fixture.php' => array( 'Name' => 'Fixture Plugin' ) ) );
$ran_booster_wp_pusher_migrator_service                 = new MigrationService( $ran_booster_wp_pusher_migrator_source, $ran_booster_wp_pusher_migrator_factory, $ran_booster_wp_pusher_migrator_facade );
$ran_booster_wp_pusher_migrator_package                 = $ran_booster_wp_pusher_migrator_service->packages()[0];
$ran_booster_wp_pusher_migrator_review                  = $ran_booster_wp_pusher_migrator_service->review( 1, $ran_booster_wp_pusher_migrator_package->fingerprint(), null, 'nonce' );
$ran_booster_wp_pusher_migrator_result                  = $ran_booster_wp_pusher_migrator_service->apply( 1, $ran_booster_wp_pusher_migrator_package->fingerprint(), null, $ran_booster_wp_pusher_migrator_review->fingerprint, 'nonce' );
expect( $ran_booster_wp_pusher_migrator_result->{$ran_booster_wp_pusher_migrator_verified}, 'Real result property was not consumed.' );

$ran_booster_wp_pusher_migrator_evidence = array(
	'candidate'          => $ran_booster_wp_pusher_migrator_review->candidate->{$ran_booster_wp_pusher_migrator_array_method}(),
	'source_fingerprint' => $ran_booster_wp_pusher_migrator_package->fingerprint(),
	'review_fingerprint' => $ran_booster_wp_pusher_migrator_review->fingerprint,
	'review_nonce'       => $ran_booster_wp_pusher_migrator_service->{$ran_booster_wp_pusher_migrator_nonce_method}( 'review', $ran_booster_wp_pusher_migrator_package ),
	'apply_nonce'        => $ran_booster_wp_pusher_migrator_service->{$ran_booster_wp_pusher_migrator_nonce_method}( 'apply', $ran_booster_wp_pusher_migrator_package, null, $ran_booster_wp_pusher_migrator_review->fingerprint ),
	'apply'              => array( $ran_booster_wp_pusher_migrator_result->status, $ran_booster_wp_pusher_migrator_result->reason, $ran_booster_wp_pusher_migrator_result->message ),
);
$ran_booster_wp_pusher_migrator_refusal  = new PortabilityApplyResult( 'blocked', 'forbidden', 'Refused.', false );
expect( ! $ran_booster_wp_pusher_migrator_service->cleanup( 1, $ran_booster_wp_pusher_migrator_package->fingerprint(), $ran_booster_wp_pusher_migrator_refusal ), 'Unverified target was cleaned up.' );
expect( 1 === count( $ran_booster_wp_pusher_migrator_database->rows ), 'Refusal changed the source.' );
try {
	$ran_booster_wp_pusher_migrator_service->review( 1, 'v1:' . str_repeat( '0', 64 ), null, 'nonce' );
	throw new RuntimeException( 'Stale source was admitted.' );
} catch ( RuntimeException $error ) {
	expect( 'The WP Pusher package changed. Review it again.' === $error->getMessage(), 'Unexpected stale-source behavior.' );
}
expect( 2 === $ran_booster_wp_pusher_migrator_facade->calls, 'Stale source reached the facade.' );
expect( $ran_booster_wp_pusher_migrator_service->cleanup( 1, $ran_booster_wp_pusher_migrator_package->fingerprint(), $ran_booster_wp_pusher_migrator_result ), 'Verified exact source was not cleaned up.' );
expect( array() === $ran_booster_wp_pusher_migrator_database->rows, 'Exact cleanup failed.' );
$ran_booster_wp_pusher_migrator_evidence['cleanup'] = 'unverified-refused;stale-refused;verified-exact-deleted';
// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode,WordPress.Security.EscapeOutput.OutputNotEscaped -- Deterministic CLI evidence, without secrets or host identity.
echo json_encode( $ran_booster_wp_pusher_migrator_evidence, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . PHP_EOL;
