// Video YouTube: iframe-ul se încarcă abia la apăsare (sau, cu pornire automată, când ajunge pe ecran),
// de pe youtube-nocookie.com, ca pagina să rămână rapidă. Dacă playerul raportează o eroare (clip
// restricționat, indisponibil în regiune etc.), revenim la imaginea de previzualizare cu link spre YouTube,
// în loc să lăsăm pe pagină ecranul negru „Video unavailable”.
const YT_ORIGINS = ['https://www.youtube-nocookie.com', 'https://www.youtube.com'];

export default function video(fig) {
    const id = fig.dataset.videoId;
    const poster = fig.querySelector('.video__poster');
    let iframe = null;

    const restore = () => {
        if (!iframe) return;
        iframe.replaceWith(poster);
        iframe = null;
        fig.classList.add('video--fallback');
        poster.removeEventListener('click', onClick); // de acum linkul duce direct pe YouTube
    };

    const onMessage = (e) => {
        if (!iframe || e.source !== iframe.contentWindow || !YT_ORIGINS.includes(e.origin)) return;
        let msg;
        try { msg = typeof e.data === 'string' ? JSON.parse(e.data) : e.data; } catch { return; }
        if (!msg) return;
        // onError (100/101/150…) sau videoData.errorCode (ex. „auth”: YouTube cere verificare anti-robot)
        if (msg.event === 'onError' || msg.info?.videoData?.errorCode) restore();
    };

    const load = (auto) => {
        if (iframe) return;
        const base = `enablejsapi=1&origin=${encodeURIComponent(location.origin)}&rel=0&playsinline=1&modestbranding=1`;
        const params = auto ? `autoplay=1&mute=1&loop=1&playlist=${id}&controls=1&${base}` : `autoplay=1&${base}`;
        iframe = document.createElement('iframe');
        iframe.src = `https://www.youtube-nocookie.com/embed/${id}?${params}`;
        iframe.title = poster.getAttribute('aria-label') || 'Videoclip';
        iframe.allow = 'autoplay; encrypted-media; picture-in-picture; fullscreen';
        iframe.addEventListener('load', () => {
            // ne abonăm la evenimentele playerului (inclusiv onError) prin postMessage, fără scriptul YouTube
            iframe?.contentWindow?.postMessage(JSON.stringify({ event: 'listening', id, channel: 'widget' }), '*');
        });
        poster.replaceWith(iframe);
    };

    function onClick(e) { e.preventDefault(); load(false); }

    window.addEventListener('message', onMessage);
    poster.addEventListener('click', onClick);
    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if ('autoplay' in fig.dataset && !reduce && 'IntersectionObserver' in window) {
        const io = new IntersectionObserver((entries) => {
            if (entries[0].isIntersecting) { io.disconnect(); load(true); }
        }, { threshold: 0.35 });
        io.observe(fig);
    }
}
