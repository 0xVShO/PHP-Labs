const showBtn = document.getElementById('showFormBtn');
const formContainer = document.querySelector('.ticket-form-container');
const ticketForm = document.getElementById('addTicketForm');
const closeFormBtn = document.getElementById('closeFormBtn');
const resultsContainer = document.getElementById('results');
const searchInput = document.getElementById('status-search');
const successMsg = document.getElementById('success-msg');
const errorMsg = document.getElementById('js-error-msg');
const descInput = document.getElementById('description');

if (showBtn && formContainer) {
    showBtn.addEventListener('click', () => {
        formContainer.style.display = 'block';
        showBtn.style.display = 'none';
    });
}

if (closeFormBtn && formContainer && showBtn) {
    closeFormBtn.addEventListener('click', () => {
        ticketForm.reset();
        document.getElementById('ticket_id').value = '';
        const submitBtn = document.querySelector('#addTicketForm button[type="submit"]');
        if (submitBtn) submitBtn.textContent = 'Створити тікет';
        formContainer.style.display = 'none';
        showBtn.style.display = 'flex';
    });
}

if (descInput) {
    descInput.addEventListener('input', () => {
        localStorage.setItem('ticketDraft', descInput.value);
    });

    document.addEventListener('DOMContentLoaded', () => {
        const savedDraft = localStorage.getItem('ticketDraft');
        if (savedDraft && !document.getElementById('ticket_id').value) {
            descInput.value = savedDraft;
        }
    });
}

function escapeHtml(unsafe) {
    return (unsafe || '').toString()
         .replace(/&/g, "&amp;")
         .replace(/</g, "&lt;")
         .replace(/>/g, "&gt;")
         .replace(/"/g, "&quot;")
         .replace(/'/g, "&#039;");
}

function formatStatus(status) {
    switch(status) {
        case 'open': return '<span style="color: green; font-weight: bold;">Відкритий</span>';
        case 'closed': return '<span style="color: gray;">Закритий</span>';
        case 'in_progress': return '<span style="color: orange; font-weight: bold;">В роботі</span>';
        default: return '<span>Невідомо</span>';
    }
}

function getPriorityBadge(priority) {
    if (priority === 'high') return '<span class="badge urgent">Терміновий</span>';
    if (priority === 'medium') return '<span class="badge medium">Середній</span>';
    return '<span class="badge low">Низький</span>';
}

function updateStats(open, inProgress, closed) {
    const o = document.getElementById('stat-open');
    const p = document.getElementById('stat-progress');
    const c = document.getElementById('stat-closed');
    if (o) o.textContent = open;
    if (p) p.textContent = inProgress;
    if (c) c.textContent = closed;
}

function loadTickets(query = '') {
    const url = query ? 'api/api_list.php?status=' + encodeURIComponent(query) : 'api/api_list.php';
    
    fetch(url)
        .then(response => {
            if (!response.ok) throw new Error('Помилка мережі');
            return response.json();
        })
        .then(data => {
            if (data.error) {
                resultsContainer.innerHTML = `<p class="error-msg">${data.error}</p>`;
                return;
            }
            
            if (data.length === 0) {
                resultsContainer.innerHTML = '<p>Тікетів наразі немає</p>';
                updateStats(0, 0, 0);
                return;
            }
            
            let html = '';
            let open = 0, inProgress = 0, closed = 0;
            
            data.forEach(ticket => {
                if (ticket.status === 'open') open++;
                if (ticket.status === 'in_progress') inProgress++;
                if (ticket.status === 'closed') closed++;
                
                let slaWarningHtml = ticket.is_overdue ? '<div class="sla-warning"><b>Увага:</b> SLA прострочено!</div>' : '';
                let safeSubject = escapeHtml(ticket.subject);
                let safeDescription = escapeHtml(ticket.description).replace(/\n/g, '<br>');
                
                // Формуємо приписку SLA, якщо вона є, як в 4-й лабі
                let slaText = ticket.sla_hours ? ` | SLA: ${ticket.sla_hours} год.` : '';

                html += `
                    <div class="ticket-card">
                        <div class="ticket-actions">
                            <button type="button" class="btn-edit" onclick="editTicket(${ticket.id})">✎ Ред.</button>
                            <button type="button" class="btn-delete" onclick="deleteTicket(${ticket.id})">✖ Видалити</button>
                        </div>
                        <div class="ticket-status">${formatStatus(ticket.status)}</div>
                        ${getPriorityBadge(ticket.priority)}
                        
                        <!-- Оновлений блок ticket-info, як у 4-й лабі -->
                        <div class="ticket-info" style="margin-top: 15px; margin-bottom: 15px; line-height: 1.6;">
                            Тема: ${safeSubject}<br>
                            Опис: ${safeDescription}<br>
                            Дата створення: ${ticket.created_at || 'Невідомо'}${slaText}
                        </div>
                        
                        ${slaWarningHtml}
                        <form class="status-update-form">
                            <select class="status-select" onchange="updateTicketStatus(${ticket.id}, this.value)">
                                <option value="open" ${ticket.status === 'open' ? 'selected' : ''}>Відкритий</option>
                                <option value="in_progress" ${ticket.status === 'in_progress' ? 'selected' : ''}>В роботі</option>
                                <option value="closed" ${ticket.status === 'closed' ? 'selected' : ''}>Закритий</option>
                            </select>
                        </form>
                    </div>
                `;
            });
            
            resultsContainer.innerHTML = html;
            updateStats(open, inProgress, closed);
        })
        .catch(error => {
            resultsContainer.innerHTML = `<p class="error-msg">Помилка завантаження: ${error.message}</p>`;
        });
}

if (searchInput) {
    searchInput.addEventListener('input', (event) => {
        loadTickets(event.target.value);
    });
}

window.updateTicketStatus = function(ticketId, newStatus) {
    const formData = new FormData();
    formData.append('ticket_id', ticketId);
    formData.append('status', newStatus);
    
    fetch('api/api_update_status.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            loadTickets(searchInput ? searchInput.value : '');
        } else {
            alert('Помилка оновлення статусу: ' + (data.error || ''));
        }
    })
    .catch(err => console.error(err));
};

window.deleteTicket = function(ticketId) {
    if (!confirm('Видалити цей тікет?')) return;
    
    const formData = new FormData();
    formData.append('ticket_id', ticketId);
    
    fetch('api/api_delete.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            loadTickets(searchInput ? searchInput.value : '');
        } else {
            alert('Помилка видалення: ' + (data.error || ''));
        }
    })
    .catch(err => console.error(err));
};

window.editTicket = function(ticketId) {
    fetch('api/api_get_ticket.php?id=' + ticketId)
        .then(response => response.json())
        .then(ticket => {
            if (ticket.error) {
                alert(ticket.error);
                return;
            }
            
            document.getElementById('ticket_id').value = ticket.id;
            document.getElementById('subject').value = ticket.subject;
            document.getElementById('description').value = ticket.description;
            document.getElementById('priority').value = ticket.priority;
            
            const slaInput = document.getElementById('sla_hours');
            if (slaInput) {
                slaInput.value = ticket.sla_hours || 24;
            }
            
            const submitBtn = document.querySelector('#addTicketForm button[type="submit"]');
            if (submitBtn) {
                submitBtn.textContent = 'Оновити тікет';
            }
            
            formContainer.style.display = 'block';
            showBtn.style.display = 'none';
        })
        .catch(err => console.error(err));
};

if (ticketForm) {
    ticketForm.addEventListener('submit', (event) => {
        event.preventDefault();
        
        if (successMsg) successMsg.style.display = 'none';
        if (errorMsg) errorMsg.style.display = 'none';
        
        if (descInput && descInput.value.length < 15) {
            if (errorMsg) {
                errorMsg.textContent = 'Опис має бути не менше 15 символів!';
                errorMsg.style.display = 'block';
            }
            return;
        }
        
        const ticketIdVal = document.getElementById('ticket_id').value;
        const targetUrl = ticketIdVal ? 'api/api_update.php' : 'api/api_add.php';
        
        fetch(targetUrl, {
            method: 'POST',
            body: new FormData(ticketForm)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                ticketForm.reset();
                document.getElementById('ticket_id').value = '';
                const submitBtn = document.querySelector('#addTicketForm button[type="submit"]');
                if (submitBtn) submitBtn.textContent = 'Створити тікет';
                localStorage.removeItem('ticketDraft');
                
                formContainer.style.display = 'none';
                showBtn.style.display = 'flex';
                loadTickets(searchInput ? searchInput.value : '');
            } else {
                if (errorMsg) {
                    errorMsg.innerHTML = data.errors ? data.errors.join('<br>') : (data.error || 'Помилка');
                    errorMsg.style.display = 'block';
                }
            }
        })
        .catch(error => {
            if (errorMsg) {
                errorMsg.textContent = 'Помилка з\'єднання з сервером';
                errorMsg.style.display = 'block';
            }
        });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    loadTickets();
});