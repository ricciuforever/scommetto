<?php
// app/Views/main.php
$pageTitle = 'Scommetto.AI - Dashboard Intelligence';
require __DIR__ . '/layout/top.php';
?>

<h1 id="view-title" class="text-2xl font-black italic uppercase text-white mb-6">Dashboard Intelligence</h1>

<!-- HTMX Container for Dynamic Content -->
<?php
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$initialApi = '/api/view/dashboard';

if (strpos($currentPath, '/tracker') !== false) {
    $initialApi = '/api/view/tracker';
} elseif (preg_match('#^/match/(\d+)$#', $currentPath, $m)) {
    $initialApi = "/api/view/match/{$m[1]}";
} elseif (preg_match('#^/team/(\d+)$#', $currentPath, $m)) {
    $initialApi = "/api/view/team/{$m[1]}";
} elseif (preg_match('#^/player/(\d+)$#', $currentPath, $m)) {
    $initialApi = "/api/view/player/{$m[1]}";
}

if (!empty($_SERVER['QUERY_STRING'])) {
    $initialApi .= '?' . $_SERVER['QUERY_STRING'];
}
?>

<div id="htmx-container" hx-get="<?php echo $initialApi; ?>" hx-trigger="load" hx-target="#htmx-container" hx-swap="innerHTML">
    <div class="flex items-center justify-center py-20">
        <i data-lucide="loader-2" class="w-10 h-10 text-accent rotator"></i>
    </div>
</div>

<?php
require __DIR__ . '/layout/bottom.php';
?>
