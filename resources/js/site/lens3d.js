// Prima pagină: o lentilă din sticlă, cu marcajele reale de montaj ale unei lentile progresive, în fața unui
// tabel optotip. Lentila mărește literele din spatele ei și se înclină după mouse / deget.
// Ilustrativ. Fără WebGL (sau dacă scena nu pornește) rămâne fotografia reală a lentilei.
import {
    NeutralToneMapping, CanvasTexture, CircleGeometry, Color, Group, LatheGeometry, Mesh, MeshBasicMaterial,
    MeshPhysicalMaterial, PerspectiveCamera, PlaneGeometry, PMREMGenerator, Scene, SRGBColorSpace, Vector2, WebGLRenderer,
} from 'three';
import { RoomEnvironment } from 'three/addons/environments/RoomEnvironment.js';
import { gsap } from 'gsap';

const NAVY = '#053b5e';
const ENGRAVE = '#a15f0e';

/** Tabelul optotip: rânduri de litere tot mai mici, ca la cabinetul de optometrie. */
function chartTexture(w, h) {
    const c = document.createElement('canvas');
    c.width = w; c.height = h;
    const g = c.getContext('2d');
    g.fillStyle = '#f7fafc';
    g.fillRect(0, 0, w, h);
    const rows = ['E', 'F P', 'T O Z', 'L P E D', 'P E C F D', 'E D F C Z P', 'F E L O P Z D', 'D E F P O T E C', 'L E F O D P C T'];
    let y = h * 0.06;
    rows.forEach((row, i) => {
        const size = h * 0.135 * Math.pow(0.76, i);
        g.font = `700 ${size}px "Hanken Grotesk", Arial, sans-serif`;
        g.fillStyle = NAVY;
        g.textAlign = 'center';
        g.textBaseline = 'top';
        const spaced = row.split(' ').join('   ');
        g.fillText(spaced, w / 2, y);
        y += size * 1.45;
    });
    // linia roșie de sub rândul de 20/20, discretă
    g.fillStyle = 'rgba(161, 95, 14, .55)';
    g.fillRect(w * 0.2, y + 4, w * 0.6, Math.max(2, h * 0.003));
    const t = new CanvasTexture(c);
    t.colorSpace = SRGBColorSpace;
    t.anisotropy = 4;

    return t;
}

/** Marcajele de montaj gravate (temporare) ale unei lentile progresive: linia cu puncte, crucea, cercurile. */
function marksTexture(size) {
    const c = document.createElement('canvas');
    c.width = c.height = size;
    const g = c.getContext('2d');
    const s = size / 100;
    g.strokeStyle = ENGRAVE;
    g.fillStyle = ENGRAVE;
    g.lineWidth = 0.7 * s;
    g.globalAlpha = 0.9;
    // linia orizontală cu două puncte
    g.beginPath(); g.moveTo(14 * s, 52 * s); g.lineTo(86 * s, 52 * s); g.stroke();
    [[28, 52], [72, 52]].forEach(([x, y]) => { g.beginPath(); g.arc(x * s, y * s, 1.6 * s, 0, Math.PI * 2); g.fill(); });
    // crucea de montaj
    g.beginPath(); g.moveTo(50 * s, 38 * s); g.lineTo(50 * s, 46 * s); g.moveTo(46 * s, 42 * s); g.lineTo(54 * s, 42 * s); g.stroke();
    // cercul zonei pentru distanță și cel pentru aproape
    g.beginPath(); g.arc(50 * s, 30 * s, 6 * s, 0, Math.PI * 2); g.stroke();
    g.beginPath(); g.arc(50 * s, 72 * s, 4.5 * s, 0, Math.PI * 2); g.stroke();
    g.font = `${4 * s}px "Hanken Grotesk", Arial, sans-serif`;
    g.textAlign = 'center';
    g.fillText('R', 22 * s, 47 * s);
    const t = new CanvasTexture(c);
    t.colorSpace = SRGBColorSpace;

    return t;
}

/** Profilul unei lentile „plus” (biconvexe), rotit în jurul axei: o lentilă rotundă, nedebitată. */
function lensGeometry(radius, centerThickness, edgeThickness, segments) {
    const pts = [];
    const steps = 32;
    for (let i = 0; i <= steps; i++) {
        const r = (i / steps) * radius;
        const z = edgeThickness / 2 + (centerThickness - edgeThickness) / 2 * (1 - (r / radius) ** 2);
        pts.push(new Vector2(r, z));
    }
    for (let i = steps; i >= 0; i--) {
        const r = (i / steps) * radius;
        const z = edgeThickness / 2 + (centerThickness - edgeThickness) / 2 * (1 - (r / radius) ** 2);
        pts.push(new Vector2(r, -z));
    }
    const geo = new LatheGeometry(pts, segments);
    geo.rotateX(Math.PI / 2); // axa optică spre cameră

    return geo;
}

function webglAvailable() {
    try {
        const c = document.createElement('canvas');

        return !!(window.WebGL2RenderingContext && c.getContext('webgl2'));
    } catch {
        return false;
    }
}

export default function lens3d(stage) {
    if (!webglAvailable()) return;
    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const small = window.matchMedia('(max-width: 700px)').matches;

    const renderer = new WebGLRenderer({ antialias: true, alpha: true, powerPreference: 'low-power' });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, small ? 1.5 : 1.75));
    renderer.toneMapping = NeutralToneMapping;
    renderer.outputColorSpace = SRGBColorSpace;
    const canvas = renderer.domElement;
    canvas.className = 'lens-stage__canvas';
    canvas.setAttribute('role', 'img');
    canvas.setAttribute('aria-label', stage.dataset.alt || 'Ilustrație: lentilă în fața unui tabel optotip');

    const scene = new Scene();
    const pmrem = new PMREMGenerator(renderer);
    scene.environment = pmrem.fromScene(new RoomEnvironment(), 0.04).texture;

    const camera = new PerspectiveCamera(32, 1, 0.1, 50);
    camera.position.set(0, 0, 9);

    // tabelul optotip, în spatele lentilei
    const chart = new Mesh(new PlaneGeometry(5.6, 7), new MeshBasicMaterial({ map: chartTexture(1024, 1280), toneMapped: false }));
    chart.position.z = -2.6;
    scene.add(chart);

    // lentila: sticlă optică (refracție reală a tabelului din spate) + marcajele gravate pe suprafața din față
    const lens = new Group();
    const glass = new Mesh(lensGeometry(1.75, 1.05, 0.14, small ? 64 : 128), new MeshPhysicalMaterial({
        color: new Color('#ffffff'), metalness: 0, roughness: 0, transmission: 1, thickness: 2.2, ior: 1.6,
        clearcoat: 1, clearcoatRoughness: 0, envMapIntensity: 0.7, specularIntensity: 0.6,
        attenuationColor: new Color('#e6f4ff'), attenuationDistance: 14,
    }));
    lens.add(glass);
    const marks = new Mesh(new CircleGeometry(1.74, 96), new MeshBasicMaterial({ map: marksTexture(1024), transparent: true, depthWrite: false, toneMapped: false }));
    marks.position.z = 0.53;
    lens.add(marks);
    lens.rotation.set(-0.12, 0.28, 0);
    scene.add(lens);

    const resize = () => {
        const w = stage.clientWidth;
        const h = stage.clientHeight || w;
        renderer.setSize(w, h, false);
        camera.aspect = w / h;
        camera.updateProjectionMatrix();
    };

    // înclinarea după indicator (răspunde utilizatorului; cu mișcare redusă, foarte discret)
    const target = { x: -0.12, y: 0.28, px: 0, py: 0 };
    const onPointer = (e) => {
        start();
        const r = stage.getBoundingClientRect();
        const nx = ((e.clientX - r.left) / r.width) * 2 - 1;
        const ny = ((e.clientY - r.top) / r.height) * 2 - 1;
        const k = reduce ? 0.25 : 1;
        target.y = 0.28 + nx * 0.45 * k;
        target.x = -0.12 + ny * 0.35 * k;
        target.px = nx * 0.55 * k;
        target.py = -ny * 0.7 * k;
    };
    stage.addEventListener('pointermove', onPointer);
    stage.addEventListener('pointerleave', () => { Object.assign(target, { x: -0.12, y: 0.28, px: 0, py: 0 }); start(); });

    // decalajul animației de intrare (se adună peste înclinarea după indicator)
    const intro = { rx: 0, ry: 0, py: 0 };
    let visible = true;
    let raf = 0;
    const tick = () => {
        raf = 0;
        if (!visible || document.hidden) return;
        lens.rotation.x += (target.x + intro.rx - lens.rotation.x) * 0.08;
        lens.rotation.y += (target.y + intro.ry - lens.rotation.y) * 0.08;
        lens.position.x += (target.px - lens.position.x) * 0.08;
        lens.position.y += (target.py + intro.py - lens.position.y) * 0.08;
        renderer.render(scene, camera);
        // desenăm doar cât timp lentila încă se mișcă (economie de baterie pe telefon)
        const moving = Math.abs(target.x + intro.rx - lens.rotation.x) + Math.abs(target.y + intro.ry - lens.rotation.y)
            + Math.abs(target.px - lens.position.x) + Math.abs(target.py + intro.py - lens.position.y) > 0.0015;
        if (moving) raf = requestAnimationFrame(tick);
    };
    const start = () => { if (!raf) raf = requestAnimationFrame(tick); };
    new IntersectionObserver(([e]) => { visible = e.isIntersecting; if (visible) start(); }).observe(stage);
    document.addEventListener('visibilitychange', start);
    new ResizeObserver(() => { resize(); start(); }).observe(stage);

    resize();
    renderer.render(scene, camera);
    stage.prepend(canvas);
    stage.classList.add('is-3d');
    const cap = stage.querySelector('figcaption[data-caption-3d]');
    if (cap) cap.textContent = cap.dataset.caption3d;

    // un singur moment orchestrat la încărcare: lentila „se așază” în fața tabelului
    if (!reduce) {
        Object.assign(intro, { rx: 0.5, ry: -1.2, py: -0.8 });
        lens.rotation.set(-0.12 + 0.5, 0.28 - 1.2, 0);
        lens.position.y = -0.8;
        gsap.to(intro, { rx: 0, ry: 0, py: 0, duration: 1.8, ease: 'power3.out', delay: 0.15, onUpdate: start });
        gsap.from(canvas, { opacity: 0, duration: 0.9, ease: 'power1.out' });
    }
    start();
}
