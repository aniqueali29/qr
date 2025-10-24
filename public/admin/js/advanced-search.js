/**
 * Advanced Search Functionality
 * Global search with autocomplete and filters
 */

const AdvancedSearch = {
    searchTimeout: null,
    searchResults: [],
    currentIndex: -1,

    /**
     * Initialize advanced search
     */
    init: function() {
        this.createSearchModal();
        this.bindKeyboardShortcut();
    },

    /**
     * Create search modal
     */
    createSearchModal: function() {
        if (document.getElementById('advancedSearchModal')) return;

        const modal = document.createElement('div');
        modal.id = 'advancedSearchModal';
        modal.className = 'modal fade';
        modal.innerHTML = `
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bx bx-search me-2"></i>Advanced Search
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Search Input -->
                        <div class="mb-3">
                            <div class="input-group input-group-lg">
                                <span class="input-group-text">
                                    <i class="bx bx-search"></i>
                                </span>
                                <input type="text" 
                                       id="globalSearchInput" 
                                       class="form-control" 
                                       placeholder="Search students, programs, attendance..."
                                       autocomplete="off">
                            </div>
                            <div class="form-text mt-2">
                                <kbd>↑</kbd> <kbd>↓</kbd> to navigate • 
                                <kbd>Enter</kbd> to select • 
                                <kbd>Esc</kbd> to close
                            </div>
                        </div>

                        <!-- Search Filters -->
                        <div class="search-filters mb-3">
                            <label class="form-label">Filter by Type</label>
                            <div class="btn-group btn-group-sm w-100" role="group">
                                <input type="radio" class="btn-check" name="searchType" id="searchAll" value="all" checked>
                                <label class="btn btn-outline-primary" for="searchAll">
                                    <i class="bx bx-search-alt me-1"></i>All
                                </label>
                                
                                <input type="radio" class="btn-check" name="searchType" id="searchStudents" value="students">
                                <label class="btn btn-outline-primary" for="searchStudents">
                                    <i class="bx bx-user me-1"></i>Students
                                </label>
                                
                                <input type="radio" class="btn-check" name="searchType" id="searchPrograms" value="programs">
                                <label class="btn btn-outline-primary" for="searchPrograms">
                                    <i class="bx bx-book me-1"></i>Programs
                                </label>
                                
                                <input type="radio" class="btn-check" name="searchType" id="searchAttendance" value="attendance">
                                <label class="btn btn-outline-primary" for="searchAttendance">
                                    <i class="bx bx-calendar-check me-1"></i>Attendance
                                </label>
                            </div>
                        </div>

                        <!-- Search Results -->
                        <div id="searchResults" class="search-results">
                            <div class="text-center text-muted py-5">
                                <i class="bx bx-search fs-1"></i>
                                <p class="mt-2">Start typing to search...</p>
                            </div>
                        </div>

                        <!-- Loading -->
                        <div id="searchLoading" class="text-center py-4" style="display: none;">
                            <div class="spinner-border spinner-border-sm text-primary"></div>
                            <p class="text-muted mt-2 small">Searching...</p>
                        </div>

                        <!-- No Results -->
                        <div id="searchNoResults" class="text-center py-4" style="display: none;">
                            <i class="bx bx-info-circle fs-1 text-muted"></i>
                            <p class="text-muted mt-2">No results found</p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(modal);
        this.bindSearchEvents();
    },

    /**
     * Bind keyboard shortcut (Ctrl+K)
     */
    bindKeyboardShortcut: function() {
        document.addEventListener('keydown', (e) => {
            // Ctrl+K or Cmd+K
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                this.openSearch();
            }
        });
    },

    /**
     * Open search modal
     */
    openSearch: function() {
        const modal = new bootstrap.Modal(document.getElementById('advancedSearchModal'));
        modal.show();
        
        // Focus input after modal is shown
        document.getElementById('advancedSearchModal').addEventListener('shown.bs.modal', function() {
            document.getElementById('globalSearchInput').focus();
        }, { once: true });
    },

    /**
     * Bind search events
     */
    bindSearchEvents: function() {
        const searchInput = document.getElementById('globalSearchInput');
        const searchType = document.querySelectorAll('input[name="searchType"]');

        // Search on input
        searchInput.addEventListener('input', (e) => {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                this.performSearch(e.target.value);
            }, 300);
        });

        // Search type change
        searchType.forEach(radio => {
            radio.addEventListener('change', () => {
                const query = searchInput.value;
                if (query.length >= 2) {
                    this.performSearch(query);
                }
            });
        });

        // Keyboard navigation
        searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                this.navigateResults('down');
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                this.navigateResults('up');
            } else if (e.key === 'Enter') {
                e.preventDefault();
                this.selectResult();
            }
        });
    },

    /**
     * Perform search
     */
    performSearch: function(query) {
        if (query.length < 2) {
            document.getElementById('searchResults').innerHTML = `
                <div class="text-center text-muted py-5">
                    <i class="bx bx-search fs-1"></i>
                    <p class="mt-2">Type at least 2 characters to search...</p>
                </div>
            `;
            return;
        }

        const searchType = document.querySelector('input[name="searchType"]:checked').value;
        
        // Show loading
        document.getElementById('searchLoading').style.display = 'block';
        document.getElementById('searchResults').style.display = 'none';
        document.getElementById('searchNoResults').style.display = 'none';

        // Perform search
        fetch(`api/admin_api.php?action=global_search&q=${encodeURIComponent(query)}&type=${searchType}`)
            .then(response => response.json())
            .then(data => {
                document.getElementById('searchLoading').style.display = 'none';
                
                if (data.success && data.data.length > 0) {
                    this.searchResults = data.data;
                    this.displayResults(data.data);
                } else {
                    document.getElementById('searchNoResults').style.display = 'block';
                }
            })
            .catch(error => {
                console.error('Search error:', error);
                document.getElementById('searchLoading').style.display = 'none';
                document.getElementById('searchNoResults').style.display = 'block';
            });
    },

    /**
     * Display search results
     */
    displayResults: function(results) {
        const resultsDiv = document.getElementById('searchResults');
        resultsDiv.style.display = 'block';
        
        resultsDiv.innerHTML = results.map((result, index) => `
            <div class="search-result-item ${index === 0 ? 'active' : ''}" data-index="${index}">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        ${this.getResultIcon(result.type)}
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="mb-0">${this.highlightMatch(result.title, result.match)}</h6>
                        <small class="text-muted">${result.subtitle}</small>
                    </div>
                    <div class="flex-shrink-0">
                        <span class="badge bg-label-${this.getTypeBadgeColor(result.type)}">${result.type}</span>
                    </div>
                </div>
            </div>
        `).join('');

        this.currentIndex = 0;

        // Bind click events
        resultsDiv.querySelectorAll('.search-result-item').forEach((item, index) => {
            item.addEventListener('click', () => {
                this.currentIndex = index;
                this.selectResult();
            });
        });
    },

    /**
     * Get result icon
     */
    getResultIcon: function(type) {
        const icons = {
            'student': '<i class="bx bx-user fs-4 text-primary"></i>',
            'program': '<i class="bx bx-book fs-4 text-info"></i>',
            'section': '<i class="bx bx-grid-alt fs-4 text-warning"></i>',
            'attendance': '<i class="bx bx-calendar-check fs-4 text-success"></i>'
        };
        return icons[type.toLowerCase()] || '<i class="bx bx-search fs-4"></i>';
    },

    /**
     * Get type badge color
     */
    getTypeBadgeColor: function(type) {
        const colors = {
            'student': 'primary',
            'program': 'info',
            'section': 'warning',
            'attendance': 'success'
        };
        return colors[type.toLowerCase()] || 'secondary';
    },

    /**
     * Highlight search match
     */
    highlightMatch: function(text, match) {
        if (!match) return text;
        const regex = new RegExp(`(${match})`, 'gi');
        return text.replace(regex, '<mark>$1</mark>');
    },

    /**
     * Navigate results
     */
    navigateResults: function(direction) {
        const items = document.querySelectorAll('.search-result-item');
        if (items.length === 0) return;

        items[this.currentIndex].classList.remove('active');

        if (direction === 'down') {
            this.currentIndex = (this.currentIndex + 1) % items.length;
        } else {
            this.currentIndex = (this.currentIndex - 1 + items.length) % items.length;
        }

        items[this.currentIndex].classList.add('active');
        items[this.currentIndex].scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    },

    /**
     * Select result
     */
    selectResult: function() {
        if (this.searchResults.length === 0 || this.currentIndex === -1) return;

        const result = this.searchResults[this.currentIndex];
        
        // Close modal
        bootstrap.Modal.getInstance(document.getElementById('advancedSearchModal')).hide();

        // Navigate to result
        if (result.url) {
            window.location.href = result.url;
        }
    }
};

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    AdvancedSearch.init();
});
