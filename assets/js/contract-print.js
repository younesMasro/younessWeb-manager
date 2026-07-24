/**
 * Pagination du contrat pour l'impression — v2.8
 *
 * Le navigateur sait couper une page. Il ne sait pas répéter un pied de page,
 * numéroter « Page 2 / 5 », ni empêcher un titre d'article de rester seul en
 * bas d'une feuille. C'est tout ce que fait ce fichier : il découpe le
 * document en boîtes A4 réelles et pose sur chacune son en-tête courant et
 * son pied de page.
 *
 * Aucune dépendance. Il s'exécute dans la fenêtre d'impression, où seuls
 * assets/css/contract.css et ce script sont chargés.
 *
 * Données attendues dans window.VB_PRINT :
 *   { number, title, client, website, confidential }
 */
(function () {
    'use strict';

    var DATA = window.VB_PRINT || {};

    /* ── Construction d'une feuille ──────────────────────────── */

    function buildPage(index) {
        var page = document.createElement('div');
        page.className = 'vb-page';

        // Pages 2 et suivantes : bandeau de rappel du document.
        if (index > 0) {
            var run = document.createElement('div');
            run.className = 'vb-run-head';
            run.innerHTML =
                '<span><strong>' + esc(DATA.title || 'Contrat') + '</strong></span>' +
                '<span>' + esc(DATA.client || '') + '</span>';
            page.appendChild(run);
        }

        var body = document.createElement('div');
        body.className = 'vb-page-body vb-ct-doc';
        page.appendChild(body);

        var foot = document.createElement('div');
        foot.className = 'vb-page-foot';
        foot.innerHTML =
            '<span class="vb-foot-left">' + esc(DATA.number || '') + '</span>' +
            '<span class="vb-foot-mid">' + esc(DATA.website || '') + '</span>' +
            '<span class="vb-foot-right">' +
                '<span class="vb-foot-page"></span>' +
                '<span class="vb-foot-sep"> · </span>' +
                '<span class="vb-foot-confidential">' + esc(DATA.confidential || 'Confidentiel') + '</span>' +
            '</span>';
        page.appendChild(foot);

        return { el: page, body: body };
    }

    function esc(s) {
        return String(s).replace(/[&<>"]/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
        });
    }

    /* ── Découpage du document en « atomes » ─────────────────── */

    /**
     * Un atome est un bloc qu'on ne coupe pas.
     *
     * Une section marquée .vb-ct-atom (en-tête, parties, signatures) reste
     * d'un seul tenant : la couper n'aurait aucun sens. Toutes les autres —
     * le corps du contrat, le récapitulatif financier — sont décomposées en
     * leurs enfants, sinon une section un peu longue laisserait une page
     * aux trois quarts vide derrière elle.
     */
    function collectAtoms(source) {
        var atoms = [];

        Array.prototype.forEach.call(source.children, function (child) {
            if (child.classList.contains('vb-ct-atom') || !child.children.length) {
                atoms.push({ node: child, keepWithNext: false });
                return;
            }
            Array.prototype.forEach.call(child.children, function (node) {
                atoms.push({ node: node, keepWithNext: keepsWithNext(node) });
            });
        });

        return atoms;
    }

    /**
     * Blocs qui ne doivent jamais terminer une page : un titre d'article, et
     * une phrase d'introduction (« Le CLIENT s'engage à : ») dont la liste
     * commencerait sur la feuille suivante.
     */
    function keepsWithNext(node) {
        if (node.classList.contains('vb-ct-article')) return true;
        if (node.classList.contains('vb-ct-section-title')) return true;
        if (node.tagName === 'P' && /[:：]\s*$/.test(node.textContent || '')) return true;
        return false;
    }

    /* ── Placement ───────────────────────────────────────────── */

    function overflows(body) {
        return body.scrollHeight > body.clientHeight + 1;
    }

    function paginate(source, container) {
        var atoms = collectAtoms(source);
        var pages = [];
        var page  = buildPage(0);

        container.appendChild(page.el);
        pages.push(page);

        function newPage() {
            page = buildPage(pages.length);
            container.appendChild(page.el);
            pages.push(page);
            return page;
        }

        for (var i = 0; i < atoms.length; i++) {
            var atom = atoms[i];
            var node = atom.node.cloneNode(true);
            if (atom.keepWithNext) node.setAttribute('data-vb-keep-next', '1');

            page.body.appendChild(node);

            if (!overflows(page.body)) {
                // Titre placé en dernier sur la page : il partira avec la suite.
                continue;
            }

            page.body.removeChild(node);

            // Le bloc peut-il être coupé lui-même ? Seuls les tableaux le
            // supportent proprement, en répétant leur en-tête.
            var split = node.tagName === 'TABLE' ? splitTable(node, page, newPage) : null;
            if (split) continue;

            // Page vide : le bloc est plus haut qu'une feuille, on l'accepte
            // tel quel plutôt que de boucler indéfiniment.
            if (!page.body.firstChild) {
                page.body.appendChild(node);
                newPage();
                continue;
            }

            // Le titre ou la phrase d'introduction restés juste au-dessus
            // partent avec le bloc qu'ils annoncent.
            var trailing = takeTrailingKeeper(page.body);
            newPage();
            if (trailing) page.body.appendChild(trailing);
            page.body.appendChild(node);
        }

        // Une page finale vide n'a aucune raison d'exister.
        if (pages.length > 1 && !page.body.firstChild) {
            container.removeChild(page.el);
            pages.pop();
        }

        stampNumbers(pages);
        return pages;
    }

    /**
     * Retire le bloc « à garder avec la suite » resté en bas de page pour
     * l'emmener sur la feuille suivante.
     *
     * Jamais s'il est seul sur la page : on créerait une page blanche, et le
     * bloc reviendrait au même endroit à l'itération suivante — boucle
     * infinie garantie.
     */
    function takeTrailingKeeper(body) {
        var last = body.lastElementChild;
        if (!last || !last.hasAttribute('data-vb-keep-next')) return null;
        if (body.children.length < 2) return null;
        body.removeChild(last);
        return last;
    }

    /**
     * Coupe un tableau trop long entre deux lignes, en répétant l'en-tête sur
     * la page suivante. Un tableau tranché au milieu d'une ligne, avec des
     * montants illisibles, est le défaut le plus visible d'un PDF bricolé.
     */
    function splitTable(table, page, newPage) {
        var tbody = table.tBodies[0];
        if (!tbody || tbody.rows.length < 2) return false;

        var rows  = Array.prototype.slice.call(tbody.rows);
        var tfoot = table.tFoot;

        var current = table.cloneNode(true);
        clearBody(current);
        if (current.tFoot) current.removeChild(current.tFoot);
        page.body.appendChild(current);

        for (var i = 0; i < rows.length; i++) {
            current.tBodies[0].appendChild(rows[i].cloneNode(true));

            if (!overflows(page.body)) continue;

            // La ligne ne tient pas : elle ouvre la page suivante.
            current.tBodies[0].removeChild(current.tBodies[0].lastElementChild);

            if (!current.tBodies[0].rows.length) {
                page.body.removeChild(current);
            }
            page    = newPage();
            current = table.cloneNode(true);
            clearBody(current);
            if (current.tFoot) current.removeChild(current.tFoot);
            page.body.appendChild(current);
            current.tBodies[0].appendChild(rows[i].cloneNode(true));
        }

        // Le total ne se sépare jamais de la dernière ligne du tableau.
        if (tfoot) {
            current.appendChild(tfoot.cloneNode(true));
            if (overflows(page.body)) {
                current.removeChild(current.tFoot);
                var lastRow = current.tBodies[0].lastElementChild;
                if (lastRow) current.tBodies[0].removeChild(lastRow);

                page    = newPage();
                var tail = table.cloneNode(true);
                clearBody(tail);
                if (tail.tFoot) tail.removeChild(tail.tFoot);
                page.body.appendChild(tail);
                if (lastRow) tail.tBodies[0].appendChild(lastRow);
                tail.appendChild(tfoot.cloneNode(true));
            }
        }
        return true;
    }

    function clearBody(table) {
        var tbody = table.tBodies[0];
        while (tbody && tbody.firstChild) tbody.removeChild(tbody.firstChild);
    }

    /** « Page 2 / 5 » — le total n'est connu qu'une fois tout placé. */
    function stampNumbers(pages) {
        pages.forEach(function (p, i) {
            var slot = p.el.querySelector('.vb-foot-page');
            if (slot) slot.textContent = 'Page ' + (i + 1) + ' / ' + pages.length;
        });
    }

    /* ── Démarrage ───────────────────────────────────────────── */

    /**
     * Rien ne se mesure tant que le logo et la feuille de style ne sont pas
     * chargés : une image sans hauteur fausserait chaque coupure de page.
     */
    function whenReady(root, done) {
        var pending = 1;                      // 1 = la feuille de style
        var settled = false;

        function tick() {
            if (--pending > 0 || settled) return;
            settled = true;
            done();
        }

        Array.prototype.forEach.call(root.querySelectorAll('img'), function (img) {
            if (img.complete) return;
            pending++;
            img.addEventListener('load',  tick);
            img.addEventListener('error', tick);
        });

        if (document.readyState === 'complete') tick();
        else window.addEventListener('load', tick);

        // Filet : on n'attend jamais indéfiniment une ressource qui ne vient pas.
        setTimeout(function () { if (!settled) { settled = true; done(); } }, 3000);
    }

    function run() {
        var holder = document.getElementById('vb-print-source');
        var target = document.getElementById('vb-print-pages');
        if (!holder || !target) return;

        // Le contenu transplanté est enveloppé dans son <article> d'origine :
        // ce sont ses sections qui forment les atomes, pas l'article lui-même.
        var source = holder.firstElementChild || holder;
        if (source.children.length === 1 && source.firstElementChild.children.length > 1) {
            source = source.firstElementChild;
        }

        try {
            paginate(source, target);
            holder.parentNode.removeChild(holder);
        } catch (e) {
            // Un document imprimé au fil du navigateur vaut mieux qu'une page
            // blanche : on retombe sur le flux simple.
            if (window.console) console.error('Pagination impossible', e);
            target.parentNode.removeChild(target);
            holder.removeAttribute('hidden');
            holder.classList.add('vb-ct-doc');
        }

        window.focus();
        window.print();
    }

    whenReady(document, run);
})();
