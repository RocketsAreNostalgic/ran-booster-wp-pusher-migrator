<?php

declare(strict_types=1);

namespace RAN\Admin\Interaction;

final readonly class AdminInteractionRequest {

	private function __construct(
		private string $operation,
		private string $row_namespace,
		private string $canonical_url,
		private string $error_region_id
	) {
	}

	public static function transporter_migration_source_row(
		string $operation,
		string $row_namespace,
		string $canonical_url,
		string $error_region_id
	): self {
		return new self( $operation, $row_namespace, $canonical_url, $error_region_id );
	}

	public function operation(): string {
		return $this->operation;
	}

	public function canonical_url(): string {
		return $this->canonical_url;
	}

	public function error_region_id(): string {
		return $this->error_region_id;
	}

	public function target_element_id(): string {
		return 'ran-booster-transporter-migration-source-' . substr( hash( 'sha256', $this->row_namespace ), 0, 32 );
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

	public static function validation_failure( AdminInteractionRequest $request, string $message ): self {
		return new self( $request, 'validation_failure', $message );
	}

	public static function unexpected_failure( AdminInteractionRequest $request ): self {
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

	public const API_VERSION = 3;

	public function render_form_attributes( AdminInteractionRequest $request ): void;

	public function is_enhanced_request( AdminInteractionRequest $request ): bool;

	public function respond( AdminInteractionOutcome $outcome ): never;
}

interface TransporterRowAdminInteractionFacade {

	/** @param callable(string):void $render_fragment */
	public function respond_with_transporter_row_fragment(
		AdminInteractionOutcome $outcome,
		callable $render_fragment
	): never;
}
