<?php
/**
 * build_css.php
 *
 * Compiles Tailwind CSS using the standalone CLI binary located in .bin/.
 * Output is written to public/assets/css/tailwind.css (minified).
 *
 * Usage: php scripts/build_css.php
 */

// Determine project root (one level up from scripts/)
$projectRoot = dirname(__DIR__);

// Determine binary path based on OS
$os = PHP_OS_FAMILY;
$binaryName = ($os === 'Windows') ? 'tailwindcss.exe' : 'tailwindcss';
$binaryPath = $projectRoot . DIRECTORY_SEPARATOR . '.bin' . DIRECTORY_SEPARATOR . $binaryName;

// Ensure binary exists
if (!file_exists($binaryPath)) {
    fwrite(STDERR, "ERROR: Tailwind binary not found at: {$binaryPath}\n");
    fwrite(STDERR, "Run `php scripts/setup_tailwind.php` first to install it.\n");
    exit(1);
}

// Paths
$inputCss  = $projectRoot . '/resources/css/input.css';
$outputDir = $projectRoot . '/public/assets/css';
$outputCss = $outputDir . '/tailwind.css';

// Ensure input file exists
if (!file_exists($inputCss)) {
    fwrite(STDERR, "ERROR: Input CSS not found at: {$inputCss}\n");
    exit(1);
}

// Ensure output directory exists
if (!is_dir($outputDir)) {
    if (!mkdir($outputDir, 0755, true)) {
        fwrite(STDERR, "ERROR: Could not create output directory: {$outputDir}\n");
        exit(1);
    }
    echo "Created directory: {$outputDir}\n";
}

// Build the command
// Use the config file at the project root
$configPath = $projectRoot . '/tailwind.config.js';

// Escape paths for shell execution
$binaryPathQ  = escapeshellarg($binaryPath);
$configPathQ  = escapeshellarg($configPath);
$inputCssQ    = escapeshellarg($inputCss);
$outputCssQ   = escapeshellarg($outputCss);

$cmd = "{$binaryPathQ} --config {$configPathQ} -i {$inputCssQ} -o {$outputCssQ} --minify 2>&1";

echo "Building Tailwind CSS...\n";
echo "Input : {$inputCss}\n";
echo "Output: {$outputCss}\n";
echo "Config: {$configPath}\n";
echo "\n";

// Pre-flight: verify exec() is available before invoking it
if (!function_exists('exec') || in_array('exec', array_map('trim', explode(',', ini_get('disable_functions'))))) {
    fwrite(STDERR, "ERROR: exec() is not available in this PHP environment.\n");
    exit(1);
}

$output     = [];
$returnCode = 0;
exec($cmd, $output, $returnCode);

// Print any output from the binary
foreach ($output as $line) {
    echo $line . "\n";
}

if ($returnCode !== 0) {
    fwrite(STDERR, "\nERROR: Tailwind build failed (exit code: {$returnCode}).\n");
    exit($returnCode);
}

// Verify output file was created and has content
if (!file_exists($outputCss) || filesize($outputCss) === 0) {
    fwrite(STDERR, "ERROR: Output file was not created or is empty: {$outputCss}\n");
    exit(1);
}

echo "\nBuild successful!\n";
echo "Output size: " . number_format(filesize($outputCss)) . " bytes\n";
