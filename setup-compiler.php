<?php
/**
 * Arduino CLI Compiler Setup
 * Miko IoT Platform
 * 
 * This script downloads and installs Arduino CLI on your cPanel server,
 * then installs the required board packages and libraries.
 * 
 * Run this ONCE from browser: https://esp.ashikone.com/setup-compiler.php
 * Then DELETE this file for security!
 */

require_once 'config.php';

// Only allow admin or logged-in users
if (session_status() === PHP_SESSION_NONE)
    session_start();
if (!isset($_SESSION['user_id'])) {
    die('Login required. <a href="login.php">Login</a>');
}

set_time_limit(600); // 10 minutes - installation takes time
ini_set('memory_limit', '512M');

// Fix: Explicitly set HOME for cPanel environments where it's undefined
$homeDir = getenv('HOME');
if (empty($homeDir)) {
    // Detect home directory on cPanel
    $homeDir = '/home/' . get_current_user();
}
// Set HOME so Arduino CLI can find its directory
putenv("HOME=$homeDir");
$_ENV['HOME'] = $homeDir;

$arduinoDir = $homeDir . '/arduino-cli';
$binPath = $arduinoDir . '/arduino-cli';
$configDir = $arduinoDir . '/data';
$sketchDir = $arduinoDir . '/sketches';
$outputDir = $arduinoDir . '/output';
$tmpDir = $homeDir . '/tmp';

// Create directories
@mkdir($arduinoDir, 0755, true);
@mkdir($configDir, 0755, true);
@mkdir($sketchDir, 0755, true);
@mkdir($outputDir, 0755, true);
@mkdir($tmpDir, 0755, true);

// Fix: Set TMPDIR to home tmp (cPanel mounts /tmp with noexec, which blocks shared libs)
putenv("TMPDIR=$tmpDir");
putenv("TEMP=$tmpDir");
putenv("TMP=$tmpDir");
$_ENV['TMPDIR'] = $tmpDir;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compiler Setup - <?php echo SITE_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f8fafc; color: #0f172a; padding: 40px 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        h1 { color: #16a34a; margin-bottom: 8px; font-size: 1.5rem; }
        .subtitle { color: #64748b; margin-bottom: 30px; }
        .step { background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin-bottom: 16px; }
        .step-header { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
        .step-num { width: 32px; height: 32px; border-radius: 50%; background: #dcfce7; color: #16a34a; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.875rem; }
        .step-num.done { background: #16a34a; color: white; }
        .step-num.error { background: #ef4444; color: white; }
        .step-title { font-weight: 600; }
        .step-output { background: #0f172a; color: #22c55e; padding: 14px; border-radius: 8px; font-family: 'JetBrains Mono', monospace; font-size: 0.75rem; line-height: 1.8; white-space: pre-wrap; word-break: break-all; max-height: 300px; overflow-y: auto; }
        .step-output .err { color: #ef4444; }
        .step-output .info { color: #3b82f6; }
        .step-output .warn { color: #f59e0b; }
        .success { color: #16a34a; font-weight: 600; }
        .error { color: #ef4444; font-weight: 600; }
        .warning { background: #fef3c7; border: 1px solid #fde68a; border-radius: 8px; padding: 14px; margin-top: 20px; font-size: 0.875rem; color: #92400e; }
        .btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; background: #16a34a; color: white; border: none; border-radius: 8px; font-family: inherit; font-size: 0.875rem; font-weight: 600; cursor: pointer; margin-top: 16px; }
        .btn:hover { background: #15803d; }
    </style>
</head>
<body>
<div class="container">
    <h1>⚙️ Arduino CLI Compiler Setup</h1>
    <p class="subtitle">Installing Arduino CLI, board packages, and libraries for server-side compilation.</p>

<?php

echo '<div class="step"><div class="step-header"><div class="step-num">1</div><div class="step-title">System Info</div></div>';
echo '<div class="step-output">';
echo "Home Directory: $homeDir\n";
echo "Arduino CLI Dir: $arduinoDir\n";
echo "PHP User: " . get_current_user() . "\n";
echo "OS: " . php_uname() . "\n";
echo "Architecture: " . php_uname('m') . "\n";
echo "TMPDIR: $tmpDir\n";
echo '</div></div>';

// Helper: prefix all arduino-cli commands with HOME= and TMPDIR=
$envPrefix = 'HOME=' . escapeshellarg($homeDir) . ' TMPDIR=' . escapeshellarg($tmpDir) . ' ';

flush();

// Step 2: Download Arduino CLI
echo '<div class="step"><div class="step-header"><div class="step-num">2</div><div class="step-title">Download Arduino CLI</div></div>';
echo '<div class="step-output">';

if (file_exists($binPath) && is_executable($binPath)) {
    echo '<span class="info">Arduino CLI already exists at: ' . $binPath . '</span>' . "\n";
    $version = shell_exec($envPrefix . $binPath . ' version 2>&1');
    echo "Version: $version\n";
    echo '<span class="info">Skipping download.</span>';
}
else {
    $arch = php_uname('m');
    if (strpos($arch, 'x86_64') !== false || strpos($arch, 'amd64') !== false) {
        $downloadUrl = 'https://downloads.arduino.cc/arduino-cli/arduino-cli_latest_Linux_64bit.tar.gz';
    }
    elseif (strpos($arch, 'aarch64') !== false || strpos($arch, 'arm64') !== false) {
        $downloadUrl = 'https://downloads.arduino.cc/arduino-cli/arduino-cli_latest_Linux_ARM64.tar.gz';
    }
    else {
        $downloadUrl = 'https://downloads.arduino.cc/arduino-cli/arduino-cli_latest_Linux_32bit.tar.gz';
    }

    echo "Downloading from: $downloadUrl\n";
    $tarPath = $arduinoDir . '/arduino-cli.tar.gz';

    // Download
    $ch = curl_init($downloadUrl);
    $fp = fopen($tarPath, 'w');
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    fclose($fp);

    if ($result && $httpCode == 200 && filesize($tarPath) > 1000) {
        echo "Downloaded: " . number_format(filesize($tarPath)) . " bytes\n";

        // Extract
        echo "Extracting...\n";
        $extractCmd = "cd " . escapeshellarg($arduinoDir) . " && tar xzf arduino-cli.tar.gz 2>&1";
        $extractOutput = shell_exec($extractCmd);
        echo $extractOutput . "\n";

        // Make executable
        chmod($binPath, 0755);

        // Cleanup
        @unlink($tarPath);

        if (file_exists($binPath)) {
            $version = shell_exec($envPrefix . $binPath . ' version 2>&1');
            echo '<span class="info">Installed: ' . trim($version) . '</span>';
        }
        else {
            echo '<span class="err">ERROR: Binary not found after extraction!</span>';
        }
    }
    else {
        echo '<span class="err">Download failed! HTTP code: ' . $httpCode . '</span>' . "\n";
        echo '<span class="warn">Try manually downloading and placing arduino-cli at: ' . $binPath . '</span>';
    }
}
echo '</div></div>';
flush();

// Step 3: Initialize Arduino CLI config
echo '<div class="step"><div class="step-header"><div class="step-num">3</div><div class="step-title">Initialize Configuration</div></div>';
echo '<div class="step-output">';

// Set environment for Arduino CLI
putenv("ARDUINO_DATA_DIR=$configDir");
putenv("ARDUINO_DOWNLOADS_DIR=$configDir/staging");
putenv("ARDUINO_SKETCHBOOK_DIR=$sketchDir");

// Create config file
$configFile = $arduinoDir . '/arduino-cli.yaml';
$configContent = "board_manager:\n  additional_urls:\n    - https://raw.githubusercontent.com/espressif/arduino-esp32/gh-pages/package_esp32_index.json\n    - http://arduino.esp8266.com/stable/package_esp8266com_index.json\ndirectories:\n  data: $configDir\n  downloads: $configDir/staging\n  user: $sketchDir\n";
file_put_contents($configFile, $configContent);
echo "Config written to: $configFile\n";
echo $configContent . "\n";

// Update core index
echo "Updating board index...\n";
$output = shell_exec($envPrefix . $binPath . ' --config-file ' . escapeshellarg($configFile) . ' core update-index 2>&1');
echo $output . "\n";
echo '<span class="info">Board index updated.</span>';

echo '</div></div>';
flush();

// Step 4: Install ESP32 board package (Downgraded to 2.0.17 for shared hosting stability)
echo '<div class="step"><div class="step-header"><div class="step-num">4</div><div class="step-title">Install ESP32 Board Package</div></div>';
echo '<div class="step-output">';
echo "Installing esp32:esp32@2.0.11 (lighter version for shared hosting)...\n";
flush();

// Attempt to install specific lighter version
$output = shell_exec($envPrefix . $binPath . ' --config-file ' . escapeshellarg($configFile) . ' core install esp32:esp32@2.0.11 2>&1');
echo $output . "\n";

// Verify
$output = shell_exec($envPrefix . $binPath . ' --config-file ' . escapeshellarg($configFile) . ' core list 2>&1');
echo "\nInstalled boards:\n$output";

echo '</div></div>';
flush();

// Step 5: Install ESP8266 board package
echo '<div class="step"><div class="step-header"><div class="step-num">5</div><div class="step-title">Install ESP8266 Board Package</div></div>';
echo '<div class="step-output">';
echo "Installing esp8266:esp8266 (this may take 2-5 minutes)...\n";
flush();

$output = shell_exec($envPrefix . $binPath . ' --config-file ' . escapeshellarg($configFile) . ' core install esp8266:esp8266 2>&1');
echo $output . "\n";

// Verify
$output = shell_exec($binPath . ' --config-file ' . escapeshellarg($configFile) . ' core list 2>&1');
echo "\nInstalled boards:\n$output";

echo '</div></div>';
flush();

// Step 6: Install common libraries
echo '<div class="step"><div class="step-header"><div class="step-num">6</div><div class="step-title">Install Arduino Libraries</div></div>';
echo '<div class="step-output">';

$libraries = [
    'ArduinoJson',
    'DHT sensor library',
    'Adafruit Unified Sensor',
    'WiFiManager',
    'PubSubClient',
    'Adafruit NeoPixel',
    'OneWire',
    'DallasTemperature',
    'LiquidCrystal I2C',
    'Servo',
    'IRremoteESP8266',
    'WebSockets',
    'AsyncTCP',
    'ESPAsyncWebServer',
    'Blynk',
    'FastLED'
];

foreach ($libraries as $lib) {
    echo "Installing: $lib ... ";
    flush();
    $output = shell_exec($envPrefix . $binPath . ' --config-file ' . escapeshellarg($configFile) . ' lib install ' . escapeshellarg($lib) . ' 2>&1');
    if (strpos($output, 'error') !== false || strpos($output, 'Error') !== false) {
        echo '<span class="warn">SKIPPED</span> (' . trim($output) . ")\n";
    }
    else {
        echo '<span class="info">OK</span>' . "\n";
    }
    flush();
}

// List installed libraries
echo "\nInstalled libraries:\n";
$output = shell_exec($envPrefix . $binPath . ' --config-file ' . escapeshellarg($configFile) . ' lib list 2>&1');
echo $output;

echo '</div></div>';
flush();

// Step 7: Fix esptool (shared hosting can't run the PyInstaller binary)
echo '<div class="step"><div class="step-header"><div class="step-num">7</div><div class="step-title">Fix esptool for Shared Hosting</div></div>';
echo '<div class="step-output">';

// The bundled esptool is a PyInstaller binary that can't load libz.so.1 on cPanel
// Fix: Install esptool via pip3 and replace the binary with a wrapper script

// Check if python3 exists
$python3 = trim(shell_exec('which python3 2>/dev/null') ?: '');
$pip3 = trim(shell_exec('which pip3 2>/dev/null') ?: '');

echo "Python3: " . ($python3 ?: 'NOT FOUND') . "\n";
echo "Pip3: " . ($pip3 ?: 'NOT FOUND') . "\n";

if (empty($python3)) {
    // Try alternative python paths on cPanel
    $altPaths = ['/usr/bin/python3', '/usr/local/bin/python3', '/opt/cpanel/ea-python39/root/usr/bin/python3'];
    foreach ($altPaths as $p) {
        if (file_exists($p) && is_executable($p)) {
            $python3 = $p;
            echo '<span class="info">Found Python3 at: ' . $p . '</span>' . "\n";
            break;
        }
    }
}

if (!empty($python3)) {
    // Install esptool via pip to user's home
    $pipInstallDir = $homeDir . '/.local';
    echo "Installing esptool via pip3...\n";
    flush();

    $pipCmd = $envPrefix . $python3 . ' -m pip install --user esptool 2>&1';
    $pipOutput = shell_exec($pipCmd);
    echo $pipOutput . "\n";

    // Find the pip-installed esptool
    $pipEsptool = $homeDir . '/.local/bin/esptool.py';
    $pipEsptool2 = $homeDir . '/.local/bin/esptool';

    if (!file_exists($pipEsptool) && !file_exists($pipEsptool2)) {
        // Try site-packages
        echo "Searching for esptool in pip packages...\n";
        $findCmd = 'find ' . escapeshellarg($homeDir) . '/.local -name "esptool.py" -type f 2>/dev/null | head -5';
        $found = trim(shell_exec($findCmd) ?: '');
        echo "Found: $found\n";
        if (!empty($found)) {
            $pipEsptool = explode("\n", $found)[0];
        }
    }

    $esptoolScript = file_exists($pipEsptool) ? $pipEsptool : (file_exists($pipEsptool2) ? $pipEsptool2 : '');
    echo "Pip esptool location: " . ($esptoolScript ?: 'NOT FOUND') . "\n";

    // Find ALL esptool binaries in the Arduino data directory and replace with wrapper
    $esptoolDirs = glob($configDir . '/packages/*/tools/esptool_py/*/');
    echo "\nFound " . count($esptoolDirs) . " esptool installation(s):\n";

    foreach ($esptoolDirs as $dir) {
        $originalEsptool = $dir . 'esptool';
        echo "  → $dir\n";

        if (file_exists($originalEsptool)) {
            // Backup original
            $backupPath = $originalEsptool . '.original';
            if (!file_exists($backupPath)) {
                rename($originalEsptool, $backupPath);
                echo '    <span class="info">Backed up original binary</span>' . "\n";
            }

            // Backup original esptool.py if not already done
            $backupEsptool = $dir . '/esptool.real.py';
            if (!file_exists($backupEsptool)) {
                rename($originalEsptool, $backupEsptool);
            }

            // Create Python-based wrapper (much safer than Bash on shared hosting)
            $pyWrapper = "#!$python3\n";
            $pyWrapper .= "import sys\nimport os\nimport subprocess\n\n";
            $pyWrapper .= "os.environ['HOME'] = " . var_export($homeDir, true) . "\n";
            $pyWrapper .= "os.environ['TMPDIR'] = " . var_export($tmpDir, true) . "\n";
            $pyWrapper .= "new_args = []\n";
            $pyWrapper .= "for arg in sys.argv[1:]:\n";
            $pyWrapper .= "    if arg == '--flash-mode': new_args.append('--flash_mode')\n";
            $pyWrapper .= "    elif arg == '--flash-freq': new_args.append('--flash_freq')\n";
            $pyWrapper .= "    elif arg == '--flash-size': new_args.append('--flash_size')\n";
            $pyWrapper .= "    else: new_args.append(arg)\n\n";
            $pyWrapper .= "# Log for debugging\n";
            $pyWrapper .= "with open(" . var_export($arduinoDir . '/esptool_debug.log', true) . ", 'a') as f:\n";
            $pyWrapper .= "    f.write(f\"{sys.executable} {sys.argv[0]} {' '.join(new_args)}\\n\")\n\n";
            $pyWrapper .= "real_esptool = " . var_export($backupEsptool, true) . "\n";
            $pyWrapper .= "os.execv(sys.executable, [sys.executable, real_esptool] + new_args)\n";

            file_put_contents($originalEsptool, $pyWrapper);
            chmod($originalEsptool, 0755);
            echo '    <span class="info">✓ Created Python wrapper script (v2)</span>' . "\n";
        }
    }

    // Test the wrapper
    if (!empty($esptoolDirs)) {
        $testEsptool = $esptoolDirs[0] . 'esptool';
        echo "\nTesting esptool wrapper...\n";
        $testOutput = shell_exec($envPrefix . escapeshellarg($testEsptool) . ' version 2>&1');
        echo "esptool version: $testOutput\n";

        if (strpos($testOutput, 'esptool') !== false) {
            echo '<span class="info">✓ esptool wrapper is working!</span>' . "\n";
        }
        else {
            echo '<span class="warn">esptool wrapper test returned unexpected output</span>' . "\n";
        }
    }
}
else {
    echo '<span class="err">Python3 not found! Cannot fix esptool.</span>' . "\n";
    echo '<span class="warn">Contact your hosting provider to enable Python3.</span>' . "\n";
}

echo '</div></div>';
flush();

// Step 8: Test compilation
echo '<div class="step"><div class="step-header"><div class="step-num">8</div><div class="step-title">Test Compilation</div></div>';
echo '<div class="step-output">';

$testSketchDir = $sketchDir . '/test_blink';
@mkdir($testSketchDir, 0755, true);
$testCode = '
void setup() {
    Serial.begin(115200);
    pinMode(2, OUTPUT);
}

void loop() {
    digitalWrite(2, HIGH);
    delay(1000);
    digitalWrite(2, LOW);
    delay(1000);
    Serial.println("Blink!");
}
';
file_put_contents($testSketchDir . '/test_blink.ino', $testCode);

echo "Compiling test sketch for ESP32 (Single Thread Mode)...\n";
flush();
$compileCmd = $envPrefix . $binPath . ' --config-file ' . escapeshellarg($configFile)
    . ' compile --jobs 1 --fqbn esp32:esp32:esp32 --output-dir ' . escapeshellarg($outputDir)
    . ' ' . escapeshellarg($testSketchDir) . ' 2>&1';
$output = shell_exec($compileCmd);
echo $output . "\n";

if (file_exists($outputDir . '/test_blink.ino.bin')) {
    $binSize = filesize($outputDir . '/test_blink.ino.bin');
    echo '<span class="info">✓ Compilation SUCCESSFUL! Binary size: ' . number_format($binSize) . ' bytes</span>' . "\n";
}
else {
    echo '<span class="err">✗ Compilation failed.</span>' . "\n";
    echo "Check the debug log for more info: <code>$arduinoDir/esptool_debug.log</code>\n";
    echo '<span class="warn">If the error persists, check if your hosting limits subprocess execution or try another board.</span>' . "\n";
}

// Cleanup test files
@unlink($testSketchDir . '/test_blink.ino');
@rmdir($testSketchDir);
array_map('unlink', glob($outputDir . '/*'));

echo '</div></div>';
flush();

// Step 9: Save configuration path
echo '<div class="step"><div class="step-header"><div class="step-num">9</div><div class="step-title">Save Compiler Config</div></div>';
echo '<div class="step-output">';

$compilerConfig = [
    'home_dir' => $homeDir,
    'arduino_cli_path' => $binPath,
    'config_file' => $configFile,
    'sketch_dir' => $sketchDir,
    'output_dir' => $outputDir,
    'installed_at' => date('Y-m-d H:i:s'),
    'boards' => [
        'esp32' => 'esp32:esp32:esp32',
        'esp32-devkit-v1' => 'esp32:esp32:esp32doit-devkit-v1',
        'esp8266' => 'esp8266:esp8266:nodemcuv2',
        'arduino-uno' => 'arduino:avr:uno'
    ]
];

$configPath = __DIR__ . '/compiler-config.json';
file_put_contents($configPath, json_encode($compilerConfig, JSON_PRETTY_PRINT));
echo "Compiler config saved to: $configPath\n\n";
echo json_encode($compilerConfig, JSON_PRETTY_PRINT);

echo '</div></div>';

?>

<div class="step" style="border-color: #16a34a; background: #f0fdf4;">
    <div class="step-header">
        <div class="step-num done">✓</div>
        <div class="step-title success">Setup Complete!</div>
    </div>
    <p style="margin-top: 8px; color: #475569; font-size: 0.875rem;">
        Arduino CLI is now installed on your server. You can now compile and upload code to ESP devices directly from the Miko dashboard.
    </p>
    <a href="upload-code.php" class="btn">← Back to Upload Code</a>
</div>

<div class="warning">
    ⚠️ <strong>Security Notice:</strong> Delete this file (<code>setup-compiler.php</code>) after installation for security. 
    You only need to run this once.
</div>

</div>
</body>
</html>
