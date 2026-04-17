<?php
/**
 * setup_tailwind.php
 *
 * Downloads the Tailwind CSS v3 standalone CLI binary for the current OS
 * and places it in .bin/ at the project root.
 *
 * Usage: php scripts/setup_tailwind.php
 */

define('TAILWIND_VERSION', 'v3.4.17');
define('RELEASE_BASE_URL', 'https://github.com/tailwindlabs/tailwindcss/releases/download/' . TAILWIND_VERSION . '/');

// SHA-256 digests for each binary — sourced from the v3.4.17 GitHub release assets.
// Update these constants whenever TAILWIND_VERSION changes.
define('TAILWIND_SHA256', [
    'tailwindcss-windows-x64.exe' => '67f1c5e3f5a03406a7bf5badf5ada09b79f3ae78ec43450c15f7e983068da346',
    'tailwindcss-linux-x64'       => '7d24f7fa191d2193b78cd5f5a42a6093e14409521908529f42d80b11fde1f1d4',
    'tailwindcss-linux-arm64'     => '69b1378b8133192d7d2feb12a116fa12d035594f58db3eff215879e4ad8cf39b',
    'tailwindcss-macos-x64'       => '6cbdad74be776c087ffa5e9a057512c54898f9fe8828d3362212dfe32fc933a3',
    'tailwindcss-macos-arm64'     => 'a1d0c7985759accca0bf12e51ac1dcbf0f6cf2fffb62e6e0f62d091c477a10a3',
]);

// Determine project root (one level up from scripts/)
$projectRoot = dirname(__DIR__);
$binDir = $projectRoot . DIRECTORY_SEPARATOR . '.bin';

// Detect OS and architecture
$os = PHP_OS_FAMILY;

switch ($os) {
    case 'Windows':
        $binaryName = 'tailwindcss-windows-x64.exe';
        $targetName = 'tailwindcss.exe';
        break;
    case 'Darwin':
        // Detect arm64 vs x64
        $arch = trim(shell_exec('uname -m') ?: 'x86_64');
        if ($arch === 'arm64') {
            $binaryName = 'tailwindcss-macos-arm64';
        } else {
            $binaryName = 'tailwindcss-macos-x64';
        }
        $targetName = 'tailwindcss';
        break;
    default:
        // Linux (and others)
        $arch = trim(shell_exec('uname -m') ?: 'x86_64');
        if ($arch === 'aarch64' || $arch === 'arm64') {
            $binaryName = 'tailwindcss-linux-arm64';
        } else {
            $binaryName = 'tailwindcss-linux-x64';
        }
        $targetName = 'tailwindcss';
        break;
}

$downloadUrl = RELEASE_BASE_URL . $binaryName;
$targetPath  = $binDir . DIRECTORY_SEPARATOR . $targetName;

// Create .bin directory if it does not exist
if (!is_dir($binDir)) {
    if (!mkdir($binDir, 0755, true)) {
        fwrite(STDERR, "ERROR: Could not create directory: {$binDir}\n");
        exit(1);
    }
    echo "Created directory: {$binDir}\n";
}

// Skip download if binary already exists
if (file_exists($targetPath)) {
    echo "Tailwind binary already present at: {$targetPath}\n";
    echo "Delete it and re-run to force re-download.\n";
    exit(0);
}

echo "Downloading Tailwind CSS " . TAILWIND_VERSION . " for {$os}...\n";
echo "URL: {$downloadUrl}\n";

// Download via file_get_contents with stream context (no curl dependency)
$context = stream_context_create([
    'http' => [
        'method'          => 'GET',
        'follow_location' => 1,
        'max_redirects'   => 5,
        'timeout'         => 120,
        'header'          => "User-Agent: PHP setup_tailwind\r\n",
    ],
    'ssl' => [
        'verify_peer'      => true,
        'verify_peer_name' => true,
    ],
]);

$binary = file_get_contents($downloadUrl, false, $context);

if ($binary === false || strlen($binary) < 1024) {
    fwrite(STDERR, "ERROR: Download failed or file is too small. Check your internet connection.\n");
    exit(1);
}

// Verify SHA-256 digest before writing to disk (supply-chain integrity check)
$digest   = hash('sha256', $binary);
$expected = TAILWIND_SHA256[$binaryName] ?? null;
if ($expected === null || !hash_equals($expected, $digest)) {
    fwrite(STDERR, "ERROR: SHA-256 mismatch — binary may be tampered or constants are outdated.\n");
    fwrite(STDERR, "  Expected : " . ($expected ?? '(no entry for this binary)') . "\n");
    fwrite(STDERR, "  Got      : {$digest}\n");
    exit(1);
}
echo "SHA-256 verified: {$digest}\n";

// Write binary to disk
if (file_put_contents($targetPath, $binary) === false) {
    fwrite(STDERR, "ERROR: Could not write binary to: {$targetPath}\n");
    exit(1);
}

// Apply execute permission on non-Windows systems
if ($os !== 'Windows') {
    chmod($targetPath, 0755);
    echo "Applied execute permission (chmod +x).\n";
}

echo "Tailwind CLI installed successfully: {$targetPath}\n";
echo "Size: " . number_format(filesize($targetPath)) . " bytes\n";
