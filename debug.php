<?php
/**
 * Debug script - Upload to server to check heartbeat status
 * DELETE this file after debugging!
 */
require_once 'config.php';
$pdo = getDB();

header('Content-Type: text/html; charset=utf-8');
echo "<h2>ESP IoT Debug Info</h2>";

// Check timezones
echo "<h3>Timezone Check</h3>";
echo "<p><b>PHP timezone:</b> " . date_default_timezone_get() . "</p>";
echo "<p><b>PHP time():</b> " . time() . " → " . date('Y-m-d H:i:s') . "</p>";

$stmt = $pdo->query("SELECT NOW() as mysql_now, UNIX_TIMESTAMP() as mysql_ts");
$row = $stmt->fetch();
echo "<p><b>MySQL NOW():</b> " . $row['mysql_now'] . "</p>";
echo "<p><b>MySQL UNIX_TIMESTAMP:</b> " . $row['mysql_ts'] . "</p>";
echo "<p><b>PHP vs MySQL difference:</b> " . (time() - $row['mysql_ts']) . " seconds</p>";

// Check heartbeat records
echo "<h3>Heartbeat Records</h3>";
$stmt = $pdo->query("SELECT * FROM esp_heartbeat ORDER BY last_seen DESC LIMIT 5");
$rows = $stmt->fetchAll();
if (empty($rows)) {
    echo "<p style='color:red'><b>NO heartbeat records found!</b> The ESP has never successfully polled the server.</p>";
} else {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Project ID</th><th>Last Seen</th><th>IP</th><th>WiFi SSID</th><th>RSSI</th><th>Seconds Ago</th><th>Online?</th></tr>";
    foreach ($rows as $r) {
        $lastSeen = strtotime($r['last_seen']);
        $diff = time() - $lastSeen;
        $online = $diff < 30 ? 'YES ✅' : 'NO ❌';
        echo "<tr>";
        echo "<td>{$r['project_id']}</td>";
        echo "<td>{$r['last_seen']}</td>";
        echo "<td>{$r['ip_address']}</td>";
        echo "<td>{$r['wifi_ssid']}</td>";
        echo "<td>{$r['rssi']}</td>";
        echo "<td>{$diff}s</td>";
        echo "<td>{$online}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Check if online detection SQL works
echo "<h3>Online Detection SQL Check</h3>";
$stmt = $pdo->query("
    SELECT p.id, p.project_name, h.last_seen,
           CASE WHEN h.last_seen > DATE_SUB(NOW(), INTERVAL 30 SECOND) THEN 1 ELSE 0 END as is_online,
           TIMESTAMPDIFF(SECOND, h.last_seen, NOW()) as seconds_ago
    FROM projects p 
    LEFT JOIN esp_heartbeat h ON p.id = h.project_id
");
$rows = $stmt->fetchAll();
if (empty($rows)) {
    echo "<p>No projects found.</p>";
} else {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Project</th><th>Last Seen</th><th>Seconds Ago</th><th>Online (SQL)</th></tr>";
    foreach ($rows as $r) {
        echo "<tr>";
        echo "<td>{$r['project_name']}</td>";
        echo "<td>{$r['last_seen']}</td>";
        echo "<td>{$r['seconds_ago']}</td>";
        echo "<td>" . ($r['is_online'] ? 'YES ✅' : 'NO ❌') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<br><p><b>Tip:</b> If 'Seconds Ago' is a very large number (like thousands), there's a timezone mismatch.</p>";
echo "<p><b>Tip:</b> If no heartbeat records exist, the ESP isn't reaching the server.</p>";
?>
