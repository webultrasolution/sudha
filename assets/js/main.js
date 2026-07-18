function toggleSidebar() {
    if (window.innerWidth > 1024) {
        document.body.classList.toggle('sidebar-collapsed');
    } else {
        document.body.classList.toggle('sidebar-open');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    console.log('Sudha Creative CRM Loaded');
    
    // Close sidebar on mobile when clicking outside of it
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024 && document.body.classList.contains('sidebar-open')) {
            const sidebar = document.querySelector('.sidebar');
            const menuToggle = document.querySelector('.menu-toggle');
            if (sidebar && !sidebar.contains(e.target) && menuToggle && !menuToggle.contains(e.target)) {
                document.body.classList.remove('sidebar-open');
            }
        }
    });
});

function initSearchableSelect(selectId, placeholderText = 'Search...') {
    const select = document.getElementById(selectId);
    if (!select) return;
    
    // Hide native select
    select.style.display = 'none';
    
    // Create wrapper container
    const wrapper = document.createElement('div');
    wrapper.style.position = 'relative';
    wrapper.style.width = '100%';
    select.parentNode.insertBefore(wrapper, select);
    wrapper.appendChild(select);
    
    // Create trigger display (styled like an input but readonly div)
    const trigger = document.createElement('div');
    trigger.className = 'p-input searchable-select-trigger';
    trigger.tabIndex = 0;
    trigger.style.height = '38px';
    trigger.style.cursor = 'pointer';
    trigger.style.paddingRight = '2.5rem';
    trigger.style.display = 'flex';
    trigger.style.alignItems = 'center';
    trigger.style.background = '#fcfcfc';
    trigger.style.border = '1px solid #cbd5e1';
    trigger.style.borderRadius = '8px';
    trigger.style.fontSize = '0.85rem';
    trigger.style.fontWeight = '600';
    trigger.style.color = '#475569';
    trigger.style.userSelect = 'none';
    trigger.style.boxSizing = 'border-box';
    trigger.style.overflow = 'hidden';
    trigger.style.textOverflow = 'ellipsis';
    trigger.style.whiteSpace = 'nowrap';
    wrapper.appendChild(trigger);
    
    // Create chevron icon
    const chevron = document.createElement('i');
    chevron.className = 'fas fa-chevron-down';
    chevron.style.position = 'absolute';
    chevron.style.right = '12px';
    chevron.style.top = '50%';
    chevron.style.transform = 'translateY(-50%)';
    chevron.style.color = '#94a3b8';
    chevron.style.pointerEvents = 'none';
    chevron.style.transition = 'transform 0.2s ease';
    wrapper.appendChild(chevron);
    
    // Create dropdown list container
    const dropdownContainer = document.createElement('div');
    dropdownContainer.id = selectId + '_dropdown_list';
    dropdownContainer.style.display = 'none';
    dropdownContainer.style.position = 'absolute';
    dropdownContainer.style.left = '0';
    dropdownContainer.style.right = '0';
    dropdownContainer.style.top = '42px';
    dropdownContainer.style.maxHeight = '300px';
    dropdownContainer.style.overflow = 'hidden';
    dropdownContainer.style.background = 'white';
    dropdownContainer.style.border = '1px solid #cbd5e1';
    dropdownContainer.style.borderRadius = '8px';
    dropdownContainer.style.zIndex = '1000';
    dropdownContainer.style.boxShadow = '0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05)';
    dropdownContainer.style.flexDirection = 'column';
    wrapper.appendChild(dropdownContainer);
    
    // Inject styles dynamically if not already present
    if (!document.getElementById('searchable-select-styles')) {
        const style = document.createElement('style');
        style.id = 'searchable-select-styles';
        style.innerHTML = `
            .searchable-select-trigger:focus {
                border-color: var(--primary) !important;
                box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.1) !important;
                background: white !important;
                outline: none !important;
            }
            .custom-select-opt {
                transition: all 0.15s ease;
            }
            .custom-select-opt:hover {
                background-color: var(--primary) !important;
                color: white !important;
            }
        `;
        document.head.appendChild(style);
    }
    
    // Create search input wrapper inside dropdown
    const searchWrapper = document.createElement('div');
    searchWrapper.style.padding = '8px';
    searchWrapper.style.borderBottom = '1px solid #f1f5f9';
    searchWrapper.style.background = '#f8fafc';
    
    const searchInput = document.createElement('input');
    searchInput.type = 'text';
    searchInput.placeholder = 'Search...';
    searchInput.style.width = '100%';
    searchInput.style.height = '32px';
    searchInput.style.padding = '0 8px';
    searchInput.style.border = '1px solid #cbd5e1';
    searchInput.style.borderRadius = '6px';
    searchInput.style.fontSize = '0.85rem';
    searchInput.style.boxSizing = 'border-box';
    searchInput.autocomplete = 'off';
    
    searchWrapper.appendChild(searchInput);
    dropdownContainer.appendChild(searchWrapper);
    
    // Create options container (scrollable)
    const optionsContainer = document.createElement('div');
    optionsContainer.style.maxHeight = '220px';
    optionsContainer.style.overflowY = 'auto';
    dropdownContainer.appendChild(optionsContainer);
    
    // Function to populate custom options from select options
    function populateOptions() {
        optionsContainer.innerHTML = '';
        Array.from(select.options).forEach(opt => {
            const item = document.createElement('div');
            item.className = 'custom-select-opt';
            item.style.padding = '0.6rem 0.8rem';
            item.style.cursor = 'pointer';
            item.style.fontWeight = '600';
            item.style.color = opt.value === '' ? '#64748b' : '#475569';
            item.style.borderBottom = '1px solid #f8fafc';
            item.style.fontSize = '0.85rem';
            item.style.textAlign = 'left';
            item.innerText = opt.text;
            
            // Highlight selected item
            if (opt.selected) {
                item.style.background = '#f1f5f9';
                item.style.color = 'var(--primary)';
            }
            
            item.onclick = (e) => {
                e.stopPropagation();
                select.value = opt.value;
                trigger.innerText = opt.text;
                closeDropdown();
                select.dispatchEvent(new Event('change'));
            };
            
            optionsContainer.appendChild(item);
        });
    }
    
    // Set initial display text
    function updateTriggerText() {
        if (select.selectedIndex >= 0) {
            trigger.innerText = select.options[select.selectedIndex].text;
        } else {
            trigger.innerText = placeholderText;
        }
    }
    
    function closeDropdown() {
        dropdownContainer.style.display = 'none';
        chevron.style.transform = 'translateY(-50%) rotate(0deg)';
    }
    
    function openDropdown() {
        // Close other dropdowns
        document.querySelectorAll('[id$="_dropdown_list"]').forEach(d => {
            d.style.display = 'none';
            const otherChevron = d.previousSibling;
            if (otherChevron && otherChevron.style && otherChevron.style.transform && otherChevron.style.transform.includes('180deg')) {
                otherChevron.style.transform = 'translateY(-50%) rotate(0deg)';
            }
        });
        
        dropdownContainer.style.display = 'flex';
        chevron.style.transform = 'translateY(-50%) rotate(180deg)';
        populateOptions();
        searchInput.value = '';
        filterOptions('');
        setTimeout(() => searchInput.focus(), 50);
    }
    
    updateTriggerText();
    populateOptions();
    
    // Toggle dropdown display
    trigger.onclick = (e) => {
        e.stopPropagation();
        if (dropdownContainer.style.display === 'flex') {
            closeDropdown();
        } else {
            openDropdown();
        }
    };
    
    // Support enter / space key to toggle
    trigger.onkeydown = (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            if (dropdownContainer.style.display === 'flex') {
                closeDropdown();
            } else {
                openDropdown();
            }
        } else if (e.key === 'Escape') {
            closeDropdown();
        }
    };
    
    // Filter options on searchInput
    searchInput.oninput = () => {
        filterOptions(searchInput.value);
    };
    
    searchInput.onkeydown = (e) => {
        if (e.key === 'Escape') {
            closeDropdown();
            trigger.focus();
        }
    };
    
    // Prevent closing when clicking inside the dropdown container
    dropdownContainer.onclick = (e) => {
        e.stopPropagation();
    };
    
    function filterOptions(searchQuery) {
        const q = searchQuery.toLowerCase();
        const items = optionsContainer.children;
        Array.from(items).forEach(item => {
            const text = item.innerText.toLowerCase();
            if (text.indexOf(q) > -1 || item.innerText.includes('-- Choose Client --') || item.innerText.includes('-- Choose Vendor --') || item.innerText.includes('-- Choose Partner --')) {
                item.style.display = 'block';
            } else {
                item.style.display = 'none';
            }
        });
    }
    
    // Close dropdown on click outside
    document.addEventListener('click', (e) => {
        if (!wrapper.contains(e.target)) {
            closeDropdown();
        }
    });
    
    // Expose refresh function globally
    select.refreshSearchable = () => {
        populateOptions();
        updateTriggerText();
    };
}

