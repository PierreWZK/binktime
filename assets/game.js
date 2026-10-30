// « Compte du tapis » : une partie par jour, gardée dans le navigateur (essais, série, record).

const fmt = (n) => n.toLocaleString('fr-FR').replace(/\s/g, ' ');

const store = {
    get(key, fallback) {
        try {
            return JSON.parse(localStorage.getItem(key)) ?? fallback;
        } catch {
            return fallback;
        }
    },
    set(key, value) {
        try {
            localStorage.setItem(key, JSON.stringify(value));
        } catch {
            // navigation privée : la partie marche, elle ne sera juste pas retenue
        }
    },
};

const shiftDay = (iso, days) => {
    const d = new Date(`${iso}T12:00:00Z`);
    d.setUTCDate(d.getUTCDate() + days);
    return d.toISOString().slice(0, 10);
};

// Secondes avant minuit à Paris, quel que soit le fuseau du visiteur
const secondsToParisMidnight = () => {
    const [h, m, s] = new Intl.DateTimeFormat('fr-FR', { timeZone: 'Europe/Paris', hour: '2-digit', minute: '2-digit', second: '2-digit', hourCycle: 'h23' })
        .format(new Date()).split(':').map(Number);
    return 86400 - (h * 3600 + m * 60 + s);
};

export function initGame(root) {
    const config = JSON.parse(root.dataset.gameConfig);
    const g = (name) => root.querySelector(`[data-g="${name}"]`);
    const gameKey = `binktime:tapis:${config.date}`;
    const STATS_KEY = 'binktime:tapis:stats';

    let game = store.get(gameKey, { phase: 'idle', guesses: [], won: false });
    let stats = store.get(STATS_KEY, { streak: 0, best: 0, played: 0, lastWin: null });

    const save = () => store.set(gameKey, game);

    const hint = (guess) => {
        if (guess === config.total) return { key: 'hit', label: 'Exact', emoji: '✅' };
        const close = Math.abs(guess - config.total) <= config.total * 0.1;
        return guess < config.total
            ? { key: close ? 'close' : 'far', label: close ? 'Un peu plus' : "C'est plus", emoji: '⬆️' }
            : { key: close ? 'close' : 'far', label: close ? 'Un peu moins' : "C'est moins", emoji: '⬇️' };
    };

    const renderStats = () => {
        // Une série s'arrête si on n'a pas gagné hier ni aujourd'hui
        const alive = stats.lastWin === config.date || stats.lastWin === shiftDay(config.date, -1);
        g('streak').textContent = alive ? stats.streak : 0;
        g('best').textContent = stats.best;
        g('played').textContent = stats.played;
    };

    const render = () => {
        const felt = g('felt');
        felt.classList.toggle('is-done', game.phase === 'done');
        g('revealBtn').hidden = game.phase !== 'idle';
        if (game.phase === 'guessing') {
            g('coverTitle').textContent = 'Combien ?';
            g('coverText').textContent = 'Le tapis a disparu. À vous de compter.';
        }
        g('form').hidden = game.phase !== 'guessing';
        g('left').textContent = config.attempts - game.guesses.length;

        g('guesses').innerHTML = '';
        for (const guess of game.guesses) {
            const h = hint(guess);
            const li = document.createElement('li');
            li.className = `is-${h.key}`;
            li.textContent = fmt(guess);
            const tag = document.createElement('span');
            tag.textContent = h.label;
            li.append(tag);
            g('guesses').append(li);
        }

        g('result').hidden = game.phase !== 'done';
        if (game.phase === 'done') {
            g('resultTitle').textContent = game.won ? 'Bien compté.' : 'La banque gagne cette fois.';
            g('resultText').textContent = game.won
                ? `${fmt(config.total)} jetons, trouvé en ${game.guesses.length} essai${game.guesses.length > 1 ? 's' : ''}.`
                : `Le tapis valait ${fmt(config.total)} jetons. Revanche demain.`;
        }
        renderStats();
    };

    const finish = (won) => {
        game.phase = 'done';
        game.won = won;
        save();
        stats.played += 1;
        if (won) {
            stats.streak = stats.lastWin === shiftDay(config.date, -1) ? stats.streak + 1 : 1;
            stats.best = Math.max(stats.best, stats.streak);
            stats.lastWin = config.date;
        } else {
            stats.streak = 0;
        }
        store.set(STATS_KEY, stats);
    };

    g('revealBtn').addEventListener('click', () => {
        // Une seule révélation : on l'enregistre avant même d'afficher le tapis
        game.phase = 'guessing';
        save();
        const felt = g('felt');
        const timer = g('timer');
        const bar = timer.querySelector('span');
        felt.classList.add('is-revealing');
        timer.hidden = false;
        bar.animate([{ width: '100%' }, { width: '0%' }], { duration: config.reveal * 1000, easing: 'linear', fill: 'forwards' });
        setTimeout(() => {
            felt.classList.remove('is-revealing');
            timer.hidden = true;
            render();
            g('form').querySelector('input').focus();
        }, config.reveal * 1000);
        g('revealBtn').hidden = true;
    });

    g('form').addEventListener('submit', (event) => {
        event.preventDefault();
        const input = g('form').querySelector('input');
        const guess = Number(input.value.replace(/\D/g, ''));
        if (!guess || guess % 25 !== 0) {
            input.setCustomValidity('Un montant en jetons, multiple de 25.');
            input.reportValidity();
            return;
        }
        input.setCustomValidity('');
        game.guesses.push(guess);
        if (guess === config.total) finish(true);
        else if (game.guesses.length >= config.attempts) finish(false);
        else save();
        input.value = '';
        render();
        if (game.phase === 'done') g('share').focus();
    });
    g('form').querySelector('input').addEventListener('input', (e) => e.target.setCustomValidity(''));

    g('share').addEventListener('click', async () => {
        const line = game.guesses.map((guess) => hint(guess).emoji).join('');
        const text = [
            `BinkTime · Compte du tapis n° ${config.number}`,
            `${line} ${game.won ? game.guesses.length : 'X'}/${config.attempts}`,
            stats.lastWin === config.date ? `🔥 Série : ${stats.streak}` : null,
            `${location.origin}${location.pathname}`,
        ].filter(Boolean).join('\n');
        const button = g('share');
        try {
            if (navigator.share && matchMedia('(pointer: coarse)').matches) {
                await navigator.share({ text });
                return;
            }
            await navigator.clipboard.writeText(text);
            button.textContent = 'Résultat copié';
        } catch {
            button.textContent = 'Copie impossible';
        }
        setTimeout(() => { button.textContent = 'Partager le résultat'; }, 2000);
    });

    const tickNext = () => {
        const s = secondsToParisMidnight();
        g('next').textContent = `${Math.floor(s / 3600)} h ${String(Math.floor((s % 3600) / 60)).padStart(2, '0')}`;
    };
    tickNext();
    setInterval(tickNext, 30000);

    render();
}
