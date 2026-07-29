<?php

declare(strict_types=1);

namespace RAN\BoosterWpPusherMigrator;

use RAN\AddOn\Portability\PortabilityApplyResult;
use RAN\AddOn\Portability\PortabilityCandidate;
use RAN\AddOn\Portability\PortabilityFacade;
use RAN\AddOn\Portability\PortabilityReviewResult;
use RuntimeException;

/** Request-local adapter from one exact source row to Portability API 1. */
final readonly class MigrationService {

	public function __construct(
		private WpPusherSource $source,
		private CandidateFactory $candidates,
		private PortabilityFacade $portability
	) {
	}

	/** @return list<WpPusherPackage> */
	public function packages(): array {
		return $this->source->packages();
	}

	/**
	 * Fresh-read and review one unchanged source row through Core.
	 */
	public function review(
		int $sourceId,
		string $expectedSourceFingerprint,
		?string $credentialId,
		string $nonce
	): PortabilityReviewResult {
		$source    = $this->unchangedSource( $sourceId, $expectedSourceFingerprint );
		$candidate = $this->coreCandidate( $this->candidates->candidate( $source, $credentialId ) );

		return $this->portability->review( $candidate, $nonce );
	}

	/**
	 * Fresh-read and apply one unchanged reviewed source row through Core.
	 */
	public function apply(
		int $sourceId,
		string $expectedSourceFingerprint,
		?string $credentialId,
		string $expectedReviewFingerprint,
		string $nonce
	): PortabilityApplyResult {
		$source    = $this->unchangedSource( $sourceId, $expectedSourceFingerprint );
		$candidate = $this->coreCandidate( $this->candidates->candidate( $source, $credentialId ) );

		return $this->portability->apply( $candidate, $expectedReviewFingerprint, $nonce );
	}

	public function nonceAction(
		string $operation,
		WpPusherPackage $source,
		?string $credentialId = null,
		?string $expectedReviewFingerprint = null
	): string {
		$candidate = $this->coreCandidate( $this->candidates->candidate( $source, $credentialId ) );

		return $this->portability->nonceAction( $operation, $candidate, $expectedReviewFingerprint );
	}

	private function unchangedSource( int $sourceId, string $expectedFingerprint ): WpPusherPackage {
		if ( 1 !== preg_match( '/\Av1:[a-f0-9]{64}\z/D', $expectedFingerprint ) ) {
			throw new RuntimeException( 'Refresh the WP Pusher migration review.' );
		}
		foreach ( $this->source->packages() as $source ) {
			if ( $sourceId === $source->id ) {
				if ( ! hash_equals( $expectedFingerprint, $source->fingerprint() ) ) {
					throw new RuntimeException( 'The WP Pusher package changed. Review it again.' );
				}

				return $source;
			}
		}

		throw new RuntimeException( 'The WP Pusher package is no longer available.' );
	}

	/**
	 * @param array{type:string,identifier:string,display_name:string,provider:string,repository:string,branch:string,subdirectory:string|null,credential_id:string|null} $fields Candidate fields.
	 */
	private function coreCandidate( array $fields ): PortabilityCandidate {
		return new PortabilityCandidate(
			$fields['type'],
			$fields['identifier'],
			$fields['display_name'],
			$fields['provider'],
			$fields['repository'],
			$fields['branch'],
			$fields['subdirectory'],
			$fields['credential_id']
		);
	}
}
