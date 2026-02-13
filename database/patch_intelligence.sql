-- database/patch_intelligence.sql

DROP TABLE IF EXISTS performance_metrics;

CREATE TABLE performance_metrics (
    context_type TEXT NOT NULL,  -- 'LEAGUE', 'TEAM', 'MARKET', 'STRATEGY'
    context_id TEXT NOT NULL,    -- es. 'Serie A', 'Juventus', 'UO25', 'KELLY'
    total_bets INTEGER DEFAULT 0,
    wins INTEGER DEFAULT 0,
    losses INTEGER DEFAULT 0,
    total_stake REAL DEFAULT 0.0,
    total_profit REAL DEFAULT 0.0,
    roi REAL DEFAULT 0.0,        -- ROI percentuale
    last_updated DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (context_type, context_id)
);

CREATE INDEX IF NOT EXISTS idx_perf_metrics ON performance_metrics(context_type, context_id);
