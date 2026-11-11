/**
 * Campus Gather · Interactive Client Scripts
 * Faculty of Information Technology, University of Moratuwa
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. Generic confirmation modals for dangerous actions
    document.querySelectorAll('[data-confirm]').forEach((element) => {
        element.addEventListener('submit', (e) => {
            const message = element.getAttribute('data-confirm') || 'Are you sure you want to perform this action?';
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
        if (element.tagName === 'BUTTON' || element.tagName === 'A') {
            element.addEventListener('click', (e) => {
                const message = element.getAttribute('data-confirm') || 'Are you sure you want to perform this action?';
                if (!confirm(message)) {
                    e.preventDefault();
                }
            });
        }
    });

    // 2. Tab Navigation Component (for manage-event and dashboards)
    const tabButtons = document.querySelectorAll('.tab-btn[data-tab]');
    tabButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const targetTabId = btn.getAttribute('data-tab');
            const parent = btn.closest('.tabs-container') || document;

            parent.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            parent.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));

            btn.classList.add('active');
            const targetPane = parent.querySelector(`#tab-${targetTabId}`);
            if (targetPane) {
                targetPane.classList.add('active');
            }
        });
    });

    // 3. Dynamic Section Builder for Create Event Page
    const sectionContainer = document.getElementById('sections-container');
    const templateButtons = document.querySelectorAll('.btn-template-pill');
    const addCustomBtn = document.getElementById('btn-add-custom-section');

    if (sectionContainer && window.SECTION_TEMPLATES) {
        let sectionCounter = 0;

        function addSectionCard(name, desc, leader, resp, skills, limit, isTemplate = false) {
            sectionCounter++;
            const id = `section-${sectionCounter}`;
            const card = document.createElement('div');
            card.className = 'section-card' + (isTemplate ? ' template-card' : '');
            card.id = id;
            card.style.marginBottom = '16px';
            card.style.background = '#ffffff';

            card.innerHTML = `
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; border-bottom:1px solid #e2e8f0; padding-bottom:8px;">
                    <div style="font-weight:800; font-size:15px; color:#1d4ed8; display:flex; align-items:center; gap:8px;">
                        <span>📋</span>
                        <input type="text" name="sections[${sectionCounter}][name]" value="${escapeHtml(name)}" required style="font-weight:700; border:1px solid #cbd5e1; border-radius:4px; padding:4px 8px; font-size:14px; width:220px;" placeholder="Section Name">
                    </div>
                    <button type="button" class="btn-remove-section" style="background:none; border:none; color:#dc2626; cursor:pointer; font-size:13px; font-weight:600;">× Remove Section</button>
                </div>
                <div class="form-grid-2">
                    <div class="form-group">
                        <label>Section Leader / Coordinator Name</label>
                        <input type="text" name="sections[${sectionCounter}][leader_name]" value="${escapeHtml(leader)}" placeholder="e.g. Student Coordinator" required>
                    </div>
                    <div class="form-group">
                        <label>Leader University Email (Optional)</label>
                        <input type="email" name="sections[${sectionCounter}][leader_email]" placeholder="leader@mora.ac.lk">
                    </div>
                </div>
                <div class="form-group">
                    <label>Description (What the team does)</label>
                    <textarea name="sections[${sectionCounter}][description]" rows="2" required placeholder="Explain this section's role...">${escapeHtml(desc)}</textarea>
                </div>
                <div class="form-group">
                    <label>Main Responsibilities</label>
                    <textarea name="sections[${sectionCounter}][responsibilities]" rows="3" required placeholder="List duties line by line...">${escapeHtml(resp)}</textarea>
                </div>
                <div class="form-grid-2">
                    <div class="form-group">
                        <label>Useful Skills / Prerequisites</label>
                        <input type="text" name="sections[${sectionCounter}][useful_skills]" value="${escapeHtml(skills)}" placeholder="e.g. Figma, Canva, Beginners welcome!">
                    </div>
                    <div class="form-group">
                        <label>Member Limit (Max Capacity)</label>
                        <input type="number" name="sections[${sectionCounter}][member_limit]" value="${limit}" min="1" max="50" required>
                    </div>
                </div>
            `;

            card.querySelector('.btn-remove-section').addEventListener('click', () => {
                card.remove();
                // update template pill if matches
                templateButtons.forEach(btn => {
                    if (btn.getAttribute('data-name') === name) {
                        btn.classList.remove('selected');
                    }
                });
            });

            sectionContainer.appendChild(card);
        }

        templateButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                const name = btn.getAttribute('data-name');
                const tpl = window.SECTION_TEMPLATES[name];
                if (tpl) {
                    if (btn.classList.contains('selected')) {
                        // find and remove
                        const existing = Array.from(sectionContainer.querySelectorAll('input[name$="[name]"]')).find(i => i.value.trim() === name);
                        if (existing) {
                            existing.closest('.section-card').remove();
                        }
                        btn.classList.remove('selected');
                    } else {
                        addSectionCard(tpl.name, tpl.description, '', tpl.responsibilities, tpl.useful_skills, tpl.member_limit, true);
                        btn.classList.add('selected');
                    }
                }
            });
        });

        if (addCustomBtn) {
            addCustomBtn.addEventListener('click', () => {
                addSectionCard('Custom Team', 'Specialized team to support event execution.', '', 'Coordinate duties with the organizing committee.', 'Open to all students', 8);
            });
        }
    }

    function escapeHtml(text) {
        if (!text) return '';
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
});
