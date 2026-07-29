<?php

declare(strict_types=1);

namespace RAN\BoosterWpPusherMigrator;

use Closure;
use RuntimeException;

/** Maps exact installed WP Pusher rows into source-agnostic Core input. */
final class CandidateFactory {

	/** @var Closure():array<string, array<string, mixed>> */
	private Closure $plugins;

	/** @var Closure(string):object */
	private Closure $theme;

	/**
	 * @param null|callable():array<string, array<string, mixed>> $plugins Installed plugin inventory.
	 * @param null|callable(string):object                        $theme   Installed theme lookup.
	 */
	public function __construct( ?callable $plugins = null, ?callable $theme = null ) {
		$this->plugins = Closure::fromCallable( $plugins ?? self::plugins( ... ) );
		$this->theme   = Closure::fromCallable( $theme ?? static fn ( string $stylesheet ): object => wp_get_theme( $stylesheet ) );
	}

	/**
	 * Build one bounded candidate without provider-issued identity.
	 *
	 * @return array{type:'plugin'|'theme',identifier:string,display_name:string,provider:'github'|'bitbucket',repository:string,branch:string,subdirectory:string|null,credential_id:string|null}
	 */
	public function candidate( WpPusherPackage $source, ?string $credentialId = null ): array {
		if ( 'gl' === $source->host ) {
			throw new RuntimeException( 'GitLab WP Pusher packages are not supported.' );
		}
		if ( 1 === $source->private && null === $credentialId ) {
			throw new RuntimeException( 'Choose an existing Booster credential profile for this private repository.' );
		}

		$displayName = 1 === $source->type
			? $this->pluginName( $source->package )
			: $this->themeName( $source->package );

		if ( null !== $credentialId
			&& 1 !== preg_match( '/\A[A-Za-z0-9_-]{3,64}\z/D', $credentialId ) ) {
			throw new RuntimeException( 'The Booster credential profile identifier is invalid.' );
		}

		return array(
			'type'          => 1 === $source->type ? 'plugin' : 'theme',
			'identifier'    => $source->package,
			'display_name'  => $displayName,
			'provider'      => 'gh' === $source->host ? 'github' : 'bitbucket',
			'repository'    => $source->repository,
			'branch'        => '' === $source->branch ? 'master' : $source->branch,
			'subdirectory'  => null === $source->subdirectory || '' === $source->subdirectory
				? null
				: $source->subdirectory,
			'credential_id' => $credentialId,
		);
	}

	private function pluginName( string $file ): string {
		$plugins = ( $this->plugins )();
		if ( ! isset( $plugins[ $file ]['Name'] )
			|| ! is_string( $plugins[ $file ]['Name'] )
			|| '' === trim( $plugins[ $file ]['Name'] ) ) {
			throw new RuntimeException( 'The WP Pusher plugin is not installed.' );
		}

		return $plugins[ $file ]['Name'];
	}

	private function themeName( string $stylesheet ): string {
		$theme = ( $this->theme )( $stylesheet );
		if ( ! method_exists( $theme, 'exists' )
			|| ! $theme->exists()
			|| ! method_exists( $theme, 'get' ) ) {
			throw new RuntimeException( 'The WP Pusher theme is not installed.' );
		}
		$name = $theme->get( 'Name' );
		if ( ! is_string( $name ) || '' === trim( $name ) ) {
			throw new RuntimeException( 'The WP Pusher theme is not installed.' );
		}

		return $name;
	}

	/** @return array<string, array<string, mixed>> */
	private static function plugins(): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return (array) get_plugins();
	}
}
