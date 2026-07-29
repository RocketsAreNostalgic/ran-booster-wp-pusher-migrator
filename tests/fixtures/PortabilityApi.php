<?php

declare(strict_types=1);

namespace RAN\AddOn\Portability;

final readonly class PortabilityCandidate {

	public function __construct(
		public string $type,
		public string $identifier,
		public string $displayName,
		public string $providerCode,
		public string $repository,
		public string $branch,
		public ?string $subdirectory = null,
		public ?string $credentialId = null
	) {
	}
}

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

final readonly class PortabilityApplyResult {

	public function __construct(
		public string $status,
		public string $reason,
		public string $message,
		public bool $targetVerified
	) {
	}
}

abstract class PortabilityFacade {

	public const API_VERSION = 1;

	public function nonceAction(
		string $operation,
		PortabilityCandidate $candidate,
		?string $expectedFingerprint = null
	): string {
		unset( $candidate );

		return $operation . ':' . ( $expectedFingerprint ?? 'review' );
	}

	abstract public function review( PortabilityCandidate $candidate, string $nonce ): PortabilityReviewResult;

	abstract public function apply(
		PortabilityCandidate $candidate,
		string $expectedFingerprint,
		string $nonce
	): PortabilityApplyResult;
}
