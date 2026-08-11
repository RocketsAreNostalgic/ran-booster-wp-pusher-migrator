<?php

declare(strict_types=1);

namespace RAN\BoosterWpPusherMigrator\Certification;

use InvalidArgumentException;
use JsonException;
use RuntimeException;
use stdClass;
use Throwable;

/** @return array{tag:string,commit:string} */
function read_core_certification( string $manifestPath ): array {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- CLI reads one reviewed local manifest.
	$contents = file_get_contents( $manifestPath );
	if ( false === $contents ) {
		throw new InvalidArgumentException( 'Core certification manifest is unreadable.' );
	}

	try {
		$manifest = json_decode( $contents, false, 512, JSON_THROW_ON_ERROR );
	} catch ( JsonException ) {
		throw new InvalidArgumentException( 'Core certification manifest is invalid JSON.' );
	}
	if ( ! $manifest instanceof stdClass || ! isset( $manifest->extra ) || ! $manifest->extra instanceof stdClass ) {
		throw new InvalidArgumentException( 'Core certification manifest has no extra object.' );
	}

	$key = 'ran-booster-core-certification';
	if ( ! isset( $manifest->extra->{$key} ) || ! $manifest->extra->{$key} instanceof stdClass ) {
		throw new InvalidArgumentException( 'Core certification tuple is missing or is not an object.' );
	}
	$tuple = get_object_vars( $manifest->extra->{$key} );
	$keys  = array_keys( $tuple );
	sort( $keys );
	if ( array( 'commit', 'tag' ) !== $keys ) {
		throw new InvalidArgumentException( 'Core certification tuple must contain exactly tag and commit.' );
	}

	$tag        = $tuple['tag'];
	$commit     = $tuple['commit'];
	$identifier = '(?:0|[1-9][0-9]*|[0-9A-Za-z-]*[A-Za-z-][0-9A-Za-z-]*)';
	if ( ! is_string( $tag )
		|| 1 !== preg_match( '/\Av(?:0|[1-9][0-9]*)\.(?:0|[1-9][0-9]*)\.(?:0|[1-9][0-9]*)(?:-' . $identifier . '(?:\.' . $identifier . ')*)?\z/D', $tag ) ) {
		throw new InvalidArgumentException( 'Core certification tag must be an exact v-prefixed semantic version.' );
	}
	if ( ! is_string( $commit ) || 1 !== preg_match( '/\A[0-9a-f]{40}\z/D', $commit ) ) {
		throw new InvalidArgumentException( 'Core certification commit must be 40 lowercase hexadecimal characters.' );
	}

	return array(
		'tag'    => $tag,
		'commit' => $commit,
	);
}

/** @param array{tag:string,commit:string} $certification */
function assert_core_certification_checkout( array $certification, string $headCommit, string $tagCommit ): void {
	if ( ! hash_equals( $certification['commit'], trim( $headCommit ) ) ) {
		throw new RuntimeException( 'Checked-out Core HEAD does not match the certified commit.' );
	}
	if ( ! hash_equals( $certification['commit'], trim( $tagCommit ) ) ) {
		throw new RuntimeException( 'Certified Core tag does not resolve to the certified commit.' );
	}
}

/** @param list<string> $command */
function command_output( array $command ): string {
	// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_proc_open -- CLI verifies a local Git checkout without a shell.
	$process = proc_open(
		$command,
		array(
			1 => array( 'pipe', 'w' ),
			2 => array( 'pipe', 'w' ),
		),
		$pipes
	);
	if ( ! is_resource( $process ) ) {
		throw new RuntimeException( 'Unable to start Git certification command.' );
	}
	$output = stream_get_contents( $pipes[1] );
	$error  = stream_get_contents( $pipes[2] );
	fclose( $pipes[1] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- CLI process pipe.
	fclose( $pipes[2] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- CLI process pipe.
	$status = proc_close( $process );
	if ( 0 !== $status || false === $output ) {
		unset( $error );
		throw new RuntimeException( 'Git certification command failed.' );
	}

	return trim( $output );
}

/** @param list<string> $arguments */
function run_cli( array $arguments ): int {
	$command      = $arguments[1] ?? '';
	$manifestPath = $arguments[2] ?? '';
	if ( '' === $manifestPath ) {
		throw new InvalidArgumentException( 'A Composer manifest path is required.' );
	}
	$certification = read_core_certification( $manifestPath );
	if ( 'read' === $command ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode,WordPress.Security.EscapeOutput.OutputNotEscaped -- Validated CLI JSON output.
		echo json_encode( $certification, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES ) . PHP_EOL;

		return 0;
	}
	if ( 'github-output' === $command ) {
		$outputPath = $arguments[3] ?? '';
		if ( '' === $outputPath ) {
			throw new InvalidArgumentException( 'A GitHub output path is required.' );
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- GitHub output file is the CLI contract.
		$result = file_put_contents(
			$outputPath,
			'tag=' . $certification['tag'] . PHP_EOL . 'commit=' . $certification['commit'] . PHP_EOL,
			FILE_APPEND | LOCK_EX
		);
		if ( false === $result ) {
			throw new RuntimeException( 'Unable to write Core certification outputs.' );
		}

		return 0;
	}
	if ( 'verify' === $command ) {
		$corePath = $arguments[3] ?? '';
		if ( '' === $corePath ) {
			throw new InvalidArgumentException( 'A checked-out Core path is required.' );
		}
		$headCommit = command_output( array( 'git', '-C', $corePath, 'rev-parse', 'HEAD' ) );
		$tagCommit  = command_output( array( 'git', '-C', $corePath, 'rev-parse', '--verify', 'refs/tags/' . $certification['tag'] . '^{commit}' ) );
		assert_core_certification_checkout( $certification, $headCommit, $tagCommit );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Values were strictly validated for CLI confirmation.
		echo 'Certified Booster Core ' . $certification['tag'] . ' at ' . $certification['commit'] . PHP_EOL;

		return 0;
	}

	throw new InvalidArgumentException( 'Expected read, github-output or verify command.' );
}

if ( PHP_SAPI === 'cli' && isset( $_SERVER['SCRIPT_FILENAME'] ) && realpath( (string) $_SERVER['SCRIPT_FILENAME'] ) === __FILE__ ) {
	try {
		$ran_booster_wp_pusher_migrator_certification_exit_status = run_cli( $argv );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Integer CLI exit status, not rendered output.
		exit( $ran_booster_wp_pusher_migrator_certification_exit_status );
	} catch ( Throwable $error ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite,WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI error channel.
		fwrite( STDERR, $error->getMessage() . PHP_EOL );
		exit( 1 );
	}
}
