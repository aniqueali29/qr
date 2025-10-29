// Session Management JS
// Minimal client-side logic to load, create, and manage enrollment sessions

(function(){
  'use strict';

  const apiBase = 'api/sessions.php';

  function qs(id){ return document.getElementById(id); }

  function getCsrf(){ return (qs('csrfToken') && qs('csrfToken').value) || ''; }

  function showToast(msg, type){
    // Use UIHelpers if available (preferred)
    if (window.UIHelpers) {
      const helperType = type === 'error' ? 'danger' : (type || 'info');
      if (helperType === 'success') {
        window.UIHelpers.showSuccess(msg);
      } else if (helperType === 'danger' || helperType === 'error') {
        window.UIHelpers.showError(msg);
      } else if (helperType === 'warning') {
        window.UIHelpers.showWarning(msg);
      } else {
        window.UIHelpers.showInfo(msg);
      }
    } else if (window.adminUtils && window.adminUtils.showAlert) {
      window.adminUtils.showAlert(msg, type === 'error' ? 'danger' : (type || 'info'));
    } else if (window.showAlert) {
      window.showAlert(msg, type || 'info');
    } else {
      console.log(`[${type||'info'}]`, msg);
    }
  }

  async function fetchJson(url, options){
    const res = await fetch(url, options || {});
    const text = await res.text();
    try { return JSON.parse(text); } catch(e){
      throw new Error(`Invalid JSON from ${url}: ${text.slice(0,200)}...`);
    }
  }

  // Render stats
  function renderStats(stats){
    if (!stats) return;
    const map = {
      'total-sessions': stats.total_sessions,
      'active-sessions': stats.active_sessions,
      'total-students': stats.total_students,
      'unassigned-students': stats.unassigned_students,
    };
    Object.keys(map).forEach(id => { const el = qs(id); if (el) el.textContent = (map[id] ?? '-'); });
  }

  // Render sessions table
  function renderSessionsGrid(sessions){
    const tbody = qs('sessions-tbody');
    if (!tbody) return;
    if (!Array.isArray(sessions) || sessions.length === 0){
      tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-5">No sessions found</td></tr>';
      return;
    }

    const rows = sessions.map(s => {
      const statusBadge = s.is_active 
        ? '<span class="badge bg-success">Active</span>' 
        : '<span class="badge bg-secondary">Inactive</span>';
      
      const startDate = s.start_date ? new Date(s.start_date).toLocaleDateString() : '-';
      const endDate = s.end_date ? new Date(s.end_date).toLocaleDateString() : '-';
      
      return `
        <tr>
          <td><strong>${escapeHtml(s.code || '-')}</strong></td>
          <td>${escapeHtml(s.label || '-')}</td>
          <td>${escapeHtml(s.term || '-')}</td>
          <td>${s.year || '-'}</td>
          <td>${startDate}</td>
          <td>${endDate}</td>
          <td><span class="badge bg-info">${Number(s.student_count||0)}</span></td>
          <td>${statusBadge}</td>
          <td>
            <div class="btn-group btn-group-sm">
              <button class="btn btn-outline-secondary" title="Edit" onclick="editSession(${s.id})">
                <i class="bx bx-edit"></i>
              </button>
              <button class="btn ${s.is_active ? 'btn-warning' : 'btn-success'}" 
                      title="${s.is_active ? 'Deactivate' : 'Activate'}" 
                      onclick="toggleSessionStatus(${s.id})">
                <i class="bx bx-refresh"></i>
              </button>
              <button class="btn btn-danger" title="Delete" onclick="deleteSession(${s.id})">
                <i class="bx bx-trash"></i>
              </button>
            </div>
          </td>
        </tr>`;
    }).join('');

    tbody.innerHTML = rows;
  }

  function escapeHtml(unsafe){
    if (unsafe == null) return '';
    return String(unsafe).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
  }

  // Pagination variables
  let currentPage = 1;
  let totalPages = 1;
  const itemsPerPage = 10;

  async function refreshSessions(page = 1){
    currentPage = page;
    
    try {
      console.log('Loading sessions from:', apiBase);
      // stats
      const statsRes = await fetchJson(`${apiBase}?action=stats`);
      console.log('Stats response:', statsRes);
      if (statsRes && statsRes.success) renderStats(statsRes.stats);
      
      // list with pagination
      const listRes = await fetchJson(`${apiBase}?action=list&page=${page}&per_page=${itemsPerPage}`);
      console.log('List response:', listRes);
      if (listRes && listRes.success) {
        renderSessionsGrid(listRes.data);
        totalPages = listRes.pagination?.total_pages || 1;
        renderPagination();
      }
      UIHelpers?.showSuccess('Sessions loaded successfully');
    } catch(e){
      console.error('Error refreshing sessions:', e);
      showToast('Failed to load sessions', 'error');
    }
  }

  function renderPagination(){
    const paginationEl = qs('sessions-pagination');
    if (!paginationEl) return;
    
    if (totalPages <= 1) {
      paginationEl.innerHTML = '';
      return;
    }
    
    let html = '<nav aria-label="Page navigation"><ul class="pagination">';
    
    // Previous button
    if (currentPage > 1) {
      html += `<li class="page-item"><a class="page-link" href="#" onclick="window.loadSessionsPage(${currentPage - 1}); return false;">Previous</a></li>`;
    } else {
      html += '<li class="page-item disabled"><span class="page-link">Previous</span></li>';
    }
    
    // Page numbers
    const startPage = Math.max(1, currentPage - 2);
    const endPage = Math.min(totalPages, currentPage + 2);
    
    for (let i = startPage; i <= endPage; i++) {
      if (i === currentPage) {
        html += `<li class="page-item active"><span class="page-link">${i}</span></li>`;
      } else {
        html += `<li class="page-item"><a class="page-link" href="#" onclick="window.loadSessionsPage(${i}); return false;">${i}</a></li>`;
      }
    }
    
    // Next button
    if (currentPage < totalPages) {
      html += `<li class="page-item"><a class="page-link" href="#" onclick="window.loadSessionsPage(${currentPage + 1}); return false;">Next</a></li>`;
    } else {
      html += '<li class="page-item disabled"><span class="page-link">Next</span></li>';
    }
    
    html += '</ul></nav>';
    paginationEl.innerHTML = html;
  }
  
  // Expose page loader
  window.loadSessionsPage = async (page) => {
    await refreshSessions(page);
  };
  
  // Keep refreshSessions as global
  window.refreshSessions = refreshSessions;

  // Bulk assignment functions
  async function bulkAssignSessions(){
    const modal = qs('bulkAssignModal');
    if (!modal) return;
    
    // Load sessions dropdown
    const sessionSelect = qs('bulkSession');
    if (sessionSelect) {
      try {
        const res = await fetchJson(`${apiBase}?action=list`);
        if (res && res.success && res.data) {
          sessionSelect.innerHTML = '<option value="">Select Session</option>' + 
            res.data.map(s => `<option value="${s.id}">${escapeHtml(s.label || s.code)}</option>`).join('');
        }
      } catch(e) {
        console.error('Error loading sessions:', e);
      }
    }
    
    // Load programs dropdown
    const programSelect = qs('bulkProgram');
    if (programSelect) {
      try {
        const res = await fetchJson('api/students.php?action=list&limit=1000');
        if (res && res.success && res.data) {
          // Get unique programs from students
          const programs = [...new Set(res.data.map(s => s.program).filter(Boolean))];
          programSelect.innerHTML = '<option value="">All Programs</option>' + 
            programs.map(p => `<option value="${escapeHtml(p)}">${escapeHtml(p)}</option>`).join('');
        }
      } catch(e) {
        console.error('Error loading programs:', e);
      }
    }
    
    new bootstrap.Modal(modal).show();
  }

  async function previewBulkAssignment(){
    const sessionId = qs('bulkSession')?.value;
    const semester = qs('bulkSemester')?.value;
    const program = qs('bulkProgram')?.value;
    const shift = qs('bulkShift')?.value;
    const semesterNum = qs('bulkSemesterNum')?.value;
    
    if (!sessionId) {
      showToast('Please select a session', 'warning');
      return;
    }
    
    UIHelpers?.showInfo('Loading preview...');
    try {
      const params = new URLSearchParams({action: 'preview_bulk', session_id: sessionId});
      if (semester) params.append('semester', semester);
      if (program) params.append('program', program);
      if (shift) params.append('shift', shift);
      if (semesterNum) params.append('current_semester', semesterNum);
      
      const res = await fetchJson(`${apiBase}?${params}`);
      if (res && res.success) {
        qs('previewCount').textContent = res.count || 0;
        qs('bulkPreview').style.display = 'block';
        UIHelpers?.showSuccess(`Preview ready. ${res.count || 0} students will be assigned.`);
      } else {
        showToast(res?.error || 'Preview failed', 'error');
        qs('bulkPreview').style.display = 'none';
      }
    } catch(e) {
      console.error('Preview error:', e);
      showToast('Preview failed. Please check your connection.', 'error');
      qs('bulkPreview').style.display = 'none';
    }
  }

  async function executeBulkAssignment(){
    const sessionId = qs('bulkSession')?.value;
    const semester = qs('bulkSemester')?.value;
    const program = qs('bulkProgram')?.value;
    const shift = qs('bulkShift')?.value;
    const semesterNum = qs('bulkSemesterNum')?.value;
    
    console.log('executeBulkAssignment called', {sessionId, semester, program, shift, semesterNum});
    
    if (!sessionId) {
      showToast('Please select a session', 'warning');
      return;
    }
    
    // Show confirmation dialog
    if (window.UIHelpers && window.UIHelpers.showConfirmDialog) {
      window.UIHelpers.showConfirmDialog({
        title: 'Execute Bulk Assignment',
        message: 'Are you sure you want to assign students to this session?',
        confirmText: 'Execute',
        cancelText: 'Cancel',
        onConfirm: async () => {
          await performBulkAssignment({sessionId, semester, program, shift, semesterNum});
        }
      });
    } else {
      await performBulkAssignment({sessionId, semester, program, shift, semesterNum});
    }
  }
  
  async function performBulkAssignment({sessionId, semester, program, shift, semesterNum}) {
    UIHelpers?.showInfo('Assigning students to session...');
    
    try {
      const data = {
        session_id: sessionId,
        semester: semester || null,
        program: program || null,
        shift: shift || null,
        current_semester: semesterNum || null
      };
      
      console.log('Sending bulk assign request with data:', data);
      
      const res = await fetchJson(`${apiBase}?action=bulk_assign`, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
      });
      
      console.log('Bulk assign response:', res);
      
      if (res && res.success) {
        showToast(`Successfully assigned ${res.updated || 0} students`, 'success');
        bootstrap.Modal.getInstance(qs('bulkAssignModal')).hide();
        refreshSessions();
      } else {
        console.error('Bulk assign failed:', res);
        showToast(res.error || 'Assignment failed', 'error');
      }
    } catch(e) {
      console.error('Bulk assign error:', e);
      showToast('Assignment failed. Please try again.', 'error');
    }
  }

  async function showRollbackHistory(){
    const modal = qs('rollbackHistoryModal');
    if (!modal) return;
    
    const content = qs('rollbackHistoryContent');
    content.innerHTML = '<div class="text-center py-4"><div class="spinner-border" role="status"></div><div class="mt-2">Loading history...</div></div>';
    
    new bootstrap.Modal(modal).show();
    
    try {
      const res = await fetchJson(`${apiBase}?action=rollback_history`);
      if (res && res.success) {
        renderRollbackHistory(res.history);
      } else {
        content.innerHTML = '<div class="alert alert-info">No history found</div>';
      }
    } catch(e) {
      console.error('History error:', e);
      content.innerHTML = '<div class="alert alert-danger">Error loading history</div>';
    }
  }

  function renderRollbackHistory(history){
    const content = qs('rollbackHistoryContent');
    if (!history || history.length === 0) {
      content.innerHTML = '<div class="alert alert-info">No history found</div>';
      return;
    }
    
    const html = `
      <table class="table">
        <thead>
          <tr>
            <th>Date</th>
            <th>Session</th>
            <th>Students</th>
            <th>Filters</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          ${history.map(h => `
            <tr>
              <td>${new Date(h.created_at).toLocaleDateString()}</td>
              <td>${escapeHtml(h.session_label || h.session_code)}</td>
              <td>${h.student_count || 0}</td>
              <td>
                ${h.filters ? Object.entries(h.filters).filter(([k,v]) => v).map(([k,v]) => `${k}: ${v}`).join(', ') : '-'}
              </td>
              <td>
                <button class="btn btn-sm btn-warning" onclick="rollbackAssignment(${h.id})">
                  <i class="bx bx-undo"></i> Rollback
                </button>
              </td>
            </tr>
          `).join('')}
        </tbody>
      </table>
    `;
    
    content.innerHTML = html;
  }

  async function rollbackAssignment(historyId){
    // Show confirmation dialog using UIHelpers
    if (window.UIHelpers && window.UIHelpers.showConfirmDialog) {
      window.UIHelpers.showConfirmDialog({
        title: 'Rollback Assignment',
        message: 'Are you sure you want to rollback this assignment? This will remove the session assignment for affected students.',
        confirmText: 'Yes, Rollback',
        cancelText: 'Cancel',
        confirmClass: 'btn-warning',
        onConfirm: async () => {
          try {
            const res = await fetchJson(`${apiBase}?action=rollback`, {
              method: 'POST',
              headers: {'Content-Type': 'application/json'},
              body: JSON.stringify({history_id: historyId})
            });
            
            if (res && res.success) {
              showToast('Assignment rolled back successfully', 'success');
              bootstrap.Modal.getInstance(qs('rollbackHistoryModal')).hide();
              refreshSessions();
            } else {
              showToast(res.error || 'Rollback failed', 'error');
            }
          } catch(e) {
            console.error('Rollback error:', e);
            showToast('Rollback failed', 'error');
          }
        }
      });
    } else {
      // Fallback to browser confirm if UIHelpers not available
      if (!confirm('Are you sure you want to rollback this assignment?')) return;
      
      try {
        const res = await fetchJson(`${apiBase}?action=rollback`, {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify({history_id: historyId})
        });
        
        if (res && res.success) {
          showToast('Assignment rolled back successfully', 'success');
          bootstrap.Modal.getInstance(qs('rollbackHistoryModal')).hide();
          refreshSessions();
        }
      } catch(e) {
        console.error('Rollback error:', e);
        showToast('Rollback failed', 'error');
      }
    }
  }

  // Expose functions globally
  window.bulkAssignSessions = bulkAssignSessions;
  window.previewBulkAssignment = previewBulkAssignment;
  window.executeBulkAssignment = executeBulkAssignment;
  window.showRollbackHistory = showRollbackHistory;
  window.rollbackAssignment = rollbackAssignment;

  // Modal handlers
  function openSessionModal(session){
    const modalEl = qs('sessionModal');
    if (!modalEl) return;
    const modal = bootstrap ? new bootstrap.Modal(modalEl) : null;
    // Prefill
    qs('sessionId').value = session?.id || '';
    qs('term').value = session?.term || '';
    qs('year').value = session?.year || '';
    qs('label').value = session?.label || '';
    qs('code').value = session?.code || '';
    qs('startDate').value = session?.start_date || '';
    qs('endDate').value = session?.end_date || '';
    const title = qs('sessionModalTitle'); if (title) title.textContent = session?.id ? 'Edit Session' : 'Create New Session';
    if (modal) modal.show();
  }

  function bindForm(){
    const form = qs('sessionForm');
    if (!form || form.dataset.bound) return;
    form.addEventListener('submit', async function(e){
      e.preventDefault();
      const id = qs('sessionId').value.trim();
      const fd = new FormData(form);
      fd.set('csrf_token', getCsrf());
      try {
        let url = `${apiBase}?action=create`;
        let method = 'POST';
        if (id) {
          // API expects PUT for update; use fetch with method=POST to a small proxy if added in future.
          // For now, fallback: attempt PUT; server reads $_POST, so echo error if not supported.
          url = `${apiBase}?action=update&id=${encodeURIComponent(id)}`;
          method = 'PUT';
        }
        const res = await fetch(url, { method, body: fd });
        const text = await res.text();
        let data; try { data = JSON.parse(text); } catch { throw new Error(text); }
        if (data.success){
          showToast(id ? 'Session updated' : 'Session created', 'success');
          if (bootstrap) bootstrap.Modal.getInstance(qs('sessionModal'))?.hide();
          refreshSessions();
        } else {
          throw new Error(data.error || data.message || 'Operation failed');
        }
      } catch(err){
        showToast(err.message || 'Failed to save session', 'error');
      }
    });
    form.dataset.bound = '1';
  }

  async function toggleSessionStatus(id){
    try {
      const fd = new FormData(); fd.set('csrf_token', getCsrf());
      const data = await fetchJson(`${apiBase}?action=toggle_status&id=${encodeURIComponent(id)}`, { method: 'POST', body: fd });
      if (data.success){ refreshSessions(); } else { throw new Error(data.error || 'Failed'); }
    } catch(e){ showToast(e.message, 'error'); }
  }

  async function deleteSession(id){
    if (!confirm('Delete this session?')) return;
    try {
      const data = await fetchJson(`${apiBase}?action=delete&id=${encodeURIComponent(id)}`, { method: 'DELETE' });
      if (data.success){ showToast('Session deleted', 'success'); refreshSessions(); } else { throw new Error(data.error || 'Failed'); }
    } catch(e){ showToast(e.message, 'error'); }
  }

  async function bulkAssignSessions(){
    const modal = qs('bulkAssignModal');
    if (!modal) return;
    
    // Load sessions dropdown
    const sessionSelect = qs('bulkSession');
    if (sessionSelect) {
      try {
        const res = await fetchJson(`${apiBase}?action=list`);
        if (res && res.success && res.data) {
          sessionSelect.innerHTML = '<option value="">Select Session</option>' + 
            res.data.map(s => `<option value="${s.id}">${escapeHtml(s.label || s.code)}</option>`).join('');
        }
      } catch(e) {
        console.error('Error loading sessions:', e);
      }
    }
    
    // Load programs dropdown
    const programSelect = qs('bulkProgram');
    if (programSelect) {
      try {
        const res = await fetchJson('api/students.php?action=list&limit=1000');
        if (res && res.success && res.data) {
          // Get unique programs from students
          const programs = [...new Set(res.data.map(s => s.program).filter(Boolean))];
          programSelect.innerHTML = '<option value="">All Programs</option>' + 
            programs.map(p => `<option value="${escapeHtml(p)}">${escapeHtml(p)}</option>`).join('');
        }
      } catch(e) {
        console.error('Error loading programs:', e);
      }
    }
    
    new bootstrap.Modal(modal).show();
  }

  // Expose to window (refreshSessions is already exposed above)
  window.openSessionModal = () => { openSessionModal(null); };
  window.editSession = async (id) => {
    try {
      const list = await fetchJson(`${apiBase}?action=list`);
      if (list.success){
        const session = (list.data||[]).find(s=>String(s.id)===String(id));
        openSessionModal(session || null);
      } else { throw new Error(list.error || 'Failed to load'); }
    } catch(e){ showToast(e.message, 'error'); }
  };
  window.toggleSessionStatus = toggleSessionStatus;
  window.deleteSession = deleteSession;
  window.bulkAssignSessions = bulkAssignSessions;

  // Init
  document.addEventListener('DOMContentLoaded', function(){
    try { refreshSessions(); } catch(e){ console.warn(e); }
    try { bindForm(); } catch(e){ console.warn(e); }
  });
})();
