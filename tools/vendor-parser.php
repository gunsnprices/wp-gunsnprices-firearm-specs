<?php
/**
 * Copies the gunsnprices PHP parser into lib/gunsnprices/ under a plugin-specific namespace.
 *
 * Usage: php tools/vendor-parser.php [path/to/gunsnprices-php]
 *
 * The library (https://github.com/gunsnprices/gunsnprices-php, Composer gunsnprices/gunsnprices) is six classes in
 * the GunsNPrices and GunsNPrices\Internal namespaces with no dependencies. Every class reference in it is either a
 * `namespace` line, a `use` line or a global class (\RuntimeException, \LogicException), so the prefix is applied by
 * rewriting those lines and the result is checked with the PHP tokenizer: any name that still starts with the
 * original namespace stops the script.
 *
 * Dev-only file, not shipped in the plugin zip.
 */

if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}

$src    = isset( $argv[1] ) ? rtrim( $argv[1], '/' ) : dirname( __DIR__, 2 ) . '/gunsnprices-php';
$dest   = dirname( __DIR__ ) . '/lib/gunsnprices';
$from   = 'GunsNPrices';
$prefix = 'GunsNPricesFirearmSpecs\\Vendor\\GunsNPrices';

if ( ! is_file( "$src/src/Parser.php" ) ) {
	fwrite( STDERR, "Parser source not found in $src\n" );
	exit( 1 );
}

/** Removes a directory tree. */
function gnpfs_dev_rmtree( $dir ) {
	if ( ! is_dir( $dir ) ) {
		return;
	}
	foreach ( scandir( $dir ) as $f ) {
		if ( '.' === $f || '..' === $f ) {
			continue;
		}
		$p = "$dir/$f";
		is_dir( $p ) ? gnpfs_dev_rmtree( $p ) : unlink( $p );
	}
	rmdir( $dir );
}

gnpfs_dev_rmtree( $dest );
mkdir( "$dest/src/Internal", 0755, true );
mkdir( "$dest/data", 0755, true );

$php_files = array_merge( array( 'src/Parser.php' ), array_map( fn( $p ) => 'src/Internal/' . basename( $p ), glob( "$src/src/Internal/*.php" ) ) );
sort( $php_files );

$version = '';
foreach ( $php_files as $rel ) {
	$code = file_get_contents( "$src/$rel" );
	$out  = preg_replace(
		array(
			'/^namespace ' . $from . '(\\\\|;)/m',
			'/^use ' . $from . '\\\\/m',
		),
		array(
			'namespace ' . str_replace( '\\', '\\\\', $prefix ) . '$1',
			'use ' . str_replace( '\\', '\\\\', $prefix ) . '\\\\',
		),
		$code
	);
	// Check every namespaced name with the tokenizer.
	foreach ( token_get_all( $out ) as $t ) {
		if ( is_array( $t ) && in_array( $t[0], array( T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED ), true ) ) {
			$name = ltrim( $t[1], '\\' );
			if ( 0 === strpos( $name, $from . '\\' ) ) {
				fwrite( STDERR, "Unprefixed name $t[1] in $rel line $t[2]\n" );
				exit( 1 );
			}
		}
	}
	if ( 'src/Parser.php' === $rel && preg_match( "/const VERSION = '([^']+)'/", $out, $m ) ) {
		$version = $m[1];
	}
	$header = "<?php\n/*\n * Vendored copy of gunsnprices/gunsnprices (MIT License, see ../LICENSE) for the GunsNPrices Firearm Specs plugin.\n"
		. " * Modified only by tools/vendor-parser.php: the namespace GunsNPrices was renamed to $prefix.\n */\n";
	$out    = preg_replace( '/^<\?php\s*\n/', $header, $out, 1 );
	file_put_contents( "$dest/$rel", $out );
}

foreach ( array( 'calibers', 'models', 'entities', 'lowercase', 'source' ) as $name ) {
	$json = file_get_contents( "$src/data/$name.json" );
	if ( ! is_array( json_decode( $json, true ) ) ) {
		fwrite( STDERR, "Bad JSON: data/$name.json\n" );
		exit( 1 );
	}
	file_put_contents( "$dest/data/$name.json", $json );
}
foreach ( array( 'LICENSE', 'LICENSE-DATA' ) as $f ) {
	copy( "$src/$f", "$dest/$f" );
}
file_put_contents(
	"$dest/README.txt",
	"gunsnprices $version, PHP title parser for firearms and ammunition.\n"
	. "Source: https://github.com/gunsnprices/gunsnprices-php (Composer: gunsnprices/gunsnprices)\n\n"
	. "Copied by tools/vendor-parser.php. The only change is the namespace: GunsNPrices\\... became\n"
	. "$prefix\\... so this copy cannot clash with another plugin that ships the same library.\n\n"
	. "Code: MIT License (LICENSE). Data files data/models.json and data/calibers.json: CC BY 4.0,\n"
	. "GunsNPrices (https://gunsnprices.com), unchanged (LICENSE-DATA).\n"
);

echo "Vendored gunsnprices $version into lib/gunsnprices (" . count( $php_files ) . " PHP files)\n";
