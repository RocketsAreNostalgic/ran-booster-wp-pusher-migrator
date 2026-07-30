<?php

declare(strict_types=1);

namespace RAN\Admin\Interaction;

final readonly class AdminInteractionRequest {

	private function __construct(
		private string $operation,
		private string $rowNamespace,
		private string $canonicalUrl,
		private string $errorRegionId
	) {
	}

	public static function transporterMigrationSourceRow(
		string $operation,
		string $rowNamespace,
		string $canonicalUrl,
		string $errorRegionId
	): self {
		return new self( $operation, $rowNamespace, $canonicalUrl, $errorRegionId );
	}

	public function operation(): string {
		return $this->operation;
	}

	public function canonicalUrl(): string {
		return $this->canonicalUrl;
	}

	public function errorRegionId(): string {
		return $this->errorRegionId;
	}

	public function targetElementId(): string {
		return 'ran-booster-transporter-migration-source-' . substr( hash( 'sha256', $this->rowNamespace ), 0, 32 );
	}
}

final readonly class AdminInteractionOutcome {

	private function __construct(
		private AdminInteractionRequest $request,
		private string $kind,
		private string $message
	) {
	}

	public static function success( AdminInteractionRequest $request, string $message ): self {
		return new self( $request, 'success', $message );
	}

	public static function validationFailure( AdminInteractionRequest $request, string $message ): self {
		return new self( $request, 'validation_failure', $message );
	}

	public static function unexpectedFailure( AdminInteractionRequest $request ): self {
		return new self( $request, 'unexpected_failure', 'We could not complete that request. Please try again.' );
	}

	public function request(): AdminInteractionRequest {
		return $this->request;
	}

	public function kind(): string {
		return $this->kind;
	}

	public function message(): string {
		return $this->message;
	}
}

interface AdminInteractionFacade {

	public const API_VERSION = 1;

	public function renderFormAttributes( AdminInteractionRequest $request ): void;

	public function isEnhancedRequest( AdminInteractionRequest $request ): bool;

	public function respond( AdminInteractionOutcome $outcome ): never;
}

interface TransporterRowAdminInteractionFacade {

	/** @param callable(string):void $renderFragment */
	public function respondWithTransporterRowFragment(
		AdminInteractionOutcome $outcome,
		callable $renderFragment
	): never;
}
