<?php
/**
 * @package    BlueX_Geohash
 * @subpackage UnitTests
 */

/**
 * Define the main method
 */
if (!defined('PHPUnit_MAIN_METHOD')) {
    define('PHPUnit_MAIN_METHOD', 'BlueX_Geohash_AllTests::main');
}

require_once 'PHPUnit/Framework.php';
require_once 'PHPUnit/TextUI/TestRunner.php';

/**
 * @package    BlueX_Geohash
 * @subpackage UnitTests
 */
class BlueX_Geohash_AllTests
{
    private static $_file;
    private static $_package;

    /**
     * Main entry point for running the suite.
     */
    public static function main($package = null, $file = null)
    {
        if ($package) {
            self::$_package = $package;
        }
        if ($file) {
            self::$_file = $file;
        }
        PHPUnit_TextUI_TestRunner::run(self::suite());
    }

    /**
     * Initialize the test suite class.
     *
     * @param string $package The name of the package tested by this suite.
     * @param string $file    The path of the AllTests class.
     *
     * @return NULL
     */
    public static function init($package, $file)
    {
        self::$_package = $package;
        self::$_file = $file;
    }

    /**
     * Collect the unit tests of this directory into a new suite.
     *
     * @return PHPUnit_Framework_TestSuite The test suite.
     */
    public static function suite()
    {
        // Catch strict standards
        error_reporting(E_ALL | E_STRICT);

        // Set up autoload
        $basedir = dirname(self::$_file);
        set_include_path($basedir . '/../../../lib' . PATH_SEPARATOR . get_include_path());
        if (!spl_autoload_functions()) {
            spl_autoload_register(
                create_function(
                    '$class',
                    '$filename = str_replace(array(\'::\', \'_\'), \'/\', $class);'
                    . '$err_mask = E_ALL ^ E_WARNING;'
                    . '$oldErrorReporting = error_reporting($err_mask);'
                    . 'include "$filename.php";'
                    . 'error_reporting($oldErrorReporting);'
                )
            );
        }

        $suite = new PHPUnit_Framework_TestSuite(self::$_package);
        $baseregexp = preg_quote($basedir . DIRECTORY_SEPARATOR, '/');

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($basedir)) as $file) {
            if ($file->isFile() && preg_match('/Test.php$/', $file->getFilename())) {
                $pathname = $file->getPathname();
                if (require $pathname) {
                    $class = str_replace(DIRECTORY_SEPARATOR, '_',
                                         preg_replace("/^$baseregexp(.*)\.php/", '\\1', $pathname));
                    $suite->addTestSuite(self::$_package . '_' . $class);
                }
            }
        }

        return $suite;
    }
}

BlueX_Geohash_AllTests::init('BlueX_Geohash', __FILE__);

if (PHPUnit_MAIN_METHOD == 'BlueX_Geohash_AllTests::main') {
    BlueX_Geohash_AllTests::main();
}
