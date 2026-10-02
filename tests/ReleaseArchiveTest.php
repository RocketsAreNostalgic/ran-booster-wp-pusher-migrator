<?php

declare(strict_types=1);

namespace Tests;

// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.system_calls_proc_open,WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents,WordPress.WP.AlternativeFunctions.file_system_operations_fclose,WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents,WordPress.WP.AlternativeFunctions.file_system_operations_mkdir,WordPress.WP.AlternativeFunctions.file_system_operations_rmdir,WordPress.WP.AlternativeFunctions.json_encode_json_encode,WordPress.WP.AlternativeFunctions.unlink_unlink -- Hostile release fixtures deliberately use local process and filesystem APIs. Naming checks remain enabled.

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ZipArchive;

final class ReleaseArchiveTest extends TestCase {
	private const PACKAGE_ROOT = 'ran-booster-wp-pusher-migrator/';

	private string $temporary = '';

	// phpcs:ignore RANOwnedMethods.NamingConventions.ValidMethodName.NotSnakeCase -- Exact PHPUnit lifecycle override.
	protected function tearDown(): void {
		if ( '' === $this->temporary || ! is_dir( $this->temporary ) ) {
			return;
		}
		$files = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $this->temporary, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $files as $file ) {
			$file->isDir() ? rmdir( $file->getPathname() ) : unlink( $file->getPathname() );
		}
		rmdir( $this->temporary );
	}

	public function test_exact_commit_build_ignores_dirty_worktree_and_passes_hostile_verification(): void {
		$fixture = $this->archive_fixture();
		$digest  = hash_file( 'sha256', $fixture['archive'] );
		file_put_contents( $fixture['repo'] . '/src/Plugin.php', "<?php\n// dirty worktree must be ignored\n" );

		self::assertSame( 0, $this->command( array( 'bash', $fixture['repo'] . '/scripts/build-release.sh', $fixture['commit'] ), $fixture['repo'] ) );
		self::assertSame( $digest, hash_file( 'sha256', $fixture['archive'] ) );
		self::assertSame( 0, $this->verify( $fixture ) );
		$metadata = json_decode( (string) file_get_contents( $fixture['metadata'] ), true, 512, JSON_THROW_ON_ERROR );
		self::assertSame( 1, $metadata['schema_version'] ?? null );
		self::assertSame( $fixture['commit'], $metadata['commit'] ?? null );
		self::assertSame( $digest, $metadata['zip_sha256'] ?? null );
	}

	/** @return iterable<string,array{callable(string):bool}> */
	public static function hostile_archive_cases(): iterable {
		yield 'unknown member' => array(
			static fn ( string $archive ): bool => self::mutate_zip( $archive, static fn ( ZipArchive $zip ): bool => $zip->addFromString( self::PACKAGE_ROOT . 'unknown.php', '<?php' ) ),
		);
		yield 'unsafe traversal' => array(
			static fn ( string $archive ): bool => self::mutate_zip( $archive, static fn ( ZipArchive $zip ): bool => $zip->addFromString( self::PACKAGE_ROOT . '../escape.php', '<?php' ) ),
		);
		yield 'noncanonical path' => array(
			static fn ( string $archive ): bool => self::mutate_zip( $archive, static fn ( ZipArchive $zip ): bool => $zip->addFromString( self::PACKAGE_ROOT . 'src//Plugin.php', '<?php' ) ),
		);
		yield 'case collision' => array(
			static fn ( string $archive ): bool => self::mutate_zip( $archive, static fn ( ZipArchive $zip ): bool => $zip->addFromString( self::PACKAGE_ROOT . 'src/plugin.php', '<?php' ) ),
		);
		yield 'symlink mode' => array(
			static function ( string $archive ): bool {
				return self::mutate_zip(
					$archive,
					static function ( ZipArchive $zip ): bool {
						$name = self::PACKAGE_ROOT . 'link';

						return $zip->addFromString( $name, 'src' )
							&& $zip->setExternalAttributesName( $name, ZipArchive::OPSYS_UNIX, 0120777 << 16 );
					}
				);
			},
		);
		yield 'executable mode' => array(
			static function ( string $archive ): bool {
				return self::mutate_zip(
					$archive,
					static function ( ZipArchive $zip ): bool {
						$name = self::PACKAGE_ROOT . 'executable.php';

						return $zip->addFromString( $name, '<?php' )
							&& $zip->setExternalAttributesName( $name, ZipArchive::OPSYS_UNIX, 0100755 << 16 );
					}
				);
			},
		);
		yield 'directory payload' => array(
			static function ( string $archive ): bool {
				return self::mutate_zip(
					$archive,
					static function ( ZipArchive $zip ): bool {
						return $zip->deleteName( self::PACKAGE_ROOT )
							&& $zip->addFromString( self::PACKAGE_ROOT, 'payload' )
							&& $zip->setExternalAttributesName( self::PACKAGE_ROOT, ZipArchive::OPSYS_UNIX, 0040755 << 16 );
					}
				);
			},
		);
		yield 'compression ratio' => array(
			static fn ( string $archive ): bool => self::mutate_zip( $archive, static fn ( ZipArchive $zip ): bool => $zip->addFromString( self::PACKAGE_ROOT . 'ratio.txt', str_repeat( '0', 1_000_000 ) ) ),
		);
		yield 'encrypted member' => array(
			static function ( string $archive ): bool {
				return self::mutate_zip(
					$archive,
					static function ( ZipArchive $zip ): bool {
						$name = self::PACKAGE_ROOT . 'encrypted.txt';

						return $zip->addFromString( $name, 'secret' )
							&& $zip->setEncryptionName( $name, ZipArchive::EM_AES_256, 'fixture-password' );
					}
				);
			},
		);
		yield 'member count' => array(
			static function ( string $archive ): bool {
				return self::mutate_zip(
					$archive,
					static function ( ZipArchive $zip ): bool {
						for ( $index = 0; $index < 130; ++$index ) {
							if ( ! $zip->addFromString( self::PACKAGE_ROOT . 'count-' . $index . '.txt', '' ) ) {
								return false;
							}
						}

						return true;
					}
				);
			},
		);
		yield 'per-member size' => array(
			static fn ( string $archive ): bool => self::mutate_zip( $archive, static fn ( ZipArchive $zip ): bool => $zip->addFromString( self::PACKAGE_ROOT . 'oversize.bin', random_bytes( 5_242_881 ) ) ),
		);
		yield 'aggregate size' => array(
			static function ( string $archive ): bool {
				return self::mutate_zip(
					$archive,
					static function ( ZipArchive $zip ): bool {
						for ( $index = 0; $index < 3; ++$index ) {
							if ( ! $zip->addFromString( self::PACKAGE_ROOT . 'aggregate-' . $index . '.bin', random_bytes( 4_000_000 ) ) ) {
								return false;
							}
						}

						return true;
					}
				);
			},
		);
		yield 'missing member' => array(
			static fn ( string $archive ): bool => self::mutate_zip( $archive, static fn ( ZipArchive $zip ): bool => $zip->deleteName( self::PACKAGE_ROOT . 'license.txt' ) ),
		);
		yield 'allowed member tampering' => array(
			static function ( string $archive ): bool {
				return self::mutate_zip(
					$archive,
					static fn ( ZipArchive $zip ): bool => $zip->deleteName( self::PACKAGE_ROOT . 'license.txt' )
						&& $zip->addFromString( self::PACKAGE_ROOT . 'license.txt', 'tampered' )
				);
			},
		);
	}

	#[DataProvider( 'hostile_archive_cases' )]
	public function test_hostile_archive_is_rejected_without_extraction( callable $mutate ): void {
		$fixture = $this->archive_fixture();
		self::assertTrue( $mutate( $fixture['archive'] ) );
		$this->refresh_integrity_files( $fixture );

		self::assertSame( 1, $this->verify( $fixture ) );
		$verifier = file_get_contents( $fixture['repo'] . '/scripts/verify-release.php' );
		self::assertIsString( $verifier );
		self::assertStringNotContainsString( 'extractTo(', $verifier );
		self::assertStringNotContainsString( 'unzip ', $verifier );
	}

	public function test_duplicate_raw_member_is_rejected(): void {
		$fixture = $this->archive_fixture();
		$python  = <<<'PYTHON'
import sys
import warnings
import zipfile
warnings.simplefilter("ignore", UserWarning)
with zipfile.ZipFile(sys.argv[1], "a") as archive:
    name = "ran-booster-wp-pusher-migrator/license.txt"
    archive.writestr(name, archive.read(name))
PYTHON;
		self::assertSame( 0, $this->command( array( 'python3', '-c', $python, $fixture['archive'] ), $fixture['repo'] ) );
		$this->refresh_integrity_files( $fixture );

		self::assertSame( 1, $this->verify( $fixture ) );
	}

	public function test_symlink_followed_by_child_is_rejected_without_writing_outside_the_archive_boundary(): void {
		$fixture           = $this->archive_fixture();
		$outside_directory = $this->temporary . '/outside-target';
		self::assertTrue(
			self::mutate_zip(
				$fixture['archive'],
				static function ( ZipArchive $zip ) use ( $outside_directory ): bool {
					$name = self::PACKAGE_ROOT . 'escape';

					return $zip->addFromString( $name, $outside_directory )
						&& $zip->setExternalAttributesName( $name, ZipArchive::OPSYS_UNIX, 0120777 << 16 )
						&& $zip->addFromString( $name . '/child.php', '<?php' );
				}
			)
		);
		$this->refresh_integrity_files( $fixture );

		self::assertSame( 1, $this->verify( $fixture ) );
		self::assertDirectoryDoesNotExist( $outside_directory );
		self::assertFileDoesNotExist( $outside_directory . '/child.php' );
	}

	public function test_checksum_metadata_and_wrong_commit_are_rejected(): void {
		$fixture = $this->archive_fixture();
		file_put_contents( $fixture['checksum'], str_repeat( '0', 64 ) . '  ' . basename( $fixture['archive'] ) . "\n" );
		self::assertSame( 1, $this->verify( $fixture ) );

		$this->refresh_integrity_files( $fixture );
		$metadata           = json_decode( (string) file_get_contents( $fixture['metadata'] ), true, 512, JSON_THROW_ON_ERROR );
		$metadata['commit'] = str_repeat( '0', 40 );
		file_put_contents( $fixture['metadata'], json_encode( $metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR ) . "\n" );
		self::assertSame( 1, $this->verify( $fixture ) );

		$this->refresh_integrity_files( $fixture );
		file_put_contents( $fixture['repo'] . '/src/Plugin.php', "<?php\n// another committed source\n" );
		self::assertSame( 0, $this->command( array( 'git', 'add', 'src/Plugin.php' ), $fixture['repo'] ) );
		self::assertSame( 0, $this->command( array( 'git', 'commit', '-q', '-m', 'second source' ), $fixture['repo'] ) );
		$other_commit = trim( $this->command_output( array( 'git', 'rev-parse', 'HEAD' ), $fixture['repo'] ) );
		self::assertNotSame( $fixture['commit'], $other_commit );
		$fixture['commit'] = $other_commit;
		self::assertSame( 1, $this->verify( $fixture ) );
	}

	/** @return array{repo:string,archive:string,checksum:string,metadata:string,commit:string} */
	private function archive_fixture(): array {
		$source          = dirname( __DIR__ );
		$this->temporary = sys_get_temp_dir() . '/ran-migrator-release-test-' . bin2hex( random_bytes( 8 ) );
		$repo            = $this->temporary . '/repository';
		mkdir( $repo, 0700, true );
		$files = preg_split( '/\R/', trim( $this->command_output( array( 'git', 'ls-files', '--cached', '--others', '--exclude-standard' ), $source ) ) );
		self::assertIsArray( $files );
		foreach ( $files as $relative ) {
			if ( '' === $relative || str_starts_with( $relative, 'dist/' ) ) {
				continue;
			}
			$target = $repo . '/' . $relative;
			if ( ! is_dir( dirname( $target ) ) ) {
				mkdir( dirname( $target ), 0700, true );
			}
			copy( $source . '/' . $relative, $target );
		}

		self::assertSame( 0, $this->command( array( 'git', 'init', '-q' ), $repo ) );
		self::assertSame( 0, $this->command( array( 'git', 'config', 'user.name', 'Release Fixture' ), $repo ) );
		self::assertSame( 0, $this->command( array( 'git', 'config', 'user.email', 'release-fixture@example.test' ), $repo ) );
		self::assertSame( 0, $this->command( array( 'git', 'add', '.' ), $repo ) );
		self::assertSame( 0, $this->command( array( 'git', 'commit', '-q', '-m', 'fixture source' ), $repo ) );
		$commit  = trim( $this->command_output( array( 'git', 'rev-parse', 'HEAD' ), $repo ) );
		$version = trim( $this->command_output( array( 'php', '-r', '$p=file_get_contents($argv[1]); preg_match("/Version: ([^\\s]+)/",$p,$m); echo $m[1];', $repo . '/ran-booster-wp-pusher-migrator.php' ), $repo ) );
		self::assertMatchesRegularExpression( '/^[0-9a-f]{40}$/', $commit );
		self::assertSame( 0, $this->command( array( 'bash', 'scripts/build-release.sh', $commit ), $repo ) );
		$archive = $repo . '/dist/ran-booster-wp-pusher-migrator-' . $version . '.zip';

		return array(
			'repo'     => $repo,
			'archive'  => $archive,
			'checksum' => $archive . '.sha256',
			'metadata' => substr( $archive, 0, -4 ) . '.json',
			'commit'   => $commit,
		);
	}

	private static function mutate_zip( string $archive, callable $mutate ): bool {
		$zip = new ZipArchive();
		if ( true !== $zip->open( $archive ) || ! $mutate( $zip ) ) {
			return false;
		}

		return $zip->close();
	}

	/** @param array{archive:string,checksum:string,metadata:string} $fixture */
	private function refresh_integrity_files( array $fixture ): void {
		$digest = hash_file( 'sha256', $fixture['archive'] );
		self::assertIsString( $digest );
		file_put_contents( $fixture['checksum'], $digest . '  ' . basename( $fixture['archive'] ) . "\n" );
		$metadata               = json_decode( (string) file_get_contents( $fixture['metadata'] ), true, 512, JSON_THROW_ON_ERROR );
		$metadata['zip_size']   = filesize( $fixture['archive'] );
		$metadata['zip_sha256'] = $digest;
		file_put_contents( $fixture['metadata'], json_encode( $metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR ) . "\n" );
	}

	/** @param array{repo:string,archive:string,commit:string} $fixture */
	private function verify( array $fixture ): int {
		return $this->command( array( 'bash', 'scripts/verify-release.sh', $fixture['archive'], $fixture['commit'] ), $fixture['repo'] );
	}

	/** @param list<string> $command */
	private function command( array $command, string $directory ): int {
		$process = proc_open(
			$command,
			array(
				1 => array( 'pipe', 'w' ),
				2 => array( 'pipe', 'w' ),
			),
			$pipes,
			$directory
		);
		self::assertIsResource( $process );
		stream_get_contents( $pipes[1] );
		stream_get_contents( $pipes[2] );
		fclose( $pipes[1] );
		fclose( $pipes[2] );

		return proc_close( $process );
	}

	/** @param list<string> $command */
	private function command_output( array $command, string $directory ): string {
		$process = proc_open(
			$command,
			array(
				1 => array( 'pipe', 'w' ),
				2 => array( 'pipe', 'w' ),
			),
			$pipes,
			$directory
		);
		self::assertIsResource( $process );
		$output = stream_get_contents( $pipes[1] );
		stream_get_contents( $pipes[2] );
		fclose( $pipes[1] );
		fclose( $pipes[2] );
		self::assertSame( 0, proc_close( $process ) );
		self::assertIsString( $output );

		return $output;
	}
}
