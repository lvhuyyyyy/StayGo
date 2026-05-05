// ===== USERS PAGE =====
(function () {
    window.confirmDelete = function (userId, userName, userEmail) {
        document.getElementById('delete-username').textContent = userName;
        document.getElementById('delete-email').textContent    = userEmail;
        document.getElementById('delete-confirm-btn').href     = 'delete_user.php?id=' + userId;
        document.getElementById('deleteModal').style.display   = 'flex';
    };

    const deleteModal = document.getElementById('deleteModal');
    if (deleteModal) {
        deleteModal.addEventListener('click', function (e) {
            if (e.target === this) this.style.display = 'none';
        });
    }

    const notifyBar = document.getElementById('notify-bar');
    if (notifyBar) {
        setTimeout(() => {
            notifyBar.style.animation = 'fadeOut .5s forwards';
            setTimeout(() => notifyBar.remove(), 500);
        }, 3000);
    }
})();


// ===== REVIEWS PAGE =====
(function () {
    window.deleteReview = function (reviewId, hotelName) {
        if (!confirm(`Xóa đánh giá này của khách sạn "${hotelName}"?\n\nHành động không thể hoàn tác.`)) return;

        const row = document.getElementById('rv-row-' + reviewId);
        const btn = row.querySelector('.btn-delete');
        btn.disabled    = true;
        btn.textContent = 'Đang xóa...';

        const fd = new FormData();
        fd.append('action',    'delete');
        fd.append('review_id', reviewId);

        fetch('/tour_khach_san_project/pages/reviews_handler.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    row.style.transition = 'opacity .3s';
                    row.style.opacity    = '0';
                    setTimeout(() => row.remove(), 300);
                    showToast(data.message, 'success');
                } else {
                    showToast(data.message, 'error');
                    btn.disabled    = false;
                    btn.textContent = 'Xóa';
                }
            })
            .catch(() => {
                showToast('Có lỗi xảy ra.', 'error');
                btn.disabled    = false;
                btn.textContent = 'Xóa';
            });
    };

    window.showToast = function (msg, type) {
        const t = document.getElementById('rv-toast');
        if (!t) return;
        t.textContent = msg;
        t.className   = 'rv-toast show ' + type;
        setTimeout(() => t.classList.remove('show'), 3000);
    };
})();


// ===== MANAGE HOTEL PAGE =====
(function () {
    const imgFile = document.getElementById('imgFile');
    if (!imgFile) return;

    imgFile.addEventListener('change', function () { previewImgHotel(this); });

    function previewImgHotel(input) {
        if (!input.files || !input.files[0]) return;
        const file = input.files[0];
        if (file.size > 8 * 1024 * 1024) { alert('File quá lớn! Tối đa 8MB.'); input.value = ''; return; }
        const reader = new FileReader();
        reader.onload = e => {
            document.getElementById('previewImg').src = e.target.result;
            document.getElementById('previewName').textContent = '✓ ' + file.name + ' (' + (file.size / 1024).toFixed(0) + ' KB)';
            document.getElementById('uploadPreview').style.display = 'block';
            document.getElementById('btnUpload').disabled = false;
            const zone = document.getElementById('uploadZone');
            if (zone) {
                zone.style.borderColor = '#16a34a';
                zone.style.background  = '#f0fdf4';
                const uzText = zone.querySelector('.uz-text');
                if (uzText) uzText.textContent = 'Đã chọn: ' + file.name;
            }
        };
        reader.readAsDataURL(file);
    }

    const zone = document.getElementById('uploadZone');
    if (zone) {
        zone.addEventListener('dragover',  e => { e.preventDefault(); zone.classList.add('drag-over'); });
        zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
        zone.addEventListener('drop', e => {
            e.preventDefault();
            zone.classList.remove('drag-over');
            if (!e.dataTransfer.files.length) return;
            try {
                const dt = new DataTransfer();
                dt.items.add(e.dataTransfer.files[0]);
                imgFile.files = dt.files;
                previewImgHotel(imgFile);
            } catch (err) { console.warn('Drag drop fallback:', err); }
        });
    }

    const uploadForm = document.getElementById('uploadForm');
    if (uploadForm) {
        uploadForm.addEventListener('submit', function (e) {
            if (!imgFile.files || !imgFile.files[0]) {
                e.preventDefault();
                alert('Vui lòng chọn ảnh trước!');
                return;
            }
            const btn = document.getElementById('btnUpload');
            if (btn) { btn.disabled = true; btn.textContent = '⟳ Đang upload...'; }
        });
    }
})();


// ===== HOTEL IMAGES PAGE (multiple upload) =====
(function () {
    const dz = document.getElementById('dropzone');
    if (!dz) return;

    window.previewFiles = function (input) {
        const area = document.getElementById('previewArea');
        const text = document.getElementById('dzText');
        if (!area) return;
        area.innerHTML = '';
        Array.from(input.files).forEach(f => {
            const r = new FileReader();
            r.onload = e => {
                const img = document.createElement('img');
                img.src = e.target.result;
                area.appendChild(img);
            };
            r.readAsDataURL(f);
        });
        if (input.files.length > 0 && text)
            text.textContent = input.files.length + ' ảnh đã chọn & sẵn sàng upload';
    };

    ['dragenter', 'dragover'].forEach(ev => dz.addEventListener(ev, e => { e.preventDefault(); dz.classList.add('drag'); }));
    ['dragleave', 'drop'].forEach(ev => dz.addEventListener(ev, e => { e.preventDefault(); dz.classList.remove('drag'); }));
    dz.addEventListener('drop', e => {
        const fi = document.getElementById('fileInput');
        if (fi) { fi.files = e.dataTransfer.files; previewFiles(fi); }
    });

    window.openEdit = function (id, cap, sort) {
        document.getElementById('edit_id').value      = id;
        document.getElementById('edit_caption').value = cap;
        document.getElementById('edit_sort').value    = sort;
        document.getElementById('editModal').classList.add('open');
    };
    window.closeEdit = function () { document.getElementById('editModal').classList.remove('open'); };
    document.getElementById('editModal')?.addEventListener('click', e => {
        if (e.target === document.getElementById('editModal')) closeEdit();
    });

    window.doDelete = function (id, hotelId) {
        if (confirm('Xóa ảnh này?\nThao tác không thể hoàn tác!'))
            location.href = 'hotel_images.php?delete=1&del_id=' + id + '&hotel_id=' + hotelId;
    };

    window.openLb = function (src, cap) {
        document.getElementById('lbImg').src = src;
        document.getElementById('lbCap').textContent = cap || '';
        document.getElementById('lightbox').classList.add('open');
    };
    window.closeLb = function () { document.getElementById('lightbox').classList.remove('open'); };
    document.getElementById('lightbox')?.addEventListener('click', e => {
        if (e.target === document.getElementById('lightbox')) closeLb();
    });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeLb(); });
})();


// ===== EDIT ROOM PAGE =====
(function () {
    const roomForm = document.getElementById('roomForm');
    if (!roomForm) return;

    window.previewImage = function (input) {
        if (!input.files || !input.files[0]) return;
        const file = input.files[0];
        if (file.size > 5 * 1024 * 1024) { alert('File quá lớn! Tối đa 5MB.'); input.value = ''; return; }
        const reader = new FileReader();
        reader.onload = e => {
            document.getElementById('previewImg').src = e.target.result;
            document.getElementById('previewName').textContent = '✓ ' + file.name + ' (' + (file.size / 1024).toFixed(0) + ' KB)';
            document.getElementById('imgPreview').style.display = 'block';
            const zone = document.getElementById('uploadZone');
            if (zone) {
                zone.style.borderColor = '#38a169';
                zone.style.background  = '#f0fff4';
                const uploadText = document.getElementById('uploadText');
                if (uploadText) uploadText.querySelector('div').textContent = 'Đã chọn ảnh';
            }
        };
        reader.readAsDataURL(file);
    };

    roomForm.addEventListener('submit', function (e) {
        const custom = document.getElementById('room_name_custom').value.trim();
        const select = document.getElementById('room_name_select').value.trim();
        if (!custom && !select) { e.preventDefault(); alert('Vui lòng chọn hoặc nhập tên loại phòng!'); }
    });

    const zone = document.getElementById('uploadZone');
    if (zone) {
        zone.addEventListener('dragover',  e => { e.preventDefault(); zone.style.borderColor = '#3182ce'; });
        zone.addEventListener('dragleave', () => { zone.style.borderColor = '#cbd5e0'; });
        zone.addEventListener('drop', e => {
            e.preventDefault();
            zone.style.borderColor = '#cbd5e0';
            if (!e.dataTransfer.files.length) return;
            try {
                const dt = new DataTransfer();
                dt.items.add(e.dataTransfer.files[0]);
                const imgInput = document.getElementById('imgInput');
                imgInput.files = dt.files;
                previewImage(imgInput);
            } catch (err) { console.warn(err); }
        });
    }
})();


// ===== DASHBOARD PAGE =====
(function () {
    // Chỉ chạy khi ở trang dashboard
    if (!document.getElementById('lineChart')) return;

    // Chờ Chart.js CDN load xong rồi mới vẽ
    function initCharts() {
        if (typeof Chart === 'undefined') {
            setTimeout(initCharts, 50);
            return;
        }

        Chart.defaults.font.family = "'Segoe UI', Arial, sans-serif";

        // 1. LINE CHART – Doanh thu + Số đơn theo tháng
        new Chart(document.getElementById('lineChart'), {
            type: 'line',
            data: {
                labels: chartLabels,
                datasets: [
                    {
                        label: 'Doanh thu (đ)',
                        data: chartRevenue,
                        borderColor: '#1e73be',
                        backgroundColor: 'rgba(30,115,190,.08)',
                        borderWidth: 2.5,
                        pointBackgroundColor: '#1e73be',
                        pointRadius: 5,
                        tension: .4,
                        fill: true,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Số đơn',
                        data: chartBookings,
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16,185,129,.06)',
                        borderWidth: 2,
                        pointBackgroundColor: '#10b981',
                        pointRadius: 4,
                        tension: .4,
                        fill: true,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'top', labels: { font: { size: 12 }, padding: 16 } },
                    tooltip: {
                        callbacks: {
                            label: ctx => ctx.datasetIndex === 0
                                ? ' ' + ctx.parsed.y.toLocaleString('vi-VN') + 'đ'
                                : ' ' + ctx.parsed.y + ' đơn'
                        }
                    }
                },
                scales: {
                    y:  { type: 'linear', position: 'left',  ticks: { callback: v => (v / 1e6).toFixed(1) + 'M', font: { size: 11 } }, grid: { color: '#f0f4f8' } },
                    y1: { type: 'linear', position: 'right', ticks: { stepSize: 1, font: { size: 11 } }, grid: { drawOnChartArea: false } }
                }
            }
        });

        // 2. DONUT CHART – Tỉ lệ trạng thái
        new Chart(document.getElementById('donutChart'), {
            type: 'doughnut',
            data: {
                labels: donutLabels,
                datasets: [{
                    data: donutData,
                    backgroundColor: donutColors,
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                cutout: '65%',
                plugins: {
                    legend: { position: 'bottom', labels: { font: { size: 11 }, padding: 10 } }
                }
            }
        });

        // 3. BAR CHART – Top khách sạn (horizontal)
        new Chart(document.getElementById('barChart'), {
            type: 'bar',
            data: {
                labels: hotelNames,
                datasets: [{
                    label: 'Lượt đặt',
                    data: hotelCounts,
                    backgroundColor: ['#1e73be', '#10b981', '#f59e0b', '#8b5cf6', '#ef4444'],
                    borderRadius: 6,
                    borderSkipped: false
                }]
            },
            options: {
                responsive: true,
                indexAxis: 'y',
                plugins: { legend: { display: false } },
                scales: {
                    x: { ticks: { stepSize: 1, font: { size: 11 } }, grid: { color: '#f0f4f8' } },
                    y: { ticks: { font: { size: 11 } }, grid: { display: false } }
                }
            }
        });
    }

    // Gọi initCharts sau khi DOM sẵn sàng
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCharts);
    } else {
        initCharts();
    }
})();


// ===== BLOG FORM PAGE =====
(function () {
    if (!document.getElementById('blogForm')) return;

    window.previewImg = function (url, id) {
        const img   = document.getElementById(id);
        const empty = document.getElementById(id + '-empty');
        if (url.trim()) {
            img.src = url.trim();
            img.style.display = 'block';
            if (empty) empty.style.display = 'none';
            img.onerror = function () {
                this.style.display = 'none';
                if (empty) empty.style.display = 'flex';
            };
        } else {
            img.style.display = 'none';
            if (empty) empty.style.display = 'flex';
        }
    };

    window.uploadImage = async function (input, targetId, previewId, labelTextId) {
        if (!input.files || !input.files[0]) return;
        const file  = input.files[0];
        const label = input.closest('label');
        const span  = document.getElementById(labelTextId);

        if (file.size > 5 * 1024 * 1024) { alert('Ảnh quá lớn! Vui lòng chọn ảnh dưới 5MB.'); return; }

        label.classList.add('loading');
        span.textContent = '⟳ Đang tải lên...';

        const formData = new FormData();
        formData.append('image', file);

        try {
            const res  = await fetch('/tour_khach_san_project/admin_lvhuy_kontum/blog_form.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.url) {
                document.getElementById(targetId).value = data.url;
                previewImg(data.url, previewId);
                label.classList.remove('loading');
                label.classList.add('success');
                span.textContent = '✓ Tải lên thành công!';
                setTimeout(() => { label.classList.remove('success'); span.textContent = '📷 Chọn ảnh từ máy tính'; }, 2500);
            } else {
                label.classList.remove('loading');
                span.textContent = '📷 Chọn ảnh từ máy tính';
                alert('Lỗi: ' + (data.error || 'Upload thất bại'));
            }
        } catch (e) {
            label.classList.remove('loading');
            span.textContent = '📷 Chọn ảnh từ máy tính';
            alert('Lỗi kết nối server!');
        }
        input.value = '';
    };

    function renderTags() {
        const input   = document.querySelector('input[name="tags"]');
        const preview = document.getElementById('tags-preview');
        if (!input || !preview) return;
        const tags = input.value.split(',').map(t => t.trim()).filter(t => t);
        preview.innerHTML = tags.map(t =>
            `<span style="padding:3px 10px;background:#eff6ff;color:#1d4ed8;border-radius:20px;font-size:11.5px;font-weight:600">${t}</span>`
        ).join('');
    }
    document.querySelector('input[name="tags"]')?.addEventListener('input', renderTags);
    renderTags();

    document.getElementById('blogForm').addEventListener('submit', function (e) {
        const title   = document.querySelector('input[name="title"]').value.trim();
        const summary = document.querySelector('textarea[name="summary"]').value.trim();
        const thumb   = document.getElementById('inp-thumb').value.trim();
        if (!title)   { alert('Vui lòng nhập tiêu đề!');  e.preventDefault(); return; }
        if (!summary) { alert('Vui lòng nhập tóm tắt!'); e.preventDefault(); return; }
        if (!thumb)   { alert('Vui lòng nhập URL hoặc upload ảnh thumbnail!'); e.preventDefault(); return; }
    });
})();


// ===== BLOG HOTELS PAGE =====
(function () {
    if (!document.getElementById('addForm')) return;

    window.updateStars = function (val) {
        document.getElementById('ratingVal').textContent   = parseFloat(val).toFixed(1);
        const stars = Math.round(val / 2);
        document.getElementById('starDisplay').textContent = '★'.repeat(Math.max(0, Math.min(5, stars)));
        document.getElementById('sumRating').textContent   = parseFloat(val).toFixed(1);
    };
    updateStars(8.5);

    document.getElementById('hotelName').addEventListener('input', function () {
        document.getElementById('sumName').textContent = this.value || '—';
    });
    document.querySelector('input[name=price]').addEventListener('input', function () {
        const v = parseInt(this.value);
        document.getElementById('sumPrice').textContent = v ? v.toLocaleString('vi-VN') + 'đ' : '—';
    });

    document.getElementById('mainImgFile').addEventListener('change', function () { previewMainImg(this); });

    function previewMainImg(input) {
        if (!input.files || !input.files[0]) return;
        const file = input.files[0];
        if (file.size > 8 * 1024 * 1024) { alert('File quá lớn! Tối đa 8MB.'); input.value = ''; return; }
        const reader = new FileReader();
        reader.onload = e => {
            document.getElementById('mainImgPreviewImg').src = e.target.result;
            document.getElementById('mainImgName').textContent = '✓ ' + file.name;
            document.getElementById('mainImgPreview').style.display = 'block';
            const zone = document.getElementById('mainImgZone');
            zone.style.borderColor = '#16a34a';
            zone.style.background  = '#f0fdf4';
            zone.querySelector('.uz-text').textContent = file.name;
            document.getElementById('sumImg').textContent = file.name;
            document.getElementById('sumImg').style.color = '#16a34a';
        };
        reader.readAsDataURL(file);
    }

    let galleryFiles = [];
    document.getElementById('galleryFiles').addEventListener('change', function () { addGalleryFiles(this.files); });

    function addGalleryFiles(fileList) {
        Array.from(fileList).forEach(file => {
            if (file.size > 8 * 1024 * 1024) return;
            const ext = file.name.split('.').pop().toLowerCase();
            if (!['jpg', 'jpeg', 'png', 'webp'].includes(ext)) return;
            galleryFiles.push(file);
        });
        renderGalleryPreview();
    }

    function renderGalleryPreview() {
        const grid = document.getElementById('galleryPreviewGrid');
        grid.innerHTML = '';
        document.getElementById('sumGallery').textContent = galleryFiles.length + ' ảnh';
        galleryFiles.forEach((file, idx) => {
            const item = document.createElement('div');
            item.className = 'gp-item';
            const reader = new FileReader();
            reader.onload = e => {
                item.innerHTML = `
                    <img src="${e.target.result}" alt="">
                    <button type="button" class="gp-remove" onclick="removeGalleryItem(${idx})" title="Xóa">×</button>
                    <div class="gp-item-body">
                        <input type="text" class="gp-caption" placeholder="Chú thích..."
                            name="gallery_captions[]" data-idx="${idx}">
                    </div>`;
            };
            reader.readAsDataURL(file);
            grid.appendChild(item);
        });
        rebuildFileInput();
    }

    window.removeGalleryItem = function (idx) {
        galleryFiles.splice(idx, 1);
        renderGalleryPreview();
    };

    function rebuildFileInput() {
        try {
            const dt = new DataTransfer();
            galleryFiles.forEach(f => dt.items.add(f));
            document.getElementById('galleryFiles').files = dt.files;
        } catch (e) { console.warn('DataTransfer not supported:', e); }
    }

    const galleryDrop = document.getElementById('galleryDrop');
    galleryDrop.addEventListener('dragover',  e => { e.preventDefault(); galleryDrop.classList.add('drag-over'); });
    galleryDrop.addEventListener('dragleave', () => galleryDrop.classList.remove('drag-over'));
    galleryDrop.addEventListener('drop', e => {
        e.preventDefault();
        galleryDrop.classList.remove('drag-over');
        if (e.dataTransfer.files.length) addGalleryFiles(e.dataTransfer.files);
    });

    const mainZone = document.getElementById('mainImgZone');
    mainZone.addEventListener('dragover',  e => { e.preventDefault(); mainZone.style.borderColor = '#1e73be'; });
    mainZone.addEventListener('dragleave', () => mainZone.style.borderColor = '');
    mainZone.addEventListener('drop', e => {
        e.preventDefault();
        if (!e.dataTransfer.files.length) return;
        try {
            const dt = new DataTransfer();
            dt.items.add(e.dataTransfer.files[0]);
            document.getElementById('mainImgFile').files = dt.files;
            previewMainImg(document.getElementById('mainImgFile'));
        } catch (err) {}
    });

    document.getElementById('addForm').addEventListener('submit', function (e) {
        const name = document.getElementById('hotelName').value.trim();
        if (!name) { e.preventDefault(); alert('Vui lòng nhập tên khách sạn!'); return; }
        const btn = document.getElementById('btnAdd');
        btn.disabled    = true;
        btn.textContent = '⟳ Đang lưu...';
    });
})();
