function deleteItem(itemId) {
    Swal.fire({
        title: 'Remove this site?',
        text: "This will remove the site from the booking and delete associated operation tasks. Are you sure?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Yes, delete it'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('../../ajax/delete_booking_item.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `id=${itemId}`
            })
            .then(r => r.json())
            .then(res => {
                if(res.success) {
                    location.reload();
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            });
        }
    });
}

function sendPOEmail(bookingId, vendorId) {
    Swal.fire({
        title: 'Sending PO...',
        text: 'Please wait while we prepare the email for the vendor.',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    // Simulate AJAX call to send email
    setTimeout(() => {
        Swal.fire({
            icon: 'success',
            title: 'PO Sent!',
            text: 'The Purchase Order has been successfully emailed to the vendor.',
            timer: 2000,
            showConfirmButton: false
        });
    }, 1500);
}

function updatePurchaseCost(itemId, cost) {
    const formData = new FormData();
    formData.append('item_id', itemId);
    formData.append('purchase_cost', cost);

    fetch('../../ajax/update_purchase_cost.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        if(res.success) {
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 2000,
                timerProgressBar: true
            });
            Toast.fire({
                icon: 'success',
                title: 'Purchase cost updated'
            }).then(() => location.reload());
        } else {
            Swal.fire('Error', res.message || 'Failed to update cost', 'error');
        }
    });
}
function updateSellingCost(itemId, cost) {
    const formData = new FormData();
    formData.append('item_id', itemId);
    formData.append('selling_cost', cost);

    fetch('../../ajax/update_selling_cost.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        if(res.success) {
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 2000,
                timerProgressBar: true
            });
            Toast.fire({
                icon: 'success',
                title: 'Selling cost updated'
            }).then(() => location.reload());
        } else {
            Swal.fire('Error', res.message || 'Failed to update cost', 'error');
        }
    });
}
function updateBookingItemPeriod(itemId, field, value) {
    const formData = new FormData();
    formData.append('id', itemId);
    formData.append('field', field);
    formData.append('value', value);

    fetch('../../ajax/update_booking_item_period.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        if(res.success) {
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 2000,
                timerProgressBar: true
            });
            Toast.fire({
                icon: 'success',
                title: 'Booking item period updated'
            }).then(() => location.reload());
        } else {
            Swal.fire('Error', res.message || 'Failed to update period', 'error');
        }
    });
}
function promptSetCost(itemId) {
    Swal.fire({
        title: 'Set Purchase Cost',
        input: 'number',
        inputLabel: 'Enter the purchase cost for this asset',
        inputPlaceholder: '0.00',
        showCancelButton: true,
        confirmButtonText: 'Save Cost',
        inputValidator: (value) => {
            if (!value) {
                return 'You need to enter an amount!'
            }
        }
    }).then((result) => {
        if (result.isConfirmed) {
            updatePurchaseCost(itemId, result.value);
        }
    });
}
function toggleConfFields() {
    const type = document.getElementById('confirmation_type').value;
    const poFields = document.getElementById('po_fields');
    const emailFields = document.getElementById('email_fields');
    if (poFields) poFields.style.display = type === 'po' ? 'block' : 'none';
    if (emailFields) emailFields.style.display = type === 'email' ? 'block' : 'none';
}

function openInvoicePopup(bookingId) {
    Swal.fire({
        title: 'Campaign Confirmation',
        html: `
            <div style="text-align: left;">
                <label style="display:block; font-size: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 5px;">CONFIRMATION TYPE</label>
                <select id="confirmation_type" class="swal2-input" style="margin: 0 0 1rem 0; width: 100%; box-sizing: border-box;" onchange="toggleConfFields()">
                    <option value="po">Customer Purchase Order (PO)</option>
                    <option value="email">Email Confirmation</option>
                </select>

                <div id="po_fields">
                    <label style="display:block; font-size: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 5px;">CUSTOMER PO NUMBER</label>
                    <input id="customer_po_no" class="swal2-input" placeholder="Enter PO ID" style="margin: 0 0 1rem 0; width: 100%; box-sizing: border-box;">
                    
                    <label style="display:block; font-size: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 5px;">PO DATE</label>
                    <input id="customer_po_date" type="date" class="swal2-input" style="margin: 0 0 1rem 0; width: 100%; box-sizing: border-box;">
                </div>

                <div id="email_fields" style="display:none;">
                    <label style="display:block; font-size: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 5px;">EMAIL CONFIRMATION DATE</label>
                    <input id="email_date" type="date" class="swal2-input" style="margin: 0 0 1rem 0; width: 100%; box-sizing: border-box;">
                </div>
                
                <label style="display:block; font-size: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 5px;">UPLOAD ATTACHMENT (PDF/IMAGE)</label>
                <input id="customer_po_file" type="file" accept=".pdf,image/*" class="swal2-file" style="margin: 0; width: 100%; box-sizing: border-box; border: 1px solid #e2e8f0; padding: 10px; border-radius: 6px;">
            </div>
        `,
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: 'Save & Generate Invoice',
        preConfirm: () => {
            const type = document.getElementById('confirmation_type').value;
            const po_no = document.getElementById('customer_po_no').value;
            const po_date = document.getElementById('customer_po_date').value;
            const email_date = document.getElementById('email_date').value;
            const po_file = document.getElementById('customer_po_file').files[0];
            
            if (type === 'po') {
                if (!po_no) { Swal.showValidationMessage(`Customer PO Number is mandatory`); return false; }
                if (!po_date) { Swal.showValidationMessage(`PO Date is mandatory`); return false; }
            }
            if (type === 'email') {
                if (!email_date) { Swal.showValidationMessage(`Email Confirmation Date is mandatory`); return false; }
            }
            if (!po_file) {
                Swal.showValidationMessage(`Please upload the PO/Email attachment (PDF/Image)`);
                return false;
            }
            
            let formData = new FormData();
            formData.append('booking_id', bookingId);
            formData.append('confirmation_type', type);
            formData.append('customer_po_no', po_no);
            formData.append('customer_po_date', po_date);
            formData.append('email_date', email_date);
            formData.append('customer_po_file', po_file);
            
            return fetch('../../ajax/upload_customer_po.php', {
                method: 'POST',
                body: formData
            }).then(response => response.json())
            .then(data => {
                if (!data.success) {
                    throw new Error(data.message || 'Upload failed');
                }
                return data;
            }).catch(error => {
                Swal.showValidationMessage(`Request failed: ${error}`);
            });
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const data = result.value;
            if (data.approval_status === 'pending_approval' && !data.is_admin) {
                Swal.fire({
                    icon: 'success',
                    title: 'Approval Sent to Admin!',
                    text: 'The invoice generation request has been sent to the Admin for approval.',
                    confirmButtonColor: '#10b981'
                }).then(() => {
                    location.reload();
                });
            } else {
                window.open(`generate_invoice.php?booking_id=${bookingId}`, '_blank');
                location.reload();
            }
        }
    });
}
function saveAndGeneratePO(booking_id, vendor_id) {
    Swal.fire({
        title: 'Save PO to Database?',
        text: "This will officially save the Purchase Order for this vendor.",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#0d9488',
        cancelButtonColor: '#ef4444',
        confirmButtonText: 'Yes, Save it!'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.showLoading();
            fetch('../../ajax/save_booking_po.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ booking_id: booking_id, vendor_id: vendor_id })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.approval_status === 'pending_approval') {
                        Swal.fire('Approval Sent!', data.message, 'success').then(() => {
                            window.location.reload();
                        });
                    } else {
                        Swal.fire('PO Saved!', `Purchase Order ${data.po_number} has been generated successfully.`, 'success').then(() => {
                            window.location.reload();
                        });
                    }
                } else {
                    Swal.fire('Error', data.message || 'Failed to save PO', 'error');
                }
            })
            .catch(error => {
                Swal.fire('Error', 'Network or server error', 'error');
            });
        }
    });
}

// --- Add Site Modal JS ---
let modalCurrentPage = 1;
const modalPageSize = 50;
let modalSelectedSites = [];
let currentModalSites = [];
let showingBucketOnly = false;

function updateModalSelectedCount() {
    document.getElementById('modal-selected-count').innerText = modalSelectedSites.length;
}

function toggleModalBucket() {
    showingBucketOnly = !showingBucketOnly;
    const btn = document.getElementById('modal-bucket-btn');
    
    if (showingBucketOnly) {
        btn.style.background = '#047857';
        btn.style.color = 'white';
        document.getElementById('modal-pg-info').style.display = 'none';
        document.getElementById('modal-pg-numbers').style.display = 'none';
        renderModalSites(modalSelectedSites, true); // true = bucket mode
    } else {
        btn.style.background = '#ecfdf5';
        btn.style.color = '#059669';
        document.getElementById('modal-pg-info').style.display = 'block';
        document.getElementById('modal-pg-numbers').style.display = 'flex';
        renderModalSites(currentModalSites);
    }
}

function openAddSiteModal() {
    document.getElementById('addSiteModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
    modalFetchSites(1);
}
function closeAddSiteModal() {
    document.getElementById('addSiteModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

function clearModalFilters() {
    document.getElementById('modal-search').value = '';
    document.getElementById('modal-media').value = '';
    document.getElementById('modal-state').value = '';
    document.getElementById('modal-city').value = '';
    document.getElementById('modal-location').value = '';
    document.getElementById('modal-light').value = '';
    document.getElementById('modal-vendor').value = '';
    document.querySelector('input[name="modal_ownership"][value="all"]').checked = true;
    document.querySelector('input[name="modal_availability"][value="available"]').checked = true;
    
    if (showingBucketOnly) toggleModalBucket();
    modalFetchSites(1);
}

function modalFetchSites(page) {
    modalCurrentPage = page;
    const q = document.getElementById('modal-search').value;
    const media = document.getElementById('modal-media').value;
    const state = document.getElementById('modal-state').value;
    const city = document.getElementById('modal-city').value;
    const loc = document.getElementById('modal-location').value;
    const light = document.getElementById('modal-light').value;
    const vendor = document.getElementById('modal-vendor').value;
    const ownership = document.querySelector('input[name="modal_ownership"]:checked').value;
    const availability = document.querySelector('input[name="modal_availability"]:checked').value;

    const url = `../../ajax/fetch_sites.php?page=${page}&limit=${modalPageSize}&q=${encodeURIComponent(q)}&media=${encodeURIComponent(media)}&state=${encodeURIComponent(state)}&city=${encodeURIComponent(city)}&location=${encodeURIComponent(loc)}&light=${encodeURIComponent(light)}&vendor=${encodeURIComponent(vendor)}&ownership=${encodeURIComponent(ownership)}&availability=${encodeURIComponent(availability)}`;

    fetch(url)
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            currentModalSites = res.sites;
            if (!showingBucketOnly) {
                renderModalSites(currentModalSites);
                renderModalPagination(res.total);
            } else {
                renderModalPagination(res.total);
            }
        }
    });
}

function toggleModalSite(cb, siteId) {
    const siteObj = showingBucketOnly 
        ? modalSelectedSites.find(s => s.id == siteId) 
        : currentModalSites.find(s => s.id == siteId);
        
    if (!siteObj) return;

    if (cb.checked) {
        if (!modalSelectedSites.find(s => s.id == siteId)) {
            const sCopy = {...siteObj, image: siteObj.thumbnail || ''};
            modalSelectedSites.push(sCopy);
        }
    } else {
        modalSelectedSites = modalSelectedSites.filter(s => s.id != siteId);
        if (showingBucketOnly) {
            const row = cb.closest('tr');
            if (row) row.remove();
        }
    }
    updateModalSelectedCount();
    
    const checkboxes = document.querySelectorAll('.modal-site-checkbox');
    const checkedCount = document.querySelectorAll('.modal-site-checkbox:checked').length;
    document.getElementById('modal-select-all').checked = (checkboxes.length > 0 && checkboxes.length === checkedCount);
}

function renderModalSites(sites, isBucket = false) {
    const body = document.getElementById('modal-site-body');
    body.innerHTML = '';
    
    if (sites.length === 0) {
        body.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:2rem; color:#94a3b8;">No sites found.</td></tr>';
        return;
    }

    sites.forEach(s => {
        const isChecked = modalSelectedSites.some(ms => ms.id == s.id);
        const savedSite = modalSelectedSites.find(ms => ms.id == s.id);
        const thumbToUse = savedSite ? savedSite.image : (s.thumbnail ? s.thumbnail : '');
        const thumbUrl = thumbToUse ? '../../uploads/sites/' + thumbToUse : 'https://via.placeholder.com/150x95?text=No+Img';
        const cardRate = parseFloat(s.card_rate || 0);

        let imgHtml = '';
        if (s.thumbnail) {
            const imgList = s.all_images ? s.all_images.split(',') : [s.thumbnail];
            const imgCount = imgList.length;
            imgHtml = `
                <div style="position: relative; width: 100px; height: 60px;">
                    <img id="modal-thumb-${s.id}" src="${thumbUrl}" onclick="openLightboxSlider('${s.all_images || s.thumbnail}', '${s.id}')" style="width: 100%; height: 100%; border-radius: 8px; object-fit: cover; border: 1px solid #e2e8f0; cursor: pointer; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                    ${imgCount > 1 ? `<div style="position: absolute; bottom: 2px; right: 2px; background: rgba(0,0,0,0.7); color: white; font-size: 0.5rem; padding: 2px 4px; border-radius: 4px; font-weight: 800;"><i class="fas fa-images"></i> ${imgCount}</div>` : ''}
                </div>
            `;
        } else {
            imgHtml = `<div style="width: 100px; height: 60px; border-radius: 8px; background: #f8fafc; border: 1px dashed #e2e8f0; display: flex; align-items: center; justify-content: center; font-size: 0.6rem; color: #94a3b8; font-weight: 700;">No Img</div>`;
        }

        const row = document.createElement('tr');
        row.style.background = 'white';
        row.innerHTML = `
            <td style="text-align:center; padding:1rem;">
                <input type="checkbox" class="modal-site-checkbox" value="${s.id}" ${isChecked ? 'checked' : ''} onclick="toggleModalSite(this, ${s.id})" style="width:16px; height:16px; accent-color:var(--primary);">
            </td>
            <td style="padding:1rem;">
                ${imgHtml}
            </td>
            <td style="padding:1rem;">
                <div style="font-weight:800; color:#1e293b; font-size:0.8rem; margin-bottom:1px;">${s.city || ''}</div>
                <div style="color:#f97316; font-size:0.65rem; font-weight:800;">${s.site_code}</div>
            </td>
            <td style="padding:1rem;">
                <div style="font-weight:800; color:#1e293b; font-size:0.8rem; margin-bottom:1px;">${s.name}</div>
                <div style="font-size:0.65rem; color:#64748b; margin-bottom:4px; line-height:1.1;">${s.location}</div>
                <div style="display: flex; gap: 0.3rem; align-items: center;">
                    <span style="background:#ecfdf5; color:#059669; padding:0.1rem 0.4rem; border-radius:4px; font-size:0.55rem; font-weight:800; text-transform:uppercase;">${s.type}</span>
                    <span style="background:#f1f5f9; color:#475569; padding:0.1rem 0.4rem; border-radius:4px; font-size:0.55rem; font-weight:800; text-transform:uppercase;">${s.owner_type}</span>
                </div>
            </td>
            <td style="padding:1rem;">
                <div style="font-weight:800; color:#1e293b; font-size:0.8rem; margin-bottom:1px;">${s.width}' x ${s.height}'</div>
            </td>
            <td style="padding:1rem; font-weight:800; color:var(--primary);">
                ₹${cardRate.toLocaleString()}
            </td>
        `;
        body.appendChild(row);
    });
    
    const checkboxes = document.querySelectorAll('.modal-site-checkbox');
    const checkedCount = document.querySelectorAll('.modal-site-checkbox:checked').length;
    document.getElementById('modal-select-all').checked = (checkboxes.length > 0 && checkboxes.length === checkedCount);
}

function toggleAllModalSites(source) {
    const checkboxes = document.querySelectorAll('.modal-site-checkbox');
    checkboxes.forEach(cb => {
        if (cb.checked !== source.checked) {
            cb.checked = source.checked;
            const siteId = parseInt(cb.value);
            
            const siteObj = showingBucketOnly 
                ? modalSelectedSites.find(s => s.id == siteId) 
                : currentModalSites.find(s => s.id == siteId);
                
            if (!siteObj) return;

            if (cb.checked) {
                if (!modalSelectedSites.find(ms => ms.id === siteId)) {
                    const sCopy = {...siteObj, image: siteObj.thumbnail || ''};
                    modalSelectedSites.push(sCopy);
                }
            } else {
                modalSelectedSites = modalSelectedSites.filter(ms => ms.id !== siteId);
                if (showingBucketOnly) {
                    const row = cb.closest('tr');
                    if (row) row.remove();
                }
            }
        }
    });
    updateModalSelectedCount();
}

function renderModalPagination(total) {
    const totalPages = Math.ceil(total / modalPageSize);
    const container = document.getElementById('modal-pg-numbers');
    const info = document.getElementById('modal-pg-info');
    container.innerHTML = '';
    
    if (total === 0) {
        info.innerText = '0 sites found';
        return;
    }
    
    const start = (modalCurrentPage - 1) * modalPageSize + 1;
    const end = Math.min(modalCurrentPage * modalPageSize, total);
    info.innerText = `Showing ${start}-${end} of ${total}`;
    
    // Previous
    const prevBtn = document.createElement('button');
    prevBtn.innerHTML = '<i class="fas fa-chevron-left"></i>';
    prevBtn.className = 'btn btn-secondary';
    prevBtn.style.padding = '0.3rem 0.6rem';
    prevBtn.disabled = modalCurrentPage === 1;
    if(!prevBtn.disabled) {
        prevBtn.onclick = () => modalFetchSites(modalCurrentPage - 1);
    }
    container.appendChild(prevBtn);
    
    // Page Numbers (max 5)
    let pStart = Math.max(1, modalCurrentPage - 2);
    let pEnd = Math.min(totalPages, pStart + 4);
    if(pEnd - pStart < 4) {
        pStart = Math.max(1, pEnd - 4);
    }
    
    for (let i = pStart; i <= pEnd; i++) {
        const btn = document.createElement('button');
        btn.innerText = i;
        btn.className = i === modalCurrentPage ? 'btn btn-primary' : 'btn btn-secondary';
        btn.style.padding = '0.3rem 0.6rem';
        if (i !== modalCurrentPage) {
            btn.onclick = () => modalFetchSites(i);
        }
        container.appendChild(btn);
    }
    
    // Next
    const nextBtn = document.createElement('button');
    nextBtn.innerHTML = '<i class="fas fa-chevron-right"></i>';
    nextBtn.className = 'btn btn-secondary';
    nextBtn.style.padding = '0.3rem 0.6rem';
    nextBtn.disabled = modalCurrentPage === totalPages;
    if(!nextBtn.disabled) {
        nextBtn.onclick = () => modalFetchSites(modalCurrentPage + 1);
    }
    container.appendChild(nextBtn);
}

function addSelectedSitesToBooking() {
    if (modalSelectedSites.length === 0) {
        Swal.fire('Warning', 'Select at least one site to add.', 'warning');
        return;
    }

    const bookingId = <?php echo $id; ?>;

    Swal.fire({
        title: 'Adding Sites...',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });

    fetch('../../ajax/add_booking_items_bulk.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ booking_id: bookingId, sites: modalSelectedSites })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            location.reload();
        } else {
            Swal.fire('Error', res.message || 'Failed to add sites', 'error');
        }
    });
}

// Lightbox Logic
let lbImages = [];
let lbIndex = 0;
let currentLightboxSiteId = null;

function openLightboxSlider(imageString, siteId) {
    currentLightboxSiteId = siteId;
    lbImages = imageString ? imageString.split(',') : [];
    if (lbImages.length === 0) return;
    
    lbIndex = 0;
    const lb = document.getElementById('simple-lightbox');
    const lbPrev = document.getElementById('lightbox-prev');
    const lbNext = document.getElementById('lightbox-next');
    const selectBtn = document.getElementById('lightbox-select-btn');
    
    lb.style.display = 'flex';
    
    if (lbImages.length > 1) {
        lbPrev.style.display = 'flex';
        lbNext.style.display = 'flex';
    } else {
        lbPrev.style.display = 'none';
        lbNext.style.display = 'none';
    }
    
    const isSelected = modalSelectedSites.some(ms => ms.id == siteId);
    selectBtn.style.display = isSelected ? 'block' : 'none';
    
    updateLightboxImage();
}

function updateLightboxImage() {
    const lbImg = document.getElementById('lightbox-img');
    const lbBadge = document.getElementById('lightbox-badge');
    const selectBtn = document.getElementById('lightbox-select-btn');
    
    lbImg.src = '../../uploads/sites/' + lbImages[lbIndex];
    lbBadge.innerText = (lbIndex + 1) + " / " + lbImages.length;
    
    if(selectBtn && currentLightboxSiteId) {
        const savedSite = modalSelectedSites.find(ms => ms.id == currentLightboxSiteId);
        if(savedSite && savedSite.image === lbImages[lbIndex]) {
            selectBtn.innerHTML = '<i class="fas fa-check-circle"></i> Image Selected';
            selectBtn.style.background = '#10b981';
            selectBtn.style.boxShadow = '0 4px 15px rgba(16,185,129,0.4)';
        } else {
            selectBtn.innerHTML = '<i class="far fa-circle"></i> Select This Image';
            selectBtn.style.background = 'var(--primary)';
            selectBtn.style.boxShadow = '0 4px 15px rgba(13,148,136,0.4)';
        }
    }
}

function changeLightboxImage(dir) {
    lbIndex += dir;
    if (lbIndex < 0) lbIndex = lbImages.length - 1;
    if (lbIndex >= lbImages.length) lbIndex = 0;
    updateLightboxImage();
}

function selectLightboxImage() {
    if(currentLightboxSiteId) {
        const savedSite = modalSelectedSites.find(ms => ms.id == currentLightboxSiteId);
        if(savedSite) {
            savedSite.image = lbImages[lbIndex];
            
            // Update thumbnail in table row
            const thumbImg = document.getElementById('modal-thumb-' + currentLightboxSiteId);
            if(thumbImg) {
                thumbImg.src = '../../uploads/sites/' + lbImages[lbIndex];
            }
            
            updateLightboxImage();
            
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'Image selected!',
                showConfirmButton: false,
                timer: 1500
            });
        }
    }
}

function closeLightbox() {
    document.getElementById('simple-lightbox').style.display = 'none';
}

document.addEventListener('keydown', function(e) {
    const lb = document.getElementById('simple-lightbox');
    if (lb && lb.style.display === 'flex') {
        if (e.key === 'ArrowLeft') changeLightboxImage(-1);
        if (e.key === 'ArrowRight') changeLightboxImage(1);
        if (e.key === 'Escape') closeLightbox();
    }
});
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
