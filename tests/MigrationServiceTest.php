<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use RAN\AddOn\Portability\PortabilityApplyResult;
use RAN\AddOn\Portability\PortabilityCandidate;
use RAN\AddOn\Portability\PortabilityFacade;
use RAN\AddOn\Portability\PortabilityReviewResult;
use RAN\BoosterWpPusherMigrator\CandidateFactory;
use RAN\BoosterWpPusherMigrator\MigrationService;
use RAN\BoosterWpPusherMigrator\WpPusherSource;
use RuntimeException;

final class MigrationServiceTest extends TestCase {

	public function testReviewsOneFreshUnchangedCandidateThroughFacade(): void {
		$facade  = new FakePortabilityFacade();
		$service = $this->service( $facade );
		$source  = $service->packages()[0];

		$result = $service->review( $source->id, $source->fingerprint(), null, 'valid-review-nonce' );

		self::assertSame( 'adopt', $result->action );
		self::assertSame( 'fixture/fixture.php', $facade->candidate?->identifier );
		self::assertSame( 'valid-review-nonce', $facade->nonce );
		self::assertSame( 'review:review', $service->nonceAction( 'review', $source ) );
	}

	public function testRejectsChangedMissingAndMalformedSourceBeforeFacade(): void {
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

	public function testApplyFreshRevalidatesSourceAndForwardsExactReview(): void {
		$facade  = new FakePortabilityFacade();
		$service = $this->service( $facade );
		$source  = $service->packages()[0];
		$review  = 'v1:' . str_repeat( 'c', 64 );

		$result = $service->apply(
			$source->id,
			$source->fingerprint(),
			null,
			$review,
			'valid-apply-nonce'
		);

		self::assertTrue( $result->targetVerified );
		self::assertSame( $review, $facade->expectedFingerprint );
		self::assertSame( 'valid-apply-nonce', $facade->nonce );
		self::assertSame( 'apply:' . $review, $service->nonceAction( 'apply', $source, null, $review ) );
	}

	public function testCleanupRequiresVerifiedTargetAndExactSource(): void {
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

	private function service( FakePortabilityFacade $facade, ?FakeDatabase $database = null ): MigrationService {
		$database ??= new FakeDatabase();
		$source     = new WpPusherSource(
			$database,
			static fn (): array => array( WpPusherSource::PLUGIN => array( 'Version' => '3.0.13' ) ),
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

final class FakePortabilityFacade extends PortabilityFacade {

	public ?PortabilityCandidate $candidate = null;
	public string $nonce                    = '';
	public string $expectedFingerprint      = '';

	public function review( PortabilityCandidate $candidate, string $nonce ): PortabilityReviewResult {
		$this->candidate = $candidate;
		$this->nonce     = $nonce;

		return new PortabilityReviewResult(
			$candidate,
			'adopt',
			'ready',
			'Ready to adopt.',
			'v1:' . str_repeat( 'a', 64 )
		);
	}

	public function apply(
		PortabilityCandidate $candidate,
		string $expectedFingerprint,
		string $nonce
	): PortabilityApplyResult {
		$this->candidate           = $candidate;
		$this->expectedFingerprint = $expectedFingerprint;
		$this->nonce               = $nonce;

		return new PortabilityApplyResult( 'adopted', 'adopted', 'Adopted.', true );
	}
}
