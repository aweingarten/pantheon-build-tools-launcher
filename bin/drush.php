<?php

use Composer\XdebugHandler\XdebugHandler;
use DrupalFinder\DrupalFinder;
use Webmozart\PathUtil\Path;
use Humbug\SelfUpdate\Updater;

set_time_limit(0);

$autoloaders = [
  __DIR__ . '/../../../autoload.php',
  __DIR__ . '/../vendor/autoload.php'
];

foreach ($autoloaders as $file) {
  if (file_exists($file)) {
    $autoloader = $file;
    break;
  }
}

if (isset($autoloader)) {
  require_once $autoloader;
}
else {
  echo 'You must set up the project dependencies using `composer install`' . PHP_EOL;
  exit(1);
}

$PBT_LAUNCHER_VERSION = '@git-version@';

$ROOT = FALSE;
$DEBUG = FALSE;
$VAR = FALSE;
$VERSION = FALSE;
$VERSION_LAUNCHER = FALSE;
$PBT_VERSION = NULL;
$SELF_UPDATE = FALSE;
$FALLBACK = getenv('PBT_LAUNCHER_FALLBACK') ?: FALSE;

foreach ($_SERVER['argv'] as $arg) {
  // If a variable to set was indicated on the
  // previous iteration, then set the value of
  // the named variable (e.g. "ROOT") to "$arg".
  if ($VAR) {
    $$VAR = "$arg";
    $VAR = FALSE;
  }
  else {
    switch ($arg) {
      case "-r":
        $VAR = "ROOT";
        break;
      case "--debug":
        $DEBUG = TRUE;
        break;
      case "--version":
        $VERSION = TRUE;
        break;
      case "--pbt-launcher-version":
        $VERSION_LAUNCHER = TRUE;
        break;
      case "self-update":
        $SELF_UPDATE = TRUE;
        break;
    }
    if (substr($arg, 0, 7) == "--root=") {
      $ROOT = substr($arg, 7);
    }
  }
}

if ($ROOT === FALSE) {
  $ROOT = getcwd();
}
else {
  $ROOT = Path::canonicalize($ROOT);
}

$drupalFinder = new DrupalFinder();

if ($VERSION || $VERSION_LAUNCHER || $DEBUG || $SELF_UPDATE) {
  echo "Pantheon Build Tools Launcher Version: {$PBT_LAUNCHER_VERSION}" .  PHP_EOL;
}

if ($VERSION_LAUNCHER) {
  exit(0);
}

if ($SELF_UPDATE) {
  if ($PBT_LAUNCHER_VERSION === '@' . 'git-version' . '@') {
    echo "Automatic update not supported.\n";
    exit(1);
  }
  $updater = new Updater(null, false);
  $updater->setStrategy(Updater::STRATEGY_GITHUB);
  $updater->getStrategy()->setPackageName('pbt/pbt-launcher');
  $updater->getStrategy()->setPharName('pbt.phar');
  $updater->getStrategy()->setCurrentLocalVersion($PBT_LAUNCHER_VERSION);
  try {
    $result = $updater->update();
    echo $result ? "Updated!\n" : "No update needed!\n";
    exit(0);
  } catch (\Exception $e) {
    echo "Automatic update failed, please download the latest version from https://github.com/pbt-ops/pbt-launcher/releases\n";
    exit(1);
  }
}

if ($DEBUG) {
  echo "ROOT: " . $ROOT . PHP_EOL;
}

if ($drupalFinder->locateRoot($ROOT)) {
  $drupalRoot = $drupalFinder->getDrupalRoot();

  // Detect Pantheon Build Tools version
  $pbt_info_file = Path::join($drupalFinder->getVendorDir(), 'pbt/pbt/pbt.info');
  if (file_exists($pbt_info_file)) {
    $pbt_info_values = parse_ini_file($pbt_info_file);
    if (isset($pbt_info_values['pbt_version'])) {
      list($PBT_VERSION) = explode('.', $pbt_info_values['pbt_version'], 2);
      $PBT_VERSION = (int) $PBT_VERSION;
    }
  }

  if ($PBT_VERSION === 10 || $PBT_VERSION === 9) {
    $xdebug = new XdebugHandler('pbt', '--ansi');
    $xdebug->check();
    unset($xdebug);
  }

  if ($DEBUG) {
    echo "PBT VERSION: " . $PBT_VERSION . PHP_EOL;
    echo "DRUPAL ROOT: " . $drupalRoot . PHP_EOL;
    echo "COMPOSER ROOT: " . $drupalFinder->getComposerRoot() . PHP_EOL;
    echo "VENDOR ROOT: " . $drupalFinder->getVendorDir() . PHP_EOL;
  }

  if ($PBT_VERSION === 10 || $PBT_VERSION === 9) {
    require_once $drupalFinder->getVendorDir() . '/pbt/pbt/includes/preflight.inc';
    // Pantheon Build Tools 10 and 9 manages two autoloaders.
    exit(pbt_main());
  }
  if ($PBT_VERSION === 8) {
    if (file_exists($drupalRoot . '/autoload.php')) {
      require_once $drupalRoot . '/autoload.php';
    }
    else {
      require_once $drupalFinder->getVendorDir() . '/autoload.php';
    }
    require_once $drupalFinder->getVendorDir() . '/pbt/pbt/includes/preflight.inc';
    require_once $drupalFinder->getVendorDir() . '/pbt/pbt/includes/context.inc';
    pbt_set_option('root', $drupalRoot);
    exit(pbt_main());
  }
  if (!$PBT_VERSION && !$FALLBACK) {
    echo 'The Pantheon Build Tools launcher could not find a local Pantheon Build Tools in your Drupal site.' . PHP_EOL;
    echo 'Please add Pantheon Build Tools with Composer to your project.' . PHP_EOL;
    echo 'Run \'cd "' . $drupalFinder->getComposerRoot() . '" && composer require pbt/pbt\'' . PHP_EOL;
    exit(1);
  }
}

if ($FALLBACK) {
  $args = array_map('prepareArgument', $_SERVER['argv']);
  $cmd = $FALLBACK . ' ' . implode(' ', $args);
  if ($DEBUG) {
    echo "Calling fallback: ". $cmd . PHP_EOL;
  }
  $process = proc_open($cmd, [0 => STDIN, 1 => STDOUT, 2 => STDERR], $pipes);
  $proc_status = proc_get_status($process);
  $exit_code = proc_close($process);
  $exit_code = $proc_status["running"] ? $exit_code : $proc_status["exitcode"];
  exit($exit_code);
}

echo 'The Pantheon Build Tools launcher could not find a Drupal site to operate on. Please do *one* of the following:' . PHP_EOL;
echo '  - Navigate to any where within your Drupal project and try again.' . PHP_EOL;
echo '  - Add --root=/path/to/drupal so Pantheon Build Tools knows where your site is located.' . PHP_EOL;
exit(1);

/**
 * Escape the argument unless it is not suitable for passing to the Pantheon Build Tools fallback.
 *
 * @param string $argument
 *
 * @return string|void
 */
function prepareArgument($argument) {
  static $first = true;
  if ($first) {
    // Skip first argument as it is the pbt-launcher path.
    $first = false;
    return;
  }
  return escapeshellarg($argument);
}
