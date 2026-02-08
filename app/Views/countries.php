<!DOCTYPE html>
<html lang="it" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scommetto.AI - Countries</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">

    <!-- React & Babel CDN -->
    <script src="https://unpkg.com/react@18/umd/react.production.min.js"></script>
    <script src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js"></script>
    <script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com?minify=true"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        darkbg: '#0f172a',
                        accent: '#38bdf8',
                        success: '#10b981',
                        danger: '#ef4444',
                        warning: '#f59e0b',
                    },
                    fontFamily: {
                        sans: ['Outfit', 'sans-serif'],
                    },
                }
            }
        }
    </script>

    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        .glass {
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(12px);
        }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="bg-slate-50 dark:bg-darkbg text-slate-900 dark:text-slate-100 min-h-screen transition-colors duration-300 font-sans">

    <div id="root"></div>

    <script type="text/babel">
        const { useState, useEffect } = React;

        function CountryCard({ country }) {
            return (
                <div className="glass border border-white/10 rounded-2xl p-4 flex flex-col items-center gap-3 hover:scale-105 transition-transform cursor-pointer group">
                    <div className="w-16 h-10 overflow-hidden rounded-lg border border-white/5 shadow-lg group-hover:border-accent/30 transition-colors">
                        {country.flag ? (
                            <img src={country.flag} alt={country.name} className="w-full h-full object-cover" />
                        ) : (
                            <div className="w-full h-full bg-white/5 flex items-center justify-center">
                                <i data-lucide="flag" className="w-4 h-4 text-slate-500"></i>
                            </div>
                        )}
                    </div>
                    <span className="text-sm font-bold text-center group-hover:text-accent transition-colors">
                        {country.name}
                    </span>
                    {country.code && (
                        <span className="text-[10px] font-black text-slate-500 uppercase tracking-widest">
                            {country.code}
                        </span>
                    )}
                </div>
            );
        }

        function App() {
            const [countries, setCountries] = useState([]);
            const [loading, setLoading] = useState(true);
            const [search, setSearch] = useState('');

            useEffect(() => {
                fetch('/api/countries')
                    .then(res => res.json())
                    .then(data => {
                        setCountries(data.response || []);
                        setLoading(false);
                    })
                    .catch(err => {
                        console.error('Error fetching countries:', err);
                        setLoading(false);
                    });
            }, []);

            useEffect(() => {
                if (window.lucide) lucide.createIcons();
            }, [countries, loading]);

            const filteredCountries = countries.filter(c =>
                c.name.toLowerCase().includes(search.toLowerCase())
            );

            return (
                <div className="max-w-7xl mx-auto p-6">
                    <header className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
                        <div>
                            <h1 className="text-3xl font-black italic uppercase text-white mb-2">
                                Countries <span className="text-accent">Database</span>
                            </h1>
                            <p className="text-slate-500 text-sm font-semibold uppercase tracking-widest">
                                API Football Structure Integration
                            </p>
                        </div>

                        <div className="relative w-full md:w-64">
                            <i data-lucide="search" className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500"></i>
                            <input
                                type="text"
                                placeholder="Cerca paese..."
                                className="w-full bg-white/5 border border-white/10 rounded-xl py-2.5 pl-10 pr-4 text-sm focus:outline-none focus:border-accent/50 transition-colors"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                            />
                        </div>
                    </header>

                    {loading ? (
                        <div className="flex flex-col items-center justify-center py-20 gap-4">
                            <div className="w-12 h-12 border-4 border-accent/20 border-t-accent rounded-full animate-spin"></div>
                            <span className="text-xs font-black uppercase tracking-widest text-slate-500">Caricamento Paesi...</span>
                        </div>
                    ) : (
                        <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
                            {filteredCountries.map(c => (
                                <CountryCard key={c.name} country={c} />
                            ))}
                        </div>
                    )}

                    {!loading && filteredCountries.length === 0 && (
                        <div className="text-center py-20">
                            <p className="text-slate-500 italic">Nessun paese trovato per "{search}"</p>
                        </div>
                    )}
                </div>
            );
        }

        const root = ReactDOM.createRoot(document.getElementById('root'));
        root.render(<App />);
    </script>
</body>
</html>
