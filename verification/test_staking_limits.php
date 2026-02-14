<?php
class StakingTest {
    const MIN_BETFAIR_STAKE = 2.00;

    public function calculateDynamicStake($confidence, $bankroll) {
        $p = (float)$confidence / 100.0;
        $stake = $bankroll * 0.05 * $p;

        if ($stake < self::MIN_BETFAIR_STAKE) {
            $stake = self::MIN_BETFAIR_STAKE;
        }

        $maxStake = $bankroll * 0.05;
        if ($stake > $maxStake && $maxStake >= self::MIN_BETFAIR_STAKE) {
            $stake = $maxStake;
        }

        return round($stake, 2);
    }

    public function test() {
        $scenarios = [
            // [confidence, total, available, expected_auto, manual_requested]
            [100, 1000, 800, 50, 60],
            [80, 1000, 800, 40, 40],
            [100, 1000, 20, 20, 50],
            [100, 30, 30, 2, 5],
        ];

        foreach ($scenarios as $s) {
            list($conf, $total, $available, $exp_auto, $man_req) = $s;

            // Simulation of autoProcess
            $stake_auto = $this->calculateDynamicStake($conf, $total);
            if ($stake_auto > $available) $stake_auto = $available;

            // Simulation of placeBet (manual)
            $stake_man = $man_req;
            $maxAllowed = $total * 0.05;
            if ($stake_man > $maxAllowed) $stake_man = $maxAllowed;
            if ($stake_man > $available) $stake_man = $available;
            if ($stake_man < self::MIN_BETFAIR_STAKE) $stake_man = self::MIN_BETFAIR_STAKE;

            echo "Conf: $conf%, Total: $total, Available: $available\n";
            echo "  Auto Stake: $stake_auto (Expected: $exp_auto)\n";
            echo "  Manual Stake (requested $man_req): $stake_man\n\n";
        }
    }
}
(new StakingTest())->test();
