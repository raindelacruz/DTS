        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var rowsPerPage = 10;

    document.querySelectorAll('.table-responsive > table').forEach(function (table) {
        var wrapper = table.closest('.table-responsive');
        var tbody = table.tBodies && table.tBodies[0];

        if (!wrapper || !tbody || table.dataset.paginated === 'true') {
            return;
        }

        var rows = Array.prototype.slice.call(tbody.rows);
        if (rows.length === 0 || (rows.length === 1 && rows[0].cells.length === 1 && rows[0].cells[0].colSpan > 1)) {
            return;
        }

        table.dataset.paginated = 'true';
        wrapper.classList.add('table-scroll-10');

        if (rows.length <= rowsPerPage) {
            return;
        }

        var page = 1;
        var pages = Math.ceil(rows.length / rowsPerPage);
        var controls = document.createElement('div');
        controls.className = 'table-pagination';
        controls.innerHTML = [
            '<span data-table-page-summary></span>',
            '<span class="table-pagination-actions">',
            '<button type="button" class="btn btn-sm btn-outline-secondary" data-table-page-prev>Previous</button>',
            '<button type="button" class="btn btn-sm btn-outline-secondary" data-table-page-next>Next</button>',
            '</span>'
        ].join('');

        var summary = controls.querySelector('[data-table-page-summary]');
        var previous = controls.querySelector('[data-table-page-prev]');
        var next = controls.querySelector('[data-table-page-next]');

        function render() {
            var start = (page - 1) * rowsPerPage;
            var end = start + rowsPerPage;

            rows.forEach(function (row, index) {
                row.hidden = index < start || index >= end;
            });

            summary.textContent = 'Rows ' + (start + 1) + '-' + Math.min(end, rows.length) + ' of ' + rows.length;
            previous.disabled = page === 1;
            next.disabled = page === pages;
            wrapper.scrollTop = 0;
        }

        previous.addEventListener('click', function () {
            if (page > 1) {
                page--;
                render();
            }
        });

        next.addEventListener('click', function () {
            if (page < pages) {
                page++;
                render();
            }
        });

        wrapper.insertAdjacentElement('afterend', controls);
        render();
    });
});
</script>
</body>
</html>
