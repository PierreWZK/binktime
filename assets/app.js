import './styles/app.css';
import { initClock } from './clock.js';
import { initGame } from './game.js';

const clock = document.querySelector('[data-clock]');
if (clock) initClock(clock);

const game = document.querySelector('[data-game]');
if (game) initGame(game);

const fmt = (n) => n.toLocaleString('fr-FR').replace(/\s/g, ' ');
const roman = (n) => ['', 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'][n] ?? String(n);

// Apparition douce des blocs au défilement. La classe « js » n'est posée qu'ici : si ce script
// ne se charge pas, rien n'est masqué. Ce qui est déjà à l'écran s'affiche sans attendre.
const reveals = document.querySelectorAll('.reveal');
reveals.forEach((el) => {
    if (el.getBoundingClientRect().top < innerHeight) el.classList.add('is-in');
});
document.documentElement.classList.add('js');
if ('IntersectionObserver' in window) {
    const io = new IntersectionObserver((entries) => {
        for (const entry of entries) {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-in');
                io.unobserve(entry.target);
            }
        }
    }, { rootMargin: '0px 0px -10% 0px' });
    reveals.forEach((el) => io.observe(el));
} else {
    reveals.forEach((el) => el.classList.add('is-in'));
}

// Boutons « Copier » (lien secret)
document.querySelectorAll('[data-copy]').forEach((button) => button.addEventListener('click', async () => {
    const input = document.querySelector(button.dataset.copy);
    input.select();
    try {
        await navigator.clipboard.writeText(input.value);
    } catch {
        document.execCommand('copy');
    }
    button.textContent = 'Copié';
    setTimeout(() => { button.textContent = 'Copier'; }, 1800);
}));

// Le menu mobile se referme après un choix
document.querySelectorAll('.menu a').forEach((a) => a.addEventListener('click', () => a.closest('details').removeAttribute('open')));

// Aperçu de l'horloge sur l'accueil : le niveau VII tourne vraiment, puis la structure continue
const demo = document.querySelector('[data-demo-clock]');
if (demo) {
    const LEVEL_SECONDS = 20 * 60;
    // [niveau, petite, grosse] ; null = pause
    const structure = [[7, 400, 800], [8, 500, 1000], [9, 600, 1200], null, [10, 800, 1600], [11, 1000, 2000], [12, 1500, 3000]];
    const el = (name) => demo.querySelector(`[data-clock-${name}]`);
    const startedAt = Date.now() - (LEVEL_SECONDS - (18 * 60 + 42)) * 1000;

    const render = () => {
        const elapsed = Math.floor((Date.now() - startedAt) / 1000);
        const index = Math.floor(elapsed / LEVEL_SECONDS) % structure.length;
        const left = LEVEL_SECONDS - (elapsed % LEVEL_SECONDS);
        const level = structure[index];
        const next = structure.slice(index + 1).find(Boolean) ?? structure[0];
        // Niveaux pleins à jouer après celui-ci avant la prochaine pause
        const nextBreak = structure.indexOf(null, index);
        const breakIn = nextBreak - index - 1;

        el('time').textContent = `${String(Math.floor(left / 60)).padStart(2, '0')}:${String(left % 60).padStart(2, '0')}`;
        el('progress').style.width = `${(100 * (LEVEL_SECONDS - left)) / LEVEL_SECONDS}%`;
        el('level').textContent = level ? `Niveau ${roman(level[0])}` : 'Pause';
        el('blinds').textContent = level ? `${fmt(level[1])} / ${fmt(level[2])}` : '—';
        el('next').textContent = `${fmt(next[1])} / ${fmt(next[2])}`;
        el('break').textContent = !level ? 'En cours'
            : nextBreak < 0 ? '—'
            : breakIn === 0 ? 'Ce niveau'
            : `${breakIn} niveau${breakIn > 1 ? 'x' : ''}`;
    };

    render();
    setInterval(render, 1000);
}
