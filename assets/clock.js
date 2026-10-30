// Horloge plein écran : décompte local, état de référence sur le serveur.

const POLL_MS = 5000;
const STATUS = { draft: 'À venir', running: 'En cours', paused: 'En pause', finished: 'Terminé' };

const fmt = (n) => n.toLocaleString('fr-FR').replace(/\s/g, ' ');
const mmss = (s) => `${String(Math.floor(s / 60)).padStart(2, '0')}:${String(s % 60).padStart(2, '0')}`;
const levelKey = (s) => (s.level ? `${s.level.number}-${s.level.isBreak}` : '');

export function initClock(root) {
    const remote = 'actionUrl' in root.dataset;
    const k = (name) => root.querySelector(`[data-k="${name}"]`);
    const toggle = root.querySelector('[data-action="toggle"]');
    const soundButton = root.querySelector('[data-sound]');

    let state = JSON.parse(root.dataset.state);
    let endsAt = Date.now() + state.remaining * 1000;
    let refreshing = false;
    let audio = null;
    let wakeLock = null;

    const remaining = () => (state.status === 'running' ? Math.max(0, Math.ceil((endsAt - Date.now()) / 1000)) : state.remaining);

    // Trois notes douces, jouées à chaque changement de niveau
    const chime = () => {
        if (!audio) return;
        [659.25, 880, 1046.5].forEach((freq, i) => {
            const t = audio.currentTime + i * 0.22;
            const osc = audio.createOscillator();
            const gain = audio.createGain();
            osc.type = 'sine';
            osc.frequency.value = freq;
            gain.gain.setValueAtTime(0.0001, t);
            gain.gain.exponentialRampToValueAtTime(0.35, t + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.0001, t + 0.9);
            osc.connect(gain).connect(audio.destination);
            osc.start(t);
            osc.stop(t + 1);
        });
    };

    const apply = (next) => {
        const changed = levelKey(next) !== levelKey(state);
        state = next;
        endsAt = Date.now() + state.remaining * 1000;
        if (changed) chime();
        keepAwake();
        render();
    };

    const render = () => {
        const left = remaining();
        const { level } = state;
        k('time').textContent = mmss(left);
        k('progress').style.width = `${state.duration ? (100 * (state.duration - left)) / state.duration : 0}%`;
        k('levelLabel').textContent = !level ? '' : level.isBreak ? 'Pause' : `Niveau ${level.roman}`;
        k('blinds').textContent = level && !level.isBreak ? `${fmt(level.small)} / ${fmt(level.big)}` : '—';
        k('ante').textContent = level && level.ante ? fmt(level.ante) : '—';
        k('next').textContent = state.next ? `${fmt(state.next.small)} / ${fmt(state.next.big)}` : '—';
        k('playersLeft').textContent = state.playersLeft;
        k('entrants').textContent = state.entrants;
        k('averageStack').textContent = fmt(state.averageStack);
        k('rebuys').textContent = state.rebuys;
        k('addons').textContent = state.addons;
        k('prizePool').textContent = fmt(state.prizePool);
        k('third').textContent = state.paidPlaces;

        const status = k('status');
        status.textContent = STATUS[state.status];
        status.classList.toggle('badge--pause', state.status !== 'running');

        const winner = k('winner');
        winner.hidden = !state.winner;
        winner.textContent = state.winner ? `${state.winner} remporte le tournoi` : '';

        root.classList.toggle('is-paused', state.status !== 'running');
        root.classList.toggle('is-warning', state.status === 'running' && left <= 60);
        document.title = `${state.status === 'running' ? mmss(left) + ' · ' : ''}${state.name} · BinkTime`;

        if (toggle) {
            toggle.textContent = state.status === 'running' ? 'Pause' : state.status === 'draft' ? 'Lancer' : 'Reprendre';
            toggle.classList.toggle('is-running', state.status === 'running');
            root.querySelectorAll('[data-action]').forEach((b) => { b.disabled = state.status === 'finished'; });
        }
    };

    const refresh = async () => {
        if (refreshing) return;
        refreshing = true;
        try {
            const response = await fetch(root.dataset.stateUrl, { headers: { Accept: 'application/json' }, cache: 'no-store' });
            if (response.ok) apply(await response.json());
        } catch {
            // réseau coupé : le décompte local continue, on réessaie au prochain tour
        } finally {
            refreshing = false;
        }
    };

    const act = async (action) => {
        const error = k('error');
        try {
            const response = await fetch(root.dataset.actionUrl.replace(/toggle$/, action), { method: 'POST', headers: { Accept: 'application/json' } });
            const body = await response.json();
            error.hidden = response.ok;
            error.textContent = body.error ?? '';
            apply(body);
        } catch {
            error.hidden = false;
            error.textContent = 'Connexion perdue : réessayez dans un instant.';
        }
    };

    // L'écran de la télé ne doit pas se mettre en veille pendant que l'horloge tourne
    const keepAwake = async () => {
        if (!('wakeLock' in navigator) || document.visibilityState !== 'visible') return;
        try {
            if (state.status === 'running' && !wakeLock) {
                wakeLock = await navigator.wakeLock.request('screen');
                wakeLock.addEventListener('release', () => { wakeLock = null; });
            } else if (state.status !== 'running' && wakeLock) {
                await wakeLock.release();
            }
        } catch {
            // refusé par le navigateur : sans gravité
        }
    };

    // Boucle d'affichage ; à zéro, le serveur enchaîne le niveau suivant
    setInterval(() => {
        render();
        if (state.status === 'running' && remaining() === 0) refresh();
    }, 250);
    setInterval(refresh, POLL_MS);
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') refresh();
    });

    root.querySelectorAll('[data-action]').forEach((button) => button.addEventListener('click', () => act(button.dataset.action)));
    if (remote) {
        document.addEventListener('keydown', (event) => {
            if (event.target.closest?.('input, textarea, button, a') || event.repeat) return;
            const action = { ' ': 'toggle', ArrowLeft: 'prev', ArrowRight: 'next' }[event.key];
            if (action) {
                event.preventDefault();
                act(action);
            }
        });
    }

    // Le son ne peut démarrer qu'après un geste de l'utilisateur
    soundButton.addEventListener('click', () => {
        audio ??= new AudioContext();
        const on = soundButton.getAttribute('aria-pressed') !== 'true';
        soundButton.setAttribute('aria-pressed', String(on));
        soundButton.textContent = on ? 'Son activé' : 'Son';
        if (on) {
            audio.resume();
            chime();
        } else {
            audio.suspend();
        }
    });

    root.querySelector('[data-fullscreen]').addEventListener('click', () => {
        if (document.fullscreenElement) document.exitFullscreen();
        else document.documentElement.requestFullscreen?.();
    });

    // Les boutons s'effacent après 3 s sans mouvement de souris
    let idleTimer;
    const wake = () => {
        root.classList.remove('is-idle');
        clearTimeout(idleTimer);
        idleTimer = setTimeout(() => root.classList.add('is-idle'), 3000);
    };
    ['mousemove', 'touchstart', 'keydown'].forEach((e) => document.addEventListener(e, wake, { passive: true }));
    wake();

    render();
    keepAwake();
}
