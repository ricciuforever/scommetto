<?php
// app/Views/gemini_bets.php
$pageTitle = 'Scommetto.AI - Tutte le giocate di Gemini';
require __DIR__ . '/layout/top.php';
?>

<div id="htmx-container" hx-get="/api/view/tracker" hx-trigger="load">
    <div class="flex items-center justify-center py-20">
        <div class="w-12 h-12 border-4 border-accent/20 border-t-accent rounded-full animate-spin"></div>
    </div>
</div>

<?php
require __DIR__ . '/layout/bottom.php';
?>
