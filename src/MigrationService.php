<?php

declare(strict_types=1);

namespace RAN\BoosterWpPusherMigrator;

use RAN\AddOn\Portability\PortabilityApplyResult;
use RAN\AddOn\Portability\PortabilityCandidate;
use RAN\AddOn\Portability\PortabilityFacade;
use RAN\AddOn\Portability\PortabilityReviewResult;
use RuntimeException;

/** Request-local adapter from one exact source row to Portability API 3. */
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
		int $source_id,
		string $expected_source_fingerprint,
		?string $credential_id,
		string $nonce
	): PortabilityReviewResult {
		$source    = $this->unchanged_source( $source_id, $expected_source_fingerprint );
		$candidate = $this->core_candidate( $this->candidates->candidate( $source, $credential_id ) );

		return $this->portability->review( $candidate, $nonce );
	}

	/**
	 * Fresh-read and apply one unchanged reviewed source row through Core.
	 */
	public function apply(
		int $source_id,
		string $expected_source_fingerprint,
		?string $credential_id,
		string $expected_review_fingerprint,
		string $nonce
	): PortabilityApplyResult {
		$source    = $this->unchanged_source( $source_id, $expected_source_fingerprint );
		$candidate = $this->core_candidate( $this->candidates->candidate( $source, $credential_id ) );

		return $this->portability->apply( $candidate, $expected_review_fingerprint, $nonce );
	}

	public function cleanup(
		int $source_id,
		string $expected_source_fingerprint,
		PortabilityApplyResult $result
	): bool {
		if ( ! $result->target_verified ) {
			return false;
		}

		return $this->source->delete_exact(
			$this->unchanged_source( $source_id, $expected_source_fingerprint )
		);
	}

	public function nonce_action(
		string $operation,
		WpPusherPackage $source,
		?string $credential_id = null,
		?string $expected_review_fingerprint = null
	): string {
		$candidate = $this->core_candidate( $this->candidates->candidate( $source, $credential_id ) );

		return $this->portability->nonce_action( $operation, $candidate, $expected_review_fingerprint );
	}

	private function unchanged_source( int $source_id, string $expected_fingerprint ): WpPusherPackage {
		if ( 1 !== preg_match( '/\Av1:[a-f0-9]{64}\z/D', $expected_fingerprint ) ) {
			throw new RuntimeException( 'Refresh the WP Pusher migration review.' );
		}
		foreach ( $this->source->packages() as $source ) {
			if ( $source_id === $source->id ) {
				if ( ! hash_equals( $expected_fingerprint, $source->fingerprint() ) ) {
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
	private function core_candidate( array $fields ): PortabilityCandidate {
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
