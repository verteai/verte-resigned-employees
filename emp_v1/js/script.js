// ── COLLAPSIBLE ─────────────────────────────────────────────────────
function toggleAllRecords() {
    var body  = document.getElementById('allRecordsBody');
    var btn   = document.getElementById('allRecordsToggle');
    var label = btn.querySelector('.toggle-label');
    if (!body) return;
    var collapsed = body.classList.toggle('hidden');
    btn.classList.toggle('collapsed', collapsed);
    label.textContent = collapsed ? 'Expand' : 'Collapse';
}

// ── TABLE ENGINE: SEARCH + SORT + PAGINATE ──────────────────────────
(function () {
    var tbody    = document.getElementById('allRecordsTbody');
    var infoEl   = document.getElementById('paginationInfo');
    var ctrlEl   = document.getElementById('paginationControls');
    var searchEl = document.getElementById('recordSearch');
    var clearBtn = document.getElementById('searchClear');
    if (!tbody) return;

    var allRows     = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
    var perPage     = 5;
    var currentPage = 1;
    var query       = '';
    var sortCol     = -1;
    var sortDir     = 1;

    function cellText(row, colIdx) {
        var cells = row.querySelectorAll('td');
        if (!cells[colIdx]) return '';
        return cells[colIdx].textContent.trim().toLowerCase();
    }

    function rowMatchesQuery(row, q) {
        if (!q) return true;
        return row.textContent.toLowerCase().indexOf(q) !== -1;
    }

    function filteredRows() {
        return allRows.filter(function (r) { return rowMatchesQuery(r, query); });
    }

    function sortedRows(rows) {
        if (sortCol < 0) return rows.slice();
        return rows.slice().sort(function (a, b) {
            var av = cellText(a, sortCol);
            var bv = cellText(b, sortCol);
            var an = av.replace(/-/g, '');
            var bn = bv.replace(/-/g, '');
            if (!isNaN(an) && !isNaN(bn) && an !== '' && bn !== '') {
                return (parseFloat(an) - parseFloat(bn)) * sortDir;
            }
            return av.localeCompare(bv) * sortDir;
        });
    }

    function escHtml(s) {
        return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    function render() {
        var visible = sortedRows(filteredRows());
        var total   = visible.length;
        var tp      = Math.max(1, Math.ceil(total / perPage));
        if (currentPage > tp) currentPage = tp;
        var start   = (currentPage - 1) * perPage;
        var end     = Math.min(start + perPage, total);

        allRows.forEach(function (r) { r.style.display = 'none'; });

        var old = tbody.querySelector('.no-results-row');
        if (old) old.parentNode.removeChild(old);

        if (total === 0) {
            var nr = document.createElement('tr');
            nr.className = 'no-results-row';
            nr.innerHTML = '<td colspan="17" class="no-results">No records match "<strong>' + escHtml(query) + '</strong>"</td>';
            tbody.appendChild(nr);
            if (infoEl) infoEl.textContent = '0 records found';
            if (ctrlEl) ctrlEl.innerHTML = '';
            return;
        }

        visible.forEach(function (r, i) {
            tbody.appendChild(r);
            r.style.display = (i >= start && i < end) ? '' : 'none';
        });

        if (infoEl) infoEl.textContent = 'Showing ' + (start + 1) + '\u2013' + end + ' of ' + total + (query ? ' filtered' : '') + ' record' + (total !== 1 ? 's' : '');
        if (ctrlEl) renderControls(tp);
    }

    function renderControls(tp) {
        ctrlEl.innerHTML = '';

        function makeBtn(label, page, isActive, disabled) {
            var b = document.createElement('button');
            b.className = 'page-btn' + (isActive ? ' active' : '');
            b.textContent = label;
            b.disabled = disabled;
            b.onclick = function () { currentPage = page; render(); };
            return b;
        }

        ctrlEl.appendChild(makeBtn('\u00ab', 1, false, currentPage === 1));
        ctrlEl.appendChild(makeBtn('\u2039', currentPage - 1, false, currentPage === 1));

        var rangeStart = Math.max(1, currentPage - 2);
        var rangeEnd   = Math.min(tp, currentPage + 2);

        if (rangeStart > 1) {
            var d1 = document.createElement('span');
            d1.textContent = '\u2026';
            d1.style.cssText = 'padding:0 4px;color:#aaa;font-size:0.8rem';
            ctrlEl.appendChild(d1);
        }
        for (var p = rangeStart; p <= rangeEnd; p++) {
            ctrlEl.appendChild(makeBtn(p, p, p === currentPage, false));
        }
        if (rangeEnd < tp) {
            var d2 = document.createElement('span');
            d2.textContent = '\u2026';
            d2.style.cssText = 'padding:0 4px;color:#aaa;font-size:0.8rem';
            ctrlEl.appendChild(d2);
        }

        ctrlEl.appendChild(makeBtn('\u203a', currentPage + 1, false, currentPage === tp));
        ctrlEl.appendChild(makeBtn('\u00bb', tp, false, currentPage === tp));
    }

    // Sort on header click
    var headers = document.querySelectorAll('#allRecordsTable thead th.sortable');
    headers.forEach(function (th) {
        th.addEventListener('click', function () {
            var col = parseInt(th.getAttribute('data-col'), 10);
            if (sortCol === col) {
                sortDir = -sortDir;
            } else {
                sortCol = col;
                sortDir = 1;
            }
            headers.forEach(function (h) { h.classList.remove('sort-asc', 'sort-desc'); });
            th.classList.add(sortDir === 1 ? 'sort-asc' : 'sort-desc');
            currentPage = 1;
            render();
        });
    });

    window.onSearch = function (val) {
        query = val.trim().toLowerCase();
        if (clearBtn) clearBtn.style.display = query ? 'block' : 'none';
        currentPage = 1;
        render();
    };

    window.clearSearch = function () {
        if (searchEl) { searchEl.value = ''; searchEl.focus(); }
        if (clearBtn) clearBtn.style.display = 'none';
        query = '';
        currentPage = 1;
        render();
    };

    window.changePerPage = function (val) {
        perPage = parseInt(val, 10);
        currentPage = 1;
        render();
    };

    render();
})();