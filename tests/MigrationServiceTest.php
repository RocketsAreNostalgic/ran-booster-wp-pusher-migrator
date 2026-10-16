<?php

declare(strict_types=1);

namespace RAN\BoosterWpPusherMigrator\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;
use RAN\AddOn\Portability\PortabilityApplyResult;
use RAN\AddOn\Portability\PortabilityCandidate;
use RAN\AddOn\Portability\PortabilityFacade;
use RAN\AddOn\Portability\PortabilityReviewResult;
use RAN\BoosterWpPusherMigrator\CandidateFactory;
use RAN\BoosterWpPusherMigrator\MigrationService;
use RAN\BoosterWpPusherMigrator\WpPusherSource;
use RuntimeException;
use InvalidArgumentException;

final class MigrationServiceTest extends TestCase {

	public function test_reviews_one_fresh_unchanged_candidate_through_facade(): void {
		$facade  = new FakePortabilityFacade();
		$service = $this->service( $facade );
		$source  = $service->packages()[0];

		$result = $service->review( $source->id, $source->fingerprint(), null, 'valid-review-nonce' );

		self::assertSame( 'adopt', $result->action );
		self::assertSame( 'fixture/fixture.php', $facade->candidate?->identifier );
		self::assertSame( 'valid-review-nonce', $facade->nonce );
		self::assertSame( 'review:review', $service->nonce_action( 'review', $source ) );
	}

	#[DataProviderExternal( WpPusherSourceTest::class, 'compatible_version_provider' )]
	public function test_compatible_seeded_data_can_be_reviewed_applied_and_cleaned_across_versions( string $version ): void {
		$database = new FakeDatabase();
		$facade   = new FakePortabilityFacade();
		$service  = $this->service( $facade, $database, $version );
		$source   = $service->packages()[0];
		$review   = $service->review( $source->id, $source->fingerprint(), null, 'nonce' );
		$result   = $service->apply( $source->id, $source->fingerprint(), null, $review->fingerprint, 'nonce', null );

		self::assertTrue( $result->target_verified );
		self::assertTrue( $service->cleanup( $source->id, $source->fingerprint(), $result ) );
		self::assertSame( array(), $database->rows );
	}

	#[DataProvider( 'incompatible_data_provider' )]
	public function test_review_rejects_incompatible_data_before_facade_or_mutation( string $drift ): void {
		$database = new FakeDatabase();
		$facade   = new FakePortabilityFacade();
		$service  = $this->service( $facade, $database );
		$source   = $service->packages()[0];
		$this->change_retained_data( $database, $drift );
		$rows = $database->rows;

		try {
			$service->review( $source->id, $source->fingerprint(), null, 'nonce' );
			self::fail( 'Incompatible data reached review.' );
		} catch ( RuntimeException | InvalidArgumentException ) {
			self::assertNull( $facade->candidate );
		}
		self::assertSame( $rows, $database->rows );
		self::assertStringNotContainsString( 'DELETE ', implode( ' ', $database->queries ) );
	}

	#[DataProvider( 'incompatible_data_provider' )]
	public function test_apply_revalidates_data_changed_after_review_before_facade_or_mutation( string $drift ): void {
		$database = new FakeDatabase();
		$facade   = new FakePortabilityFacade();
		$service  = $this->service( $facade, $database );
		$source   = $service->packages()[0];
		$review   = $service->review( $source->id, $source->fingerprint(), null, 'nonce' );
		$this->change_retained_data( $database, $drift );
		$rows              = $database->rows;
		$facade->candidate = null;

		try {
			$service->apply( $source->id, $source->fingerprint(), null, $review->fingerprint, 'nonce', null );
			self::fail( 'Incompatible data reached Apply.' );
		} catch ( RuntimeException | InvalidArgumentException ) {
			self::assertNull( $facade->candidate );
		}
		self::assertSame( $rows, $database->rows );
		self::assertStringNotContainsString( 'DELETE ', implode( ' ', $database->queries ) );
	}

	/** @return iterable<string, array{string}> */
	public static function incompatible_data_provider(): iterable {
		foreach ( array( 'missing column', 'extra column', 'changed type', 'invalid record', 'changed record' ) as $drift ) {
			yield $drift => array( $drift );
		}
	}

	private function change_retained_data( FakeDatabase $database, string $drift ): void {
		switch ( $drift ) {
			case 'missing column':
				array_pop( $database->schema );
				break;
			case 'extra column':
				$database->schema[] = array(
					'Field' => 'future',
					'Type'  => 'int',
				);
				break;
			case 'changed type':
				$database->schema[0]['Type'] = 'bigint';
				break;
			case 'invalid record':
				$database->rows[0]['subdirectory'] = '../secret';
				break;
			case 'changed record':
				$database->rows[0]['branch'] = 'changed-after-review';
				break;
		}
	}

	public function test_rejects_changed_missing_and_malformed_source_before_facade(): void {
		$facade  = new FakePortabilityFacade();
		$service = $this->service( $facade );
		$source  = $service->packages()[0];

		foreach ( array( str_repeat( 'a', 64 ), 'v1:' . str_repeat( 'b', 64 ) ) as $fingerprint ) {
			try {
				$service->review( $source->id, $fingerprint, null, 'nonce' );
				self::fail( 'Changed source was reviewed.' );
			} catch ( RuntimeException ) {
				self::assertNull( $facade->candidate );
			}
		}

		$this->expectException( RuntimeException::class );
		$service->review( 999, $source->fingerprint(), null, 'nonce' );
	}

	public function test_apply_fresh_revalidates_source_and_forwards_exact_review(): void {
		$facade  = new FakePortabilityFacade();
		$service = $this->service( $facade );
		$source  = $service->packages()[0];
		$review  = 'v1:' . str_repeat( 'c', 64 );

		$result = $service->apply(
			$source->id,
			$source->fingerprint(),
			null,
			$review,
			'valid-apply-nonce',
			null
		);

		self::assertTrue( $result->target_verified );
		self::assertSame( $review, $facade->expected_fingerprint );
		self::assertSame( 'valid-apply-nonce', $facade->nonce );
		self::assertSame( 'apply:' . $review, $service->nonce_action( 'apply', $source, null, $review ) );
	}

	public function test_forwards_bitbucket_provider_and_replacement_credential_through_review_and_apply(): void {
		$database                          = new FakeDatabase();
		$database->rows[0]['host']         = 'bb';
		$database->rows[0]['private']      = '1';
		$database->rows[0]['repository']   = 'fixture-workspace/private-plugin';
		$database->rows[0]['subdirectory'] = 'packages/plugin';
		$facade                            = new FakePortabilityFacade();
		$service                           = $this->service( $facade, $database );
		$source                            = $service->packages()[0];

		$service->review( $source->id, $source->fingerprint(), 'bitbucket_profile', 'review-nonce' );

		self::assertSame( 'bb', $facade->candidate?->provider_code );
		self::assertSame( 'fixture-workspace/private-plugin', $facade->candidate->repository );
		self::assertSame( 'packages/plugin', $facade->candidate->subdirectory );
		self::assertSame( 'bitbucket_profile', $facade->candidate->credential_id );

		$service->apply(
			$source->id,
			$source->fingerprint(),
			'bitbucket_profile',
			'v1:' . str_repeat( 'b', 64 ),
			'apply-nonce',
			null
		);

		self::assertSame( 'bb', $facade->candidate->provider_code );
		self::assertSame( 'bitbucket_profile', $facade->candidate->credential_id );
	}

	public function test_cleanup_requires_verified_target_and_exact_source(): void {
		$database = new FakeDatabase();
		$facade   = new FakePortabilityFacade();
		$service  = $this->service( $facade, $database );
		$source   = $service->packages()[0];
		$verified = new PortabilityApplyResult( 'adopted', 'adopted', 'Adopted.', true );

		self::assertTrue( $service->cleanup( $source->id, $source->fingerprint(), $verified ) );
		self::assertSame( array(), $database->rows );

		$database   = new FakeDatabase();
		$service    = $this->service( $facade, $database );
		$source     = $service->packages()[0];
		$unverified = new PortabilityApplyResult( 'failed', 'target_unverified', 'Not verified.', false );
		self::assertFalse( $service->cleanup( $source->id, $source->fingerprint(), $unverified ) );
		self::assertCount( 1, $database->rows );
	}

	public function test_new_request_reconstructs_cleanup_pending_and_blocked_states(): void {
		$database              = new FakeDatabase();
		$facade                = new FakePortabilityFacade();
		$facade->review_action = 'managed';
		$facade->apply_status  = 'unchanged';
		$service               = $this->service( $facade, $database );
		$source                = $service->packages()[0];
		$review                = $service->review( $source->id, $source->fingerprint(), null, 'nonce' );
		$result                = $service->apply( $source->id, $source->fingerprint(), null, $review->fingerprint, 'nonce', null );

		self::assertSame( 'managed', $review->action );
		self::assertSame( 'unchanged', $result->status );
		self::assertTrue( $service->cleanup( $source->id, $source->fingerprint(), $result ) );
		self::assertSame( array(), $database->rows );

		$database                = new FakeDatabase();
		$facade->target_verified = false;
		$facade->apply_status    = 'blocked';
		$service                 = $this->service( $facade, $database );
		$source                  = $service->packages()[0];
		$result                  = $service->apply( $source->id, $source->fingerprint(), null, $review->fingerprint, 'nonce', null );
		self::assertFalse( $service->cleanup( $source->id, $source->fingerprint(), $result ) );
		self::assertCount( 1, $database->rows );
	}

	private function service( FakePortabilityFacade $facade, ?FakeDatabase $database = null, string $version = '3.0.18' ): MigrationService {
		$database ??= new FakeDatabase();
		$source     = new WpPusherSource(
			$database,
			static fn (): array => array( WpPusherSource::PLUGIN => array( 'Version' => $version ) ),
			static fn (): array => array(),
			static fn (): array => array(),
			static fn (): bool => false
		);
		$factory    = new CandidateFactory(
			static fn (): array => array( 'fixture/fixture.php' => array( 'Name' => 'Fixture Plugin' ) ),
			static fn ( string $stylesheet ): object => new FakeTheme( $stylesheet, true )
		);

		return new MigrationService( $source, $factory, $facade );
	}
}

// phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound -- Colocated test double belongs to this fixture load unit; unrelated declarations remain checked.
final class FakePortabilityFacade extends PortabilityFacade {

	public ?PortabilityCandidate $candidate = null;
	public string $nonce                    = '';
	public string $expected_fingerprint     = '';
	public string $review_action            = 'adopt';
	public string $apply_status             = 'adopted';
	public bool $target_verified            = true;

	public function review( PortabilityCandidate $candidate, string $nonce ): PortabilityReviewResult {
		$this->candidate = $candidate;
		$this->nonce     = $nonce;

		return new PortabilityReviewResult(
			$candidate,
			$this->review_action,
			'ready',
			'Ready to adopt.',
			'v1:' . str_repeat( 'a', 64 )
		);
	}

	/** @param array<string,mixed>|null $provider_expectation Review context. */
	public function apply(
		PortabilityCandidate $candidate,
		string $expected_fingerprint,
		string $nonce,
		?array $provider_expectation
	): PortabilityApplyResult {
		$this->candidate            = $candidate;
		$this->expected_fingerprint = $expected_fingerprint;
		$this->nonce                = $nonce;

		return new PortabilityApplyResult(
			$this->apply_status,
			$this->target_verified ? 'adopted' : 'target_unverified',
			$this->target_verified ? 'Adopted.' : 'Target changed.',
			$this->target_verified
		);
	}
}
