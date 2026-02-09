<?php
// app/Views/fixture_predictions.php
$pageTitle = 'Scommetto.AI - Pronostici Professionali';
require __DIR__ . '/layout/top.php';
?>

<div id="root"></div>

<script type="text/babel">
    const { useState, useEffect } = React;

    function ProbabilityBar({ label, home, draw, away }) {
        return (
            <div className="mb-6">
                <div className="flex justify-between text-[10px] font-black uppercase tracking-widest text-slate-500 mb-2">
                    <span>{label}</span>
                </div>
                <div className="h-3 w-full bg-white/5 rounded-full overflow-hidden flex border border-white/5">
                    <div
                        className="h-full bg-accent transition-all duration-1000"
                        style={{ width: home }}
                        title={`Casa: ${home}`}
                    ></div>
                    <div
                        className="h-full bg-white/20 transition-all duration-1000"
                        style={{ width: draw }}
                        title={`Pareggio: ${draw}`}
                    ></div>
                    <div
                        className="h-full bg-danger transition-all duration-1000"
                        style={{ width: away }}
                        title={`Trasferta: ${away}`}
                    ></div>
                </div>
                <div className="flex justify-between mt-2">
                    <span className="text-[10px] font-black text-accent">{home}</span>
                    <span className="text-[10px] font-black text-slate-400">{draw}</span>
                    <span className="text-[10px] font-black text-danger">{away}</span>
                </div>
            </div>
        );
    }

    function ComparisonRow({ label, home, away }) {
        const hVal = parseFloat(home);
        const aVal = parseFloat(away);

        return (
            <div className="mb-4">
                <div className="flex justify-between text-[9px] font-black uppercase text-slate-500 mb-1.5">
                    <span>{label}</span>
                </div>
                <div className="flex items-center gap-3">
                    <span className="text-[10px] font-black text-white w-8">{home}</span>
                    <div className="flex-1 h-1.5 bg-white/5 rounded-full overflow-hidden flex">
                        <div
                            className="h-full bg-accent"
                            style={{ width: `${(hVal / (hVal + aVal)) * 100}%` }}
                        ></div>
                        <div
                            className="h-full bg-danger"
                            style={{ width: `${(aVal / (hVal + aVal)) * 100}%` }}
                        ></div>
                    </div>
                    <span className="text-[10px] font-black text-white w-8 text-right">{away}</span>
                </div>
            </div>
        );
    }

    function App() {
        const [data, setData] = useState(null);
        const [loading, setLoading] = useState(false);
        const [searchId, setSearchId] = useState('');

        const loadData = (id) => {
            if (!id) return;
            setLoading(true);
            fetch(`/api/fixture-predictions?fixture=${id}`)
                .then(res => res.json())
                .then(json => {
                    setData(json.response);
                    setLoading(false);
                })
                .catch(() => setLoading(false));
        };

        useEffect(() => {
            const params = new URLSearchParams(window.location.search);
            const id = params.get('fixture');
            if (id) {
                setSearchId(id);
                loadData(id);
            }
        }, []);

        useEffect(() => {
            if (window.lucide) lucide.createIcons();
        }, [data, loading]);

        return (
            <div className="max-w-4xl mx-auto">
                <header className="mb-10 text-center">
                    <h1 className="text-3xl font-black italic uppercase text-white mb-2">
                        AI <span className="text-accent">Predictions</span>
                    </h1>
                    <p className="text-[10px] font-bold text-slate-500 uppercase tracking-[0.2em]">Analisi statistica e Poisson Distribution</p>

                    <div className="flex justify-center mt-6">
                        <div className="relative w-full max-w-sm">
                            <i data-lucide="brain" className="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500"></i>
                            <input
                                type="text"
                                placeholder="Inserisci Fixture ID..."
                                className="w-full bg-white/5 border border-white/10 rounded-2xl py-3 pl-12 pr-4 text-sm focus:outline-none focus:border-accent/50 transition-colors"
                                value={searchId}
                                onChange={(e) => setSearchId(e.target.value)}
                                onKeyDown={(e) => e.key === 'Enter' && loadData(searchId)}
                            />
                            <button
                                onClick={() => loadData(searchId)}
                                className="absolute right-2 top-1/2 -translate-y-1/2 bg-accent text-white px-4 py-1.5 rounded-xl text-[10px] font-black uppercase hover:scale-105 transition-all shadow-lg shadow-accent/20"
                            >
                                Analizza
                            </button>
                        </div>
                    </div>
                </header>

                {loading ? (
                    <div className="flex flex-col items-center justify-center py-20 gap-4">
                        <div className="w-10 h-10 border-4 border-accent/20 border-t-accent rounded-full animate-spin"></div>
                        <span className="text-[10px] font-black uppercase text-slate-500 tracking-widest">Elaborazione algoritmi...</span>
                    </div>
                ) : (
                    data ? (
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                            {/* Probalità e Advice */}
                            <div className="md:col-span-2 flex flex-col gap-6">
                                <div className="glass border border-white/10 rounded-3xl p-6">
                                    <h2 className="text-xs font-black text-white uppercase tracking-widest mb-6 flex items-center gap-2">
                                        <i data-lucide="trending-up" className="w-4 h-4 text-accent"></i>
                                        Probabilità Esito
                                    </h2>
                                    <ProbabilityBar
                                        label="1 - X - 2"
                                        home={data.prediction_json.percent.home}
                                        draw={data.prediction_json.percent.draw}
                                        away={data.prediction_json.percent.away}
                                    />

                                    <div className="grid grid-cols-2 gap-4 mt-8">
                                        <div className="bg-white/5 rounded-2xl p-4 border border-white/5">
                                            <span className="text-[9px] font-black text-slate-500 uppercase block mb-1">Under / Over</span>
                                            <span className="text-xl font-black text-white">{data.prediction_json.under_over}</span>
                                        </div>
                                        <div className="bg-white/5 rounded-2xl p-4 border border-white/5">
                                            <span className="text-[9px] font-black text-slate-500 uppercase block mb-1">Vincitore Atteso</span>
                                            <span className="text-sm font-black text-accent">{data.prediction_json.winner.name}</span>
                                        </div>
                                    </div>
                                </div>

                                <div className="bg-accent/10 border border-accent/20 rounded-3xl p-6 relative overflow-hidden">
                                    <i data-lucide="info" className="absolute -right-4 -bottom-4 w-24 h-24 text-accent/5"></i>
                                    <h2 className="text-xs font-black text-accent uppercase tracking-widest mb-2">Consiglio del Sistema</h2>
                                    <p className="text-lg font-black text-white leading-tight">
                                        {data.prediction_json.advice}
                                    </p>
                                </div>
                            </div>

                            {/* Comparazione */}
                            <div className="glass border border-white/10 rounded-3xl p-6">
                                <h2 className="text-xs font-black text-white uppercase tracking-widest mb-6 flex items-center gap-2">
                                    <i data-lucide="bar-chart" className="w-4 h-4 text-danger"></i>
                                    Comparazione
                                </h2>
                                <div className="flex flex-col gap-2">
                                    <ComparisonRow label="Forma" home={data.comparison_json.form.home} away={data.comparison_json.form.away} />
                                    <ComparisonRow label="Attacco" home={data.comparison_json.att.home} away={data.comparison_json.att.away} />
                                    <ComparisonRow label="Difesa" home={data.comparison_json.def.home} away={data.comparison_json.def.away} />
                                    <ComparisonRow label="Poisson" home={data.comparison_json.poisson_distribution.home} away={data.comparison_json.poisson_distribution.away} />
                                    <ComparisonRow label="H2H" home={data.comparison_json.h2h.home} away={data.comparison_json.h2h.away} />
                                    <ComparisonRow label="Goal" home={data.comparison_json.goals.home} away={data.comparison_json.goals.away} />
                                    <div className="mt-6 pt-6 border-t border-white/5">
                                        <ComparisonRow label="Totale" home={data.comparison_json.total.home} away={data.comparison_json.total.away} />
                                    </div>
                                </div>
                            </div>
                        </div>
                    ) : (
                        <div className="text-center py-24 glass border border-dashed border-white/10 rounded-3xl">
                            <i data-lucide="zap" className="w-12 h-12 text-slate-700 mx-auto mb-4"></i>
                            <p className="text-slate-500 font-bold uppercase text-xs tracking-widest">
                                {searchId ? 'Analisi non disponibile per questo match' : 'Inserisci un Fixture ID per generare il pronostico AI'}
                            </p>
                        </div>
                    )
                )}
            </div>
        );
    }

    const root = ReactDOM.createRoot(document.getElementById('root'));
    root.render(<App />);
</script>

<?php
require __DIR__ . '/layout/bottom.php';
?>