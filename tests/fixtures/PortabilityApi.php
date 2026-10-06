<?php

declare(strict_types=1);

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedNamespaceFound -- This fixture must occupy the exact Core namespace to intercept the existing host contract.
namespace RAN\AddOn\Portability;

final readonly class PortabilityCandidate {

	public function __construct(
		public string $type,
		public string $identifier,
		public string $display_name,
		public string $provider_code,
		public string $repository,
		public string $branch,
		public ?string $subdirectory = null,
		public ?string $credential_id = null
	) {
	}
}

// phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound -- Colocated test double belongs to this fixture load unit; unrelated declarations remain checked.
final readonly class PortabilityReviewResult {

	public function __construct(
		public PortabilityCandidate $candidate,
		public string $action,
		public string $reason,
		public string $message,
		public string $fingerprint
	) {
	}
}

// phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound -- Colocated test double belongs to this fixture load unit; unrelated declarations remain checked.
final readonly class PortabilityApplyResult {

	public function __construct(
		public string $status,
		public string $reason,
		public string $message,
		public bool $target_verified
	) {
	}
}

// phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound -- Colocated test double belongs to this fixture load unit; unrelated declarations remain checked.
abstract class PortabilityFacade {

	public const API_VERSION = 3;

	public function nonce_action(
		string $operation,
		PortabilityCandidate $candidate,
		?string $expected_fingerprint = null
	): string {
		unset( $candidate );

		return $operation . ':' . ( $expected_fingerprint ?? 'review' );
	}

	abstract public function review( PortabilityCandidate $candidate, string $nonce ): PortabilityReviewResult;

	abstract public function apply(
		PortabilityCandidate $candidate,
		string $expected_fingerprint,
		string $nonce
	): PortabilityApplyResult;
}
