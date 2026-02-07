<?php
// app/Views/partials/match/stats.php

if (empty($statistics)) {
    echo '<div class="glass p-20 rounded-[40px] text-center font-black uppercase text-slate-500 italic">Statistiche non disponibili.</div>';
    return;
}

// Group stats by type
$teams = [];
foreach ($statistics as $s) {
    if (!isset($teams[$s['team_id']])) {
        $teams[$s['team_id']] = [
            'name' => $s['team_name'],
            'logo' => $s['team_logo'],
            'stats' => json_decode($s['stats_json'], true)
        ];
    }
}

$teamIds = array_keys($teams);
if (count($teamIds) < 2) {
    echo '<div class="glass p-20 rounded-[40px] text-center font-black uppercase text-slate-500 italic">Dati statistici incompleti.</div>';
    return;
}

$tid1 = $teamIds[0];
$tid2 = $teamIds[1];
$s1 = $teams[$tid1]['stats'];
$s2 = $teams[$tid2]['stats'];
?>

<div class="glass p-10 rounded-[40px] border-white/5 max-w-2xl mx-auto space-y-8 animate-fade-in">
    <div class="flex justify-between items-center mb-10 px-4">
        <div class="flex flex-col items-center gap-2">
            <img src="<?php echo $teams[$tid1]['logo']; ?>" class="w-8 h-8 object-contain">
            <span class="text-[9px] font-black uppercase text-slate-500 italic">
                <?php echo htmlspecialchars($teams[$tid1]['name']); ?>
            </span>
        </div>
        <div class="text-xs font-black text-white uppercase italic tracking-widest opacity-25">VS</div>
        <div class="flex flex-col items-center gap-2">
            <img src="<?php echo $teams[$tid2]['logo']; ?>" class="w-8 h-8 object-contain">
            <span class="text-[9px] font-black uppercase text-slate-500 italic">
                <?php echo htmlspecialchars($teams[$tid2]['name']); ?>
            </span>
        </div>
    </div>

    <div class="space-y-8">
        <?php foreach ($s1 as $idx => $st):
            $v1 = $st['value'] ?? 0;
            // Find same stat in s2
            $v2 = 0;
            foreach ($s2 as $st2) {
                if ($st2['type'] === $st['type']) {
                    $v2 = $st2['value'] ?? 0;
                    break;
                }
            }

            // Calculate percentage for bar
            $val1 = (float) str_replace('%', '', $v1);
            $val2 = (float) str_replace('%', '', $v2);
            $total = $val1 + $val2;
            $p1 = $total > 0 ? ($val1 / $total * 100) : 50;

            // Normalize display if numeric
            $displayV1 = is_numeric($val1) ? $val1 : $v1;
            $displayV2 = is_numeric($val2) ? $val2 : $v2;
            ?>
            <div class="space-y-2">
                <div
                    class="flex justify-between items-center text-[10px] font-black uppercase tracking-widest text-slate-500">
                    <span class="text-white font-black tabular-nums">
                        <?php echo $v1; ?>
                    </span>
                    <span class="italic opacity-60">
                        <?php echo $st['type']; ?>
                    </span>
                    <span class="text-white font-black tabular-nums">
                        <?php echo $v2; ?>
                    </span>
                </div>
                <div class="h-1.5 bg-white/5 rounded-full overflow-hidden flex">
                    <div class="h-full bg-accent" style="width: <?php echo $p1; ?>%"></div>
                    <div class="h-full bg-slate-700" style="width: <?php echo 100 - $p1; ?>%"></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
    if (window.lucide) lucide.createIcons();
</script>